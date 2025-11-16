<?php
// student_meeting.php
// Single-file student meeting page (PHP + HTML + JS)
// - Uses same DB/signaling tables as lecturer side (meeting_signals, meeting_attendance, etc.)
// - WebRTC: lecturer -> students (lecturer sends offers); students answer.
// - Students can publish (camera/audio or screen) only after lecturer approval (signal).
// - Sidebar (participants + chat), responsive, attendance toggle.
// NOTE: Adjust DB table/column names if different in your schema.

session_start();
require_once '../config/db.php'; // expects $conn (mysqli)

// --- Guard ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Student';
$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id) {
    http_response_code(400);
    echo "Meeting ID required.";
    exit;
}

// --- Fetch meeting info (simple) ---
$stmt = $conn->prepare("SELECT id, title, scheduled_time, duration, lecturer_id FROM meetings WHERE id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$meeting) {
    http_response_code(404);
    echo "Meeting not found.";
    exit;
}
$lecturer_id = (int)$meeting['lecturer_id'];

// Ensure meeting_signals table exists (non-destructive attempt - mostly for dev)
$conn->query("
CREATE TABLE IF NOT EXISTS meeting_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    from_student_id INT NULL,
    from_lecturer_id INT NULL,
    to_student_id INT NULL,
    to_lecturer_id INT NULL,
    type VARCHAR(50) NOT NULL,
    data TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// --- AJAX actions (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // small helper
    function out($arr){ echo json_encode($arr); exit; }

    switch ($action) {
        // participants list (joined from meeting_attendance)
        case 'get_participants':
            $sql = "SELECT ma.id as attendance_id, COALESCE(s.name, ma.guest_name) AS name, COALESCE(s.reg_no,'') AS reg_no, ma.joined_at
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON s.id = ma.student_id
                    WHERE ma.meeting_id = ? AND (ma.status='joined' OR ma.status IS NULL)";
            $st = $conn->prepare($sql);
            $st->bind_param("i", $meeting_id);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            out($rows);
            break;

        // chat (persisted in chat table). If not present, you can change to session.
        case 'get_chat':
            $st = $conn->prepare("SELECT user_id, user_name, message, created_at FROM chat WHERE meeting_id = ? ORDER BY created_at ASC");
            $st->bind_param("i", $meeting_id);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            out($rows);
            break;

        case 'send_chat':
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') out(['success'=>false,'error'=>'empty']);
            $st = $conn->prepare("INSERT INTO chat (meeting_id, user_id, user_name, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $st->bind_param("iiss", $meeting_id, $student_id, $userName, $msg);
            $ok = $st->execute();
            $st->close();
            out(['success' => (bool)$ok]);
            break;

        // attendance (student join auto-logging or manual snapshot)
        case 'log_attendance_on_join':
            // Insert or update meeting_attendance for this student (or guest)
            // We'll upsert by student_id + meeting_id
            $st = $conn->prepare("SELECT id FROM meeting_attendance WHERE meeting_id = ? AND student_id = ? LIMIT 1");
            $st->bind_param("ii", $meeting_id, $student_id);
            $st->execute();
            $existing = $st->get_result()->fetch_assoc();
            $st->close();
            if ($existing) {
                $upd = $conn->prepare("UPDATE meeting_attendance SET joined_at = NOW(), status='joined', active=1 WHERE id = ?");
                $upd->bind_param("i", $existing['id']);
                $upd->execute();
                $upd->close();
                out(['success'=>true,'id'=>$existing['id']]);
            } else {
                $ins = $conn->prepare("INSERT INTO meeting_attendance (meeting_id, student_id, guest_name, reg_no, joined_at, duration_minutes, status, active) VALUES (?, ?, NULL, '', NOW(), 0, 'joined', 1)");
                $ins->bind_param("ii", $meeting_id, $student_id);
                $ins->execute();
                $id = $ins->insert_id;
                $ins->close();
                out(['success'=>true,'id'=>$id]);
            }
            break;

        // attendance listing for student UI (optional)
        case 'get_attendance':
            $st = $conn->prepare("SELECT id, COALESCE(s.name, ma.guest_name) AS name, COALESCE(s.reg_no, ma.reg_no) AS reg_no, ma.joined_at, ma.duration_minutes AS duration, ma.active, ma.marks
                                  FROM meeting_attendance ma LEFT JOIN students s ON s.id = ma.student_id
                                  WHERE ma.meeting_id = ? AND (ma.student_id = ? OR ma.student_id IS NULL)");
            $st->bind_param("ii", $meeting_id, $student_id);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            out($rows);
            break;

        // signaling: send signal (student -> lecturer)
        case 'send_signal':
            $to_lecturer = $lecturer_id; // send to meeting lecturer
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            $st = $conn->prepare("INSERT INTO meeting_signals (meeting_id, from_student_id, to_lecturer_id, type, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $st->bind_param("iiiss", $meeting_id, $student_id, $to_lecturer, $type, $data);
            $ok = $st->execute();
            $st->close();
            out(['success' => (bool)$ok]);
            break;

        // get signals directed to this student (from lecturer or other)
        case 'get_signals':
            $st = $conn->prepare("SELECT id, from_lecturer_id, type, data FROM meeting_signals WHERE meeting_id = ? AND to_student_id = ? ORDER BY id ASC");
            $st->bind_param("ii", $meeting_id, $student_id);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            // delete after fetch
            if (!empty($rows)) {
                $ids = array_column($rows, 'id');
                $in = implode(',', array_map('intval', $ids));
                $conn->query("DELETE FROM meeting_signals WHERE id IN ($in)");
            }
            out($rows);
            break;

        // clear-chat for UI convenience (not recommended in production)
        case 'clear_chat':
            $st = $conn->prepare("DELETE FROM chat WHERE meeting_id = ?");
            $st->bind_param("i", $meeting_id);
            $st->execute();
            $st->close();
            out(['success'=>true]);
            break;

        default:
            out(['success'=>false,'error'=>'unknown action']);
    }
    // exit handled in out()
}

// If not POST action - render page
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= htmlspecialchars($meeting['title']) ?> — Student</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#0b0b0d; --panel:#07161c; --muted:#7c8792; --accent:#06b6d4; --green:#10b981; --red:#ef4444; --orange:#f97316; --purple:#8b5cf6; --cyan:#22d3ee;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0;background:var(--bg);color:#fff;font-family:Inter,system-ui,Arial;-webkit-font-smoothing:antialiased}
.container{display:flex;height:100vh;overflow:hidden}
#mainArea{flex:1;display:flex;flex-direction:column;min-width:0}
#videoGrid{display:grid;gap:8px;padding:8px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-content:start;overflow:auto}
.tile{position:relative;background:#060607;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:120px}
.tile .meta{position:absolute;left:8px;top:8px;background:rgba(255,255,255,0.03);padding:6px 8px;border-radius:6px;font-size:13px}
.tile.highlight{box-shadow:0 0 12px var(--accent)}
video{width:100%;height:100%;object-fit:cover;display:block}

/* controls */
.controls{position:fixed;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:10px;padding:6px;z-index:50}
.ctrl{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;cursor:pointer;transition:transform .12s,opacity .12s;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01))}
.ctrl:hover{transform:translateY(-6px)}
.ctrl.cam{color:var(--green)}
.ctrl.mic{color:var(--cyan)}
.ctrl.screen{color:var(--purple)}
.ctrl.mute{color:var(--red)}
.ctrl.share{color:var(--orange)}
.ctrl.chat{color:var(--accent)}
.ctrl.record{color:var(--red)}
.ctrl.end{color:#7f1d1d}

/* sidebar */
.sidebar{width:340px;background:var(--panel);border-left:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;transition:transform .18s ease;z-index:40}
.sidebar.hidden{transform:translateX(100%)}
.sidebar header{padding:12px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
#participants{flex:1;overflow:auto;padding:8px}
.participant{display:flex;gap:8px;align-items:center;padding:8px;border-radius:8px;margin-bottom:8px;background:rgba(255,255,255,0.02)}
.avatar{width:40px;height:40px;border-radius:999px;background:#0b1220;display:flex;align-items:center;justify-content:center;font-weight:700;color:#cfeff6}

/* chat */
#chatBox{border-top:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;height:40%}
#chatLog{flex:1;padding:8px;overflow:auto;display:flex;flex-direction:column;gap:8px}
.bubble{max-width:85%;padding:8px;border-radius:10px;background:rgba(255,255,255,0.02)}
.bubble.me{align-self:flex-end;background:rgba(16,185,129,0.12);color:#e6fff2}
#chatInput{display:flex;gap:8px;padding:8px}
#chatInput textarea{flex:1;height:48px;border-radius:8px;padding:8px;background:#07161c;color:#fff;border:1px solid rgba(255,255,255,0.03);resize:none}

/* attendance panel (toggleable) */
#attendancePanel{position:fixed;right:50%;bottom:80px;transform:translateX(50%);width:90%;max-width:760px;background:var(--panel);border-radius:12px;padding:12px;display:none;z-index:60}
#attendancePanel table{width:100%;border-collapse:collapse;font-size:14px}
#attendancePanel th,#attendancePanel td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.03);text-align:left}

/* responsive tweaks */
@media (max-width:900px){
  .sidebar{position:fixed;right:0;top:0;bottom:0;width:80%;max-width:340px}
  #videoGrid{grid-template-columns:repeat(auto-fit,minmax(140px,1fr))}
  .controls{bottom:10px}
}
@media (max-width:480px){
  .ctrl{width:48px;height:48px;font-size:18px}
  #videoGrid{gap:6px;padding:6px}
  .tile{min-height:88px}
}
.smallBtn{padding:6px 10px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.02);cursor:pointer;color:#dff6fb}
</style>
</head>
<body>
<div class="container">
  <div id="mainArea">
    <div id="videoGrid" aria-live="polite" role="application">
      <!-- Lecturer tile (primary) -->
      <div class="tile" id="lecturerTile">
        <div class="meta" id="lecturerMeta">Lecturer</div>
        <video id="lecturerVideo" autoplay playsinline></video>
      </div>

      <!-- Local student preview -->
      <div class="tile" id="localTile">
        <div class="meta">You — <?= htmlspecialchars($userName) ?></div>
        <video id="localVideo" autoplay muted playsinline></video>
      </div>

      <!-- Other students will be appended here as tiles -->
    </div>
  </div>

  <div id="sidebar" class="sidebar">
    <header>
      <div>Participants</div>
      <div style="display:flex;gap:8px">
        <button id="refreshBtn" class="smallBtn">Refresh</button>
        <button id="takeInlineBtn" class="smallBtn">Take</button>
      </div>
    </header>

    <div id="participants"></div>

    <div id="chatBox">
      <div id="chatLog" aria-live="polite"></div>
      <div id="chatInput">
        <textarea id="chatMsg" placeholder="Write a message..."></textarea>
        <div style="display:flex;flex-direction:column;gap:6px">
          <button id="sendChat" class="smallBtn">Send</button>
          <button id="clearChat" class="smallBtn">Clear</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Controls -->
<div class="controls" role="toolbar" aria-label="Meeting controls">
  <div id="btnCam" class="ctrl cam" title="Toggle camera" data-state="on"><i class="fa fa-video"></i></div>
  <div id="btnMic" class="ctrl mic" title="Toggle mic" data-state="on"><i class="fa fa-microphone"></i></div>
  <div id="btnShare" class="ctrl screen" title="Request screen share"><i class="fa fa-desktop"></i></div>
  <div id="btnRequestPublish" class="ctrl share" title="Request to publish camera & mic (ask lecturer)"><i class="fa fa-upload"></i></div>
  <div id="btnAttendance" class="ctrl chat" title="Toggle attendance panel"><i class="fa fa-list-check"></i></div>
  <div id="btnToggleSidebar" class="ctrl chat" title="Toggle participants & chat"><i class="fa fa-comments"></i></div>
</div>

<!-- Attendance panel (toggle) -->
<div id="attendancePanel" role="dialog" aria-modal="true">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <strong>Attendance — Meeting #<?= (int)$meeting_id ?></strong>
    <button id="closeAttendancePanel" class="smallBtn">Close</button>
  </div>
  <div style="margin-top:8px">
    <button id="refreshAttendanceBtn" class="smallBtn">Refresh</button>
  </div>
  <table id="attendanceTable" style="margin-top:8px">
    <thead><tr><th>#</th><th>Name</th><th>Reg No</th><th>Joined</th><th>Duration</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<script>
/* ====== Config/state ====== */
const meetingId = <?= (int)$meeting_id ?>;
const studentId = <?= (int)$student_id ?>;
const userName = <?= json_encode($userName) ?>;
const lecturerId = <?= (int)$lecturer_id ?>;

let localStream = null;
let published = false; // whether student is publishing (camera+mic)
let pcForLecturer = null; // RTCPeerConnection for lecturer -> student (lecturer sending)
let pcPublish = null; // RTCPeerConnection used when student is allowed to publish (student -> lecturer)
let pendingCandidates = []; // ICE candidates received before pc exists

/* Helper: POST to same file and return JSON */
async function postAction(data, isForm=false){
  const opts = { method:'POST' };
  if (isForm) {
    opts.body = data;
  } else {
    opts.headers = {'Content-Type':'application/x-www-form-urlencoded'};
    opts.body = new URLSearchParams(data);
  }
  const url = location.pathname + '?meeting_id=' + meetingId;
  const res = await fetch(url, opts);
  if (!res.ok) throw new Error('Server error ' + res.status);
  return res.json();
}

/* ====== UI helpers ====== */
function el(id){ return document.getElementById(id); }
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* Toggle camera icon (Option A) */
el('btnCam').addEventListener('click', () => {
  const btn = el('btnCam');
  const state = btn.getAttribute('data-state') === 'on';
  if (!localStream) return;
  localStream.getVideoTracks().forEach(t => t.enabled = !state);
  const icon = btn.querySelector('i');
  if (state) { icon.className = 'fa fa-video-slash'; btn.setAttribute('data-state','off'); }
  else { icon.className = 'fa fa-video'; btn.setAttribute('data-state','on'); }
});

/* Toggle mic icon */
el('btnMic').addEventListener('click', () => {
  const btn = el('btnMic');
  const state = btn.getAttribute('data-state') === 'on';
  if (!localStream) return;
  localStream.getAudioTracks().forEach(t => t.enabled = !state);
  const icon = btn.querySelector('i');
  if (state) { icon.className = 'fa fa-microphone-slash'; btn.setAttribute('data-state','off'); }
  else { icon.className = 'fa fa-microphone'; btn.setAttribute('data-state','on'); }
});

/* Toggle sidebar */
el('btnToggleSidebar').addEventListener('click', () => {
  const sb = el('sidebar');
  sb.classList.toggle('hidden');
});

/* Attendance panel toggle */
el('btnAttendance').addEventListener('click', async () => {
  const p = el('attendancePanel');
  if (p.style.display === 'block') { p.style.display = 'none'; }
  else { p.style.display = 'block'; await refreshAttendance(); }
});
el('closeAttendancePanel').addEventListener('click', ()=> el('attendancePanel').style.display='none');

/* Request to publish (ask lecturer) */
el('btnRequestPublish').addEventListener('click', async () => {
  if (published) { alert('You are already publishing'); return; }
  // send signal to lecturer requesting publish permission
  try {
    await postAction({ action:'send_signal', type:'request-publish', data: JSON.stringify({ student_id: studentId, name: userName }) });
    alert('Publish request sent to lecturer. Wait for approval.');
  } catch (e) { console.error(e); alert('Failed to send request'); }
});

/* Request screen share (student asks lecturer to approve) */
el('btnShare').addEventListener('click', async () => {
  try {
    await postAction({ action:'send_signal', type:'request-screen', data: JSON.stringify({ student_id: studentId, name: userName }) });
    alert('Screen-share request sent to lecturer. Wait for approval.');
  } catch (e) { console.error(e); alert('Failed to send request'); }
});

/* Refresh participants */
el('refreshBtn').addEventListener('click', refreshParticipants);
el('takeInlineBtn').addEventListener('click', async ()=> {
  try {
    const res = await postAction({ action:'take_attendance' });
    alert('Attendance snapshot: ' + (res.inserted || 0));
  } catch (e) { console.error(e); alert('Failed'); }
});

/* Chat send/clear */
el('sendChat').addEventListener('click', async () => {
  const t = el('chatMsg'); const txt = t.value.trim(); if (!txt) return;
  try { await postAction({ action:'send_chat', message: txt }); t.value=''; pollChat(); } catch(e){console.error(e)}
});
el('clearChat').addEventListener('click', async ()=> {
  try { await postAction({ action:'clear_chat' }); pollChat(); } catch(e){console.error(e)}
});

/* ====== Local media (student preview only until allowed) ====== */
async function initLocalPreview(){
  try {
    localStream = await navigator.mediaDevices.getUserMedia({ video: { width: 1280 }, audio: true });
    el('localVideo').srcObject = localStream;
    // by default, don't publish tracks to lecturer until approved (published=false)
  } catch (err) {
    console.warn('Media permissions denied or not available', err);
    // show placeholder
  }
}

/* ====== Attendance: auto-log on join ====== */
async function logAttendanceOnJoin(){
  try {
    await postAction({ action:'log_attendance_on_join' });
  } catch (e) { console.error('attendance log failed', e); }
}

/* ====== Participants & Chat Polling ====== */
async function refreshParticipants(){
  try {
    const arr = await postAction({ action:'get_participants' });
    const container = el('participants');
    container.innerHTML = '';
    (arr||[]).forEach(p => {
      const d = document.createElement('div'); d.className='participant';
      d.innerHTML = `<div class="avatar">${escapeHtml((p.name||'U').charAt(0).toUpperCase())}</div><div><strong>${escapeHtml(p.name)}</strong><br><small>${escapeHtml(p.reg_no||'')}</small></div>`;
      container.appendChild(d);
      // ensure student tiles exist for others (we show thumbnails for all students)
      ensureStudentTile(p);
    });
  } catch (e) { console.error(e); }
}

async function pollChat(){
  try {
    const arr = await postAction({ action:'get_chat' });
    const log = el('chatLog'); log.innerHTML = '';
    (arr||[]).forEach(m => {
      const b = document.createElement('div'); b.className = 'bubble' + (parseInt(m.user_id) === studentId ? ' me' : '');
      b.innerHTML = `<strong>${escapeHtml(m.user_name)}</strong><div style="margin-top:4px">${escapeHtml(m.message)}</div>`;
      log.appendChild(b);
    });
    log.scrollTop = log.scrollHeight;
  } catch (e) { console.error(e); }
}

/* ====== Attendance UI refresh ====== */
async function refreshAttendance(){
  try {
    const res = await postAction({ action:'get_attendance' });
    const tbody = document.querySelector('#attendanceTable tbody');
    tbody.innerHTML = '';
    (res||[]).forEach((r,i) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${i+1}</td><td>${escapeHtml(r.name)}</td><td>${escapeHtml(r.reg_no)}</td><td>${r.joined_at||''}</td><td>${r.duration||''}</td>`;
      tbody.appendChild(tr);
    });
  } catch (e) { console.error(e); }
}

/* ====== WebRTC Signaling handling ======
   - Students poll for signals where to_student_id = this student
   - Types we handle:
     - 'offer' (from lecturer): contains SDP offer => student creates pc (if not exists), setRemote, createAnswer, send_answer
     - 'candidate' (from lecturer): addIceCandidate to pcForLecturer
     - 'allow-publish' or 'allow-screen' (from lecturer): means student should create offer to lecturer to publish localStream or screen
     - 'end' etc could be implemented
   - Student -> lecturer signals:
     - 'answer' — student's SDP answer to lecturer's offer (when student created offer) OR answer to lecturer's offer? (we implement consistent flows)
     - 'candidate' — ICE candidates generated locally to send to lecturer
     - 'publish-offer' — when student requests to publish, student creates offer and sends it (type: 'offer') with from_student_id set (we use generic send_signal)
*/

/* Helper: send signal to lecturer (student -> lecturer) */
async function sendSignalToLecturer(type, dataObj){
  try {
    return await postAction({ action:'send_signal', type: type, data: JSON.stringify(dataObj) });
  } catch (e) { console.error('sendSignal error', e); return null; }
}

/* Poll signals directed to this student */
async function pollSignals(){
  try {
    const rows = await postAction({ action:'get_signals' });
    if (!Array.isArray(rows) || rows.length === 0) return;
    for (const sig of rows) {
      handleSignal(sig);
    }
  } catch (e) { console.error('pollSignals error', e); }
}

/* Handle incoming signal directed to student */
async function handleSignal(sig){
  const type = sig.type;
  const data = (()=>{ try{return JSON.parse(sig.data);}catch(e){return sig.data;} })();

  if (type === 'offer') {
    // Lecturer sent an offer (lecturer -> student). Student should create pcForLecturer if not exists, setRemoteDescription(offer), createAnswer and send 'answer' back to lecturer.
    await handleLecturerOffer(sig, data);
    return;
  }

  if (type === 'candidate') {
    if (pcForLecturer) {
      try { await pcForLecturer.addIceCandidate(data.candidate); } catch(e){ console.warn('addIceCandidate failed',e); }
    } else {
      pendingCandidates.push(data.candidate);
    }
    return;
  }

  if (type === 'allow-publish' || type === 'allow-screen') {
    // Lecturer approved student's publish or screen request.
    // For 'allow-publish' we will create pcPublish (student -> lecturer) using localStream and send offer to lecturer.
    // For 'allow-screen' we first getDisplayMedia then create offer.
    if (type === 'allow-publish') {
      await startPublishingLocalStream(false); // false => not screen, normal camera+mic
    } else {
      await startPublishingLocalStream(true); // true => screen
    }
    return;
  }

  if (type === 'kick') {
    alert('You have been removed from the meeting by lecturer.');
    // optionally redirect away
    return;
  }

  // other types...
}

/* Handle offer from lecturer (lecturer -> student) */
async function handleLecturerOffer(sig, data){
  try {
    // create RTCPeerConnection to receive lecturer tracks if not exists
    if (!pcForLecturer) {
      pcForLecturer = createPeerConnection('lecturer');
      // when lecturer tracks arrive, attach to lecturerVideo
      pcForLecturer.ontrack = (evt) => {
        console.log('lecturer track', evt.streams);
        el('lecturerVideo').srcObject = evt.streams[0];
      };
    }
    const offer = data.sdp;
    await pcForLecturer.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp: offer }));
    const answer = await pcForLecturer.createAnswer();
    await pcForLecturer.setLocalDescription(answer);
    // send answer back to lecturer
    await sendSignalToLecturer('answer', { sdp: pcForLecturer.localDescription.sdp, to_student_id: studentId });
    // add any pending candidates
    if (pendingCandidates.length) {
      for (const c of pendingCandidates) {
        try { await pcForLecturer.addIceCandidate(c); } catch(e){ console.warn(e); }
      }
      pendingCandidates = [];
    }
  } catch (e) {
    console.error('handleLecturerOffer error', e);
  }
}

/* Create RTCPeerConnection with common handlers */
function createPeerConnection(kind){
  const pc = new RTCPeerConnection({
    iceServers: [
      { urls: 'stun:stun.l.google.com:19302' }
    ]
  });

  pc.onicecandidate = (evt) => {
    if (!evt.candidate) return;
    // send candidate to lecturer
    sendSignalToLecturer('candidate', { candidate: evt.candidate });
  };

  pc.onconnectionstatechange = () => {
    console.log('pc state', kind, pc.connectionState);
  };

  return pc;
}

/* Start publishing local stream (camera+mic) or screen (if screen=true)
   This will create pcPublish and make an offer to lecturer (student->lecturer flow).
*/
async function startPublishingLocalStream(screen=false){
  if (published) { console.log('already publishing'); return; }
  try {
    let streamToSend = null;
    if (screen) {
      streamToSend = await navigator.mediaDevices.getDisplayMedia({ video:true });
      // optionally also include microphone audio (ask for audio separately)
      try {
        const audio = await navigator.mediaDevices.getUserMedia({ audio:true });
        audio.getAudioTracks().forEach(t => streamToSend.addTrack(t));
      } catch(e){ console.warn('audio for screen share not available', e); }
    } else {
      if (!localStream) {
        localStream = await navigator.mediaDevices.getUserMedia({ video:true, audio:true });
        el('localVideo').srcObject = localStream;
      }
      streamToSend = localStream;
    }

    pcPublish = createPeerConnection('publish');
    // add tracks
    streamToSend.getTracks().forEach(track => pcPublish.addTrack(track, streamToSend));

    // on icecandidate handled in createPeerConnection

    // create offer and send to lecturer
    const offer = await pcPublish.createOffer();
    await pcPublish.setLocalDescription(offer);
    await sendSignalToLecturer('offer', { sdp: pcPublish.localDescription.sdp, from_student_id: studentId });

    // When remote answer will be delivered, lecturer will send 'answer' signal directed to this student (to_student_id) — lecturer side must implement that.
    // We'll listen for candidates/answers via pollSignals loop.
    published = true;

    // when stream stops (for screen) release publish state
    streamToSend.getVideoTracks().forEach(t => {
      t.onended = () => {
        // inform lecturer we're stopping
        published = false;
        try { pcPublish && pcPublish.close(); } catch(e){}
        pcPublish = null;
        sendSignalToLecturer('publish-stopped', { student_id: studentId });
      };
    });

  } catch (e) {
    console.error('startPublishingLocalStream error', e);
    alert('Failed to publish: ' + (e.message || e));
  }
}

/* Poll loop for signals */
setInterval(pollSignals, 1500);

/* Poll participants & chat */
setInterval(refreshParticipants, 5000);
setInterval(pollChat, 2500);

/* Init on load */
(async function init(){
  await initLocalPreview();
  await logAttendanceOnJoin();
  await refreshParticipants();
  await pollChat();
  // ensure instructor video plays muted/unmuted by default as needed
  el('lecturerVideo').muted = false;
})();

/* Ensure there is a student tile for a participant (thumbnail) */
function ensureStudentTile(p){
  // skip if this is current student; their local tile already exists
  if (p.student_id && parseInt(p.student_id) === studentId) return;
  // check if tile exists by attendance_id
  if (document.getElementById('stu_' + (p.attendance_id || p.reg_no || p.name).toString())) return;
  const tile = document.createElement('div'); tile.className='tile'; tile.id = 'stu_' + (p.attendance_id || ('s_' + Math.random().toString(36).slice(2)));
  tile.innerHTML = `<div class="meta">${escapeHtml(p.name)}</div><video autoplay playsinline></video>`;
  document.getElementById('videoGrid').appendChild(tile);
  // student video frames will be filled in when lecturer sends their tracks (if allowed)
}

/* Provide graceful unload: optional inform server student left (not required) */
window.addEventListener('beforeunload', ()=> {
  // You can send synchronous navigator.sendBeacon if desired to mark left
  try {
    navigator.sendBeacon(location.pathname + '?meeting_id=' + meetingId, new URLSearchParams({ action:'log_leave' }));
  } catch(e){}
});

/* Small UX: if sidebar area should be hidden on small screens by default */
if (window.innerWidth < 800) {
  document.getElementById('sidebar').classList.add('hidden');
}
</script>
</body>
</html>
