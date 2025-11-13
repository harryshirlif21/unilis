<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    die("Access denied. Only lecturers can access this page.");
}

$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) die("Meeting ID is required.");

$stmt = $conn->prepare("SELECT title, scheduled_time, duration FROM meetings WHERE id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();
$stmt->close();

if (!$meeting) die("Meeting not found.");

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($meeting['title']) ?> - Lecturer WebRTC Meeting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body, html { margin:0; padding:0; height:100%; font-family:sans-serif; background:#000; color:#fff; overflow:hidden; }
        #videos { display:flex; flex-wrap:wrap; gap:5px; padding:5px; width:100%; height:100%; box-sizing:border-box; }
        video { flex:1 1 45%; height:calc(50% - 10px); background:#111; border-radius:5px; object-fit:cover; }
        #localVideo { border:3px solid #0f0; }
        #controls { position:fixed; bottom:10px; left:50%; transform:translateX(-50%); background:rgba(34,34,34,0.8); padding:10px 15px; border-radius:10px; display:flex; gap:10px; flex-wrap:wrap; }
        .control-btn { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:5px 10px; border:none; border-radius:5px; cursor:pointer; background:#333; color:#fff; font-size:12px; width:60px; }
        .control-btn i { font-size:18px; margin-bottom:3px; }
        .control-btn:hover { background:#444; }
        #sidebar { width:300px; background:#222; display:flex; flex-direction:column; transition: transform 0.3s; transform: translateX(100%); position:fixed; right:0; top:0; bottom:0; z-index:100; }
        #sidebar.active { transform: translateX(0); }
        #participants, #chat { flex:1; overflow:auto; padding:10px; border-bottom:1px solid #444; }
        #chatInput { display:flex; padding:5px; border-top:1px solid #444; }
        #chatInput input { flex:1; padding:5px; border:none; border-radius:3px; margin-right:5px; }
        #chatInput button { padding:5px 10px; border:none; border-radius:3px; cursor:pointer; background:#0f0; color:#000; }
    </style>
</head>
<body>

<div id="videos">
    <video id="localVideo" autoplay muted></video>
</div>

<div id="controls">
    <button class="control-btn" id="toggleVideo"><i class="fa fa-video"></i>Video</button>
    <button class="control-btn" id="toggleAudio"><i class="fa fa-microphone"></i>Audio</button>
    <button class="control-btn" id="presentScreen"><i class="fa fa-desktop"></i>Share</button>
    <button class="control-btn" id="muteAll"><i class="fa fa-volume-mute"></i>Mute All</button>
    <button class="control-btn" id="record"><i class="fa fa-circle"></i>Record</button>
    <button class="control-btn" id="endMeeting"><i class="fa fa-sign-out-alt"></i>End</button>
    <button class="control-btn" id="toggleSidebar"><i class="fa fa-comments"></i>Chat</button>
</div>

<div id="sidebar">
    <div id="participants"><strong>Participants:</strong></div>
    <div id="chat"></div>
    <div id="chatInput">
        <input type="text" id="chatMessage" placeholder="Type a message...">
        <button id="sendChat">Send</button>
    </div>
</div>

<script>
const meetingId = <?= $meeting_id ?>;
const userId = <?= $userId ?>;
const userName = "<?= addslashes($userName) ?>";

let localStream;
const peers = {};
const videoContainer = document.getElementById('videos');
const participantsDiv = document.getElementById('participants');
const chatDiv = document.getElementById('chat');
const sidebar = document.getElementById('sidebar');
let mediaRecorder;
let recordedChunks = [];
let recording = false;

// --- Initialize local media ---
async function initLocalMedia() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
        document.getElementById('localVideo').srcObject = localStream;
    } catch(e) { alert("Failed to access camera/microphone: "+e.message); }
}

// --- Polling signaling ---
async function fetchSignals() {
    const res = await fetch('../actions.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=get_signals&meeting_id=${meetingId}&user_role=lecturer&user_id=${userId}`
    });
    const signals = await res.json();
    for(const sig of signals) handleSignal(sig);
}

async function sendSignal(toUserId, type, data) {
    await fetch('../actions.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=send_signal&meeting_id=${meetingId}&from_user_id=${userId}&to_user_id=${toUserId}&type=${type}&data=${encodeURIComponent(JSON.stringify(data))}&role=lecturer`
    });
}

// --- Handle signals ---
function handleSignal(sig){
    const from = sig.from_user_id;
    if(!peers[from]) createPeer(from,false);
    const pc = peers[from];
    const data = JSON.parse(sig.data);
    if(sig.type==='offer'){
        pc.setRemoteDescription(new RTCSessionDescription(data)).then(()=>{
            pc.createAnswer().then(answer=>{
                pc.setLocalDescription(answer);
                sendSignal(from,'answer',answer);
            });
        });
    } else if(sig.type==='answer'){
        pc.setRemoteDescription(new RTCSessionDescription(data));
    } else if(sig.type==='candidate'){
        pc.addIceCandidate(new RTCIceCandidate(data));
    }
}

// --- Create peer ---
function createPeer(peerId,isInitiator=true){
    const pc = new RTCPeerConnection({ iceServers:[{urls:'stun:stun.l.google.com:19302'}] });
    localStream.getTracks().forEach(track=>pc.addTrack(track,localStream));
    pc.ontrack = e => {
        if(!document.getElementById('video_'+peerId)){
            const v=document.createElement('video');
            v.id='video_'+peerId;
            v.autoplay=true;
            v.srcObject=e.streams[0];
            videoContainer.appendChild(v);
        }
    };
    pc.onicecandidate = e => { if(e.candidate) sendSignal(peerId,'candidate',e.candidate); }
    peers[peerId]=pc;
    if(isInitiator){
        pc.createOffer().then(offer=>{
            pc.setLocalDescription(offer);
            sendSignal(peerId,'offer',offer);
        });
    }
}

// --- Controls ---
document.getElementById('toggleVideo').onclick = ()=>{ localStream.getVideoTracks().forEach(t=>t.enabled=!t.enabled); };
document.getElementById('toggleAudio').onclick = ()=>{ localStream.getAudioTracks().forEach(t=>t.enabled=!t.enabled); };
document.getElementById('presentScreen').onclick = async ()=>{
    try{
        const screenStream = await navigator.mediaDevices.getDisplayMedia({video:true});
        const track = screenStream.getVideoTracks()[0];
        Object.values(peers).forEach(pc=>{
            const sender = pc.getSenders().find(s=>s.track.kind==='video');
            if(sender) sender.replaceTrack(track);
        });
        document.getElementById('localVideo').srcObject = screenStream;
        track.onended = ()=> initLocalMedia();
    } catch(e){ alert('Screen share failed: '+e.message); }
};
document.getElementById('muteAll').onclick = ()=>{ Object.values(peers).forEach(pc=>pc.getSenders().forEach(s=>{ if(s.track.kind==='audio') s.track.enabled=false; })); };
document.getElementById('endMeeting').onclick = ()=>{ location.reload(); };
document.getElementById('record').onclick = ()=>{
    if(!recording){
        mediaRecorder = new MediaRecorder(localStream);
        mediaRecorder.ondataavailable = e=>{ recordedChunks.push(e.data); }
        mediaRecorder.onstop = ()=>{
            const blob = new Blob(recordedChunks,{type:'video/webm'});
            const url = URL.createObjectURL(blob);
            const a=document.createElement('a'); a.href=url; a.download='recording_'+Date.now()+'.webm'; a.click();
            recordedChunks=[];
        };
        mediaRecorder.start(); recording=true; alert("Recording started");
    } else { mediaRecorder.stop(); recording=false; alert("Recording stopped"); }
};
document.getElementById('toggleSidebar').onclick = ()=>{ sidebar.classList.toggle('active'); };
document.getElementById('sendChat').onclick = ()=>{
    const msg = document.getElementById('chatMessage').value;
    if(msg.trim()==='') return;
    const p=document.createElement('div'); p.textContent=`${userName}: ${msg}`; chatDiv.appendChild(p);
    document.getElementById('chatMessage').value='';
};

// --- Initialize ---
initLocalMedia().then(()=> setInterval(fetchSignals,3000));
</script>
</body>
</html>
