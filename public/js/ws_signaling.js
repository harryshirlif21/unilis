/**
 * WebSocket signaling abstraction layer
 * Provides automatic fallback to PHP polling
 */

class WSSignaling {
    constructor(config = {}) {
        this.config = {
            wsUrl: 'ws://localhost:8080',
            reconnectInterval: 1000,
            maxReconnectAttempts: 5,
            ...config
        };

        this.ws = null;
        this.reconnectAttempts = 0;
        this.isConnected = false;
        this.pendingMessages = [];
        
        this.meetingId = config.meetingId;
        this.userId = config.userId;
        this.onMessage = config.onMessage || (() => {});
        this.onStatusChange = config.onStatusChange || (() => {});
    }

    async connect() {
        return new Promise((resolve, reject) => {
            try {
                this.ws = new WebSocket(this.config.wsUrl);
                
                this.ws.onopen = () => {
                    console.log('WebSocket connected');
                    this.isConnected = true;
                    this.reconnectAttempts = 0;
                    this.onStatusChange('connected');
                    
                    // Send authentication
                    this.send({
                        type: 'auth',
                        meeting_id: this.meetingId,
                        user_id: this.userId
                    });
                    
                    // Send any pending messages
                    this.flushPendingMessages();
                    
                    resolve();
                };
                
                this.ws.onmessage = (event) => {
                    try {
                        const message = JSON.parse(event.data);
                        this.handleMessage(message);
                    } catch (error) {
                        console.error('Failed to parse WebSocket message:', error);
                    }
                };
                
                this.ws.onclose = () => {
                    console.log('WebSocket disconnected');
                    this.isConnected = false;
                    this.onStatusChange('disconnected');
                    this.handleReconnect();
                };
                
                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    this.onStatusChange('error');
                    reject(error);
                };
                
            } catch (error) {
                reject(error);
            }
        });
    }

    handleMessage(message) {
        const { type, data } = message;
        
        switch (type) {
            case 'auth_success':
                console.log('WebSocket authentication successful');
                break;
                
            case 'auth_failed':
                console.error('WebSocket authentication failed:', data);
                this.disconnect();
                break;
                
            case 'signal':
                this.onMessage(data);
                break;
                
            case 'error':
                console.error('WebSocket server error:', data);
                break;
                
            default:
                console.warn('Unknown WebSocket message type:', type);
        }
    }

    async sendMessage(signalType, toUserId, signalData) {
        const message = {
            type: 'signal',
            data: {
                meeting_id: this.meetingId,
                from_user_id: this.userId,
                to_user_id: toUserId,
                signal_type: signalType,
                signal_data: signalData,
                timestamp: Date.now()
            }
        };
        
        await this.send(message);
    }

    async send(message) {
        if (this.isConnected && this.ws) {
            this.ws.send(JSON.stringify(message));
        } else {
            // Queue message for when connection is restored
            this.pendingMessages.push(message);
            
            // Try to reconnect if not already attempting
            if (this.reconnectAttempts === 0) {
                this.handleReconnect();
            }
        }
    }

    flushPendingMessages() {
        while (this.pendingMessages.length > 0) {
            const message = this.pendingMessages.shift();
            if (this.ws) {
                this.ws.send(JSON.stringify(message));
            }
        }
    }

    handleReconnect() {
        if (this.reconnectAttempts >= this.config.maxReconnectAttempts) {
            console.error('Max reconnection attempts reached');
            this.onStatusChange('failed');
            return;
        }
        
        this.reconnectAttempts++;
        
        console.log(`Attempting to reconnect... (${this.reconnectAttempts}/${this.config.maxReconnectAttempts})`);
        
        setTimeout(() => {
            this.connect().catch(error => {
                console.error('Reconnection failed:', error);
            });
        }, this.config.reconnectInterval * Math.pow(2, this.reconnectAttempts - 1)); // Exponential backoff
    }

    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.isConnected = false;
        this.pendingMessages = [];
        this.reconnectAttempts = 0;
        this.onStatusChange('disconnected');
    }

    getStatus() {
        return this.isConnected ? 'connected' : 'disconnected';
    }
}

// WebSocket signaling manager with fallback to polling
class SignalingManager {
    constructor(config = {}) {
        this.config = config;
        this.mode = 'unknown'; // 'websocket', 'polling', 'unknown'
        this.wsSignaling = null;
        this.pollingManager = null;
        this.api = new MeetingAPI(config.baseUrl);
        
        this.onMessage = config.onMessage || (() => {});
        this.onModeChange = config.onModeChange || (() => {});
    }

    async initialize() {
        // Try WebSocket first
        try {
            this.wsSignaling = new WSSignaling({
                meetingId: this.config.meetingId,
                userId: this.config.userId,
                onMessage: this.handleWSMessage.bind(this),
                onStatusChange: this.handleWSStatusChange.bind(this)
            });
            
            await this.wsSignaling.connect();
            this.setMode('websocket');
            
        } catch (error) {
            console.warn('WebSocket signaling failed, falling back to polling:', error);
            this.fallbackToPolling();
        }
    }

    handleWSMessage(message) {
        this.onMessage(message);
    }

    handleWSStatusChange(status) {
        if (status === 'disconnected' || status === 'failed') {
            console.warn('WebSocket disconnected, falling back to polling');
            this.fallbackToPolling();
        }
    }

    fallbackToPolling() {
        if (this.mode === 'polling') return; // Already in polling mode
        
        // Clean up WebSocket
        if (this.wsSignaling) {
            this.wsSignaling.disconnect();
            this.wsSignaling = null;
        }
        
        // Start polling
        this.pollingManager = new PollingManager(this.api, 700);
        this.pollingManager.start(
            this.config.meetingId,
            this.config.userId,
            this.handlePolledSignals.bind(this),
            (error) => {
                console.error('Polling error:', error);
            }
        );
        
        this.setMode('polling');
    }

    handlePolledSignals(signals) {
        signals.forEach(signal => {
            this.onMessage(signal);
        });
    }

    setMode(mode) {
        if (this.mode !== mode) {
            this.mode = mode;
            this.onModeChange(mode);
        }
    }

    async sendMessage(signalType, toUserId, signalData) {
        if (this.mode === 'websocket' && this.wsSignaling) {
            await this.wsSignaling.sendMessage(signalType, toUserId, signalData);
        } else {
            // Use API directly for polling mode
            switch (signalType) {
                case 'offer':
                    await this.api.sendOffer(this.config.meetingId, this.config.userId, signalData, toUserId);
                    break;
                case 'answer':
                    await this.api.sendAnswer(this.config.meetingId, this.config.userId, signalData, toUserId);
                    break;
                case 'candidate':
                    await this.api.sendCandidate(this.config.meetingId, this.config.userId, signalData, toUserId);
                    break;
            }
        }
    }

    disconnect() {
        if (this.wsSignaling) {
            this.wsSignaling.disconnect();
        }
        if (this.pollingManager) {
            this.pollingManager.stop();
        }
    }

    getMode() {
        return this.mode;
    }

    isWebSocketConnected() {
        return this.mode === 'websocket' && this.wsSignaling && this.wsSignaling.isConnected;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { WSSignaling, SignalingManager };
}