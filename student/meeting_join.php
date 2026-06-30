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

// Get meeting details and validate student access (must be enrolled in the meeting's unit)
$sql = "SELECT m.*, u.name as unit_name, l.name as lecturer_name 
        FROM meetings m 
        JOIN units u ON m.unit_id = u.id 
        JOIN lecturers l ON m.lecturer_id = l.id 
        JOIN student_unit_enrollments sue ON sue.unit_id = u.id 
        WHERE m.id = ? AND sue.student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $meeting_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die('Meeting not found or you are not enrolled in this unit');
}

$lecturer_id = $meeting['lecturer_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Meeting - <?php echo htmlspecialchars($meeting['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Google Sans', Roboto, Arial, sans-serif;
            background: #202124;
            color: #e8eaed;
            height: 100vh;
            overflow: hidden;
        }
        .top-bar {
            height: 48px;
            background: #171717;
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            border-bottom: 1px solid #3c4043;
        }
        .top-bar .back-btn {
            color: #9aa0a6;
            text-decoration: none;
            font-size: 13px;
        }
        .top-bar .back-btn:hover { color: #e8eaed; }
        .top-bar .meeting-title {
            font-size: 14px;
            font-weight: 500;
        }
        .top-bar .live-badge {
            font-size: 10px;
            background: #f28b82;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }
        .meeting-layout {
            display: flex;
            height: calc(100vh - 128px);
        }
        .main-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #171717;
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
        .no-stream {
            text-align: center;
            color: #9aa0a6;
        }
        .no-stream i { font-size: 64px; display: block; margin-bottom: 16px; opacity: 0.5; }
        
        /* Local preview corner */
        .local-preview {
            position: absolute;
            bottom: 16px;
            right: 16px;
            width: 180px;
            height: 101px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #3c4043;
            background: #000;
            z-index: 10;
        }
        .local-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .local-preview .label {
            position: absolute;
            bottom: 6px;
            left: 6px;
            background: rgba(0,0,0,0.7);
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 10px;
        }
        
        /* Control bar */
        .control-bar {
            height: 80px;
            background: #292929;
            border-top: 1px solid #3c4043;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .ctrl-btn {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: #171717;
            border: none;
            color: #e8eaed;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            position: relative;
        }
        .ctrl-btn:hover { background: #3c4043; transform: scale(1.05); }
        .ctrl-btn.active { background: #8ab4f8; }
        .ctrl-btn.danger { background: #f28b82; width: 52px; height: 52px; }
        .ctrl-btn.danger:hover { background: #d93025; }
        .ctrl-btn .label {
            position: absolute;
            bottom: -16px;
            font-size: 9px;
            color: #9aa0a6;
            white-space: nowrap;
        }
        
        /* Side panel */
        .side-panel {
            width: 300px;
            background: #292929;
            border-left: 1px solid #3c4043;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.3s;
        }
        .side-panel.open { transform: translateX(0); }
        .panel-tabs {
            display: flex;
            border-bottom: 1px solid #3c4043;
            flex-shrink: 0;
        }
        .panel-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            font-size: 12px;
            border-bottom: 2px solid transparent;
        }
        .panel-tab.active { border-bottom-color: #8ab4f8; color: #8ab4f8; }
        .panel-content { flex: 1; overflow-y: auto; display: none; padding: 12px; }
        .panel-content.active { display: block; }
        
        .participant-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: #171717;
            border-radius: 8px;
            margin-bottom: 6px;
        }
        .p-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #8ab4f8, #5a9fd4);
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 13px; flex-shrink: 0;
        }
        .p-info { flex: 1; min-width: 0; }
        .p-name { font-size: 12px; font-weight: 500; }
        .p-role { font-size: 10px; color: #9aa0a6; }
        
        .chat-messages { flex: 1; overflow-y: auto; padding: 12px; }
        .chat-msg { margin-bottom: 10px; }
        .chat-msg.me { text-align: right; }
        .msg-sender { font-size: 10px; color: #9aa0a6; margin-bottom: 2px; }
        .msg-bubble {
            display: inline-block; padding: 6px 10px; border-radius: 10px;
            background: #171717; max-width: 85%; font-size: 12px;
        }
        .chat-msg.me .msg-bubble { background: #8ab4f8; color: #111; }
        .chat-input-area { padding: 12px; border-top: 1px solid #3c4043; flex-shrink: 0; }
        .chat-input-area textarea {
            width: 100%; padding: 8px; border-radius: 6px;
            background: #171717; border: 1px solid #3c4043;
            color: #e8eaed; resize: none; font-family: inherit; font-size: 12px;
        }
        .chat-input-area button {
            margin-top: 6px; width: 100%; padding: 7px;
            border-radius: 6px; background: #8ab4f8; border: none;
            color: #111; font-weight: 500; cursor: pointer; font-size: 12px;
        }
        
        .toast-container {
            position: fixed; top: 60px; right: 16px; z-index: 300;
            display: flex; flex-direction: column; gap: 6px;
        }
        .toast {
            padding: 10px 16px; border-radius: 6px; font-size: 12px;
            animation: slideIn 0.3s ease; max-width: 280px;
        }
        .toast.info { background: #1a73e8; color: white; }
        .toast.success { background: #81c995; color: #111; }
        .toast.error { background: #f28b82; color: white; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        @media (max-width: 768px) {
            .side-panel { position: absolute; right: 0; top: 48px; bottom: 80px; width: 100%; max-width: 300px; }
            .local-preview { width: 120px; height: 68px; }
            .ctrl-btn { width: 42px; height: 42px; font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="top-bar">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <span class="meeting-title"><?php echo htmlspecialchars($meeting['title']); ?></span>
        <span class="live-badge">LIVE</span>
        <span style="font-size:12px;color:#9aa0a6;margin-left:auto;">
            <?php echo htmlspecialchars($meeting['unit_name']); ?> · <?php echo htmlspecialchars($meeting['lecturer_name']); ?>
        </span>
    </div>
    
    <div class="meeting-layout">
        <div class="main-area">
            <div id="noLecturerStream" class="no-stream">
                <i class="fas fa-chalkboard-teacher"></i>
                <div>Waiting for the lecturer to start...</div>
                <div style="font-size:12px;margin-top:8px;">Your camera and mic are ready</div>
            </div>
            <video id="lecturerVideo" autoplay playsinline style="display:none;"></video>
            
            <div class="local-preview">
                <video id="selfVideo" autoplay muted playsinline></video>
                <div class="label">You</div>
            </div>
        </div>
        
        <div id="sidePanel" class="side-panel">
            <div class="panel-tabs">
                <div class="panel-tab active" data-tab="participants"><i class="fas fa-users"></i> <span id="participantCount">0</span></div>
                <div class="panel-tab" data-tab="chat"><i class="fas fa-comment"></i> Chat</div>
            </div>
            <div id="participantsPanel" class="panel-content active">
                <div id="participantsList"></div>
            </div>
            <div id="chatPanel" class="panel-content">
                <div class="chat-messages" id="chatMessages">
                    <div style="color:#9aa0a6;text-align:center;padding:20px;font-size:12px;">No messages yet</div>
                </div>
                <div class="chat-input-area">
                    <textarea id="chatInput" placeholder="Type a message..." rows="2"></textarea>
                    <button id="sendChatBtn">Send</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="control-bar">
        <button id="toggleMicBtn" class="ctrl-btn" title="Microphone">
            <i class="fas fa-microphone"></i><span class="label">Mic</span>
        </button>
        <button id="toggleCameraBtn" class="ctrl-btn" title="Camera">
            <i class="fas fa-video"></i><span class="label">Camera</span>
        </button>
        <button id="participantsBtn" class="ctrl-btn" title="Participants">
            <i class="fas fa-user-friends"></i><span class="label">People</span>
        </button>
        <button id="chatBtn" class="ctrl-btn" title="Chat">
            <i class="fas fa-comment-dots"></i><span class="label">Chat</span>
        </button>
        <button id="leaveBtn" class="ctrl-btn danger" title="Leave Meeting">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>

    <script>
        // ==================== CONFIG ====================
        const MEETING_ID = <?php echo $meeting_id; ?>;
        const USER_ID = <?php echo $user_id; ?>;
        const LECTURER_ID = <?php echo $lecturer_id; ?>;
        
        // ==================== STATE ====================
        let localStream = null;
        let micEnabled = true;
        let cameraEnabled = true;
        let isWaitingForLecturer = true;
        let lastChatCount = 0;
        let isChatOpen = false;
        
        // ==================== UTILITY ====================
        function showToast(msg, type = 'info') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div'); t.className = `toast ${type}`; t.textContent = msg;
            c.appendChild(t); setTimeout(() => t.remove(), 3000);
        }
        
        function escapeHtml(text) {
            const d = document.createElement('div'); d.textContent = text; return d.innerHTML;
        }
        
        // ==================== MEDIA ====================
        async function initMedia() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: { echoCancellation: true, noiseSuppression: true }
                });
                document.getElementById('selfVideo').srcObject = localStream;
                showToast('Camera and mic ready', 'success');
            } catch(e) {
                console.error('Media error:', e);
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                    document.getElementById('selfVideo').srcObject = localStream;
                    showToast('Audio only mode', 'info');
                } catch(e2) {
                    showToast('No media access', 'error');
                }
            }
        }
        
        // ==================== API ====================
        async function apiCall(action, data = {}) {
            const fd = new URLSearchParams({ action, ...data });
            const r = await fetch('', { method: 'POST', body: fd });
            return await r.json();
        }
        
        async function pollSignals() {
            try {
                const signals = await apiCall('get_signals');
                for (const s of signals) await handleSignal(s);
            } catch(e) { console.error('Poll error:', e); }
        }
        
        async function handleSignal(signal) {
            const { from_lecturer_id, type, data } = signal;
            if (from_lecturer_id != LECTURER_ID) return;
            const parsed = JSON.parse(data || '{}');
            
            switch(type) {
                case 'offer':
                    if (parsed.sdp) {
                        await handleLecturerOffer(parsed);
                    }
                    break;
                case 'candidate':
                    if (parsed.candidate && lecturerPC) {
                        try { await lecturerPC.addIceCandidate(new RTCIceCandidate(parsed.candidate)); } catch(e) {}
                    }
                    break;
                case 'end':
                    showToast('Meeting ended by lecturer', 'info');
                    setTimeout(() => window.location.href = 'dashboard.php', 2000);
                    break;
            }
        }
        
        let lecturerPC = null;
        
        async function handleLecturerOffer(data) {
            try {
                lecturerPC = new RTCPeerConnection({
                    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }]
                });
                
                lecturerPC.ontrack = (event) => {
                    const [stream] = event.streams;
                    const video = document.getElementById('lecturerVideo');
                    video.srcObject = stream;
                    video.style.display = 'block';
                    document.getElementById('noLecturerStream').style.display = 'none';
                    isWaitingForLecturer = false;
                    showToast('Lecturer stream received!', 'success');
                };
                
                lecturerPC.onicecandidate = (event) => {
                    if (event.candidate) {
                        apiCall('send_signal', {
                            to_lecturer_id: LECTURER_ID,
                            type: 'candidate',
                            data: JSON.stringify({ candidate: event.candidate })
                        });
                    }
                };
                
                await lecturerPC.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp: data.sdp }));
                
                const answer = await lecturerPC.createAnswer();
                await lecturerPC.setLocalDescription(answer);
                
                await apiCall('send_signal', {
                    to_lecturer_id: LECTURER_ID,
                    type: 'answer',
                    data: JSON.stringify({ sdp: answer.sdp })
                });
                
                console.log('Answer sent to lecturer');
            } catch(e) {
                console.error('Error handling lecturer offer:', e);
            }
        }
        
        // ==================== PARTICIPANTS ====================
        async function loadParticipants() {
            const participants = await apiCall('get_participants');
            const list = document.getElementById('participantsList');
            document.getElementById('participantCount').textContent = participants.length + 1;
            
            list.innerHTML = `
                <div class="participant-item">
                    <div class="p-avatar" style="background:linear-gradient(135deg,#fdd663,#f59e0b);">
                        ${escapeHtml(<?php echo json_encode(htmlspecialchars($meeting['lecturer_name'])); ?>).charAt(0)}
                    </div>
                    <div class="p-info">
                        <div class="p-name">${escapeHtml(<?php echo json_encode(htmlspecialchars($meeting['lecturer_name'])); ?>)}</div>
                        <div class="p-role">Lecturer</div>
                    </div>
                </div>
            `;
            
            participants.forEach(p => {
                const initial = (p.name || '?').charAt(0).toUpperCase();
                list.innerHTML += `
                    <div class="participant-item">
                        <div class="p-avatar">${escapeHtml(initial)}</div>
                        <div class="p-info">
                            <div class="p-name">${escapeHtml(p.name || 'Unknown')}</div>
                            <div class="p-role">${escapeHtml(p.reg_no || 'Student')}</div>
                        </div>
                    </div>
                `;
            });
        }
        
        // ==================== CHAT ====================
        async function loadChat() {
            const messages = await apiCall('get_chat');
            const container = document.getElementById('chatMessages');
            container.innerHTML = '';
            
            if (messages.length === 0) {
                container.innerHTML = '<div style="color:#9aa0a6;text-align:center;padding:20px;font-size:12px;">No messages yet</div>';
            } else {
                messages.forEach(msg => {
                    const isMe = msg.user_id == USER_ID || (msg.user_id == LECTURER_ID && msg.user_name.includes('Lecturer'));
                    const div = document.createElement('div');
                    div.className = `chat-msg ${isMe ? 'me' : ''}`;
                    div.innerHTML = `
                        <div class="msg-sender">${escapeHtml(msg.user_name)}</div>
                        <div class="msg-bubble">${escapeHtml(msg.message)}</div>
                    `;
                    container.appendChild(div);
                });
                container.scrollTop = container.scrollHeight;
            }
            
            lastChatCount = messages.length;
        }
        
        async function sendChat() {
            const input = document.getElementById('chatInput');
            const msg = input.value.trim();
            if (msg) {
                await apiCall('send_chat', { message: msg, user_id: USER_ID, user_name: 'You' });
                input.value = '';
                await loadChat();
            }
        }
        
        // ==================== SIGNALING FOR STUDENT (sends to lecturer) ====================
        // Override send_signal to work for student -> lecturer
        async function sendSignalToLecturer(type, data) {
            const fd = new URLSearchParams({
                action: 'send_signal',
                to_lecturer_id: LECTURER_ID,
                type: type,
                data: JSON.stringify(data)
            });
            await fetch('', { method: 'POST', body: fd });
        }
        
        // ==================== UI CONTROLS ====================
        document.getElementById('toggleMicBtn').addEventListener('click', () => {
            if (localStream) {
                micEnabled = !micEnabled;
                localStream.getAudioTracks().forEach(t => t.enabled = micEnabled);
                const btn = document.getElementById('toggleMicBtn');
                btn.innerHTML = micEnabled ? '<i class="fas fa-microphone"></i><span class="label">Mic</span>' : '<i class="fas fa-microphone-slash"></i><span class="label">Mic</span>';
                btn.classList.toggle('active', !micEnabled);
                showToast(micEnabled ? 'Mic on' : 'Mic muted', 'info');
            }
        });
        
        document.getElementById('toggleCameraBtn').addEventListener('click', () => {
            if (localStream) {
                cameraEnabled = !cameraEnabled;
                localStream.getVideoTracks().forEach(t => t.enabled = cameraEnabled);
                const btn = document.getElementById('toggleCameraBtn');
                btn.innerHTML = cameraEnabled ? '<i class="fas fa-video"></i><span class="label">Camera</span>' : '<i class="fas fa-video-slash"></i><span class="label">Camera</span>';
                btn.classList.toggle('active', !cameraEnabled);
                showToast(cameraEnabled ? 'Camera on' : 'Camera off', 'info');
            }
        });
        
        document.getElementById('participantsBtn').addEventListener('click', () => {
            document.getElementById('sidePanel').classList.toggle('open');
            switchTab('participants');
        });
        
        document.getElementById('chatBtn').addEventListener('click', () => {
            document.getElementById('sidePanel').classList.toggle('open');
            switchTab('chat');
            isChatOpen = true;
        });
        
        document.getElementById('leaveBtn').addEventListener('click', () => {
            if (confirm('Leave the meeting?')) {
                if (localStream) localStream.getTracks().forEach(t => t.stop());
                if (lecturerPC) lecturerPC.close();
                window.location.href = 'dashboard.php';
            }
        });
        
        document.getElementById('sendChatBtn').addEventListener('click', sendChat);
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
        });
        
        function switchTab(name) {
            document.querySelectorAll('.panel-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.panel-content').forEach(c => c.classList.remove('active'));
            document.querySelector(`[data-tab="${name}"]`).classList.add('active');
            document.getElementById(`${name}Panel`).classList.add('active');
            if (name === 'chat') isChatOpen = true; else isChatOpen = false;
        }
        
        // Close panel on outside click
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('sidePanel');
            if (panel.classList.contains('open') &&
                !panel.contains(e.target) &&
                !document.getElementById('participantsBtn').contains(e.target) &&
                !document.getElementById('chatBtn').contains(e.target)) {
                panel.classList.remove('open');
            }
        });
        
        // ==================== INIT ====================
        async function init() {
            await initMedia();
            await loadParticipants();
            await loadChat();
            
            // Start polling for signals from lecturer
            setInterval(pollSignals, 1500);
            setInterval(loadParticipants, 5000);
            setInterval(loadChat, 3000);
            
            // Send join signal to lecturer
            await apiCall('send_signal', {
                to_lecturer_id: LECTURER_ID,
                type: 'request-publish',
                data: JSON.stringify({ name: '<?php echo htmlspecialchars($meeting['lecturer_name']); ?>' })
            });
        }
        
        window.addEventListener('beforeunload', () => {
            if (localStream) localStream.getTracks().forEach(t => t.stop());
            if (lecturerPC) lecturerPC.close();
        });
        
        init();
    </script>
</body>
</html>