<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and is a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);

if (!$meeting_id) {
    die('Meeting ID is required');
}

// Get meeting details
$sql = "SELECT m.*, u.name as unit_name, l.name as lecturer_name 
        FROM meetings m 
        JOIN units u ON m.unit_id = u.id 
        JOIN lecturers l ON m.lecturer_id = l.id 
        WHERE m.id = ? AND m.lecturer_id = ?";

$meeting = executeQuery($sql, [$meeting_id, $lecturer_id], "ii");

if (empty($meeting)) {
    die('Meeting not found or access denied');
}

$meeting = $meeting[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Host - <?php echo htmlspecialchars($meeting['title']); ?></title>
    <link rel="stylesheet" href="../public/css/meeting.css">
    <style>
        .debug-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
        .debug-toggle {
            background: #6c757d;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="meeting-container">
        <button class="debug-toggle" onclick="toggleDebug()">Toggle Debug</button>
        <div class="debug-panel" id="debugPanel" style="display: none;">
            <div id="debugContent">Debug information will appear here...</div>
        </div>

        <div class="meeting-header">
            <div class="meeting-title">
                <h1><?php echo htmlspecialchars($meeting['title']); ?></h1>
                <p>Unit: <?php echo htmlspecialchars($meeting['unit_name']); ?></p>
            </div>
            <div class="meeting-status">
                <span>Status: </span>
                <div class="status-indicator" id="statusIndicator"></div>
                <span id="statusText">Connecting...</span>
            </div>
        </div>

        <div class="meeting-content">
            <div class="video-section">
                <!-- Local Video -->
                <div class="video-item lecturer">
                    <video id="localVideo" autoplay muted playsinline></video>
                    <div class="video-overlay">
                        <div class="video-info">
                            <strong>You (Lecturer)</strong>
                            <div id="localStreamInfo">Camera: Off | Mic: Off</div>
                        </div>
                        <div class="video-actions">
                            <button class="btn btn-secondary btn-sm" onclick="toggleCamera()">
                                <span class="btn-icon">📹</span>
                                <span id="cameraText">Camera Off</span>
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="toggleMicrophone()">
                                <span class="btn-icon">🎤</span>
                                <span id="micText">Mic Off</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Screen Share Video -->
                <div class="video-item screen-share hidden" id="screenShareContainer">
                    <video id="screenShareVideo" autoplay playsinline></video>
                    <div class="video-overlay">
                        <div class="video-info">
                            <strong>Screen Share</strong>
                        </div>
                        <div class="video-actions">
                            <button class="btn btn-danger btn-sm" onclick="stopScreenShare()">
                                <span class="btn-icon">⏹️</span>
                                Stop Share
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Remote Videos Grid -->
                <div class="video-grid" id="remoteVideosGrid">
                    <div class="empty-state">
                        <div>No students connected yet</div>
                    </div>
                </div>

                <!-- Students Grid -->
                <div class="students-section">
                    <h3>Students</h3>
                    <div class="students-grid" id="studentsGrid">
                        <div class="empty-state">
                            <div>Waiting for students to join...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sidebar">
                <!-- Controls Panel -->
                <div class="sidebar-panel">
                    <div class="panel-header">Meeting Controls</div>
                    <div class="panel-content controls-section">
                        <div class="control-group">
                            <h3>Media Controls</h3>
                            <div class="control-buttons">
                                <button class="btn btn-primary" onclick="startMeeting()" id="startMeetingBtn">
                                    <span class="btn-icon">▶️</span>
                                    Start Meeting
                                </button>
                                <button class="btn btn-danger" onclick="endMeeting()" id="endMeetingBtn" disabled>
                                    <span class="btn-icon">⏹️</span>
                                    End Meeting
                                </button>
                                <button class="btn btn-warning" onclick="toggleScreenShare()" id="screenShareBtn">
                                    <span class="btn-icon">🖥️</span>
                                    Share Screen
                                </button>
                            </div>
                        </div>

                        <div class="control-group">
                            <h3>Recording</h3>
                            <div class="control-buttons">
                                <button class="btn btn-success" onclick="startRecording()" id="startRecordingBtn">
                                    <span class="btn-icon">🔴</span>
                                    Start Recording
                                </button>
                                <button class="btn btn-danger hidden" onclick="stopRecording()" id="stopRecordingBtn">
                                    <span class="btn-icon">⏹️</span>
                                    Stop Recording
                                </button>
                            </div>
                            <div class="recording-indicator hidden" id="recordingIndicator">
                                <span>🔴 RECORDING</span>
                                <div class="recording-progress">
                                    <div class="recording-progress-bar" id="recordingProgress"></div>
                                </div>
                            </div>
                        </div>

                        <div class="control-group">
                            <h3>Connection Info</h3>
                            <div class="control-buttons">
                                <button class="btn btn-secondary" onclick="testConnection()">
                                    <span class="btn-icon">🔍</span>
                                    Test Connection
                                </button>
                            </div>
                            <div id="connectionInfo" style="font-size: 12px; margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Participants Panel -->
                <div class="sidebar-panel">
                    <div class="panel-header">Participants</div>
                    <div class="panel-content">
                        <div class="participants-list" id="participantsList">
                            <div class="participant-item">
                                <div class="participant-avatar"><?php echo substr($meeting['lecturer_name'], 0, 1); ?></div>
                                <div class="participant-info">
                                    <div class="participant-name"><?php echo htmlspecialchars($meeting['lecturer_name']); ?></div>
                                    <div class="participant-role">Lecturer</div>
                                </div>
                                <div class="participant-status online"></div>
                            </div>
                        </div>
                    </div>
                </div>
<button onclick="alert('Button clicked!')">Test Button</button>
<button onclick="console.log('Console test')">Console Test</button>
                <!-- Chat Panel -->
                <div class="sidebar-panel">
                    <div class="panel-header">Chat</div>
                    <div class="panel-content">
                        <div class="chat-messages" id="chatMessages">
                            <div class="chat-message system">
                                <div class="message-text">Meeting room created. Waiting for participants...</div>
                                <div class="message-time"><?php echo date('H:i'); ?></div>
                            </div>
                        </div>
                        <div class="chat-input">
                            <input type="text" id="chatInput" placeholder="Type a message..." onkeypress="handleChatKeypress(event)">
                            <button class="btn btn-primary" onclick="sendChatMessage()">Send</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Scripts -->
    <script src="../public/js/api.js"></script>
    <script src="../public/js/ws_signaling.js"></script>
    <script src="../public/js/webrtc_host.js"></script>
    
    <script>
        // Debug function
        function debugLog(message, data = null) {
            console.log('[DEBUG]', message, data);
            const debugContent = document.getElementById('debugContent');
            if (debugContent) {
                debugContent.innerHTML += `<div>${new Date().toLocaleTimeString()}: ${message} ${data ? JSON.stringify(data) : ''}</div>`;
                debugContent.scrollTop = debugContent.scrollHeight;
            }
        }

        function toggleDebug() {
            const panel = document.getElementById('debugPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        // Configuration
        const config = {
            meetingId: <?php echo $meeting_id; ?>,
            userId: <?php echo $user_id; ?>,
            baseUrl: '<?php echo dirname($_SERVER['PHP_SELF']); ?>'
        };

        debugLog('Configuration loaded', config);

        // Global variables
        let webrtcHost = null;
        let isMeetingActive = false;
        let isRecording = false;
        let recordingStartTime = null;

        // Test basic functionality
        function testConnection() {
            debugLog('Testing connection...');
            
            // Test if APIs are available
            const tests = {
                'MeetingAPI': typeof MeetingAPI !== 'undefined',
                'WebRTCHost': typeof WebRTCHost !== 'undefined',
                'navigator.mediaDevices': typeof navigator.mediaDevices !== 'undefined',
                'RTCPeerConnection': typeof RTCPeerConnection !== 'undefined',
                'MediaRecorder': typeof MediaRecorder !== 'undefined'
            };

            debugLog('API Availability Test', tests);
            
            let connectionInfo = 'API Tests:<br>';
            Object.entries(tests).forEach(([name, available]) => {
                connectionInfo += `${name}: ${available ? '✅' : '❌'}<br>`;
            });
            
            document.getElementById('connectionInfo').innerHTML = connectionInfo;
            showToast('Connection test completed', 'success');
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', async function() {
            debugLog('DOM loaded, initializing meeting...');
            await initializeMeeting();
            startParticipantsPolling();
            testConnection(); // Run initial connection test
        });

        async function initializeMeeting() {
            try {
                debugLog('Initializing WebRTC host...');
                
                // Initialize WebRTC host
                webrtcHost = new WebRTCHost({
                    meetingId: config.meetingId,
                    userId: config.userId,
                    baseUrl: config.baseUrl,
                    onRemoteStream: handleRemoteStream,
                    onRemoteStreamEnded: handleRemoteStreamEnded,
                    onSignalingStateChange: handleSignalingStateChange,
                    onIceConnectionStateChange: handleIceConnectionStateChange
                });

                debugLog('WebRTCHost instance created');
                
                await webrtcHost.initialize();
                debugLog('WebRTCHost initialized successfully');
                
                // Start local stream
                debugLog('Requesting local media stream...');
                const localStream = await webrtcHost.startLocalStream();
                document.getElementById('localVideo').srcObject = localStream;
                
                updateLocalStreamInfo();
                updateStatus('connected', 'Connected');
                showToast('Meeting initialized successfully', 'success');
                debugLog('Local stream started successfully');

            } catch (error) {
                console.error('Failed to initialize meeting:', error);
                debugLog('Initialization failed', error.message);
                updateStatus('disconnected', 'Connection Failed');
                showToast('Failed to initialize meeting: ' + error.message, 'error');
            }
        }

        function handleRemoteStream(studentId, stream) {
            debugLog('Remote stream received from student:', studentId);
            // Create video element for student
            const videoId = `remoteVideo_${studentId}`;
            let videoElement = document.getElementById(videoId);
            
            if (!videoElement) {
                videoElement = document.createElement('video');
                videoElement.id = videoId;
                videoElement.autoplay = true;
                videoElement.playsInline = true;
                
                const videoItem = document.createElement('div');
                videoItem.className = 'video-item';
                videoItem.innerHTML = `
                    <div class="video-overlay">
                        <div class="video-info">
                            <strong>Student ${studentId}</strong>
                            <div>Connected</div>
                        </div>
                    </div>
                `;
                videoItem.appendChild(videoElement);
                
                // Remove empty state if it exists
                const emptyState = document.querySelector('#remoteVideosGrid .empty-state');
                if (emptyState) emptyState.remove();
                
                document.getElementById('remoteVideosGrid').appendChild(videoItem);
            }
            
            videoElement.srcObject = stream;
            updateStudentCard(studentId, 'connected');
        }

        function handleRemoteStreamEnded(studentId) {
            debugLog('Remote stream ended for student:', studentId);
            const videoElement = document.getElementById(`remoteVideo_${studentId}`);
            if (videoElement) {
                videoElement.parentElement.remove();
            }
            updateStudentCard(studentId, 'offline');
        }

        function handleSignalingStateChange(studentId, state) {
            debugLog(`Signaling state for student ${studentId}: ${state}`);
        }

        function handleIceConnectionStateChange(studentId, state) {
            debugLog(`ICE connection state for student ${studentId}: ${state}`);
            updateStudentCard(studentId, state === 'connected' ? 'connected' : 'connecting');
        }

        // Control Functions
        async function startMeeting() {
            debugLog('Starting meeting...');
            try {
                await webrtcHost.api.startMeeting(config.meetingId, config.userId);
                isMeetingActive = true;
                document.getElementById('startMeetingBtn').disabled = true;
                document.getElementById('endMeetingBtn').disabled = false;
                showToast('Meeting started', 'success');
                debugLog('Meeting started successfully');
            } catch (error) {
                debugLog('Failed to start meeting', error.message);
                showToast('Failed to start meeting: ' + error.message, 'error');
            }
        }

        async function endMeeting() {
            debugLog('Ending meeting...');
            try {
                await webrtcHost.api.endMeeting(config.meetingId, config.userId);
                isMeetingActive = false;
                document.getElementById('startMeetingBtn').disabled = false;
                document.getElementById('endMeetingBtn').disabled = true;
                webrtcHost.disconnect();
                showToast('Meeting ended', 'success');
                debugLog('Meeting ended successfully');
                
                // Redirect after delay
                setTimeout(() => {
                    window.location.href = 'meetings.php';
                }, 2000);
            } catch (error) {
                debugLog('Failed to end meeting', error.message);
                showToast('Failed to end meeting: ' + error.message, 'error');
            }
        }

        async function toggleScreenShare() {
            debugLog('Toggling screen share...');
            try {
                if (!webrtcHost.isScreenSharing) {
                    const screenStream = await webrtcHost.startScreenShare();
                    document.getElementById('screenShareVideo').srcObject = screenStream;
                    document.getElementById('screenShareContainer').classList.remove('hidden');
                    document.getElementById('screenShareBtn').innerHTML = '<span class="btn-icon">🖥️</span> Stop Share';
                    showToast('Screen sharing started', 'success');
                    debugLog('Screen sharing started');
                } else {
                    await webrtcHost.stopScreenShare();
                    document.getElementById('screenShareContainer').classList.add('hidden');
                    document.getElementById('screenShareBtn').innerHTML = '<span class="btn-icon">🖥️</span> Share Screen';
                    showToast('Screen sharing stopped', 'success');
                    debugLog('Screen sharing stopped');
                }
            } catch (error) {
                debugLog('Screen share toggle failed', error.message);
                showToast('Failed to toggle screen share: ' + error.message, 'error');
            }
        }

        function stopScreenShare() {
            debugLog('Stopping screen share via stop button');
            if (webrtcHost.isScreenSharing) {
                toggleScreenShare();
            }
        }

        async function startRecording() {
            debugLog('Starting recording...');
            try {
                await webrtcHost.startRecording();
                isRecording = true;
                recordingStartTime = Date.now();
                document.getElementById('startRecordingBtn').classList.add('hidden');
                document.getElementById('stopRecordingBtn').classList.remove('hidden');
                document.getElementById('recordingIndicator').classList.remove('hidden');
                showToast('Recording started', 'success');
                debugLog('Recording started');
                
                // Update recording progress
                updateRecordingProgress();
            } catch (error) {
                debugLog('Recording start failed', error.message);
                showToast('Failed to start recording: ' + error.message, 'error');
            }
        }

        async function stopRecording() {
            debugLog('Stopping recording...');
            try {
                webrtcHost.stopRecording();
                isRecording = false;
                document.getElementById('startRecordingBtn').classList.remove('hidden');
                document.getElementById('stopRecordingBtn').classList.add('hidden');
                document.getElementById('recordingIndicator').classList.add('hidden');
                showToast('Recording stopped and saved', 'success');
                debugLog('Recording stopped');
            } catch (error) {
                debugLog('Recording stop failed', error.message);
                showToast('Failed to stop recording: ' + error.message, 'error');
            }
        }

        function updateRecordingProgress() {
            if (!isRecording) return;
            
            const elapsed = Date.now() - recordingStartTime;
            const minutes = Math.floor(elapsed / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            document.getElementById('recordingProgress').style.width = `${(elapsed % 10000) / 100}%`;
            
            setTimeout(updateRecordingProgress, 100);
        }

        function toggleCamera() {
            debugLog('Toggling camera');
            if (webrtcHost.localStream) {
                const videoTrack = webrtcHost.localStream.getVideoTracks()[0];
                if (videoTrack) {
                    videoTrack.enabled = !videoTrack.enabled;
                    updateLocalStreamInfo();
                    showToast(`Camera ${videoTrack.enabled ? 'enabled' : 'disabled'}`, 'success');
                    debugLog(`Camera ${videoTrack.enabled ? 'enabled' : 'disabled'}`);
                }
            }
        }

        function toggleMicrophone() {
            debugLog('Toggling microphone');
            if (webrtcHost.localStream) {
                const audioTrack = webrtcHost.localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = !audioTrack.enabled;
                    updateLocalStreamInfo();
                    showToast(`Microphone ${audioTrack.enabled ? 'enabled' : 'disabled'}`, 'success');
                    debugLog(`Microphone ${audioTrack.enabled ? 'enabled' : 'disabled'}`);
                }
            }
        }

        function updateLocalStreamInfo() {
            if (webrtcHost.localStream) {
                const videoTrack = webrtcHost.localStream.getVideoTracks()[0];
                const audioTrack = webrtcHost.localStream.getAudioTracks()[0];
                
                const cameraText = videoTrack?.enabled ? 'Camera: On' : 'Camera: Off';
                const micText = audioTrack?.enabled ? 'Mic: On' : 'Mic: Off';
                
                document.getElementById('localStreamInfo').textContent = `${cameraText} | ${micText}`;
                document.getElementById('cameraText').textContent = videoTrack?.enabled ? 'Camera On' : 'Camera Off';
                document.getElementById('micText').textContent = audioTrack?.enabled ? 'Mic On' : 'Mic Off';
            }
        }

        function updateStatus(status, text) {
            const indicator = document.getElementById('statusIndicator');
            const statusText = document.getElementById('statusText');
            
            indicator.className = `status-indicator ${status}`;
            statusText.textContent = text;
            debugLog(`Status updated: ${status} - ${text}`);
        }

        async function startParticipantsPolling() {
            debugLog('Starting participants polling');
            setInterval(async () => {
                if (!isMeetingActive) return;
                
                try {
                    const response = await webrtcHost.api.listParticipants(config.meetingId, config.userId);
                    if (response.success) {
                        updateParticipantsList(response.participants);
                        updateStudentsGrid(response.participants);
                    }
                } catch (error) {
                    console.error('Failed to fetch participants:', error);
                }
            }, 1500);
        }

        function updateParticipantsList(participants) {
            const container = document.getElementById('participantsList');
            // Keep lecturer always visible
            const lecturerItem = container.querySelector('.participant-item:first-child');
            container.innerHTML = '';
            if (lecturerItem) container.appendChild(lecturerItem);
            
            participants.forEach(participant => {
                if (participant.role === 'student') {
                    const participantEl = document.createElement('div');
                    participantEl.className = 'participant-item';
                    participantEl.innerHTML = `
                        <div class="participant-avatar">
                            ${participant.name.charAt(0).toUpperCase()}
                        </div>
                        <div class="participant-info">
                            <div class="participant-name">${participant.name}</div>
                            <div class="participant-role">${participant.role}</div>
                        </div>
                        <div class="participant-status ${participant.status}"></div>
                    `;
                    container.appendChild(participantEl);
                }
            });
        }

        function updateStudentsGrid(participants) {
            const students = participants.filter(p => p.role === 'student');
            const container = document.getElementById('studentsGrid');
            
            if (students.length === 0) {
                container.innerHTML = '<div class="empty-state">Waiting for students to join...</div>';
                return;
            }
            
            container.innerHTML = '';
            students.forEach(student => {
                const studentEl = document.createElement('div');
                studentEl.className = `student-card ${student.status}`;
                studentEl.innerHTML = `
                    <div class="student-avatar">
                        ${student.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="student-name">${student.name}</div>
                    <div class="student-status status-${student.status}">
                        ${student.status === 'online' ? 'Online' : 'Offline'}
                    </div>
                `;
                container.appendChild(studentEl);
            });
        }

        function updateStudentCard(studentId, status) {
            debugLog(`Updating student card: ${studentId} - ${status}`);
            // Implementation depends on your student data structure
        }

        function handleChatKeypress(event) {
            if (event.key === 'Enter') {
                sendChatMessage();
            }
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                // Add message to chat UI
                addChatMessage(config.userId, 'You', message, true);
                input.value = '';
                debugLog('Chat message sent', message);
                
                // In a real implementation, you would send this via signaling
                // webrtcHost.sendSignal('chat', null, { message: message });
            }
        }

        function addChatMessage(userId, userName, message, isOwn = false) {
            const container = document.getElementById('chatMessages');
            const messageEl = document.createElement('div');
            messageEl.className = `chat-message ${isOwn ? 'own' : 'other'}`;
            messageEl.innerHTML = `
                <div class="message-sender">${userName}</div>
                <div class="message-text">${message}</div>
                <div class="message-time">${new Date().toLocaleTimeString()}</div>
            `;
            container.appendChild(messageEl);
            container.scrollTop = container.scrollHeight;
        }

        function showToast(message, type = 'info') {
            debugLog(`Showing toast: ${type} - ${message}`);
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-header">
                    <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                    <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
                <div class="toast-body">${message}</div>
            `;
            container.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Handle page unload
        window.addEventListener('beforeunload', () => {
            debugLog('Page unloading, cleaning up...');
            if (webrtcHost) {
                webrtcHost.disconnect();
            }
            if (isMeetingActive) {
                webrtcHost.api.leaveMeeting(config.meetingId, config.userId);
            }
        });

        // Global error handler
        window.addEventListener('error', function(e) {
            debugLog('Global error caught', {
                message: e.error?.message,
                file: e.filename,
                line: e.lineno,
                column: e.colno
            });
        });

        // Promise rejection handler
        window.addEventListener('unhandledrejection', function(e) {
            debugLog('Unhandled promise rejection', e.reason);
        });
    </script>
    <script>
// Test if scripts are loading
console.log('Testing script loading...');

// Check each required script
const scripts = [
    '../public/js/api.js',
    '../public/js/ws_signaling.js', 
    '../public/js/webrtc_host.js'
];

scripts.forEach(script => {
    const scriptTag = document.createElement('script');
    scriptTag.src = script;
    scriptTag.onload = () => console.log('✅ Loaded:', script);
    scriptTag.onerror = () => console.log('❌ Failed to load:', script);
    document.head.appendChild(scriptTag);
});
</script>
</body>
</html>