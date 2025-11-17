<?php
/**
 * STUDENT MEETING INTERFACE
 * Google Meet-style video conferencing for students
 * Features: View lecturer stream, request to publish, chat, auto-attendance
 */

session_start();
require_once '../config/db.php'; // MySQLi connection as $conn

// Authentication guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)$_SESSION['user_id'];
$student_name = $_SESSION['user_name'] ?? 'Student';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);

if (!$meeting_id) {
    die("Meeting ID required");
}

// Fetch meeting details
$stmt = $conn->prepare("SELECT m.id, m.title, m.scheduled_time, m.duration, m.lecturer_id 
                        FROM meetings m WHERE m.id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die("Meeting not found");
}

$lecturer_id = (int)$meeting['lecturer_id'];

// Ensure signals table exists
$conn->query("CREATE TABLE IF NOT EXISTS meeting_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    from_student_id INT NULL,
    from_lecturer_id INT NULL,
    to_student_id INT NULL,
    to_lecturer_id INT NULL,
    type VARCHAR(50) NOT NULL,
    data TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_meeting (meeting_id),
    INDEX idx_recipient (to_student_id, to_lecturer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    function respond($data) {
        echo json_encode($data);
        exit;
    }
    
    $action = $_POST['action'];
    
    switch ($action) {
        // Auto-log attendance on join
        case 'log_attendance':
            $check = $conn->prepare("SELECT id FROM meeting_attendance 
                                    WHERE meeting_id = ? AND student_id = ?");
            $check->bind_param("ii", $meeting_id, $student_id);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();
            
            if ($existing) {
                $update = $conn->prepare("UPDATE meeting_attendance 
                                         SET joined_at = NOW(), status = 'joined', active = 1 
                                         WHERE id = ?");
                $update->bind_param("i", $existing['id']);
                $update->execute();
                $update->close();
                respond(['success' => true, 'id' => $existing['id']]);
            } else {
                $insert = $conn->prepare("INSERT INTO meeting_attendance 
                                         (meeting_id, student_id, joined_at, status, active) 
                                         VALUES (?, ?, NOW(), 'joined', 1)");
                $insert->bind_param("ii", $meeting_id, $student_id);
                $insert->execute();
                $id = $insert->insert_id;
                $insert->close();
                respond(['success' => true, 'id' => $id]);
            }
            break;
            
        // Get participants
        case 'get_participants':
            $sql = "SELECT ma.id, ma.student_id, COALESCE(s.name, ma.guest_name) as name, 
                    COALESCE(s.reg_no, ma.reg_no) as reg_no
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON ma.student_id = s.id
                    WHERE ma.meeting_id = ? AND ma.status = 'joined'
                    ORDER BY ma.joined_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $meeting_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            respond($result);
            break;
            
        // Chat messages
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
            $stmt = $conn->prepare("INSERT INTO chat (meeting_id, user_id, user_name, message, created_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $meeting_id, $student_id, $student_name, $message);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // WebRTC Signaling - send to lecturer
        case 'send_signal':
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_student_id, to_lecturer_id, type, data, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiss", $meeting_id, $student_id, $lecturer_id, $type, $data);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // Get signals directed to this student
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
        
        /* Top Navigation */
        .top-nav {
            height: 64px;
            background: var(--bg-darker);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: relative;
            z-index: 100;
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
        
        /* Main Layout */
        .meeting-container {
            display: flex;
            height: calc(100vh - 64px);
            position: relative;
        }
        
        /* Main Video Area (Lecturer) */
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
        
        .no-video-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            color: var(--text-secondary);
        }
        
        .no-video-placeholder i {
            font-size: 64px;
        }
        
        /* Self Preview (corner) */
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
            display: none;
        }
        
        .self-preview.visible {
            display: block;
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
        
        /* Control Bar */
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
            position: relative;
        }
        
        .control-btn:hover:not(.disabled) {
            background: #3c4043;
            transform: scale(1.05);
        }
        
        .control-btn.active {
            background: var(--accent-blue);
        }
        
        .control-btn.danger {
            background: var(--accent-red);
        }
        
        .control-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        /* Side Panel */
        .side-panel {
            width: 360px;
            background: var(--bg-panel);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            position: relative;
            z-index: 40;
        }
        
        .side-panel.open {
            transform: translateX(0);
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
            transition: all 0.2s;
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
        
        /* Participants */
        .participants-list {
            padding: 16px;
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            background: var(--bg-darker);
        }
        
        .participant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .participant-info {
            flex: 1;
        }
        
        .participant-name {
            font-size: 14px;
            font-weight: 500;
        }
        
        .participant-reg {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        /* Chat */
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
        
        /* Notification Badge */
        .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--accent-red);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Status Message */
        .status-message {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-panel);
            padding: 12px 24px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            z-index: 30;
            display: none;
        }
        
        .status-message.show {
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .side-panel {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 80px;
                width: 100%;
                max-width: 360px;
            }
            
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
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="meeting-info">
            <h2><?= htmlspecialchars($meeting['title']) ?></h2>
            <p>Meeting ID: <?= $meeting_id ?></p>
        </div>
        <div>
            <a href="../student/dashboard.php" style="color: var(--text-secondary); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Leave Meeting
            </a>
        </div>
    </div>
    
    <!-- Main Meeting Container -->
    <div class="meeting-container">
        <!-- Main Video Area (Lecturer Stream) -->
        <div class="main-video-area">
            <video id="lecturerVideo" autoplay playsinline></video>
            
            <div id="noVideoPlaceholder" class="no-video-placeholder">
                <i class="fas fa-video-slash"></i>
                <p>Waiting for lecturer to start...</p>
            </div>
            
            <!-- Self Preview (shown when publishing) -->
            <div id="selfPreview" class="self-preview">
                <video id="selfVideo" autoplay muted playsinline></video>
                <div class="label">You</div>
            </div>
        </div>
        
        <!-- Side Panel (Participants/Chat) -->
        <div id="sidePanel" class="side-panel">
            <div class="panel-tabs">
                <div class="panel-tab active" data-tab="participants">
                    <i class="fas fa-users"></i> Participants (<span id="participantCount">0</span>)
                </div>
                <div class="panel-tab" data-tab="chat">
                    <i class="fas fa-comment"></i> Chat
                    <span id="chatBadge" class="badge" style="display: none;">0</span>
                </div>
            </div>
            
            <div id="participantsPanel" class="panel-content active">
                <div class="participants-list" id="participantsList"></div>
            </div>
            
            <div id="chatPanel" class="panel-content">
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
    
    <!-- Control Bar -->
    <div class="control-bar">
        <button id="toggleMicBtn" class="control-btn disabled" title="Microphone" disabled>
            <i class="fas fa-microphone-slash"></i>
        </button>
        
        <button id="toggleCameraBtn" class="control-btn disabled" title="Camera" disabled>
            <i class="fas fa-video-slash"></i>
        </button>
        
        <button id="requestPublishBtn" class="control-btn" title="Request to Publish">
            <i class="fas fa-hand"></i>
        </button>
        
        <button id="requestScreenBtn" class="control-btn" title="Request Screen Share">
            <i class="fas fa-desktop"></i>
        </button>
        
        <button id="participantsBtn" class="control-btn" title="Participants">
            <i class="fas fa-user-friends"></i>
        </button>
        
        <button id="chatBtn" class="control-btn" title="Chat">
            <i class="fas fa-comment-dots"></i>
        </button>
    </div>
    
    <!-- Status Message -->
    <div id="statusMessage" class="status-message"></div>

    <script>
        // ==================== CONFIG ====================
        const MEETING_ID = <?= $meeting_id ?>;
        const STUDENT_ID = <?= $student_id ?>;
        const STUDENT_NAME = <?= json_encode($student_name) ?>;
        const LECTURER_ID = <?= $lecturer_id ?>;
        
        // ==================== STATE ====================
        let localStream = null;
        let screenStream = null;
        let pcLecturer = null; // Connection to receive lecturer stream
        let pcPublish = null; // Connection to send our stream when approved
        let isPublishing = false;
        let micEnabled = false;
        let cameraEnabled = false;
        let pendingCandidates = [];
        
        // ==================== UTILITY FUNCTIONS ====================
        async function apiCall(action, data = {}) {
            const formData = new URLSearchParams({ action, ...data });
            const response = await fetch('', { method: 'POST', body: formData });
            return await response.json();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showStatus(message, duration = 3000) {
            const statusEl = document.getElementById('statusMessage');
            statusEl.textContent = message;
            statusEl.classList.add('show');
            setTimeout(() => statusEl.classList.remove('show'), duration);
        }
        
        // ==================== MEDIA INITIALIZATION ====================
        async function prepareLocalStream() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 1280, height: 720 },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
                
                // Keep tracks disabled until approved
                localStream.getTracks().forEach(track => track.enabled = false);
                
                document.getElementById('selfVideo').srcObject = localStream;
                console.log('Local stream prepared');
            } catch (error) {
                console.error('Error accessing media:', error);
            }
        }
        
        // ==================== WEBRTC FUNCTIONS ====================
        function createPeerConnection(type) {
            const pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            });
            
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    apiCall('send_signal', {
                        type: 'candidate',
                        data: JSON.stringify({ candidate: event.candidate })
                    });
                }
            };
            
            pc.onconnectionstatechange = () => {
                console.log(`${type} PC state:`, pc.connectionState);
            };
            
            if (type === 'lecturer') {
                // For receiving lecturer stream
                pc.ontrack = (event) => {
                    console.log('Received track from lecturer:', event.streams[0]);
                    document.getElementById('lecturerVideo').srcObject = event.streams[0];
                    document.getElementById('noVideoPlaceholder').style.display = 'none';
                };
            }
            
            return pc;
        }
        
        async function handleSignal(signal) {
            const { from_lecturer_id, type, data } = signal;
            const parsedData = JSON.parse(data || '{}');
            
            switch (type) {
                case 'offer':
                    await handleLecturerOffer(parsedData);
                    break;
                    
                case 'candidate':
                    if (pcLecturer && parsedData.candidate) {
                        try {
                            await pcLecturer.addIceCandidate(new RTCIceCandidate(parsedData.candidate));
                        } catch (error) {
                            console.error('Error adding ICE candidate:', error);
                        }
                    } else {
                        pendingCandidates.push(parsedData.candidate);
                    }
                    break;
                    
                case 'answer':
                    // Lecturer answered our publish offer
                    if (pcPublish && parsedData.sdp) {
                        await pcPublish.setRemoteDescription(new RTCSessionDescription({
                            type: 'answer',
                            sdp: parsedData.sdp
                        }));
                        console.log('Received answer from lecturer');
                    }
                    break;
                    
                case 'allow-publish':
                    await startPublishing(false);
                    break;
                    
                case 'allow-screen':
                    await startPublishing(true);
                    break;
                    
                case 'end':
                    alert('Meeting ended by lecturer');
                    window.location.href = '../student/dashboard.php';
                    break;
            }
        }
        
        async function handleLecturerOffer(data) {
            if (!pcLecturer) {
                pcLecturer = createPeerConnection('lecturer');
            }
            
            await pcLecturer.setRemoteDescription(new RTCSessionDescription({
                type: 'offer',
                sdp: data.sdp
            }));
            
            const answer = await pcLecturer.createAnswer();
            await pcLecturer.setLocalDescription(answer);
            
            await apiCall('send_signal', {
                type: 'answer',
                data: JSON.stringify({ sdp: answer.sdp })
            });
            
            // Add pending candidates
            while (pendingCandidates.length > 0) {
                const candidate = pendingCandidates.shift();
                try {
                    await pcLecturer.addIceCandidate(new RTCIceCandidate(candidate));
                } catch (error) {
                    console.error('Error adding pending candidate:', error);
                }
            }
            
            console.log('Answer sent to lecturer');
        }
        
        async function startPublishing(isScreen = false) {
            if (isPublishing) {
                showStatus('Already publishing');
                return;
            }
            
            try {
                let streamToPublish;
                
                if (isScreen) {
                    streamToPublish = await navigator.mediaDevices.getDisplayMedia({
                        video: { cursor: 'always' }
                    });
                    
                    // Add audio from mic
                    if (localStream) {
                        const audioTrack = localStream.getAudioTracks()[0];
                        if (audioTrack) {
                            streamToPublish.addTrack(audioTrack.clone());
                        }
                    }
                    
                    streamToPublish.getVideoTracks()[0].onended = () => {
                        stopPublishing();
                    };
                } else {
                    if (!localStream) {
                        await prepareLocalStream();
                    }
                    streamToPublish = localStream;
                    streamToPublish.getTracks().forEach(track => track.enabled = true);
                }
                
                pcPublish = createPeerConnection('publish');
                streamToPublish.getTracks().forEach(track => {
                    pcPublish.addTrack(track, streamToPublish);
                });
                
                const offer = await pcPublish.createOffer();
                await pcPublish.setLocalDescription(offer);
                
                await apiCall('send_signal', {
                    type: 'offer',
                    data: JSON.stringify({ sdp: offer.sdp, from_student_id: STUDENT_ID })
                });
                
                isPublishing = true;
                document.getElementById('selfPreview').classList.add('visible');
                
                // Enable controls
                document.getElementById('toggleMicBtn').classList.remove('disabled');
                document.getElementById('toggleMicBtn').disabled = false;
                document.getElementById('toggleCameraBtn').classList.remove('disabled');
                document.getElementById('toggleCameraBtn').disabled = false;
                
                micEnabled = true;
                cameraEnabled = true;
                updateControlIcons();
                
                showStatus(isScreen ? 'Screen sharing started' : 'Publishing started');
                
            } catch (error) {
                console.error('Error publishing:', error);
                showStatus('Failed to publish: ' + error.message);
            }
        }
        
        function stopPublishing() {
            if (pcPublish) {
                pcPublish.close();
                pcPublish = null;
            }
            
            if (screenStream) {
                screenStream.getTracks().forEach(track => track.stop());
                screenStream = null;
            }
            
            if (localStream) {
                localStream.getTracks().forEach(track => track.enabled = false);
            }
            
            isPublishing = false;
            document.getElementById('selfPreview').classList.remove('visible');
            
            // Disable controls
            document.getElementById('toggleMicBtn').classList.add('disabled');
            document.getElementById('toggleMicBtn').disabled = true;
            document.getElementById('toggleCameraBtn').classList.add('disabled');
            document.getElementById('toggleCameraBtn').disabled = true;
            
            showStatus('Publishing stopped');
            
            apiCall('send_signal', {
                type: 'publish-stopped',
                data: JSON.stringify({ student_id: STUDENT_ID })
            });
        }
        
        function updateControlIcons() {
            const micBtn = document.getElementById('toggleMicBtn');
            const camBtn = document.getElementById('toggleCameraBtn');
            
            micBtn.innerHTML = micEnabled ? 
                '<i class="fas fa-microphone"></i>' : 
                '<i class="fas fa-microphone-slash"></i>';
            micBtn.classList.toggle('active', !micEnabled);
            
            camBtn.innerHTML = cameraEnabled ? 
                '<i class="fas fa-video"></i>' : 
                '<i class="fas fa-video-slash"></i>';
            camBtn.classList.toggle('active', !cameraEnabled);
        }
        
        // ==================== PARTICIPANTS ====================
        async function loadParticipants() {
            const participants = await apiCall('get_participants');
            const list = document.getElementById('participantsList');
            list.innerHTML = '';
            
            document.getElementById('participantCount').textContent = participants.length;
            
            participants.forEach(p => {
                const item = document.createElement('div');
                item.className = 'participant-item';
                item.innerHTML = `
                    <div class="participant-avatar">
                        ${escapeHtml((p.name || 'U').charAt(0).toUpperCase())}
                    </div>
                    <div class="participant-info">
                        <div class="participant-name">${escapeHtml(p.name || 'Unknown')}</div>
                        <div class="participant-reg">${escapeHtml(p.reg_no || '')}</div>
                    </div>
                `;
                list.appendChild(item);
            });
        }
        
        // ==================== CHAT ====================
        let lastChatCount = 0;
        let isChatOpen = false;
        
        async function loadChat() {
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
            
            // Update badge
            if (!isChatOpen && messages.length > lastChatCount) {
                const badge = document.getElementById('chatBadge');
                const newMessages = messages.length - lastChatCount;
                badge.textContent = newMessages;
                badge.style.display = 'flex';
            }
            
            lastChatCount = messages.length;
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
            if (!isPublishing || !localStream) return;
            
            micEnabled = !micEnabled;
            localStream.getAudioTracks().forEach(track => {
                track.enabled = micEnabled;
            });
            updateControlIcons();
        });
        
        document.getElementById('toggleCameraBtn').addEventListener('click', () => {
            if (!isPublishing || !localStream) return;
            
            cameraEnabled = !cameraEnabled;
            localStream.getVideoTracks().forEach(track => {
                track.enabled = cameraEnabled;
            });
            updateControlIcons();
        });
        
        document.getElementById('requestPublishBtn').addEventListener('click', async () => {
            if (isPublishing) {
                stopPublishing();
            } else {
                await apiCall('send_signal', {
                    type: 'request-publish',
                    data: JSON.stringify({ 
                        student_id: STUDENT_ID, 
                        name: STUDENT_NAME 
                    })
                });
                showStatus('Request sent to lecturer. Waiting for approval...');
            }
        });
        
        document.getElementById('requestScreenBtn').addEventListener('click', async () => {
            await apiCall('send_signal', {
                type: 'request-screen',
                data: JSON.stringify({ 
                    student_id: STUDENT_ID, 
                    name: STUDENT_NAME 
                })
            });
            showStatus('Screen share request sent. Waiting for approval...');
        });
        
        document.getElementById('participantsBtn').addEventListener('click', () => {
            const panel = document.getElementById('sidePanel');
            panel.classList.toggle('open');
            switchTab('participants');
        });
        
        document.getElementById('chatBtn').addEventListener('click', () => {
            const panel = document.getElementById('sidePanel');
            panel.classList.toggle('open');
            switchTab('chat');
            isChatOpen = true;
            document.getElementById('chatBadge').style.display = 'none';
        });
        
        document.getElementById('sendChatBtn').addEventListener('click', sendChat);
        
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChat();
            }
        });
        
        // Panel tabs
        document.querySelectorAll('.panel-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                switchTab(tab.dataset.tab);
            });
        });
        
        function switchTab(tabName) {
            document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.panel-content').forEach(c => c.classList.remove('active'));
            
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`${tabName}Panel`).classList.add('active');
            
            if (tabName === 'chat') {
                isChatOpen = true;
                document.getElementById('chatBadge').style.display = 'none';
            } else {
                isChatOpen = false;
            }
        }
        
        // ==================== INITIALIZATION ====================
        async function initialize() {
            // Log attendance
            await apiCall('log_attendance');
            console.log('Attendance logged');
            
            // Prepare local stream (disabled by default)
            await prepareLocalStream();
            
            // Load initial data
            await loadParticipants();
            await loadChat();
            
            // Start polling
            setInterval(pollSignals, 1500);
            setInterval(loadParticipants, 5000);
            setInterval(loadChat, 3000);
            
            showStatus('Joined meeting successfully');
        }
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            if (pcLecturer) pcLecturer.close();
            if (pcPublish) pcPublish.close();
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
            }
            if (screenStream) {
                screenStream.getTracks().forEach(track => track.stop());
            }
        });
        
        // Start the application
        initialize();
    </script>
</body>
</html>
