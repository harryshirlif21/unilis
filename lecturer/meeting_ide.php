<?php
session_start();
require_once '../config/db.php';

// Security check: only lecturers
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    die("Access denied. Only lecturers can access this page.");
}

// Meeting lookup
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
    <style>
        body { margin:0; background:#111; color:#fff; font-family:sans-serif; display:flex; }
        #main { flex:1; display:flex; flex-direction:column; }
        #videos { display:flex; flex-wrap:wrap; gap:5px; padding:5px; flex:1; overflow:auto; }
        video { width:45%; height:300px; background:#333; border-radius:5px; }
        #localVideo { border:2px solid #0f0; }
        #controls { display:flex; gap:10px; padding:5px; }
        button { padding:5px 10px; border:none; border-radius:3px; cursor:pointer; background:#222; color:#fff; }
        button:hover { opacity:0.8; }
        #sidebar { width:300px; background:#222; display:flex; flex-direction:column; }
        #participants, #chat { flex:1; overflow:auto; padding:5px; border-bottom:1px solid #444; }
        #chatInput { display:flex; }
        #chatInput input { flex:1; padding:5px; border:none; border-radius:3px; margin-right:5px; }
        #chatInput button { padding:5px; border:none; border-radius:3px; cursor:pointer; }
    </style>
</head>
<body>
    <div id="main">
        <h2><?= htmlspecialchars($meeting['title']) ?> - Lecturer View</h2>
        <div id="videos">
            <video id="localVideo" autoplay muted></video>
        </div>
        <div id="controls">
            <button id="toggleVideo">Toggle Video</button>
            <button id="toggleAudio">Toggle Audio</button>
            <button id="presentScreen">Share Screen</button>
            <button id="muteAll">Mute All</button>
            <button id="record">Start Recording</button>
            <button id="endMeeting">End Meeting</button>
            <button id="showParticipants">Participants</button>
        </div>
    </div>
    <div id="sidebar">
        <div id="participants"></div>
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
let mediaRecorder;
let recordedChunks = [];
let recording = false;

// --- 1. Initialize local media ---
async function initLocalMedia() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
        document.getElementById('localVideo').srcObject = localStream;
    } catch(e) {
        alert("Failed to access camera/microphone: "+e.message);
    }
}

// --- 2. Signaling ---
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

// --- 3. Handle signals ---
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

// --- 4. Create peer ---
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

// --- 5. Controls ---
document.getElementById('toggleVideo').onclick = () => {
    localStream.getVideoTracks().forEach(t=>t.enabled = !t.enabled);
};
document.getElementById('toggleAudio').onclick = () => {
    localStream.getAudioTracks().forEach(t=>t.enabled = !t.enabled);
};
document.getElementById('presentScreen').onclick = async () => {
    try{
        const screenStream = await navigator.mediaDevices.getDisplayMedia({video:true});
        const track=screenStream.getVideoTracks()[0];
        Object.values(peers).forEach(pc=>{
            const sender=pc.getSenders().find(s=>s.track.kind==='video');
            if(sender) sender.replaceTrack(track);
        });
        document.getElementById('localVideo').srcObject=screenStream;
        track.onended = ()=> initLocalMedia();
    } catch(e){ alert('Screen share failed: '+e.message); }
};
document.getElementById('muteAll').onclick = () => {
    Object.values(peers).forEach(pc=>{
        pc.getSenders().forEach(s=>{ if(s.track.kind==='audio') s.track.enabled=false; });
    });
};
document.getElementById('endMeeting').onclick = () => { location.reload(); };

// --- Recording ---
document.getElementById('record').onclick = ()=>{
    if(!recording){
        mediaRecorder = new MediaRecorder(localStream);
        mediaRecorder.ondataavailable = e=>{ recordedChunks.push(e.data); }
        mediaRecorder.onstop = ()=>{
            const blob=new Blob(recordedChunks,{type:'video/webm'});
            const url=URL.createObjectURL(blob);
            const a=document.createElement('a');
            a.href=url; a.download='recording_'+Date.now()+'.webm'; a.click();
            recordedChunks=[];
        };
        mediaRecorder.start();
        recording=true;
        alert("Recording started");
    } else {
        mediaRecorder.stop();
        recording=false;
        alert("Recording stopped");
    }
};

// --- Participants ---
document.getElementById('showParticipants').onclick = ()=>{
    participantsDiv.innerHTML = `<strong>Participants:</strong><br>` +
        Object.keys(peers).map(id=>`User ${id}`).join('<br>');
};

// --- Chat ---
document.getElementById('sendChat').onclick = ()=>{
    const msg = document.getElementById('chatMessage').value;
    if(msg.trim()==='') return;
    const p=document.createElement('div');
    p.textContent=`${userName}: ${msg}`;
    chatDiv.appendChild(p);
    document.getElementById('chatMessage').value='';
};

// --- Initialize ---
initLocalMedia().then(()=> setInterval(fetchSignals,3000));
</script>
</body>
</html>
