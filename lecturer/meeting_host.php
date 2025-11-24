<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a lecturer
session_start();

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
$meeting = executeQuery($sql, [$meeting_id, $user_id], "ii");

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
</head>
<body>
    <div class="meeting-container">
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
                    <video id="localVideo" autoplay muted></video>
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
                    <video id="screenShareVideo" autoplay></video>
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
                    <!-- Student videos will be added here dynamically -->
                </div>

                <!-- Students Grid -->
                <div class="students-section">
                    <h3>Students</h3>
                    <div class="students-grid" id="studentsGrid">
                        <!-- Student cards will be added here dynamically -->
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
                            <h3>Signaling Mode</h3>
                            <div class="control-buttons">
                                <button class="btn btn-secondary" id="signalingModeBtn">
                                    <span class="btn-icon">🔌</span>
                                    <span id="signalingModeText">Polling Mode</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Participants Panel -->
                <div class="sidebar-panel">
                    <div class="panel-header">Participants</div>
                    <div class="panel-content">
                        <div class="participants-list" id="participantsList">
                            <!-- Participants will be added here dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Chat Panel -->
                <div class="sidebar-panel">
                    <div class="panel-header">Chat</div>
                    <div class="panel-content">
                        <div class="chat-messages" id="chatMessages">
                            <!-- Chat messages will be added here -->
                        </div>
                        <div class="chat-input">
                            <input type="text" id="chatInput" placeholder="Type a message...">
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
        // Configuration
        const config = {
            meetingId: <?php echo $meeting_id; ?>,
            userId: <?php echo $user_id; ?>,
            baseUrl: '<?php echo dirname($_SERVER['PHP_SELF']); ?>'
        };

        // Global variables
        let webrtcHost = null;
        let isMeetingActive = false;
        let isRecording = false;
        let recordingStartTime = null;

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', async function() {
            await initializeMeeting();
            startParticipantsPolling();
        });

        async function initializeMeeting() {
            try {
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

                await webrtcHost.initialize();
                
                // Start local stream
                const localStream = await webrtcHost.startLocalStream();
                document.getElementById('localVideo').srcObject = localStream;
                
                updateLocalStreamInfo();
                updateStatus('connected', 'Connected');
                showToast('Meeting initialized successfully', 'success');

            } catch (error) {
                console.error('Failed to initialize meeting:', error);
                updateStatus('disconnected', 'Connection Failed');
                showToast('Failed to initialize meeting: ' + error.message, 'error');
            }
        }

        function handleRemoteStream(studentId, stream) {
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
                document.getElementById('remoteVideosGrid').appendChild(videoItem);
            }
            
            videoElement.srcObject = stream;
            updateStudentCard(studentId, 'connected');
        }

        function handleRemoteStreamEnded(studentId) {
            const videoElement = document.getElementById(`remoteVideo_${studentId}`);
            if (videoElement) {
                videoElement.parentElement.remove();
            }
            updateStudentCard(studentId, 'offline');
        }

        function handleSignalingStateChange(studentId, state) {
            console.log(`Signaling state for student ${studentId}: ${state}`);
        }

        function handleIceConnectionStateChange(studentId, state) {
            console.log(`ICE connection state for student ${studentId}: ${state}`);
            updateStudentCard(studentId, state === 'connected' ? 'connected' : 'connecting');
        }

        async function startMeeting() {
            try {
                await webrtcHost.api.startMeeting(config.meetingId, config.userId);
                isMeetingActive = true;
                document.getElementById('startMeetingBtn').disabled = true;
                document.getElementById('endMeetingBtn').disabled = false;
                showToast('Meeting started', 'success');
            } catch (error) {
                showToast('Failed to start meeting: ' + error.message, 'error');
            }
        }

        async function endMeeting() {
            try {
                await webrtcHost.api.endMeeting(config.meetingId, config.userId);
                isMeetingActive = false;
                document.getElementById('startMeetingBtn').disabled = false;
                document.getElementById('endMeetingBtn').disabled = true;
                webrtcHost.disconnect();
                showToast('Meeting ended', 'success');
                
                // Redirect after delay
                setTimeout(() => {
                    window.location.href = 'meetings.php';
                }, 2000);
            } catch (error) {
                showToast('Failed to end meeting: ' + error.message, 'error');
            }
        }

        async function toggleScreenShare() {
            try {
                if (!webrtcHost.isScreenSharing) {
                    const screenStream = await webrtcHost.startScreenShare();
                    document.getElementById('screenShareVideo').srcObject = screenStream;
                    document.getElementById('screenShareContainer').classList.remove('hidden');
                    document.getElementById('screenShareBtn').innerHTML = '<span class="btn-icon">🖥️</span> Stop Share';
                    showToast('Screen sharing started', 'success');
                } else {
                    await webrtcHost.stopScreenShare();
                    document.getElementById('screenShareContainer').classList.add('hidden');
                    document.getElementById('screenShareBtn').innerHTML = '<span class="btn-icon">🖥️</span> Share Screen';
                    showToast('Screen sharing stopped', 'success');
                }
            } catch (error) {
                showToast('Failed to toggle screen share: ' + error.message, 'error');
            }
        }

        function stopScreenShare() {
            if (webrtcHost.isScreenSharing) {
                toggleScreenShare();
            }
        }

        async function startRecording() {
            try {
                await webrtcHost.startRecording();
                isRecording = true;
                recordingStartTime = Date.now();
                document.getElementById('startRecordingBtn').classList.add('hidden');
                document.getElementById('stopRecordingBtn').classList.remove('hidden');
                document.getElementById('recordingIndicator').classList.remove('hidden');
                showToast('Recording started', 'success');
                
                // Update recording progress
                updateRecordingProgress();
            } catch (error) {
                showToast('Failed to start recording: ' + error.message, 'error');
            }
        }

        async function stopRecording() {
            try {
                webrtcHost.stopRecording();
                isRecording = false;
                document.getElementById('startRecordingBtn').classList.remove('hidden');
                document.getElementById('stopRecordingBtn').classList.add('hidden');
                document.getElementById('recordingIndicator').classList.add('hidden');
                showToast('Recording stopped and saved', 'success');
            } catch (error) {
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
            if (webrtcHost.localStream) {
                const videoTrack = webrtcHost.localStream.getVideoTracks()[0];
                if (videoTrack) {
                    videoTrack.enabled = !videoTrack.enabled;
                    updateLocalStreamInfo();
                    showToast(`Camera ${videoTrack.enabled ? 'enabled' : 'disabled'}`, 'success');
                }
            }
        }

        function toggleMicrophone() {
            if (webrtcHost.localStream) {
                const audioTrack = webrtcHost.localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = !audioTrack.enabled;
                    updateLocalStreamInfo();
                    showToast(`Microphone ${audioTrack.enabled ? 'enabled' : 'disabled'}`, 'success');
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
        }

        async function startParticipantsPolling() {
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
            container.innerHTML = '';
            
            participants.forEach(participant => {
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
            });
        }

        function updateStudentsGrid(participants) {
            const students = participants.filter(p => p.role === 'student');
            const container = document.getElementById('studentsGrid');
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
            // This would update individual student cards based on WebRTC connection status
            // Implementation depends on your student data structure
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                // Add message to chat UI
                addChatMessage(config.userId, 'You', message, true);
                input.value = '';
                
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
            if (webrtcHost) {
                webrtcHost.disconnect();
            }
            if (isMeetingActive) {
                webrtcHost.api.leaveMeeting(config.meetingId, config.userId);
            }
        });
    </script>
</body>
</html>