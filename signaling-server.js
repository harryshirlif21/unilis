/**
 * WebSocket Signaling Server for WebRTC Meeting System
 * Node.js server with MySQL fallback support
 */

const WebSocket = require('ws');
const mysql = require('mysql2/promise');
const http = require('http');

class SignalingServer {
    constructor(config = {}) {
        this.config = {
            port: 8080,
            mysql: {
                host: 'localhost',
                user: 'root',
                password: '',
                database: 'university_system'
            },
            cleanupInterval: 30000, // 30 seconds
            maxMessageSize: 1024 * 1024, // 1MB
            ...config
        };

        this.wss = null;
        this.connections = new Map(); // meetingId -> Map(userId -> WebSocket)
        this.mysqlPool = null;
        this.cleanupInterval = null;
    }

    async initialize() {
        try {
            // Initialize MySQL connection pool
            this.mysqlPool = mysql.createPool({
                ...this.config.mysql,
                connectionLimit: 10,
                acquireTimeout: 60000,
                reconnect: true
            });

            // Test database connection
            await this.testDatabaseConnection();

            // Create HTTP server for health checks
            const server = http.createServer((req, res) => {
                if (req.url === '/health') {
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        status: 'ok',
                        connections: this.getConnectionCount(),
                        timestamp: new Date().toISOString()
                    }));
                } else {
                    res.writeHead(404);
                    res.end();
                }
            });

            // Create WebSocket server
            this.wss = new WebSocket.Server({ 
                server,
                maxPayload: this.config.maxMessageSize
            });

            // Set up WebSocket event handlers
            this.setupWebSocketHandlers();

            // Start cleanup interval
            this.startCleanupInterval();

            // Start server
            server.listen(this.config.port, () => {
                console.log(`🚀 WebSocket signaling server running on port ${this.config.port}`);
                console.log(`📊 Database: ${this.config.mysql.host}/${this.config.mysql.database}`);
            });

        } catch (error) {
            console.error('Failed to initialize signaling server:', error);
            process.exit(1);
        }
    }

    async testDatabaseConnection() {
        try {
            const connection = await this.mysqlPool.getConnection();
            console.log('✅ Database connection successful');
            connection.release();
        } catch (error) {
            console.error('❌ Database connection failed:', error.message);
            throw error;
        }
    }

    setupWebSocketHandlers() {
        this.wss.on('connection', (ws, req) => {
            console.log('🔌 New WebSocket connection');

            // Set up message handler
            ws.on('message', async (data) => {
                try {
                    await this.handleMessage(ws, data);
                } catch (error) {
                    console.error('Error handling message:', error);
                    this.sendError(ws, 'Failed to process message');
                }
            });

            // Set up close handler
            ws.on('close', () => {
                this.handleDisconnection(ws);
            });

            // Set up error handler
            ws.on('error', (error) => {
                console.error('WebSocket error:', error);
                this.handleDisconnection(ws);
            });

            // Set timeout for authentication
            setTimeout(() => {
                if (!ws.userId || !ws.meetingId) {
                    console.log('⏰ Authentication timeout - closing connection');
                    ws.close(1008, 'Authentication timeout');
                }
            }, 5000);
        });
    }

    async handleMessage(ws, data) {
        // Parse message
        let message;
        try {
            message = JSON.parse(data.toString());
        } catch (error) {
            throw new Error('Invalid JSON message');
        }

        // Validate message structure
        if (!message.type) {
            throw new Error('Message type is required');
        }

        // Handle different message types
        switch (message.type) {
            case 'auth':
                await this.handleAuth(ws, message);
                break;

            case 'signal':
                await this.handleSignal(ws, message);
                break;

            case 'ping':
                this.send(ws, { type: 'pong', timestamp: Date.now() });
                break;

            default:
                throw new Error(`Unknown message type: ${message.type}`);
        }
    }

    async handleAuth(ws, message) {
        const { meeting_id, user_id } = message;

        if (!meeting_id || !user_id) {
            throw new Error('Meeting ID and User ID are required for authentication');
        }

        // Validate user access to meeting
        const isValid = await this.validateMeetingAccess(meeting_id, user_id);
        if (!isValid) {
            throw new Error('Invalid meeting access');
        }

        // Store user info in WebSocket
        ws.meetingId = meeting_id;
        ws.userId = user_id;

        // Add to connections map
        if (!this.connections.has(meeting_id)) {
            this.connections.set(meeting_id, new Map());
        }
        this.connections.get(meeting_id).set(user_id, ws);

        console.log(`✅ User ${user_id} authenticated for meeting ${meeting_id}`);

        // Send success response
        this.send(ws, {
            type: 'auth_success',
            data: {
                meeting_id,
                user_id,
                timestamp: Date.now()
            }
        });

        // Broadcast user joined event
        this.broadcastToMeeting(meeting_id, user_id, {
            type: 'user_joined',
            data: {
                user_id,
                timestamp: Date.now()
            }
        });
    }

    async handleSignal(ws, message) {
        const { data } = message;

        if (!data) {
            throw new Error('Signal data is required');
        }

        const { meeting_id, from_user_id, to_user_id, signal_type, signal_data } = data;

        // Validate sender
        if (from_user_id !== ws.userId) {
            throw new Error('User ID mismatch');
        }

        // Validate meeting access
        if (meeting_id !== ws.meetingId) {
            throw new Error('Meeting ID mismatch');
        }

        if (to_user_id) {
            // Send to specific user
            await this.sendToUser(meeting_id, to_user_id, {
                type: 'signal',
                data: {
                    meeting_id,
                    from_user_id,
                    to_user_id,
                    signal_type,
                    signal_data,
                    timestamp: Date.now()
                }
            });
        } else {
            // Broadcast to all users in meeting (except sender)
            this.broadcastToMeeting(meeting_id, from_user_id, {
                type: 'signal',
                data: {
                    meeting_id,
                    from_user_id,
                    to_user_id,
                    signal_type,
                    signal_data,
                    timestamp: Date.now()
                }
            });
        }

        console.log(`📨 Signal ${signal_type} from ${from_user_id} to ${to_user_id || 'all'}`);
    }

    async sendToUser(meetingId, userId, message) {
        const meetingConnections = this.connections.get(meetingId);
        if (!meetingConnections) {
            // Fallback to database
            await this.storeSignalInDatabase(message.data);
            return;
        }

        const userWs = meetingConnections.get(userId);
        if (userWs && userWs.readyState === WebSocket.OPEN) {
            this.send(userWs, message);
        } else {
            // Fallback to database if user is not connected via WebSocket
            await this.storeSignalInDatabase(message.data);
        }
    }

    broadcastToMeeting(meetingId, excludeUserId, message) {
        const meetingConnections = this.connections.get(meetingId);
        if (!meetingConnections) return;

        for (const [userId, userWs] of meetingConnections) {
            if (userId !== excludeUserId && userWs.readyState === WebSocket.OPEN) {
                this.send(userWs, message);
            }
        }
    }

    async storeSignalInDatabase(signalData) {
        try {
            const {
                meeting_id,
                from_user_id,
                to_user_id,
                signal_type,
                signal_data
            } = signalData;

            const sql = `
                INSERT INTO signal_queue (meeting_id, from_user_id, to_user_id, signal_type, signal_data) 
                VALUES (?, ?, ?, ?, ?)
            `;

            await this.mysqlPool.execute(sql, [
                meeting_id,
                from_user_id,
                to_user_id,
                signal_type,
                signal_data
            ]);

            console.log(`💾 Stored signal in database: ${signal_type} from ${from_user_id}`);

        } catch (error) {
            console.error('Failed to store signal in database:', error);
        }
    }

    async validateMeetingAccess(meetingId, userId) {
        try {
            // Check if user has access to the meeting
            const sql = `
                SELECT 1 FROM meetings m 
                LEFT JOIN student_unit su ON su.unit_id = m.unit_id 
                WHERE m.id = ? AND (m.lecturer_id = ? OR su.student_id = ?)
                LIMIT 1
            `;

            const [rows] = await this.mysqlPool.execute(sql, [meetingId, userId, userId]);
            return rows.length > 0;

        } catch (error) {
            console.error('Error validating meeting access:', error);
            return false;
        }
    }

    handleDisconnection(ws) {
        if (ws.meetingId && ws.userId) {
            const meetingConnections = this.connections.get(ws.meetingId);
            if (meetingConnections) {
                meetingConnections.delete(ws.userId);

                // Remove meeting if empty
                if (meetingConnections.size === 0) {
                    this.connections.delete(ws.meetingId);
                }

                console.log(`🔴 User ${ws.userId} disconnected from meeting ${ws.meetingId}`);

                // Broadcast user left event
                this.broadcastToMeeting(ws.meetingId, ws.userId, {
                    type: 'user_left',
                    data: {
                        user_id: ws.userId,
                        timestamp: Date.now()
                    }
                });
            }
        }
    }

    send(ws, message) {
        if (ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify(message));
        }
    }

    sendError(ws, errorMessage) {
        this.send(ws, {
            type: 'error',
            data: { message: errorMessage }
        });
    }

    startCleanupInterval() {
        this.cleanupInterval = setInterval(() => {
            this.cleanupStaleConnections();
        }, this.config.cleanupInterval);
    }

    cleanupStaleConnections() {
        let cleanedCount = 0;

        for (const [meetingId, meetingConnections] of this.connections) {
            for (const [userId, ws] of meetingConnections) {
                if (ws.readyState !== WebSocket.OPEN) {
                    meetingConnections.delete(userId);
                    cleanedCount++;
                }
            }

            // Remove empty meetings
            if (meetingConnections.size === 0) {
                this.connections.delete(meetingId);
            }
        }

        if (cleanedCount > 0) {
            console.log(`🧹 Cleaned up ${cleanedCount} stale connections`);
        }
    }

    getConnectionCount() {
        let total = 0;
        for (const meetingConnections of this.connections.values()) {
            total += meetingConnections.size;
        }
        return total;
    }

    async shutdown() {
        console.log('🛑 Shutting down signaling server...');

        // Clear intervals
        if (this.cleanupInterval) {
            clearInterval(this.cleanupInterval);
        }

        // Close all WebSocket connections
        if (this.wss) {
            this.wss.close(() => {
                console.log('✅ WebSocket server closed');
            });
        }

        // Close database connections
        if (this.mysqlPool) {
            await this.mysqlPool.end();
            console.log('✅ Database connections closed');
        }

        process.exit(0);
    }
}

// Handle process termination
process.on('SIGINT', () => {
    server.shutdown();
});

process.on('SIGTERM', () => {
    server.shutdown();
});

// Create and start server
const server = new SignalingServer();
server.initialize().catch(console.error);

module.exports = SignalingServer;