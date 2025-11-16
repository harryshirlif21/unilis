<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role']!=='student'){
    header("Location: ../login.php"); exit;
}
$student_id = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Student';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id){ http_response_code(400); echo "Meeting ID required."; exit; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Meeting — Student</title>
<style>
:root{--bg:#0b0b0d;--panel:#07161c;--accent:#06b6d4;}
body{margin:0;background:var(--bg);color:#fff;font-family:sans-serif;}
.container{display:flex;height:100vh;overflow:hidden;}
#mainArea{flex:1;display:flex;flex-direction:column;min-width:0;}
#videoGrid{display:grid;gap:8px;padding:8px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-content:start;overflow:auto;}
.tile{position:relative;background:#060607;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:120px;}
.tile video{width:100%;height:100%;object-fit:cover;}
.small-preview{position:absolute;top:8px;left:8px;width:160px;height:90px;border:2px solid var(--accent);border-radius:8px;object-fit:cover;z-index:10;}
.hidden{display:none;}
.controls{position:fixed;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:10px;z-index:50;}
.ctrl{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;cursor:pointer;background:rgba(255,255,255,0.02);}
</style>
</head>
<body>
<div class="container">
  <div id="mainArea">
    <div id="videoGrid">

      <!-- Lecturer camera -->
      <div class="tile" id="lecturerVideoDiv">
        <video id="lecturerVideo" autoplay playsinline></video>
      </div>

      <!-- Lecturer screen share -->
      <div class="tile hidden" id="lecturerScreenDiv">
        <video id="lecturerScreen" autoplay playsinline></video>
        <video id="lecturerCamPreview" class="small-preview hidden" autoplay playsinline muted></video>
      </div>

      <!-- Local student preview -->
      <div class="tile" id="localTile">
        <div class="meta">You — <?= htmlspecialchars($userName) ?></div>
        <video id="localVideo" autoplay muted playsinline></video>
      </div>

    </div>
  </div>
</div>

<div class="controls">
  <div id="btnCam" class="ctrl">Cam</div>
  <div id="btnMic" class="ctrl">Mic</div>
</div>

<script>
const meetingId = <?= (int)$meeting_id ?>;
const studentId = <?= (int)$student_id ?>;
let localStream = null;
let pcForLecturer = null;
let pcPublish = null;
let pendingCandidates = [];

/* ===== Helper to POST action to PHP ===== */
async function postAction(data){
  const res = await fetch(location.pathname+'?meeting_id='+meetingId,{
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams(data)
  });
  return res.json();
}

/* ===== Init local preview ===== */
async function initLocalPreview(){
  try {
    localStream = await navigator.mediaDevices.getUserMedia({video:true,audio:true});
    document.getElementById('localVideo').srcObject = localStream;
  } catch(e){ console.warn('Local media error', e); }
}

/* ===== Send Signal to Lecturer ===== */
async function sendSignalToLecturer(type,dataObj){
  await postAction({action:'send_signal',type:type,data:JSON.stringify(dataObj)});
}

/* ===== Polling for signals ===== */
async function pollSignals(){
  try {
    const rows = await postAction({action:'get_signals'});
    if (!Array.isArray(rows) || !rows.length) return;
    for(const sig of rows) handleSignal(sig);
  } catch(e){ console.error(e); }
}
setInterval(pollSignals,1500);

/* ===== Handle incoming signals ===== */
async function handleSignal(sig){
  const type = sig.type;
  const data = (()=>{try{return JSON.parse(sig.data);}catch(e){return sig.data;}})();

  // Lecturer offer (camera)
  if(type==='offer'){
    if(!pcForLecturer) pcForLecturer = createPeerConnection('lecturer');
    await pcForLecturer.setRemoteDescription(new RTCSessionDescription({type:'offer',sdp:data.sdp}));
    const answer = await pcForLecturer.createAnswer();
    await pcForLecturer.setLocalDescription(answer);
    await sendSignalToLecturer('answer',{sdp:pcForLecturer.localDescription.sdp,to_student_id:studentId});
    return;
  }

  if(type==='candidate'){
    if(pcForLecturer) pcForLecturer.addIceCandidate(data.candidate);
    else pendingCandidates.push(data.candidate);
    return;
  }

  // START screen share
  if(type==='start-screen'){
    document.getElementById('lecturerVideoDiv').classList.add('hidden');
    const screenDiv = document.getElementById('lecturerScreenDiv');
    screenDiv.classList.remove('hidden');

    // attach stream
    const stream = data.stream; // delivered via WebRTC track
    document.getElementById('lecturerScreen').srcObject = stream;

    // small camera preview
    document.getElementById('lecturerCamPreview').srcObject = document.getElementById('lecturerVideo').srcObject;
    document.getElementById('lecturerCamPreview').classList.remove('hidden');
    return;
  }

  // STOP screen share
  if(type==='stop-screen'){
    document.getElementById('lecturerScreenDiv').classList.add('hidden');
    document.getElementById('lecturerVideoDiv').classList.remove('hidden');
    document.getElementById('lecturerCamPreview').classList.add('hidden');
    return;
  }
}

/* ===== Create RTCPeerConnection ===== */
function createPeerConnection(kind){
  const pc = new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
  pc.onicecandidate = e=>{if(e.candidate) sendSignalToLecturer('candidate',{candidate:e.candidate});};
  pc.ontrack = e=>{
    if(kind==='lecturer') document.getElementById('lecturerVideo').srcObject = e.streams[0];
    if(kind==='publish') console.log('Publish track',e.streams);
  };
  return pc;
}

/* ===== Init on load ===== */
(async function(){
  await initLocalPreview();
})();
</script>
</body>
</html>
