<?php
session_start();
require_once '../config/db.php';
require_once '../config/meeting.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$meeting_id = $_GET['meeting_id'] ?? 0;

if (!$meeting_id) {
    die('Meeting ID is required');
}

// Get meeting details and validate student access
$sql = "SELECT m.*, u.name as unit_name, l.name as lecturer_name 
        FROM meetings m 
        JOIN units u ON m.unit_id = u.id 
        JOIN lecturers l ON m.lecturer_id = l.id 
        JOIN student_unit su ON su.unit_id = u.id 
        WHERE m.id = ? AND su.student_id = ? AND m.meeting_status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $meeting_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();

if (!$meeting) {
    die('Meeting not found, access denied, or meeting is not active');
}

$lecturer_id = $meeting['lecturer_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Meeting - <?php echo htmlspecialchars($meeting['title']); ?></title>
    <link rel="stylesheet" href="../public/css/meeting.css">
</head>
<body>
    <div class="meeting-container">
        <div class="meeting-header">
            <div class="meeting-title">
                <h1><?php echo htmlspecialchars($meeting['title']); ?></h1>
                <p>Unit: <?php echo htmlspecialchars($meeting['unit_name']); ?> | Lecturer: <?php echo htmlspecialchars($meeting['lecturer_name']); ?></p>
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
                <div class="video-item">
                    <video id="localVideo" autoplay muted></video>
                    <div class="video-overlay">
                        <div class="video-info">
                            <strong>You (Student)</strong>
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

                <!-- Lecturer Video (Python-rendered composite) -->
                <div class="video-item lecturer" id="lecturerVideoContainer">
                    <canvas id="lecturerCanvas" class="meeting-render-canvas"></canvas>
                    <video id="lecturerVideo" autoplay class="hidden" style="display:none"></video>
                    <div class="video-overlay">
                        <div class="video-info">
                            <strong>Lecturer</strong>
                            <div id="lecturerStreamInfo">Waiting for stream...</div>
                        </div>
                    </div>
                </div>

                <!-- Screen Share Video -->
                <div class="video-item screen-share hidden" id="screenShareContainer">
                    <canvas id="screenShareCanvas" class="meeting-render-canvas"></canvas>
                    <video id="screenShareVideo" autoplay class="hidden" style="display:none"></video>
                    <div class="video-overlay">
                        <div class="video-info">
                            <strong>Screen Share</strong>
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
                                <button class="btn btn-primary" onclick="requestToPublish()" id="publishBtn">
                                    <span class="btn-icon">📹</span>
                                    Request to Publish
                                </button>
                                <button class="btn btn-danger hidden" onclick="stopPublishing()" id="stopPublishBtn">
                                    <span class="btn-icon">⏹️</span>
                                    Stop Publishing
                                </button>
                                <button class="btn btn-warning" onclick="leaveMeeting()" id="leaveBtn">
                                    <span class="btn-icon">🚪</span>
                                    Leave Meeting
                                </button>
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
    <script src="../public/js/webrtc_student.js"></script>
    <script src="../public/js/meeting_media_bridge.js"></script>
    
    <script>
        // Configuration
        const config = {
            meetingId: <?php echo $meeting_id; ?>,
            userId: <?php echo $user_id; ?>,
            lecturerId: <?php echo $lecturer_id; ?>,
            baseUrl: '..',
            mediaServerUrl: <?php echo json_encode(getMeetingMediaWsUrl()); ?>
        };

        // Global variables
        let webrtcStudent = null;
        let mediaBridge = null;
        let isPublishing = false;

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', async function() {
            await initializeMeeting();
            startParticipantsPolling();
        });

        async function initializeMeeting() {
            try {
                // Initialize WebRTC student
                webrtcStudent = new WebRTCStudent({
                    meetingId: config.meetingId,
                    userId: config.userId,
                    lecturerId: config.lecturerId,
                    baseUrl: config.baseUrl,
                    onRemoteStream: handleRemoteStream,
                    onRemoteStreamEnded: handleRemoteStreamEnded,
                    onConnectionStateChange: handleConnectionStateChange,
                    onSignalingStateChange: handleSignalingStateChange
                });

                await webrtcStudent.initialize();

                await initializeMediaBridge();

                updateStatus('connected', 'Connected');
                showToast('Successfully joined meeting', 'success');

            } catch (error) {
                console.error('Failed to join meeting:', error);
                updateStatus('disconnected', 'Connection Failed');
                showToast('Failed to join meeting: ' + error.message, 'error');
            }
        }

        async function initializeMediaBridge() {
            try {
                mediaBridge = new MeetingMediaBridge({
                    meetingId: config.meetingId,
                    userId: config.userId,
                    role: 'student',
                    mediaServerUrl: config.mediaServerUrl,
                    onStatusChange: (status) => console.log('Media bridge:', status),
                    onFrame: (message) => {
                        if (message.stream_type === 'screen' || message.stream_type === 'composite') {
                            document.getElementById('lecturerVideoContainer').classList.remove('hidden');
                        }
                        if (message.stream_type === 'screen') {
                            document.getElementById('screenShareContainer').classList.remove('hidden');
                        }
                    }
                });
                await mediaBridge.connect();

                const lecturerCanvas = document.getElementById('lecturerCanvas');
                mediaBridge.attachRenderCanvas(config.lecturerId, 'composite', lecturerCanvas);
                mediaBridge.attachRenderCanvas(config.lecturerId, 'screen', document.getElementById('screenShareCanvas'));
            } catch (error) {
                console.warn('Python media bridge unavailable:', error.message);
            }
        }

        function handleRemoteStream(remoteUserId, stream, role) {
            if (role === 'lecturer') {
                const videoElement = document.getElementById('lecturerVideo');
                const screenVideo = document.getElementById('screenShareVideo');
                videoElement.srcObject = stream;
                screenVideo.srcObject = stream;
                document.getElementById('lecturerStreamInfo').textContent = 'Streaming...';
                
                const videoTrack = stream.getVideoTracks()[0];
                if (videoTrack && videoTrack.label.toLowerCase().includes('screen')) {
                    document.getElementById('screenShareContainer').classList.remove('hidden');
                } else {
                    document.getElementById('lecturerVideoContainer').classList.remove('hidden');
                }
            }
        }

        function handleRemoteStreamEnded(remoteUserId, role) {
            if (role === 'lecturer') {
                document.getElementById('lecturerStreamInfo').textContent = 'Stream ended';
            }
        }

        function handleConnectionStateChange(remoteUserId, state, role) {
            console.log(`Connection state for ${role} ${remoteUserId}: ${state}`);
            
            if (role === 'lecturer') {
                const infoElement = document.getElementById('lecturerStreamInfo');
                infoElement.textContent = `Connection: ${state}`;
                
                if (state === 'connected') {
                    infoElement.textContent = 'Streaming...';
                } else if (state === 'disconnected' || state === 'failed') {
                    infoElement.textContent = 'Connection lost';
                }
            }
        }

        function handleSignalingStateChange(remoteUserId, state, role) {
            console.log(`Signaling state for ${role} ${remoteUserId}: ${state}`);
        }

        async function requestToPublish() {
            try {
                await webrtcStudent.requestToPublish();
                isPublishing = true;
                document.getElementById('publishBtn').classList.add('hidden');
                document.getElementById('stopPublishBtn').classList.remove('hidden');
                
                // Show local video
                const localVideo = document.getElementById('localVideo');
                localVideo.srcObject = webrtcStudent.localStream;
                if (mediaBridge) {
                    mediaBridge.publishStream(localVideo, 'camera', 'camera');
                }
                updateLocalStreamInfo();
                
                showToast('Requested to publish video', 'success');
            } catch (error) {
                showToast('Failed to request publishing: ' + error.message, 'error');
            }
        }

        async function stopPublishing() {
            webrtcStudent.stopPublishing();
            if (mediaBridge) {
                mediaBridge.stopPublishing('camera');
            }
            isPublishing = false;
            document.getElementById('publishBtn').classList.remove('hidden');
            document.getElementById('stopPublishBtn').classList.add('hidden');
            
            // Hide local video
            const localVideo = document.getElementById('localVideo');
            localVideo.srcObject = null;
            updateLocalStreamInfo();
            
            showToast('Stopped publishing video', 'success');
        }

        function toggleCamera() {
            if (webrtcStudent.localStream) {
                const videoTrack = webrtcStudent.localStream.getVideoTracks()[0];
                if (videoTrack) {
                    videoTrack.enabled = !videoTrack.enabled;
                    updateLocalStreamInfo();
                    showToast(`Camera ${videoTrack.enabled ? 'enabled' : 'disabled'}`, 'success');
                }
            }
        }

        function toggleMicrophone() {
            if (webrtcStudent.localStream) {
                const audioTrack = webrtcStudent.localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = !audioTrack.enabled;
                    updateLocalStreamInfo();
                    showToast(`Microphone ${audioTrack.enabled ? 'enabled' : 'disabled'}`, 'success');
                }
            }
        }

        function updateLocalStreamInfo() {
            if (webrtcStudent.localStream) {
                const videoTrack = webrtcStudent.localStream.getVideoTracks()[0];
                const audioTrack = webrtcStudent.localStream.getAudioTracks()[0];
                
                const cameraText = videoTrack?.enabled ? 'Camera: On' : 'Camera: Off';
                const micText = audioTrack?.enabled ? 'Mic: On' : 'Mic: Off';
                
                document.getElementById('localStreamInfo').textContent = `${cameraText} | ${micText}`;
                document.getElementById('cameraText').textContent = videoTrack?.enabled ? 'Camera On' : 'Camera Off';
                document.getElementById('micText').textContent = audioTrack?.enabled ? 'Mic On' : 'Mic Off';
            } else {
                document.getElementById('localStreamInfo').textContent = 'Camera: Off | Mic: Off';
                document.getElementById('cameraText').textContent = 'Camera Off';
                document.getElementById('micText').textContent = 'Mic Off';
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
                try {
                    const response = await webrtcStudent.api.listParticipants(config.meetingId, config.userId);
                    if (response.success) {
                        updateParticipantsList(response.participants);
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

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                // Add message to chat UI
                addChatMessage(config.userId, 'You', message, true);
                input.value = '';
                
                // In a real implementation, you would send this via signaling
                // webrtcStudent.sendSignal('chat', null, { message: message });
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

        async function leaveMeeting() {
            if (mediaBridge) {
                mediaBridge.disconnect();
            }
            if (webrtcStudent) {
                webrtcStudent.disconnect();
            }
            
            showToast('Leaving meeting...', 'info');
            
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1000);
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
            if (mediaBridge) {
                mediaBridge.disconnect();
            }
            if (webrtcStudent) {
                webrtcStudent.disconnect();
            }
        });
    </script>
</body>
</html>