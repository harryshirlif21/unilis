<?php
session_start();
require_once '../config/db.php';

// Lecturer-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    die("Access denied. Only lecturers can access this page.");
}

$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) die("Meeting ID is required.");

// Fetch meeting info
$stmt = $conn->prepare("SELECT id, title, scheduled_time, duration, FROM meetings WHERE id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();
$stmt->close();
if (!$meeting) die("Meeting not found.");

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// =======================
// Handle POST actions
// =======================
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    header('Content-Type: application/json');
    switch($_POST['action']){
        // --- Participants ---
        case 'get_participants':
            $stmt = $conn->prepare("SELECT id, name, reg_no, role FROM participants WHERE meeting_id=?");
            $stmt->bind_param("i", $meeting_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            exit(json_encode($res));

        // --- Chat ---
        case 'get_chat':
            $stmt = $conn->prepare("SELECT user_id, user_name, message, created_at FROM chat WHERE meeting_id=? ORDER BY created_at ASC");
            $stmt->bind_param("i",$meeting_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            exit(json_encode($res));

        case 'send_chat':
            $msg = $_POST['message'] ?? '';
            if(trim($msg)==='') exit(json_encode(['success'=>false]));
            $stmt = $conn->prepare("INSERT INTO chat(meeting_id,user_id,user_name,message,created_at) VALUES(?,?,?,?,NOW())");
            $stmt->bind_param("iiss",$meeting_id,$userId,$userName,$msg);
            $stmt->execute();
            $stmt->close();
            exit(json_encode(['success'=>true]));

        // --- Attendance ---
        case 'get_attendance':
            $stmt = $conn->prepare("SELECT id, name, reg_no, joined_at, duration, active FROM attendance WHERE meeting_id=?");
            $stmt->bind_param("i",$meeting_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            exit(json_encode($res));

        case 'award_participation':
            $student_id = $_POST['student_id'] ?? 0;
            $marks = $_POST['marks'] ?? 0;
            $stmt = $conn->prepare("UPDATE attendance SET marks=? WHERE id=? AND meeting_id=?");
            $stmt->bind_param("iii",$marks,$student_id,$meeting_id);
            $stmt->execute();
            $stmt->close();
            exit(json_encode(['success'=>true]));

        case 'take_attendance':
            // snapshot participants into attendance table
            $stmt = $conn->prepare("INSERT INTO attendance(meeting_id,name,reg_no,joined_at,active) SELECT ?,name,reg_no,NOW(),1 FROM participants WHERE meeting_id=?");
            $stmt->bind_param("ii",$meeting_id,$meeting_id);
            $stmt->execute();
            $stmt->close();
            exit(json_encode(['success'=>true]));

        // --- Signaling (placeholder) ---
        case 'send_signal':
            $to = $_POST['to_user_id'] ?? 0;
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            $stmt = $conn->prepare("INSERT INTO meeting_signals(meeting_id,from_user_id,to_user_id,type,data,created_at) VALUES(?,?,?,?,?,NOW())");
            $stmt->bind_param("iiiss",$meeting_id,$userId,$to,$type,$data);
            $stmt->execute();
            $stmt->close();
            exit(json_encode(['success'=>true]));

        case 'get_signals':
            $stmt = $conn->prepare("SELECT id,from_user_id,type,data FROM meeting_signals WHERE meeting_id=? AND to_user_id=? ORDER BY id ASC");
            $stmt->bind_param("ii",$meeting_id,$userId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            // delete after fetching
            $stmt_del = $conn->prepare("DELETE FROM meeting_signals WHERE meeting_id=? AND to_user_id=?");
            $stmt_del->bind_param("ii",$meeting_id,$userId);
            $stmt_del->execute();
            $stmt_del->close();
            $stmt->close();
            exit(json_encode($res));

        case 'end_meeting':
            $stmt = $conn->prepare("UPDATE meetings SET ended=1 WHERE id=?");
            $stmt->bind_param("i",$meeting_id);
            $stmt->execute();
            $stmt->close();
            exit(json_encode(['success'=>true]));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($meeting['title']) ?> — Lecturer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#0b0b0d; --accent:#06b6d4; --green:#10b981; --red:#ef4444; --orange:#f97316; --purple:#8b5cf6; --cyan:#22d3ee;
}
html,body{margin:0;height:100%;background:var(--bg);color:#fff;font-family:Arial,system-ui,sans-serif;overflow:hidden;}
#videos{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:8px;padding:8px;height:100vh;width:calc(100% - 340px);float:left;}
.tile{position:relative;background:#060607;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;}
video{width:100%;height:100%;object-fit:cover;}
.tile .meta{position:absolute;left:8px;top:8px;background:rgba(255,255,255,0.05);padding:6px 8px;border-radius:6px;font-size:13px;}
.tile.highlight{box-shadow:0 0 15px var(--accent);}
#controls{position:fixed;bottom:18px;left:50%;transform:translateX(-50%);display:flex;gap:12px;z-index:100;}
.control{width:56px;height:56px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;cursor:pointer;transition:transform .12s;}
.control:hover{transform:translateY(-4px);}
.control.cam{color:var(--green);}
.control.mic{color:var(--cyan);}
.control.screen{color:var(--purple);}
.control.mute{color:var(--red);}
.control.record{color:var(--red);}
.control.end{color:#7f1d1d;}
.control.attendance{color:var(--orange);}
.control.chat{color:var(--accent);}
#sidebar{position:fixed;right:0;top:0;bottom:0;width:340px;background:#071014;display:flex;flex-direction:column;z-index:110;}
#sidebar header{padding:16px;font-weight:700;font-size:16px;color:#dff6fb;display:flex;justify-content:space-between;align-items:center;}
#participantsList{padding:12px;overflow:auto;flex:1;}
.participant{display:flex;gap:10px;align-items:center;padding:8px;border-radius:10px;margin-bottom:6px;background:linear-gradient(180deg, rgba(255,255,255,0.01), transparent);}
.avatar{width:42px;height:42px;border-radius:999px;background:#0b1220;display:flex;align-items:center;justify-content:center;font-weight:700;color:#cfeff6;}
#chatBox{height:44%;border-top:1px solid rgba(255,255,255,0.02);display:flex;flex-direction:column;}
#chatLog{flex:1;padding:12px;overflow:auto;display:flex;flex-direction:column;gap:8px;}
.bubble{max-width:85%;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.05);}
.bubble.me{align-self:flex-end;background:rgba(16,185,129,0.15);}
#chatInput{display:flex;gap:8px;padding:12px;border-top:1px solid rgba(255,255,255,0.02);}
#chatInput textarea{flex:1;height:56px;border-radius:12px;padding:12px;background:#07161c;color:#fff;border:1px solid rgba(255,255,255,0.03);}
#attendancePanel{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:800px;max-width:95%;max-height:80vh;overflow:auto;background:#07161c;border-radius:12px;z-index:200;padding:16px;display:none;}
table.att{width:100%;border-collapse:collapse;margin-top:10px;}
table.att th,table.att td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.03);text-align:left;font-size:13px;}
.smallBtn{padding:6px 8px;border-radius:6px;background:#0b1220;color:#dff6fb;border:1px solid rgba(255,255,255,0.03);cursor:pointer;}
</style>
</head>
<body>

<div id="videos" aria-live="polite" role="application">
  <div class="tile" id="localVideoTile">
    <div class="meta">You — <?= htmlspecialchars($userName) ?></div>
    <video id="localVideo" autoplay muted playsinline></video>
  </div>
</div>

<div id="controls">
  <div class="control cam" id="toggleCam" title="Camera"><i class="fa fa-video"></i></div>
  <div class="control mic" id="toggleMic" title="Mic"><i class="fa fa-microphone"></i></div>
  <div class="control screen" id="shareScreen" title="Share screen"><i class="fa fa-desktop"></i></div>
  <div class="control mute" id="muteAll" title="Mute all"><i class="fa fa-volume-mute"></i></div>
  <div class="control record" id="recordBtn" title="Record"><i class="fa fa-circle"></i></div>
  <div class="control end" id="endBtn" title="End meeting"><i class="fa fa-sign-out-alt"></i></div>
  <div class="control attendance" id="attendanceBtn" title="Attendance"><i class="fa fa-list-check"></i></div>
  <div class="control chat" id="toggleChat" title="Chat"><i class="fa fa-comments"></i></div>
</div>

<div id="sidebar">
  <header>Participants</header>
  <div id="participantsList" role="list"></div>
  <div id="chatBox">
    <div id="chatLog"></div>
    <div id="chatInput">
      <textarea id="chatMsg" placeholder="Write a message..."></textarea>
      <div style="display:flex;flex-direction:column;gap:8px">
        <button class="smallBtn" id="sendChatBtn">Send</button>
        <button class="smallBtn" id="clearChatBtn">Clear</button>
      </div>
    </div>
  </div>
</div>

<div id="attendancePanel">
  <header><strong>Attendance — Meeting #<?= htmlspecialchars($meeting['id']) ?></strong>
    <button id="closeAttendance" class="smallBtn">Close</button>
  </header>
  <table class="att" id="attendanceTable">
    <thead><tr><th>#</th><th>Name</th><th>Reg No</th><th>Joined</th><th>Duration</th><th>Active</th><th>Award</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<script>
const meetingId = <?= (int)$meeting['id'] ?>;
const userId = <?= (int)$userId ?>;
const userName = <?= json_encode($userName) ?>;

let localStream=null, mediaRecorder=null, recordedChunks=[], recording=false;

async function postAction(data){
  const res = await fetch('', {method:'POST', body:new URLSearchParams(data)});
  return await res.json();
}

async function initLocalMedia(){
  try{
    localStream = await navigator.mediaDevices.getUserMedia({video:{width:1280},audio:true});
    document.getElementById('localVideo').srcObject = localStream;
  }catch(e){ alert("Camera/mic error: "+e.message);}
}

async function refreshParticipants(){
  const list = document.getElementById('participantsList');
  const res = await postAction({action:'get_participants'});
  list.innerHTML='';
  res.forEach(p=>{
    const el = document.createElement('div');
    el.className='participant';
    el.innerHTML=`<div class="avatar">${(p.name||'U').charAt(0).toUpperCase()}</div>
                  <div>${p.name||''}<br>${p.reg_no||p.role||''}</div>`;
    list.appendChild(el);
  });
}

async function pollChat(){
  const res = await postAction({action:'get_chat'});
  const log = document.getElementById('chatLog');
  log.innerHTML='';
  res.forEach(m=>{
    const b = document.createElement('div'); b.className='bubble'+(m.user_id==userId?' me':'');
    b.innerHTML=`<strong>${m.user_name}</strong><div>${m.message}</div>`;
    log.appendChild(b);
  });
  log.scrollTop = log.scrollHeight;
}

async function sendChat(){
  const t = document.getElementById('chatMsg');
  const txt = t.value.trim();
  if(!txt) return;
  await postAction({action:'send_chat', message:txt});
  t.value=''; pollChat();
}

async function openAttendance(){
  document.getElementById('attendancePanel').style.display='block';
  const res = await postAction({action:'get_attendance'});
  const tbody = document.querySelector('#attendanceTable tbody');
  tbody.innerHTML='';
  res.forEach((r,i)=>{
    const tr = document.createElement('tr');
    tr.innerHTML=`<td>${i+1}</td><td>${r.name}</td><td>${r.reg_no}</td><td>${r.joined_at||''}</td><td>${r.duration||''}</td>
                  <td><input type="checkbox" ${r.active?'checked':''}></td>
                  <td><input type="number" min="0" max="10" style="width:50px"><button class="smallBtn">Award</button></td>`;
    tbody.appendChild(tr);
  });
}

document.getElementById('toggleMic').addEventListener('click',()=>{
  if(!localStream) return;
  localStream.getAudioTracks().forEach(t=>t.enabled=!t.enabled);
});
document.getElementById('toggleCam').addEventListener('click',()=>{
  if(!localStream) return;
  localStream.getVideoTracks().forEach(t=>t.enabled=!t.enabled);
});
document.getElementById('shareScreen').addEventListener('click', async()=>{
  try{
    const s = await navigator.mediaDevices.getDisplayMedia({video:true});
    document.getElementById('localVideo').srcObject=s;
    s.getVideoTracks()[0].onended=()=>{document.getElementById('localVideo').srcObject=localStream;};
  }catch(e){alert(e);}
});
document.getElementById('recordBtn').addEventListener('click', ()=>{
  if(recording){ mediaRecorder.stop(); recording=false; return; }
  mediaRecorder = new MediaRecorder(localStream,{mimeType:'video/webm'});
  mediaRecorder.ondataavailable=e=>{if(e.data.size) recordedChunks.push(e.data);}
  mediaRecorder.onstop=()=>{
    const blob = new Blob(recordedChunks,{type:'video/webm'});
    const a = document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`meeting_${meetingId}.webm`; a.click();
    recordedChunks=[];
  };
  mediaRecorder.start(1000); recording=true;
});
document.getElementById('attendanceBtn').addEventListener('click', openAttendance);
document.getElementById('closeAttendance').addEventListener('click',()=>{document.getElementById('attendancePanel').style.display='none';});
document.getElementById('sendChatBtn').addEventListener('click', sendChat);
setInterval(refreshParticipants,5000);
setInterval(pollChat,3000);
initLocalMedia();
</script>
</body>
</html>
