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
</html>
