<?php
/**
 * STUDENT MEETING INTERFACE
 * WebRTC video conferencing for students
 */

session_start();
require_once '../config/db.php';

// Authentication guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)$_SESSION['user_id'];
$student_name = $_SESSION['user_name'] ?? 'Student';
$student_reg = $_SESSION['reg_no'] ?? '';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);

if (!$meeting_id) {
    die("Meeting ID required");
}

// Fetch meeting details and check if student is enrolled
$stmt = $conn->prepare("SELECT m.*, u.course_id, u.year 
                        FROM meetings m 
                        LEFT JOIN units u ON m.unit_id = u.id 
                        LEFT JOIN student_units su ON u.id = su.unit_id 
                        WHERE m.id = ? AND su.student_id = ?");
$stmt->bind_param("ii", $meeting_id, $student_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die("Meeting not found or access denied");
}

// Record attendance
$stmt = $conn->prepare("INSERT INTO meeting_attendance 
                       (meeting_id, student_id, joined_at, status) 
                       VALUES (?, ?, NOW(), 'joined')
                       ON DUPLICATE KEY UPDATE status='joined', joined_at=NOW()");
$stmt->bind_param("ii", $meeting_id, $student_id);
$stmt->execute();
$stmt->close();

// AJAX handlers for student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    function respond($data) {
        echo json_encode($data);
        exit;
    }
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'send_signal':
            $to_lecturer = (int)($_POST['to_lecturer_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_student_id, to_lecturer_id, type, data, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiss", $meeting_id, $student_id, $to_lecturer, $type, $data);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        case 'get_signals':
            $stmt = $conn->prepare("SELECT id, from_lecturer_id, type, data 
                                   FROM meeting_signals 
                                   WHERE meeting_id = ? AND to_student_id = ?
                                   ORDER BY id ASC");
            $stmt->bind_param("ii", $meeting_id, $student_id);
            $stmt->execute();
            $signals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Delete after fetching
            if (!empty($signals)) {
                $ids = array_column($signals, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("DELETE FROM meeting_signals WHERE id IN ($placeholders)");
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
            }
            respond($signals);
            break;
            
        case 'request_publish':
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_student_id, to_lecturer_id, type, data, created_at) 
                                   VALUES (?, ?, ?, 'request-publish', ?, NOW())");
            $data = json_encode(['name' => $student_name]);
            $stmt->bind_param("iiis", $meeting_id, $student_id, $meeting['lecturer_id'], $data);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        case 'get_chat':
            $stmt = $conn->prepare("SELECT user_id, user_name, message, created_at 
                                   FROM chat WHERE meeting_id = ? ORDER BY created_at ASC");
            $stmt->bind_param("i", $meeting_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            respond($result);
            break;
            
        case 'send_chat':
            $message = trim($_POST['message'] ?? '');
            if ($message === '') respond(['success' => false]);
            
            // Check if chat table exists, create if not
            $conn->query("CREATE TABLE IF NOT EXISTS chat (
                id INT AUTO_INCREMENT PRIMARY KEY,
                meeting_id INT NOT NULL,
                user_id INT NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_meeting (meeting_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $stmt = $conn->prepare("INSERT INTO chat (meeting_id, user_id, user_name, message, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $meeting_id, $student_id, $student_name, $message);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        case 'leave_meeting':
            // Update attendance status
            $stmt = $conn->prepare("UPDATE meeting_attendance SET status = 'left' 
                                   WHERE meeting_id = ? AND student_id = ?");
            $stmt->bind_param("ii", $meeting_id, $student_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        default:
            respond(['success' => false, 'error' => 'Unknown action']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($meeting['title']) ?> - Student</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-dark: #202124;
            --bg-darker: #171717;
            --bg-panel: #292929;
            --text-primary: #e8eaed;
            --text-secondary: #9aa0a6;
            --accent-blue: #8ab4f8;
            --accent-green: #81c995;
            --accent-red: #f28b82;
            --border-color: #3c4043;
        }
        
        body {
            font-family: 'Google Sans', Roboto, Arial, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            overflow: hidden;
            height: 100vh;
        }
        
        .top-nav {
            height: 64px;
            background: var(--bg-darker);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }
        
        .meeting-info h2 {
            font-size: 18px;
            font-weight: 400;
        }
        
        .meeting-info p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .meeting-container {
            display: flex;
            height: calc(100vh - 64px);
        }
        
        .main-video-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-darker);
            position: relative;
            overflow: hidden;
        }
        
        #lecturerVideo {
            max-width: 100%;
            max-height: 100%;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
        }
        
        .self-preview {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 240px;
            height: 135px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            border: 2px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            z-index: 10;
        }
        
        .self-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .self-preview .label {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: rgba(0,0,0,0.6);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .control-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: var(--bg-panel);
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            z-index: 50;
        }
        
        .control-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--bg-darker);
            border: none;
            color: var(--text-primary);
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .control-btn:hover {
            background: #3c4043;
            transform: scale(1.05);
        }
        
        .control-btn.active {
            background: var(--accent-blue);
        }
        
        .control-btn.danger {
            background: var(--accent-red);
        }
        
        .control-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .side-panel {
            width: 360px;
            background: var(--bg-panel);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        
        .panel-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }
        
        .panel-tab {
            flex: 1;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .panel-tab.active {
            border-bottom-color: var(--accent-blue);
            color: var(--accent-blue);
        }
        
        .panel-content {
            flex: 1;
            overflow-y: auto;
            display: none;
        }
        
        .panel-content.active {
            display: block;
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        .chat-message {
            margin-bottom: 16px;
        }
        
        .chat-message.me {
            text-align: right;
        }
        
        .message-sender {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .message-bubble {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 12px;
            background: var(--bg-darker);
            max-width: 80%;
            word-wrap: break-word;
        }
        
        .chat-message.me .message-bubble {
            background: var(--accent-blue);
            color: var(--bg-darker);
        }
        
        .chat-input-area {
            padding: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .chat-input-area textarea {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            background: var(--bg-darker);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            resize: none;
            font-family: inherit;
        }
        
        .chat-input-area button {
            margin-top: 8px;
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            background: var(--accent-blue);
            border: none;
            color: var(--bg-darker);
            font-weight: 500;
            cursor: pointer;
        }
        
        .status-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: var(--text-secondary);
        }
        
        .connection-status {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(0,0,0,0.7);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 10;
        }
        
        .connection-status.connected {
            background: rgba(16, 185, 129, 0.7);
        }
        
        .connection-status.disconnected {
            background: rgba(239, 68, 68, 0.7);
        }
        
        @media (max-width: 768px) {
            .self-preview {
                width: 120px;
                height: 68px;
                bottom: 100px;
                right: 10px;
            }
            
            .control-btn {
                width: 48px;
                height: 48px;
                font-size: 18px;
            }
            
            .side-panel {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 80px;
                width: 100%;
                max-width: 360px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }
            
            .side-panel.open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="meeting-info">
            <h2><?= htmlspecialchars($meeting['title']) ?></h2>
            <p>Meeting ID: <?= $meeting_id ?> | Joined as: <?= htmlspecialchars($student_name) ?></p>
        </div>
        <div>
            <a href="../student/dashboard.php" style="color: var(--text-secondary); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <div class="meeting-container">
        <div class="main-video-area">
            <div id="connectionStatus" class="connection-status disconnected">
                <i class="fas fa-circle"></i> Connecting...
            </div>
            
            <video id="lecturerVideo" autoplay playsinline></video>
            <div id="statusMessage" class="status-message">
                <i class="fas fa-spinner fa-spin"></i><br>
                Waiting for lecturer stream...
            </div>
            
            <div class="self-preview">
                <video id="selfVideo" autoplay muted playsinline></video>
                <div class="label">You</div>
            </div>
        </div>
        
        <div id="sidePanel" class="side-panel">
            <div class="panel-tabs">
                <div class="panel-tab active" data-tab="chat">
                    <i class="fas fa-comment"></i> Chat
                </div>
            </div>
            
            <div id="chatPanel" class="panel-content active">
                <div class="chat-container">
                    <div class="chat-messages" id="chatMessages"></div>
                    <div class="chat-input-area">
                        <textarea id="chatInput" placeholder="Type a message..." rows="2"></textarea>
                        <button id="sendChatBtn">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="control-bar">
        <button id="toggleMicBtn" class="control-btn" title="Microphone">
            <i class="fas fa-microphone"></i>
        </button>
        
        <button id="toggleCameraBtn" class="control-btn" title="Camera">
            <i class="fas fa-video"></i>
        </button>
        
        <button id="requestPublishBtn" class="control-btn" title="Request to Publish">
            <i class="fas fa-broadcast-tower"></i>
        </button>
        
        <button id="chatBtn" class="control-btn" title="Chat">
            <i class="fas fa-comment-dots"></i>
        </button>
        
        <button id="leaveBtn" class="control-btn danger" title="Leave Meeting">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>

    <script>
        // ==================== CONFIG ====================
        const MEETING_ID = <?= $meeting_id ?>;
        const STUDENT_ID = <?= $student_id ?>;
        const STUDENT_NAME = <?= json_encode($student_name) ?>;
        const LECTURER_ID = <?= $meeting['lecturer_id'] ?>;
        
        // ==================== STATE ====================
        let localStream = null;
        let lecturerStream = null;
        let peerConnection = null;
        let micEnabled = true;
        let cameraEnabled = true;
        let hasPublishPermission = false;
        let isConnected = false;
        
        // ==================== UTILITY FUNCTIONS ====================
        async function apiCall(action, data = {}) {
            try {
                const formData = new URLSearchParams({ action, ...data });
                const response = await fetch('', { method: 'POST', body: formData });
                return await response.json();
            } catch (error) {
                console.error('API call failed:', error);
                return { success: false, error: 'Network error' };
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function updateConnectionStatus(connected) {
            const statusEl = document.getElementById('connectionStatus');
            isConnected = connected;
            
            if (connected) {
                statusEl.innerHTML = '<i class="fas fa-circle"></i> Connected';
                statusEl.className = 'connection-status connected';
                document.getElementById('statusMessage').style.display = 'none';
            } else {
                statusEl.innerHTML = '<i class="fas fa-circle"></i> Connecting...';
                statusEl.className = 'connection-status disconnected';
                document.getElementById('statusMessage').style.display = 'block';
            }
        }
        
        // ==================== MEDIA INITIALIZATION ====================
        async function initializeMedia() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 640, height: 480 },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
                
                document.getElementById('selfVideo').srcObject = localStream;
                console.log('Student media initialized');
            } catch (error) {
                console.error('Error accessing media devices:', error);
                alert('Could not access camera/microphone. Please check permissions.');
            }
        }
        
        // ==================== WEBRTC FUNCTIONS ====================
        function createPeerConnection() {
            const pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            });
            
            // Handle incoming lecturer stream
            pc.ontrack = (event) => {
                console.log('Received lecturer stream:', event.streams[0]);
                lecturerStream = event.streams[0];
                document.getElementById('lecturerVideo').srcObject = lecturerStream;
                updateConnectionStatus(true);
            };
            
            // ICE candidate handling
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    apiCall('send_signal', {
                        to_lecturer_id: LECTURER_ID,
                        type: 'candidate',
                        data: JSON.stringify({ candidate: event.candidate })
                    });
                }
            };
            
            pc.onconnectionstatechange = () => {
                console.log('Connection state:', pc.connectionState);
                const connected = pc.connectionState === 'connected';
                updateConnectionStatus(connected);
                
                if (pc.connectionState === 'failed' || pc.connectionState === 'closed') {
                    setTimeout(() => {
                        if (peerConnection) {
                            peerConnection.close();
                            peerConnection = null;
                        }
                    }, 2000);
                }
            };
            
            pc.oniceconnectionstatechange = () => {
                console.log('ICE connection state:', pc.iceConnectionState);
            };
            
            return pc;
        }
        
        async function handleOffer(offerData) {
            try {
                if (!peerConnection) {
                    peerConnection = createPeerConnection();
                }
                
                await peerConnection.setRemoteDescription(new RTCSessionDescription({
                    type: 'offer',
                    sdp: offerData.sdp
                }));
                
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                
                await apiCall('send_signal', {
                    to_lecturer_id: LECTURER_ID,
                    type: 'answer',
                    data: JSON.stringify({ sdp: answer.sdp })
                });
                
                console.log('Answer sent to lecturer');
            } catch (error) {
                console.error('Error handling offer:', error);
            }
        }
        
        async function publishMyStream() {
            if (!hasPublishPermission || !localStream || !peerConnection) {
                console.log('No permission to publish or no stream');
                return;
            }
            
            try {
                // Remove existing tracks first
                const senders = peerConnection.getSenders();
                senders.forEach(sender => peerConnection.removeTrack(sender));
                
                // Add local tracks to peer connection
                localStream.getTracks().forEach(track => {
                    peerConnection.addTrack(track, localStream);
                });
                
                // Create and send offer to lecturer
                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                
                await apiCall('send_signal', {
                    to_lecturer_id: LECTURER_ID,
                    type: 'offer',
                    data: JSON.stringify({ sdp: offer.sdp })
                });
                
                console.log('Published stream to lecturer');
            } catch (error) {
                console.error('Error publishing stream:', error);
            }
        }
        
        async function handleSignal(signal) {
            const { from_lecturer_id, type, data } = signal;
            const parsedData = JSON.parse(data || '{}');
            
            try {
                switch (type) {
                    case 'offer':
                        // Lecturer is sending their stream
                        await handleOffer(parsedData);
                        break;
                        
                    case 'candidate':
                        if (peerConnection && parsedData.candidate) {
                            await peerConnection.addIceCandidate(new RTCIceCandidate(parsedData.candidate));
                        }
                        break;
                        
                    case 'allow-publish':
                        hasPublishPermission = true;
                        document.getElementById('requestPublishBtn').classList.add('active');
                        console.log('Received publish permission');
                        await publishMyStream();
                        break;
                        
                    case 'end':
                        alert('Lecturer has ended the meeting');
                        await apiCall('leave_meeting');
                        window.location.href = '../student/dashboard.php';
                        break;
                }
            } catch (error) {
                console.error('Error handling signal:', error);
            }
        }
        
        // ==================== CHAT ====================
        async function loadChat() {
            try {
                const messages = await apiCall('get_chat');
                const container = document.getElementById('chatMessages');
                container.innerHTML = '';
                
                messages.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = `chat-message ${msg.user_id == STUDENT_ID ? 'me' : ''}`;
                    div.innerHTML = `
                        <div class="message-sender">${escapeHtml(msg.user_name)}</div>
                        <div class="message-bubble">${escapeHtml(msg.message)}</div>
                    `;
                    container.appendChild(div);
                });
                
                container.scrollTop = container.scrollHeight;
            } catch (error) {
                console.error('Error loading chat:', error);
            }
        }
        
        async function sendChat() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message) {
                await apiCall('send_chat', { message });
                input.value = '';
                loadChat();
            }
        }
        
        // ==================== SIGNAL POLLING ====================
        async function pollSignals() {
            try {
                const signals = await apiCall('get_signals');
                for (const signal of signals) {
                    await handleSignal(signal);
                }
            } catch (error) {
                console.error('Error polling signals:', error);
            }
        }
        
        // ==================== UI CONTROLS ====================
        document.getElementById('toggleMicBtn').addEventListener('click', () => {
            if (localStream) {
                micEnabled = !micEnabled;
                localStream.getAudioTracks().forEach(track => {
                    track.enabled = micEnabled;
                });
                
                const btn = document.getElementById('toggleMicBtn');
                btn.innerHTML = micEnabled ? 
                    '<i class="fas fa-microphone"></i>' : 
                    '<i class="fas fa-microphone-slash"></i>';
                btn.classList.toggle('active', !micEnabled);
            }
        });
        
        document.getElementById('toggleCameraBtn').addEventListener('click', () => {
            if (localStream) {
                cameraEnabled = !cameraEnabled;
                localStream.getVideoTracks().forEach(track => {
                    track.enabled = cameraEnabled;
                });
                
                const btn = document.getElementById('toggleCameraBtn');
                btn.innerHTML = cameraEnabled ? 
                    '<i class="fas fa-video"></i>' : 
                    '<i class="fas fa-video-slash"></i>';
                btn.classList.toggle('active', !cameraEnabled);
            }
        });
        
        document.getElementById('requestPublishBtn').addEventListener('click', async () => {
            if (!hasPublishPermission) {
                await apiCall('request_publish');
                alert('Request sent to lecturer to publish your video/audio');
            } else {
                await publishMyStream();
            }
        });
        
        document.getElementById('chatBtn').addEventListener('click', () => {
            const panel = document.getElementById('sidePanel');
            panel.classList.toggle('open');
        });
        
        document.getElementById('leaveBtn').addEventListener('click', async () => {
            if (confirm('Leave this meeting?')) {
                await apiCall('leave_meeting');
                window.location.href = '../student/dashboard.php';
            }
        });
        
        document.getElementById('sendChatBtn').addEventListener('click', sendChat);
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChat();
            }
        });
        
        // ==================== INITIALIZATION ====================
        async function initialize() {
            await initializeMedia();
            await loadChat();
            
            // Start polling for signals from lecturer
            setInterval(pollSignals, 1500);
            setInterval(loadChat, 3000);
            
            console.log('Student meeting interface initialized');
            
            // Auto-connect to lecturer
            setTimeout(() => {
                if (!isConnected) {
                    console.log('Attempting to connect to lecturer...');
                }
            }, 1000);
        }
        
        // Handle page unload
        window.addEventListener('beforeunload', async () => {
            await apiCall('leave_meeting');
            if (peerConnection) {
                peerConnection.close();
            }
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
        });
        
        // Handle page visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                console.log('Page hidden');
            } else {
                console.log('Page visible');
            }
        });
        
        // Start the application
        initialize().catch(error => {
            console.error('Initialization failed:', error);
            alert('Failed to initialize meeting. Please refresh the page.');
        });
    </script>
</body>
</html>