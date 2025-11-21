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
    die("Meeting ID required. Please check the meeting link.");
}

// Fetch meeting details
$stmt = $conn->prepare("SELECT m.*, u.name as unit_name, l.name as lecturer_name, l.id as lecturer_id 
                        FROM meetings m 
                        LEFT JOIN units u ON m.unit_id = u.id 
                        LEFT JOIN lecturers l ON m.lecturer_id = l.id 
                        WHERE m.id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die("Meeting not found. Meeting ID: $meeting_id");
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
            $conn->query("CREATE TABLE IF NOT EXISTS chat (
                id INT AUTO_INCREMENT PRIMARY KEY,
                meeting_id INT NOT NULL,
                user_id INT NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_meeting (meeting_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
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
            
            $stmt = $conn->prepare("INSERT INTO chat (meeting_id, user_id, user_name, message, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $meeting_id, $student_id, $student_name, $message);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        case 'leave_meeting':
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
    exit;
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
            z-index: 5;
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
        
        .connection-status.connecting {
            background: rgba(245, 158, 11, 0.7);
        }
        
        .connection-status.disconnected {
            background: rgba(239, 68, 68, 0.7);
        }
        
        .debug-info {
            position: absolute;
            bottom: 100px;
            left: 20px;
            background: rgba(0,0,0,0.8);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            color: var(--text-secondary);
            z-index: 10;
            max-width: 300px;
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
            
            .debug-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="meeting-info">
            <h2><?= htmlspecialchars($meeting['title']) ?></h2>
            <p>Meeting ID: <?= $meeting_id ?> | Unit: <?= htmlspecialchars($meeting['unit_name']) ?> | Lecturer: <?= htmlspecialchars($meeting['lecturer_name']) ?></p>
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
                <i class="fas fa-circle"></i> <span id="statusText">Connecting...</span>
            </div>
            
            <div id="debugInfo" class="debug-info" style="display: none;">
                <div>Student ID: <?= $student_id ?></div>
                <div>Lecturer ID: <?= $meeting['lecturer_id'] ?></div>
                <div id="debugState">State: Initializing...</div>
            </div>
            
            <video id="lecturerVideo" autoplay playsinline></video>
            <div id="statusMessage" class="status-message">
                <i class="fas fa-spinner fa-spin"></i><br>
                Waiting for lecturer stream...
            </div>
            
            <div class="self-preview">
                <video id="selfVideo" autoplay muted playsinline></video>
                <div class="label">You (<?= htmlspecialchars($student_name) ?>)</div>
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
        
        <button id="debugBtn" class="control-btn" title="Debug Info">
            <i class="fas fa-bug"></i>
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
        let connectionAttempts = 0;
        const MAX_CONNECTION_ATTEMPTS = 10;
        
        // ==================== UTILITY FUNCTIONS ====================
        async function apiCall(action, data = {}) {
            try {
                const formData = new URLSearchParams({ action, ...data });
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                console.log(`API ${action}:`, result);
                return result;
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
        
        function updateConnectionStatus(status, message = '') {
            const statusEl = document.getElementById('connectionStatus');
            const statusText = document.getElementById('statusText');
            const debugState = document.getElementById('debugState');
            
            statusEl.className = `connection-status ${status}`;
            statusText.textContent = message || status.charAt(0).toUpperCase() + status.slice(1);
            debugState.textContent = `State: ${status} - ${message}`;
            
            if (status === 'connected') {
                document.getElementById('statusMessage').style.display = 'none';
                isConnected = true;
                connectionAttempts = 0;
            } else {
                document.getElementById('statusMessage').style.display = 'block';
                isConnected = false;
            }
        }
        
        function updateDebugInfo(info) {
            const debugState = document.getElementById('debugState');
            if (debugState) {
                debugState.textContent = info;
            }
        }
        
        // ==================== MEDIA INITIALIZATION ====================
        async function initializeMedia() {
            try {
                updateDebugInfo('State: Initializing media...');
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 640, height: 480 },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
                
                document.getElementById('selfVideo').srcObject = localStream;
                console.log('Student media initialized - Tracks:', localStream.getTracks().map(t => t.kind));
                updateDebugInfo('State: Media initialized - waiting for lecturer');
                
            } catch (error) {
                console.error('Error accessing media devices:', error);
                updateConnectionStatus('disconnected', 'No camera/mic access');
                alert('Could not access camera/microphone. Please check permissions.');
            }
        }
        
        // ==================== WEBRTC FUNCTIONS ====================
        function createPeerConnection() {
            updateDebugInfo('State: Creating peer connection');
            const pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' },
                    { urls: 'stun:stun2.l.google.com:19302' }
                ]
            });
            
            // Handle incoming lecturer stream - THIS IS CRITICAL
            pc.ontrack = (event) => {
                console.log('ONTTRACK EVENT FIRED:', event);
                console.log('Streams received:', event.streams);
                
                if (event.streams && event.streams.length > 0) {
                    lecturerStream = event.streams[0];
                    const videoElement = document.getElementById('lecturerVideo');
                    
                    console.log('Setting lecturer video srcObject');
                    videoElement.srcObject = lecturerStream;
                    
                    // Listen for when video actually starts playing
                    videoElement.onloadedmetadata = () => {
                        console.log('Lecturer video metadata loaded');
                        videoElement.play().catch(e => console.error('Video play error:', e));
                    };
                    
                    videoElement.onplay = () => {
                        console.log('Lecturer video started playing');
                        updateConnectionStatus('connected', 'Stream active');
                    };
                    
                    // Monitor track status
                    event.track.onmute = () => {
                        console.log('Lecturer track muted');
                        updateConnectionStatus('connecting', 'Stream paused');
                    };
                    
                    event.track.onunmute = () => {
                        console.log('Lecturer track unmuted');
                        updateConnectionStatus('connected', 'Stream active');
                    };
                    
                    event.track.onended = () => {
                        console.log('Lecturer track ended');
                        updateConnectionStatus('disconnected', 'Stream ended');
                    };
                }
            };
            
            // ICE candidate handling
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    console.log('Sending ICE candidate to lecturer');
                    apiCall('send_signal', {
                        to_lecturer_id: LECTURER_ID,
                        type: 'candidate',
                        data: JSON.stringify({ candidate: event.candidate })
                    });
                } else {
                    console.log('All ICE candidates sent');
                }
            };
            
            // Connection state monitoring
            pc.onconnectionstatechange = () => {
                const state = pc.connectionState;
                console.log('PeerConnection state:', state);
                updateDebugInfo(`State: ${state} - ICE: ${pc.iceConnectionState}`);
                
                switch(state) {
                    case 'connected':
                        updateConnectionStatus('connected', 'Connected to lecturer');
                        break;
                    case 'connecting':
                        updateConnectionStatus('connecting', 'Connecting to lecturer...');
                        break;
                    case 'disconnected':
                        updateConnectionStatus('disconnected', 'Disconnected - reconnecting...');
                        setTimeout(() => {
                            if (peerConnection && peerConnection.connectionState === 'disconnected') {
                                console.log('Attempting to reconnect...');
                                peerConnection.restartIce();
                            }
                        }, 2000);
                        break;
                    case 'failed':
                        updateConnectionStatus('disconnected', 'Connection failed');
                        connectionAttempts++;
                        if (connectionAttempts < MAX_CONNECTION_ATTEMPTS) {
                            setTimeout(attemptReconnection, 3000);
                        }
                        break;
                    case 'closed':
                        updateConnectionStatus('disconnected', 'Connection closed');
                        peerConnection = null;
                        break;
                }
            };
            
            // ICE connection state
            pc.oniceconnectionstatechange = () => {
                console.log('ICE connection state:', pc.iceConnectionState);
            };
            
            return pc;
        }
        
        async function attemptReconnection() {
            if (peerConnection && peerConnection.connectionState === 'failed') {
                console.log('Attempting to restart ICE...');
                peerConnection.restartIce();
            }
        }
        
        async function handleOffer(offerData) {
            try {
                updateDebugInfo('State: Handling lecturer offer');
                console.log('Received offer from lecturer:', offerData);
                
                if (!peerConnection) {
                    peerConnection = createPeerConnection();
                }
                
                await peerConnection.setRemoteDescription(new RTCSessionDescription({
                    type: 'offer',
                    sdp: offerData.sdp
                }));
                console.log('Remote description set');
                
                const answer = await peerConnection.createAnswer();
                console.log('Created answer:', answer);
                
                await peerConnection.setLocalDescription(answer);
                console.log('Local description set');
                
                const result = await apiCall('send_signal', {
                    to_lecturer_id: LECTURER_ID,
                    type: 'answer',
                    data: JSON.stringify({ sdp: answer.sdp })
                });
                
                if (result.success) {
                    console.log('Answer sent successfully to lecturer');
                    updateDebugInfo('State: Answer sent - waiting for stream');
                } else {
                    console.error('Failed to send answer to lecturer');
                    updateDebugInfo('State: Failed to send answer');
                }
                
            } catch (error) {
                console.error('Error handling offer:', error);
                updateDebugInfo(`State: Error - ${error.message}`);
            }
        }
        
        async function handleCandidate(candidateData) {
            try {
                if (peerConnection && candidateData.candidate) {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(candidateData.candidate));
                    console.log('Added ICE candidate from lecturer');
                }
            } catch (error) {
                console.error('Error adding ICE candidate:', error);
            }
        }
        
        async function publishMyStream() {
            if (!hasPublishPermission || !localStream || !peerConnection) {
                console.log('No permission to publish or no stream/connection');
                return;
            }
            
            try {
                updateDebugInfo('State: Publishing stream to lecturer');
                
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
                updateDebugInfo('State: Stream published to lecturer');
                
            } catch (error) {
                console.error('Error publishing stream:', error);
                updateDebugInfo(`State: Publish error - ${error.message}`);
            }
        }
        
        async function handleSignal(signal) {
            const { from_lecturer_id, type, data } = signal;
            const parsedData = JSON.parse(data || '{}');
            
            console.log('Handling signal:', type, parsedData);
            updateDebugInfo(`State: Processing ${type} signal`);
            
            try {
                switch (type) {
                    case 'offer':
                        await handleOffer(parsedData);
                        break;
                        
                    case 'candidate':
                        await handleCandidate(parsedData);
                        break;
                        
                    case 'allow-publish':
                        hasPublishPermission = true;
                        document.getElementById('requestPublishBtn').classList.add('active');
                        console.log('Received publish permission from lecturer');
                        updateDebugInfo('State: Publish permission granted');
                        await publishMyStream();
                        break;
                        
                    case 'end':
                        alert('Lecturer has ended the meeting');
                        await apiCall('leave_meeting');
                        window.location.href = '../student/dashboard.php';
                        break;
                        
                    default:
                        console.log('Unknown signal type:', type);
                }
            } catch (error) {
                console.error('Error handling signal:', error);
                updateDebugInfo(`State: Signal error - ${error.message}`);
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
                console.log(`Polled ${signals.length} signals`);
                
                if (signals.length > 0) {
                    for (const signal of signals) {
                        await handleSignal(signal);
                    }
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
                updateDebugInfo('State: Publish request sent');
            } else {
                await publishMyStream();
            }
        });
        
        document.getElementById('chatBtn').addEventListener('click', () => {
            const panel = document.getElementById('sidePanel');
            panel.classList.toggle('open');
        });
        
        document.getElementById('debugBtn').addEventListener('click', () => {
            const debugInfo = document.getElementById('debugInfo');
            debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
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
            console.log('Initializing student meeting interface...');
            updateDebugInfo('State: Starting initialization');
            
            await initializeMedia();
            await loadChat();
            
            // Start polling for signals from lecturer
            setInterval(pollSignals, 1500);
            setInterval(loadChat, 3000);
            
            // Log initial state
            console.log('Student meeting interface initialized');
            console.log('Student ID:', STUDENT_ID);
            console.log('Lecturer ID:', LECTURER_ID);
            console.log('Meeting ID:', MEETING_ID);
            
            updateDebugInfo('State: Ready - waiting for lecturer signals');
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
        
        // Start the application
        initialize().catch(error => {
            console.error('Initialization failed:', error);
            updateDebugInfo(`State: Init failed - ${error.message}`);
            alert('Failed to initialize meeting. Please refresh the page.');
        });
    </script>
</body>
</html>