<?php
<<<<<<< Updated upstream
// student_meeting_resp.php
session_start();
require_once '../config/db.php'; // expects $conn (mysqli)

// --- Guard ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
=======
/**
 * LECTURER MEETING INTERFACE
 * Google Meet-style video conferencing for lecturers
 * Features: WebRTC streaming, attendance, chat, participant management
 */

session_start();
require_once '../config/db.php'; // MySQLi connection as $conn

// Authentication guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
>>>>>>> Stashed changes
    header("Location: ../login.php");
    exit;
}

<<<<<<< Updated upstream
$student_id = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Student';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id) { http_response_code(400); echo "Meeting ID required."; exit; }

// --- Fetch meeting info ---
$stmt = $conn->prepare("SELECT id, title, lecturer_id FROM meetings WHERE id=?");
$stmt->bind_param("i",$meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$meeting) { http_response_code(404); echo "Meeting not found."; exit; }
$lecturer_id = (int)$meeting['lecturer_id'];
=======
$lecturer_id = (int)$_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? 'Lecturer';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);

if (!$meeting_id) {
    die("Meeting ID required");
}

// Fetch meeting details
$stmt = $conn->prepare("SELECT m.id, m.title, m.scheduled_time, m.duration, m.lecturer_id, u.course_id, u.year 
                        FROM meetings m 
                        LEFT JOIN units u ON m.unit_id = u.id 
                        WHERE m.id = ? AND m.lecturer_id = ?");
$stmt->bind_param("ii", $meeting_id, $lecturer_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die("Meeting not found or access denied");
}

// Create signals table if not exists
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
        // Get all participants (students who joined)
        case 'get_participants':
            $sql = "SELECT ma.id, ma.student_id, COALESCE(s.name, ma.guest_name) as name, 
                    COALESCE(s.reg_no, ma.reg_no) as reg_no, ma.joined_at, ma.status
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
            
        // Get attendance for panel
        case 'get_attendance':
            $sql = "SELECT ma.id, COALESCE(s.name, ma.guest_name) as name,
                    COALESCE(s.reg_no, ma.reg_no) as reg_no, ma.joined_at,
                    TIMESTAMPDIFF(MINUTE, ma.joined_at, NOW()) as duration,
                    ma.active, ma.marks, ma.student_id
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON ma.student_id = s.id
                    WHERE ma.meeting_id = ?
                    ORDER BY ma.joined_at ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $meeting_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            respond($result);
            break;
            
        // Award marks to student
        case 'award_marks':
            $attendance_id = (int)$_POST['attendance_id'];
            $marks = (int)$_POST['marks'];
            $stmt = $conn->prepare("UPDATE meeting_attendance SET marks = ? WHERE id = ? AND meeting_id = ?");
            $stmt->bind_param("iii", $marks, $attendance_id, $meeting_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // Toggle student active status
        case 'toggle_active':
            $attendance_id = (int)$_POST['attendance_id'];
            $stmt = $conn->prepare("UPDATE meeting_attendance SET active = NOT active WHERE id = ? AND meeting_id = ?");
            $stmt->bind_param("ii", $attendance_id, $meeting_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
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
            $stmt->bind_param("iiss", $meeting_id, $lecturer_id, $lecturer_name, $message);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // WebRTC Signaling
        case 'send_signal':
            $to_student = (int)($_POST['to_student_id'] ?? 0);
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiss", $meeting_id, $lecturer_id, $to_student, $type, $data);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        case 'get_signals':
            $stmt = $conn->prepare("SELECT id, from_student_id, type, data 
                                   FROM meeting_signals 
                                   WHERE meeting_id = ? AND to_lecturer_id = ?
                                   ORDER BY id ASC");
            $stmt->bind_param("ii", $meeting_id, $lecturer_id);
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
            
        // Approve student publish request
        case 'approve_publish':
            $student_id = (int)$_POST['student_id'];
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   VALUES (?, ?, ?, 'allow-publish', '', NOW())");
            $stmt->bind_param("iii", $meeting_id, $lecturer_id, $student_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // Approve screen share request
        case 'approve_screen':
            $student_id = (int)$_POST['student_id'];
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   VALUES (?, ?, ?, 'allow-screen', '', NOW())");
            $stmt->bind_param("iii", $meeting_id, $lecturer_id, $student_id);
            $success = $stmt->execute();
            $stmt->close();
            respond(['success' => $success]);
            break;
            
        // End meeting
        case 'end_meeting':
            // Notify all students
            $stmt = $conn->prepare("INSERT INTO meeting_signals 
                                   (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) 
                                   SELECT ?, ?, student_id, 'end', '', NOW()
                                   FROM meeting_attendance WHERE meeting_id = ?");
            $stmt->bind_param("iii", $meeting_id, $lecturer_id, $meeting_id);
            $stmt->execute();
            $stmt->close();
            respond(['success' => true]);
            break;
            
        default:
            respond(['success' => false, 'error' => 'Unknown action']);
    }
}
>>>>>>> Stashed changes
?>
<!DOCTYPE html>
<html lang="en">
<head>
<<<<<<< Updated upstream
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($meeting['title']) ?> — Student</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#0b0b0d; --panel:#07161c; --accent:#06b6d4; --green:#10b981; --red:#ef4444; --orange:#f97316; --purple:#8b5cf6; --cyan:#22d3ee;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:Inter,system-ui,Arial;background:var(--bg);color:#fff}
.container{display:flex;height:100vh;overflow:hidden;flex-direction:row}
#mainArea{flex:1;display:flex;flex-direction:column;min-width:0;position:relative}
#videoGrid{display:grid;gap:6px;padding:6px;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));align-content:start;overflow:auto}
.tile{position:relative;background:#060607;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:120px}
.tile .meta{position:absolute;left:8px;top:8px;background:rgba(255,255,255,0.03);padding:4px 6px;border-radius:6px;font-size:13px}
video{width:100%;height:100%;object-fit:cover;display:block}
#fullScreenLecturer{position:absolute;top:0;left:0;width:100%;height:100%;z-index:100;display:none;background:#000}
#fullScreenLecturer video{width:100%;height:100%;object-fit:contain}
#fullScreenLecturer button{position:absolute;top:12px;right:12px;z-index:101;background:rgba(0,0,0,0.5);border:none;color:#fff;padding:6px 12px;border-radius:6px;cursor:pointer}

/* Controls */
.controls{position:fixed;bottom:10px;left:50%;transform:translateX(-50%);display:flex;gap:8px;padding:6px;z-index:50;flex-wrap:wrap}
.ctrl{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;cursor:pointer;transition:transform .12s,opacity .12s;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01))}
.ctrl:hover{transform:translateY(-4px)}
.ctrl.cam{color:var(--green)}
.ctrl.mic{color:var(--cyan)}
.ctrl.screen{color:var(--purple)}
.ctrl.share{color:var(--orange)}
.ctrl.chat{color:var(--accent)}
.ctrl.end{color:#7f1d1d}

/* Sidebar */
.sidebar{width:300px;background:var(--panel);border-left:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;transition:transform .18s ease;z-index:40}
.sidebar.hidden{transform:translateX(100%)}
.sidebar header{padding:12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
#participants{flex:1;overflow:auto;padding:8px}
.participant{display:flex;gap:8px;align-items:center;padding:8px;border-radius:8px;margin-bottom:8px;background:rgba(255,255,255,0.02)}
.avatar{width:36px;height:36px;border-radius:999px;background:#0b1220;display:flex;align-items:center;justify-content:center;font-weight:700;color:#cfeff6}

/* Chat */
#chatBox{border-top:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;height:35%}
#chatLog{flex:1;padding:8px;overflow:auto;display:flex;flex-direction:column;gap:6px}
.bubble{max-width:85%;padding:6px;border-radius:10px;background:rgba(255,255,255,0.02)}
.bubble.me{align-self:flex-end;background:rgba(16,185,129,0.12);color:#e6fff2}
#chatInput{display:flex;gap:6px;padding:6px}
#chatInput textarea{flex:1;height:42px;border-radius:6px;padding:6px;background:#07161c;color:#fff;border:1px solid rgba(255,255,255,0.03);resize:none}
.smallBtn{padding:4px 8px;border-radius:6px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.02);cursor:pointer;color:#dff6fb}

/* Attendance panel */
#attendancePanel{position:fixed;right:50%;bottom:80px;transform:translateX(50%);width:90%;max-width:680px;background:var(--panel);border-radius:12px;padding:12px;display:none;z-index:60}
#attendancePanel table{width:100%;border-collapse:collapse;font-size:14px}
#attendancePanel th,#attendancePanel td{padding:6px;border-bottom:1px solid rgba(255,255,255,0.03);text-align:left}

/* Responsive */
@media (max-width:900px){
  .sidebar{position:fixed;right:0;top:0;bottom:0;width:80%;max-width:300px}
  #videoGrid{grid-template-columns:repeat(auto-fit,minmax(100px,1fr))}
}
@media (max-width:480px){
  .ctrl{width:42px;height:42px;font-size:18px}
  #videoGrid{gap:4px;padding:4px}
  .tile{min-height:88px}
}
</style>
</head>
<body>
<div class="container">
  <div id="mainArea">
    <div id="videoGrid" aria-live="polite" role="application">
      <div class="tile" id="lecturerTile"><div class="meta">Lecturer</div><video id="lecturerVideo" autoplay playsinline></video></div>
      <div class="tile" id="localTile"><div class="meta">You — <?= htmlspecialchars($userName) ?></div><video id="localVideo" autoplay muted playsinline></video></div>
    </div>
    <div id="fullScreenLecturer">
      <video id="lecturerScreen" autoplay playsinline></video>
      <button onclick="closeFullScreenLecturer()">Close</button>
    </div>
  </div>
  <div id="sidebar" class="sidebar">
    <header>
      <div>Participants</div>
      <div style="display:flex;gap:6px">
        <button id="refreshBtn" class="smallBtn">Refresh</button>
        <button id="takeInlineBtn" class="smallBtn">Take</button>
      </div>
    </header>
    <div id="participants"></div>
    <div id="chatBox">
      <div id="chatLog" aria-live="polite"></div>
      <div id="chatInput">
        <textarea id="chatMsg" placeholder="Write a message..."></textarea>
        <div style="display:flex;flex-direction:column;gap:4px">
          <button id="sendChat" class="smallBtn">Send</button>
          <button id="clearChat" class="smallBtn">Clear</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Controls -->
<div class="controls">
  <div id="btnCam" class="ctrl cam" data-state="on"><i class="fa fa-video"></i></div>
  <div id="btnMic" class="ctrl mic" data-state="on"><i class="fa fa-microphone"></i></div>
  <div id="btnShare" class="ctrl screen"><i class="fa fa-desktop"></i></div>
  <div id="btnRequestPublish" class="ctrl share"><i class="fa fa-upload"></i></div>
  <div id="btnAttendance" class="ctrl chat"><i class="fa fa-list-check"></i></div>
  <div id="btnToggleSidebar" class="ctrl chat"><i class="fa fa-comments"></i></div>
</div>

<div id="attendancePanel">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <strong>Attendance — Meeting #<?= (int)$meeting_id ?></strong>
    <button id="closeAttendancePanel" class="smallBtn">Close</button>
  </div>
  <div style="margin-top:6px">
    <button id="refreshAttendanceBtn" class="smallBtn">Refresh</button>
  </div>
  <table id="attendanceTable">
    <thead><tr><th>#</th><th>Name</th><th>Reg No</th><th>Joined</th><th>Duration</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<script>
// --- Config/state ---
const meetingId = <?= (int)$meeting_id ?>;
const studentId = <?= (int)$student_id ?>;
const userName = <?= json_encode($userName) ?>;
const lecturerId = <?= (int)$lecturer_id ?>;
let localStream=null, published=false, pcForLecturer=null, pcPublish=null, pendingCandidates=[];

function el(id){return document.getElementById(id);}
function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function postAction(data){return (await fetch(location.pathname+'?meeting_id='+meetingId,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)})).json();}

// UI events
el('btnCam').addEventListener('click',()=>{if(!localStream)return;const state=el('btnCam').getAttribute('data-state')==='on';localStream.getVideoTracks().forEach(t=>t.enabled=!state);el('btnCam').setAttribute('data-state',state?'off':'on');el('btnCam').querySelector('i').className=state?'fa fa-video-slash':'fa fa-video';});
el('btnMic').addEventListener('click',()=>{if(!localStream)return;const state=el('btnMic').getAttribute('data-state')==='on';localStream.getAudioTracks().forEach(t=>t.enabled=!state);el('btnMic').setAttribute('data-state',state?'off':'on');el('btnMic').querySelector('i').className=state?'fa fa-microphone-slash':'fa fa-microphone';});
el('btnToggleSidebar').addEventListener('click',()=>el('sidebar').classList.toggle('hidden'));
el('btnAttendance').addEventListener('click',()=>el('attendancePanel').style.display=el('attendancePanel').style.display==='block'?'none':'block');
el('closeAttendancePanel').addEventListener('click',()=>el('attendancePanel').style.display='none');

function showFullScreenLecturer(stream){el('fullScreenLecturer').style.display='block';el('lecturerScreen').srcObject=stream;}
function closeFullScreenLecturer(){el('fullScreenLecturer').style.display='none';el('lecturerScreen').srcObject=null;}

async function initLocalPreview(){try{localStream=await navigator.mediaDevices.getUserMedia({video:{width:1280},audio:true});el('localVideo').srcObject=localStream;}catch(e){console.warn('media denied',e);}}
async function logAttendanceOnJoin(){try{await postAction({action:'log_attendance_on_join'});}catch(e){console.warn(e);}}
async function refreshParticipants(){try{const arr=await postAction({action:'get_participants'});const c=el('participants');c.innerHTML='';(arr||[]).forEach(p=>{const d=document.createElement('div');d.className='participant';d.innerHTML=`<div class="avatar">${escapeHtml((p.name||'U').charAt(0).toUpperCase())}</div><div><strong>${escapeHtml(p.name)}</strong><br><small>${escapeHtml(p.reg_no||'')}</small></div>`;c.appendChild(d);});}catch(e){console.warn(e);}}
async function pollChat(){try{const arr=await postAction({action:'get_chat'});const log=el('chatLog');log.innerHTML='';(arr||[]).forEach(m=>{const b=document.createElement('div');b.className='bubble'+(parseInt(m.user_id)===studentId?' me':'');b.innerHTML=`<strong>${escapeHtml(m.user_name)}</strong><div style="margin-top:2px">${escapeHtml(m.message)}</div>`;log.appendChild(b);});log.scrollTop=log.scrollHeight;}catch(e){console.warn(e);}}

(async function init(){await initLocalPreview();await logAttendanceOnJoin();await refreshParticipants();await pollChat();el('lecturerVideo').muted=false;if(window.innerWidth<800)el('sidebar').classList.add('hidden');})();
setInterval(refreshParticipants,5000);
setInterval(pollChat,2500);
</script>
=======
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($meeting['title']) ?> - Lecturer</title>
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
        
        /* Main Video Area */
        .main-video-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-darker);
            position: relative;
            overflow: hidden;
        }
        
        #mainVideo {
            max-width: 100%;
            max-height: 100%;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
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
        
        .control-btn.danger:hover {
            background: #d93025;
        }
        
        .control-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Side Panel (Participants/Chat) */
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
        
        /* Attendance Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        
        .modal.open {
            display: flex;
        }
        
        .modal-content {
            background: var(--bg-panel);
            border-radius: 12px;
            padding: 24px;
            max-width: 900px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            font-size: 20px;
            font-weight: 400;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .attendance-table th {
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .attendance-table input[type="number"] {
            width: 60px;
            padding: 6px;
            background: var(--bg-darker);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-primary);
        }
        
        .award-btn {
            padding: 6px 12px;
            background: var(--accent-green);
            border: none;
            border-radius: 4px;
            color: var(--bg-darker);
            cursor: pointer;
            font-size: 12px;
        }
        
        /* Participant Grid Modal */
        .participant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            padding: 16px;
        }
        
        .participant-tile {
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .participant-tile video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .participant-tile .name-tag {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: rgba(0,0,0,0.7);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
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
            <a href="../lecturer/dashboard.php" style="color: var(--text-secondary); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Main Meeting Container -->
    <div class="meeting-container">
        <!-- Main Video Area -->
        <div class="main-video-area">
            <video id="mainVideo" autoplay playsinline></video>
            
            <!-- Self Preview (corner) -->
            <div class="self-preview">
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
        <button id="toggleMicBtn" class="control-btn" title="Microphone">
            <i class="fas fa-microphone"></i>
        </button>
        
        <button id="toggleCameraBtn" class="control-btn" title="Camera">
            <i class="fas fa-video"></i>
        </button>
        
        <button id="shareScreenBtn" class="control-btn" title="Share Screen">
            <i class="fas fa-desktop"></i>
        </button>
        
        <button id="participantsBtn" class="control-btn" title="Participants">
            <i class="fas fa-user-friends"></i>
        </button>
        
        <button id="chatBtn" class="control-btn" title="Chat">
            <i class="fas fa-comment-dots"></i>
        </button>
        
        <button id="attendanceBtn" class="control-btn" title="Attendance">
            <i class="fas fa-clipboard-check"></i>
        </button>
        
        <button id="endMeetingBtn" class="control-btn danger" title="End Meeting">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>
    
    <!-- Attendance Modal -->
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Attendance</h3>
                <button class="close-btn" id="closeAttendance">&times;</button>
            </div>
            <table class="attendance-table" id="attendanceTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Reg No</th>
                        <th>Joined At</th>
                        <th>Duration (min)</th>
                        <th>Active</th>
                        <th>Marks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="attendanceBody"></tbody>
            </table>
        </div>
    </div>
    
    <!-- Participant Grid Modal -->
    <div id="participantGridModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>All Participants</h3>
                <button class="close-btn" id="closeParticipantGrid">&times;</button>
            </div>
            <div class="participant-grid" id="participantGrid"></div>
        </div>
    </div>

    <script>
        // ==================== CONFIG ====================
        const MEETING_ID = <?= $meeting_id ?>;
        const LECTURER_ID = <?= $lecturer_id ?>;
        const LECTURER_NAME = <?= json_encode($lecturer_name) ?>;
        
        // ==================== STATE ====================
        let localStream = null;
        let screenStream = null;
        let peerConnections = {}; // studentId -> RTCPeerConnection
        let micEnabled = true;
        let cameraEnabled = true;
        let isScreenSharing = false;
        let pendingRequests = [];
        
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
        
        // ==================== MEDIA INITIALIZATION ====================
        async function initializeMedia() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 1280, height: 720 },
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                });
                
                document.getElementById('selfVideo').srcObject = localStream;
                document.getElementById('mainVideo').srcObject = localStream;
                
                console.log('Media initialized');
            } catch (error) {
                console.error('Error accessing media devices:', error);
                alert('Could not access camera/microphone. Please check permissions.');
            }
        }
        
        // ==================== WEBRTC FUNCTIONS ====================
        function createPeerConnection(studentId) {
            const pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            });
            
            // Add local tracks
            if (isScreenSharing && screenStream) {
                screenStream.getTracks().forEach(track => {
                    pc.addTrack(track, screenStream);
                });
            } else if (localStream) {
                localStream.getTracks().forEach(track => {
                    pc.addTrack(track, localStream);
                });
            }
            
            // ICE candidate handling
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    apiCall('send_signal', {
                        to_student_id: studentId,
                        type: 'candidate',
                        data: JSON.stringify({ candidate: event.candidate })
                    });
                }
            };
            
            pc.onconnectionstatechange = () => {
                console.log(`PC state for student ${studentId}:`, pc.connectionState);
                if (pc.connectionState === 'failed' || pc.connectionState === 'closed') {
                    delete peerConnections[studentId];
                }
            };
            
            peerConnections[studentId] = pc;
            return pc;
        }
        
        async function sendOfferToStudent(studentId) {
            const pc = createPeerConnection(studentId);
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            
            await apiCall('send_signal', {
                to_student_id: studentId,
                type: 'offer',
                data: JSON.stringify({ sdp: offer.sdp })
            });
            
            console.log('Offer sent to student:', studentId);
        }
        
        async function handleSignal(signal) {
            const { from_student_id, type, data } = signal;
            const parsedData = JSON.parse(data || '{}');
            
            switch (type) {
                case 'answer':
                    const pc = peerConnections[from_student_id];
                    if (pc && parsedData.sdp) {
                        await pc.setRemoteDescription(new RTCSessionDescription({
                            type: 'answer',
                            sdp: parsedData.sdp
                        }));
                        console.log('Answer received from student:', from_student_id);
                    }
                    break;
                    
                case 'candidate':
                    const peerConn = peerConnections[from_student_id];
                    if (peerConn && parsedData.candidate) {
                        await peerConn.addIceCandidate(new RTCIceCandidate(parsedData.candidate));
                    }
                    break;
                    
                case 'request-publish':
                    handlePublishRequest(from_student_id, parsedData.name);
                    break;
                    
                case 'request-screen':
                    handleScreenRequest(from_student_id, parsedData.name);
                    break;
                    
                case 'offer':
                    // Student sending their stream (after approval)
                    await handleStudentOffer(from_student_id, parsedData);
                    break;
            }
        }
        
        async function handleStudentOffer(studentId, data) {
            const pc = peerConnections[studentId] || createPeerConnection(studentId);
            
            await pc.setRemoteDescription(new RTCSessionDescription({
                type: 'offer',
                sdp: data.sdp
            }));
            
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            
            await apiCall('send_signal', {
                to_student_id: studentId,
                type: 'answer',
                data: JSON.stringify({ sdp: answer.sdp })
            });
        }
        
        function handlePublishRequest(studentId, name) {
            if (confirm(`${name} wants to publish camera/mic. Allow?`)) {
                apiCall('approve_publish', { student_id: studentId });
            }
        }
        
        function handleScreenRequest(studentId, name) {
            if (confirm(`${name} wants to share screen. Allow?`)) {
                apiCall('approve_screen', { student_id: studentId });
            }
        }
        
        // ==================== SCREEN SHARING ====================
        async function toggleScreenShare() {
            if (isScreenSharing) {
                stopScreenShare();
            } else {
                await startScreenShare();
            }
        }
        
        async function startScreenShare() {
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: { cursor: 'always' },
                    audio: false
                });
                
                // Add audio from microphone
                if (localStream) {
                    const audioTrack = localStream.getAudioTracks()[0];
                    if (audioTrack) {
                        screenStream.addTrack(audioTrack);
                    }
                }
                
                document.getElementById('mainVideo').srcObject = screenStream;
                isScreenSharing = true;
                
                // Update all peer connections
                Object.keys(peerConnections).forEach(studentId => {
                    const pc = peerConnections[studentId];
                    const senders = pc.getSenders();
                    
                    // Replace video track
                    const videoSender = senders.find(s => s.track?.kind === 'video');
                    if (videoSender) {
                        videoSender.replaceTrack(screenStream.getVideoTracks()[0]);
                    }
                });
                
                document.getElementById('shareScreenBtn').classList.add('active');
                
                // Handle screen share stop
                screenStream.getVideoTracks()[0].onended = () => {
                    stopScreenShare();
                };
                
            } catch (error) {
                console.error('Error sharing screen:', error);
                alert('Could not share screen');
            }
        }
        
        function stopScreenShare() {
            if (screenStream) {
                screenStream.getTracks().forEach(track => track.stop());
                screenStream = null;
            }
            
            document.getElementById('mainVideo').srcObject = localStream;
            isScreenSharing = false;
            
            // Restore camera track to all peer connections
            Object.keys(peerConnections).forEach(studentId => {
                const pc = peerConnections[studentId];
                const senders = pc.getSenders();
                
                const videoSender = senders.find(s => s.track?.kind === 'video');
                if (videoSender && localStream) {
                    videoSender.replaceTrack(localStream.getVideoTracks()[0]);
                }
            });
            
            document.getElementById('shareScreenBtn').classList.remove('active');
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
                        ${escapeHtml(p.name.charAt(0).toUpperCase())}
                    </div>
                    <div class="participant-info">
                        <div class="participant-name">${escapeHtml(p.name)}</div>
                        <div class="participant-reg">${escapeHtml(p.reg_no || '')}</div>
                    </div>
                `;
                list.appendChild(item);
                
                // Send offer to new participants if not already connected
                if (!peerConnections[p.student_id]) {
                    sendOfferToStudent(p.student_id);
                }
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
                div.className = `chat-message ${msg.user_id == LECTURER_ID ? 'me' : ''}`;
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
                badge.textContent = messages.length - lastChatCount;
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
       <?php
// student_meeting_resp.php
session_start();
require_once '../config/db.php'; // expects $conn (mysqli)

// --- Guard ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Student';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id) { http_response_code(400); echo "Meeting ID required."; exit; }

// --- Fetch meeting info ---
$stmt = $conn->prepare("SELECT id, title, lecturer_id FROM meetings WHERE id=?");
$stmt->bind_param("i",$meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$meeting) { http_response_code(404); echo "Meeting not found."; exit; }
$lecturer_id = (int)$meeting['lecturer_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($meeting['title']) ?> — Student</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#0b0b0d; --panel:#07161c; --accent:#06b6d4; --green:#10b981; --red:#ef4444; --orange:#f97316; --purple:#8b5cf6; --cyan:#22d3ee;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:Inter,system-ui,Arial;background:var(--bg);color:#fff}
.container{display:flex;height:100vh;overflow:hidden;flex-direction:row}
#mainArea{flex:1;display:flex;flex-direction:column;min-width:0;position:relative}
#videoGrid{display:grid;gap:6px;padding:6px;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));align-content:start;overflow:auto}
.tile{position:relative;background:#060607;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:120px}
.tile .meta{position:absolute;left:8px;top:8px;background:rgba(255,255,255,0.03);padding:4px 6px;border-radius:6px;font-size:13px}
video{width:100%;height:100%;object-fit:cover;display:block}
#fullScreenLecturer{position:absolute;top:0;left:0;width:100%;height:100%;z-index:100;display:none;background:#000}
#fullScreenLecturer video{width:100%;height:100%;object-fit:contain}
#fullScreenLecturer button{position:absolute;top:12px;right:12px;z-index:101;background:rgba(0,0,0,0.5);border:none;color:#fff;padding:6px 12px;border-radius:6px;cursor:pointer}

/* Controls */
.controls{position:fixed;bottom:10px;left:50%;transform:translateX(-50%);display:flex;gap:8px;padding:6px;z-index:50;flex-wrap:wrap}
.ctrl{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;cursor:pointer;transition:transform .12s,opacity .12s;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01))}
.ctrl:hover{transform:translateY(-4px)}
.ctrl.cam{color:var(--green)}
.ctrl.mic{color:var(--cyan)}
.ctrl.screen{color:var(--purple)}
.ctrl.share{color:var(--orange)}
.ctrl.chat{color:var(--accent)}
.ctrl.end{color:#7f1d1d}

/* Sidebar */
.sidebar{width:300px;background:var(--panel);border-left:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;transition:transform .18s ease;z-index:40}
.sidebar.hidden{transform:translateX(100%)}
.sidebar header{padding:12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
#participants{flex:1;overflow:auto;padding:8px}
.participant{display:flex;gap:8px;align-items:center;padding:8px;border-radius:8px;margin-bottom:8px;background:rgba(255,255,255,0.02)}
.avatar{width:36px;height:36px;border-radius:999px;background:#0b1220;display:flex;align-items:center;justify-content:center;font-weight:700;color:#cfeff6}

/* Chat */
#chatBox{border-top:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;height:35%}
#chatLog{flex:1;padding:8px;overflow:auto;display:flex;flex-direction:column;gap:6px}
.bubble{max-width:85%;padding:6px;border-radius:10px;background:rgba(255,255,255,0.02)}
.bubble.me{align-self:flex-end;background:rgba(16,185,129,0.12);color:#e6fff2}
#chatInput{display:flex;gap:6px;padding:6px}
#chatInput textarea{flex:1;height:42px;border-radius:6px;padding:6px;background:#07161c;color:#fff;border:1px solid rgba(255,255,255,0.03);resize:none}
.smallBtn{padding:4px 8px;border-radius:6px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.02);cursor:pointer;color:#dff6fb}

/* Attendance panel */
#attendancePanel{position:fixed;right:50%;bottom:80px;transform:translateX(50%);width:90%;max-width:680px;background:var(--panel);border-radius:12px;padding:12px;display:none;z-index:60}
#attendancePanel table{width:100%;border-collapse:collapse;font-size:14px}
#attendancePanel th,#attendancePanel td{padding:6px;border-bottom:1px solid rgba(255,255,255,0.03);text-align:left}

/* Responsive */
@media (max-width:900px){
  .sidebar{position:fixed;right:0;top:0;bottom:0;width:80%;max-width:300px}
  #videoGrid{grid-template-columns:repeat(auto-fit,minmax(100px,1fr))}
}
@media (max-width:480px){
  .ctrl{width:42px;height:42px;font-size:18px}
  #videoGrid{gap:4px;padding:4px}
  .tile{min-height:88px}
}
</style>
</head>
<body>
<div class="container">
  <div id="mainArea">
    <div id="videoGrid" aria-live="polite" role="application">
      <div class="tile" id="lecturerTile"><div class="meta">Lecturer</div><video id="lecturerVideo" autoplay playsinline></video></div>
      <div class="tile" id="localTile"><div class="meta">You — <?= htmlspecialchars($userName) ?></div><video id="localVideo" autoplay muted playsinline></video></div>
    </div>
    <div id="fullScreenLecturer">
      <video id="lecturerScreen" autoplay playsinline></video>
      <button onclick="closeFullScreenLecturer()">Close</button>
    </div>
  </div>
  <div id="sidebar" class="sidebar">
    <header>
      <div>Participants</div>
      <div style="display:flex;gap:6px">
        <button id="refreshBtn" class="smallBtn">Refresh</button>
        <button id="takeInlineBtn" class="smallBtn">Take</button>
      </div>
    </header>
    <div id="participants"></div>
    <div id="chatBox">
      <div id="chatLog" aria-live="polite"></div>
      <div id="chatInput">
        <textarea id="chatMsg" placeholder="Write a message..."></textarea>
        <div style="display:flex;flex-direction:column;gap:4px">
          <button id="sendChat" class="smallBtn">Send</button>
          <button id="clearChat" class="smallBtn">Clear</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Controls -->
<div class="controls">
  <div id="btnCam" class="ctrl cam" data-state="on"><i class="fa fa-video"></i></div>
  <div id="btnMic" class="ctrl mic" data-state="on"><i class="fa fa-microphone"></i></div>
  <div id="btnShare" class="ctrl screen"><i class="fa fa-desktop"></i></div>
  <div id="btnRequestPublish" class="ctrl share"><i class="fa fa-upload"></i></div>
  <div id="btnAttendance" class="ctrl chat"><i class="fa fa-list-check"></i></div>
  <div id="btnToggleSidebar" class="ctrl chat"><i class="fa fa-comments"></i></div>
</div>

<div id="attendancePanel">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <strong>Attendance — Meeting #<?= (int)$meeting_id ?></strong>
    <button id="closeAttendancePanel" class="smallBtn">Close</button>
  </div>
  <div style="margin-top:6px">
    <button id="refreshAttendanceBtn" class="smallBtn">Refresh</button>
  </div>
  <table id="attendanceTable">
    <thead><tr><th>#</th><th>Name</th><th>Reg No</th><th>Joined</th><th>Duration</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<script>
// --- Config/state ---
const meetingId = <?= (int)$meeting_id ?>;
const studentId = <?= (int)$student_id ?>;
const userName = <?= json_encode($userName) ?>;
const lecturerId = <?= (int)$lecturer_id ?>;
let localStream=null, published=false, pcForLecturer=null, pcPublish=null, pendingCandidates=[];

function el(id){return document.getElementById(id);}
function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function postAction(data){return (await fetch(location.pathname+'?meeting_id='+meetingId,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)})).json();}

// UI events
el('btnCam').addEventListener('click',()=>{if(!localStream)return;const state=el('btnCam').getAttribute('data-state')==='on';localStream.getVideoTracks().forEach(t=>t.enabled=!state);el('btnCam').setAttribute('data-state',state?'off':'on');el('btnCam').querySelector('i').className=state?'fa fa-video-slash':'fa fa-video';});
el('btnMic').addEventListener('click',()=>{if(!localStream)return;const state=el('btnMic').getAttribute('data-state')==='on';localStream.getAudioTracks().forEach(t=>t.enabled=!state);el('btnMic').setAttribute('data-state',state?'off':'on');el('btnMic').querySelector('i').className=state?'fa fa-microphone-slash':'fa fa-microphone';});
el('btnToggleSidebar').addEventListener('click',()=>el('sidebar').classList.toggle('hidden'));
el('btnAttendance').addEventListener('click',()=>el('attendancePanel').style.display=el('attendancePanel').style.display==='block'?'none':'block');
el('closeAttendancePanel').addEventListener('click',()=>el('attendancePanel').style.display='none');

function showFullScreenLecturer(stream){el('fullScreenLecturer').style.display='block';el('lecturerScreen').srcObject=stream;}
function closeFullScreenLecturer(){el('fullScreenLecturer').style.display='none';el('lecturerScreen').srcObject=null;}

async function initLocalPreview(){try{localStream=await navigator.mediaDevices.getUserMedia({video:{width:1280},audio:true});el('localVideo').srcObject=localStream;}catch(e){console.warn('media denied',e);}}
async function logAttendanceOnJoin(){try{await postAction({action:'log_attendance_on_join'});}catch(e){console.warn(e);}}
async function refreshParticipants(){try{const arr=await postAction({action:'get_participants'});const c=el('participants');c.innerHTML='';(arr||[]).forEach(p=>{const d=document.createElement('div');d.className='participant';d.innerHTML=`<div class="avatar">${escapeHtml((p.name||'U').charAt(0).toUpperCase())}</div><div><strong>${escapeHtml(p.name)}</strong><br><small>${escapeHtml(p.reg_no||'')}</small></div>`;c.appendChild(d);});}catch(e){console.warn(e);}}
async function pollChat(){try{const arr=await postAction({action:'get_chat'});const log=el('chatLog');log.innerHTML='';(arr||[]).forEach(m=>{const b=document.createElement('div');b.className='bubble'+(parseInt(m.user_id)===studentId?' me':'');b.innerHTML=`<strong>${escapeHtml(m.user_name)}</strong><div style="margin-top:2px">${escapeHtml(m.message)}</div>`;log.appendChild(b);});log.scrollTop=log.scrollHeight;}catch(e){console.warn(e);}}

(async function init(){await initLocalPreview();await logAttendanceOnJoin();await refreshParticipants();await pollChat();el('lecturerVideo').muted=false;if(window.innerWidth<800)el('sidebar').classList.add('hidden');})();
setInterval(refreshParticipants,5000);
setInterval(pollChat,2500);
</script>
</body>
</html>         loadChat();
            }
        }
        
        // ==================== ATTENDANCE ====================
        async function loadAttendance() {
            const records = await apiCall('get_attendance');
            const tbody = document.getElementById('attendanceBody');
            tbody.innerHTML = '';
            
            records.forEach((record, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${escapeHtml(record.name)}</td>
                    <td>${escapeHtml(record.reg_no || 'N/A')}</td>
                    <td>${record.joined_at || 'N/A'}</td>
                    <td>${record.duration || 0}</td>
                    <td>
                        <input type="checkbox" ${record.active ? 'checked' : ''} 
                               onchange="toggleActive(${record.id})">
                    </td>
                    <td>
                        <input type="number" min="0" max="10" value="${record.marks || 0}" 
                               id="marks_${record.id}" style="width: 60px;">
                    </td>
                    <td>
                        <button class="award-btn" onclick="awardMarks(${record.id})">Award</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        async function awardMarks(attendanceId) {
            const marks = document.getElementById(`marks_${attendanceId}`).value;
            await apiCall('award_marks', { attendance_id: attendanceId, marks });
            alert('Marks awarded successfully');
        }
        
        async function toggleActive(attendanceId) {
            await apiCall('toggle_active', { attendance_id: attendanceId });
        }
        
        // Make functions global for inline handlers
        window.awardMarks = awardMarks;
        window.toggleActive = toggleActive;
        
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
            if (localStream && !isScreenSharing) {
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
        
        document.getElementById('shareScreenBtn').addEventListener('click', toggleScreenShare);
        
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
        
        document.getElementById('attendanceBtn').addEventListener('click', () => {
            document.getElementById('attendanceModal').classList.add('open');
            loadAttendance();
        });
        
        document.getElementById('closeAttendance').addEventListener('click', () => {
            document.getElementById('attendanceModal').classList.remove('open');
        });
        
        document.getElementById('endMeetingBtn').addEventListener('click', async () => {
            if (confirm('Are you sure you want to end this meeting for everyone?')) {
                await apiCall('end_meeting');
                alert('Meeting ended');
                window.location.href = '../lecturer/dashboard.php';
            }
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
            await initializeMedia();
            await loadParticipants();
            await loadChat();
            
            // Start polling
            setInterval(pollSignals, 1500);
            setInterval(loadParticipants, 5000);
            setInterval(loadChat, 3000);
        }
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            Object.values(peerConnections).forEach(pc => pc.close());
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
>>>>>>> Stashed changes
</body>
</html>