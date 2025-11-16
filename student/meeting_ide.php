<?php
session_start();
require_once '../config/db.php'; // ensure $conn is your mysqli connection

// Student-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Student';

$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id) {
    http_response_code(400);
    echo "Meeting ID is required.";
    exit;
}

// Fetch meeting basic info
$stmt = $conn->prepare("SELECT id, title, scheduled_time, duration, ended FROM meetings WHERE id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$meeting) {
    http_response_code(404);
    echo "Meeting not found.";
    exit;
}

// Prepare session chat storage per meeting
if (!isset($_SESSION['meeting_chat'])) $_SESSION['meeting_chat'] = [];
if (!isset($_SESSION['meeting_chat'][$meeting_id])) $_SESSION['meeting_chat'][$meeting_id] = [];

/**
 * AJAX endpoints handled here. All responses JSON.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // helper
    function out($data) {
        echo json_encode($data);
        exit;
    }

    switch ($action) {
        // Return currently joined participants (from meeting_attendance where status=joined)
        case 'get_participants':
            $sql = "SELECT ma.id, COALESCE(s.name, ma.guest_name) AS name, COALESCE(s.reg_no, ma.reg_no) AS reg_no, ma.joined_at
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON s.id = ma.student_id
                    WHERE ma.meeting_id = ? AND (ma.status='joined' OR ma.status IS NULL)
                    ORDER BY ma.joined_at ASC";
            $st = $conn->prepare($sql);
            $st->bind_param("i", $meeting_id);
            $st->execute();
            $res = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            out($res);
            break;

        // Session-backed chat
        case 'get_chat':
            $ch = $_SESSION['meeting_chat'][$meeting_id] ?? [];
            out($ch);
            break;

        case 'send_chat':
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') out(['success' => false, 'error' => 'Empty message']);
            $entry = [
                'user_id' => $student_id,
                'user_name' => $userName,
                'message' => htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $_SESSION['meeting_chat'][$meeting_id][] = $entry;
            if (count($_SESSION['meeting_chat'][$meeting_id]) > 400) {
                $_SESSION['meeting_chat'][$meeting_id] = array_slice($_SESSION['meeting_chat'][$meeting_id], -400);
            }
            out(['success' => true, 'entry' => $entry]);
            break;

        // Log student's attendance (on join)
        case 'log_attendance':
            // Insert or update meeting_attendance row for this student
            $check = $conn->prepare("SELECT id FROM meeting_attendance WHERE meeting_id = ? AND student_id = ? LIMIT 1");
            $check->bind_param("ii", $meeting_id, $student_id);
            $check->execute();
            $found = $check->get_result()->fetch_assoc();
            $check->close();

            if ($found) {
                $upd = $conn->prepare("UPDATE meeting_attendance SET status='joined', joined_at = NOW(), active = 1 WHERE id = ?");
                $upd->bind_param("i", $found['id']);
                $ok = $upd->execute();
                $upd->close();
            } else {
                $ins = $conn->prepare("INSERT INTO meeting_attendance (meeting_id, student_id, guest_name, reg_no, joined_at, duration_minutes, status, active) VALUES (?, ?, NULL, '', NOW(), 0, 'joined', 1)");
                $ins->bind_param("ii", $meeting_id, $student_id);
                $ok = $ins->execute();
                $ins->close();
            }
            out(['success' => (bool)$ok]);
            break;

        // Fetch attendance for student view
        case 'get_my_attendance':
            $st = $conn->prepare("SELECT id, joined_at, duration_minutes AS duration, status, active FROM meeting_attendance WHERE meeting_id = ? AND student_id = ? LIMIT 1");
            $st->bind_param("ii", $meeting_id, $student_id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            out($row ?: []);
            break;

        // send signaling message to lecturer or everyone; from_student_id populated
        case 'send_signal':
            $to_lecturer = isset($_POST['to_lecturer_id']) ? (int)$_POST['to_lecturer_id'] : null; // optional
            $to_student = isset($_POST['to_student_id']) ? (int)$_POST['to_student_id'] : null;
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            $st = $conn->prepare("INSERT INTO meeting_signals (meeting_id, from_student_id, from_lecturer_id, to_student_id, to_lecturer_id, type, data, created_at) VALUES (?, ?, NULL, ?, ?, ?, ?, NOW())");
            // if sending to lecturer, put to_lecturer_id (assume lecturer id known? pass as param). We'll place to_student_id/to_lecturer_id as provided.
            $st->bind_param("iiisss", $meeting_id, $student_id, $to_student, $to_lecturer, $type, $data);
            $ok = $st->execute();
            $st->close();
            out(['success' => (bool)$ok]);
            break;

        // fetch signals directed to this student (or broadcast)
        case 'get_signals':
            $st = $conn->prepare("SELECT id, from_student_id, from_lecturer_id, to_student_id, to_lecturer_id, type, data FROM meeting_signals WHERE meeting_id = ? AND (to_student_id = ? OR to_student_id IS NULL OR to_student_id = 0) ORDER BY id ASC");
            $st->bind_param("ii", $meeting_id, $student_id);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            // delete fetched signals for this meeting and those returned (so they are consumed)
            if (!empty($rows)) {
                $ids = array_column($rows, 'id');
                $in = implode(',', array_map('intval', $ids));
                $conn->query("DELETE FROM meeting_signals WHERE id IN ($in)");
            }
            out($rows);
            break;

        // allow clearing session chat (optional)
        case 'clear_chat':
            $_SESSION['meeting_chat'][$meeting_id] = [];
            out(['success' => true]);
            break;

        default:
            out(['success' => false, 'error' => 'Unknown action']);
    }
}

// If not AJAX POST, render HTML page below
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($meeting['title']) ?> — Student</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#0b0b0d; --accent:#06b6d4; --green:#10b981; --red:#ef4444; --orange:#f97316; --purple:#8b5cf6; --cyan:#22d3ee;
}
html,body{margin:0;height:100%;background:var(--bg);color:#fff;font-family:Inter,system-ui,Arial;overflow:hidden}
#videos{display:grid;grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));grid-auto-rows:1fr;gap:8px;padding:8px;height:100vh;width:calc(100% - 340px);float:left;}
.tile{position:relative;background:#060607;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center}
video{width:100%;height:100%;object-fit:cover;display:block}
.tile .meta{position:absolute;left:8px;top:8px;background:rgba(255,255,255,0.04);padding:6px 8px;border-radius:6px;font-size:13px}
.tile.highlight{box-shadow:0 0 15px var(--accent)}
#controls{position:fixed;bottom:18px;left:50%;transform:translateX(-50%);display:flex;gap:12px;z-index:120}
.control{width:56px;height:56px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;cursor:pointer;transition:transform .12s,opacity .12s}
.control:hover{transform:translateY(-4px)}
.control.cam{color:var(--green)}
.control.mic{color:var(--cyan)}
.control.screen{color:var(--purple)}
.control.request{color:#ffd166}
.control.record{color:var(--red)}
.control.leave{color:#7f1d1d}
.control.attendance{color:var(--orange)}
.control.chat{color:var(--accent)}
.control.disabled{opacity:0.45;pointer-events:none}

/* sidebar (participants + chat) */
#sidebar{position:fixed;right:0;top:0;bottom:0;width:340px;background:#071014;border-left:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;z-index:110;transition:transform .2s}
#sidebar.hidden{transform:translateX(100%)}
#sidebar header{padding:16px;font-weight:700;font-size:16px;color:#dff6fb;display:flex;justify-content:space-between;align-items:center}
#participantsList{padding:12px;overflow:auto;flex:1}
.participant{display:flex;gap:10px;align-items:center;padding:8px;border-radius:10px;margin-bottom:6px;background:linear-gradient(180deg, rgba(255,255,255,0.01), transparent)}
.avatar{width:42px;height:42px;border-radius:999px;background:#0b1220;display:flex;align-items:center;justify-content:center;font-weight:700;color:#cfeff6}

/* chat */
#chatBox{height:44%;border-top:1px solid rgba(255,255,255,0.02);display:flex;flex-direction:column}
#chatLog{flex:1;padding:12px;overflow:auto;display:flex;flex-direction:column;gap:8px}
.bubble{max-width:85%;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.02)}
.bubble.me{align-self:flex-end;background:rgba(16,185,129,0.12)}
#chatInput{display:flex;gap:8px;padding:12px;border-top:1px solid rgba(255,255,255,0.02)}
#chatInput textarea{flex:1;height:56px;border-radius:12px;padding:12px;background:#07161c;color:#fff;border:1px solid rgba(255,255,255,0.03)}

/* attendance panel (toggleable) */
#attendancePanel{position:fixed;right:360px;bottom:120px;width:360px;max-height:60vh;overflow:auto;background:#07161c;border-radius:12px;z-index:210;padding:12px;display:none;box-shadow:0 20px 60px rgba(0,0,0,0.6)}
#attendancePanel header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:8px}
.att-row{display:flex;justify-content:space-between;gap:8px;padding:8px;border-radius:8px;background:rgba(255,255,255,0.02);margin-bottom:6px}

/* small helpers */
.smallBtn{padding:6px 8px;border-radius:6px;background:#0b1220;color:#dff6fb;border:1px solid rgba(255,255,255,0.03);cursor:pointer}
.status-request{font-size:12px;color:#ffd166;margin-top:6px}
</style>
</head>
<body>

<div id="videos" aria-live="polite" role="application">
  <div class="tile" id="localVideoTile">
    <div class="meta">You — <?= htmlspecialchars($userName) ?></div>
    <video id="localVideo" autoplay muted playsinline></video>
  </div>
</div>

<div id="controls" role="toolbar" aria-label="Meeting controls">
  <div class="control cam" id="toggleCam" title="Toggle camera" data-state="on"><i class="fa fa-video"></i></div>
  <div class="control mic" id="toggleMic" title="Toggle mic" data-state="on"><i class="fa fa-microphone"></i></div>
  <!-- Request screen-sharing (Google meet-style approval) -->
  <div class="control request" id="requestScreen" title="Request screen share"><i class="fa fa-display"></i></div>
  <div class="control record" id="recordBtn" title="Record (local)"><i class="fa fa-circle"></i></div>
  <div class="control leave" id="leaveBtn" title="Leave meeting"><i class="fa fa-sign-out-alt"></i></div>
  <div class="control attendance" id="attendanceToggle" title="Toggle attendance panel"><i class="fa fa-list-check"></i></div>
  <div class="control chat" id="toggleSidebar" title="Toggle sidebar"><i class="fa fa-comments"></i></div>
</div>

<!-- right sidebar -->
<div id="sidebar">
  <header>
    <div>Participants</div>
    <div style="display:flex;gap:8px;align-items:center">
      <button id="refreshParts" class="smallBtn">Refresh</button>
      <button id="logMe" class="smallBtn">Log attendance</button>
    </div>
  </header>

  <div id="participantsList" role="list"></div>

  <div id="chatBox">
    <div id="chatLog" aria-live="polite"></div>
    <div id="chatInput">
      <textarea id="chatMsg" placeholder="Write a message..."></textarea>
      <div style="display:flex;flex-direction:column;gap:8px">
        <button id="sendChatBtn" class="smallBtn">Send</button>
        <button id="clearChatBtn" class="smallBtn">Clear</button>
      </div>
    </div>
  </div>
</div>

<!-- attendance panel (student sees own attendance log & quick info) -->
<div id="attendancePanel" aria-hidden="true">
  <header><strong>My Attendance</strong><button id="closeAttendancePanel" class="smallBtn">Close</button></header>
  <div id="myAttendanceArea"><em>Loading…</em></div>
</div>

<!-- status area to show request status -->
<div id="statusArea" style="position:fixed;left:18px;bottom:18px;z-index:220"></div>

<script>
const meetingId = <?= (int)$meeting['id'] ?>;
const studentId = <?= (int)$student_id ?>;
const userNameStr = <?= json_encode($userName) ?>;

let localStream = null;
let mediaRecorder = null;
let recordedChunks = [];
let recording = false;

async function post(data, isForm=false) {
  const opts = { method: 'POST' };
  if (isForm) opts.body = data;
  else opts.body = new URLSearchParams(data);
  const resp = await fetch(location.pathname + '?meeting_id=' + meetingId, opts);
  if (!resp.ok) throw new Error('Server ' + resp.status);
  return resp.json();
}

/* -------------------------
   Local media + toggles (Option A)
   ------------------------- */
async function initLocalMedia() {
  try {
    localStream = await navigator.mediaDevices.getUserMedia({ video:{ width:1280 }, audio:true });
    document.getElementById('localVideo').srcObject = localStream;
  } catch (e) {
    alert('Camera/mic error: ' + e.message);
  }
}

// Camera toggle — change icon to fa-video-slash when off
document.getElementById('toggleCam').addEventListener('click', () => {
  const btn = document.getElementById('toggleCam');
  const icon = btn.querySelector('i');
  const isOn = btn.getAttribute('data-state') === 'on';
  if (!localStream) return;
  localStream.getVideoTracks().forEach(t => t.enabled = !isOn);
  if (isOn) { icon.className = 'fa fa-video-slash'; btn.setAttribute('data-state', 'off'); }
  else { icon.className = 'fa fa-video'; btn.setAttribute('data-state', 'on'); }
});

// Mic toggle — change icon to fa-microphone-slash when off
document.getElementById('toggleMic').addEventListener('click', () => {
  const btn = document.getElementById('toggleMic');
  const icon = btn.querySelector('i');
  const isOn = btn.getAttribute('data-state') === 'on';
  if (!localStream) return;
  localStream.getAudioTracks().forEach(t => t.enabled = !isOn);
  if (isOn) { icon.className = 'fa fa-microphone-slash'; btn.setAttribute('data-state', 'off'); }
  else { icon.className = 'fa fa-microphone'; btn.setAttribute('data-state', 'on'); }
});

/* -------------------------
   Sidebar toggle (hide/show)
   ------------------------- */
const sidebar = document.getElementById('sidebar');
document.getElementById('toggleSidebar').addEventListener('click', () => {
  const hidden = sidebar.classList.toggle('hidden');
  document.getElementById('toggleSidebar').style.opacity = hidden ? 0.6 : 1;
});

/* -------------------------
   Attendance panel toggle
   ------------------------- */
const attendancePanel = document.getElementById('attendancePanel');
document.getElementById('attendanceToggle').addEventListener('click', async () => {
  const visible = attendancePanel.style.display === 'block';
  if (visible) {
    attendancePanel.style.display = 'none';
    attendancePanel.setAttribute('aria-hidden', 'true');
  } else {
    attendancePanel.style.display = 'block';
    attendancePanel.setAttribute('aria-hidden', 'false');
    await refreshMyAttendance();
  }
});
document.getElementById('closeAttendancePanel').addEventListener('click', () => {
  attendancePanel.style.display = 'none';
  attendancePanel.setAttribute('aria-hidden', 'true');
});

/* -------------------------
   Participants & chat
   ------------------------- */
async function refreshParticipants(){
  try {
    const res = await post({ action:'get_participants' });
    const list = document.getElementById('participantsList');
    list.innerHTML = '';
    if (!Array.isArray(res)) return;
    res.forEach(p => {
      const div = document.createElement('div'); div.className = 'participant';
      const av = document.createElement('div'); av.className = 'avatar'; av.textContent = (p.name||'U').charAt(0).toUpperCase();
      const meta = document.createElement('div'); meta.innerHTML = `<strong>${escapeHtml(p.name||'')}</strong><br><small>${escapeHtml(p.reg_no||'')}</small>`;
      div.appendChild(av); div.appendChild(meta);
      list.appendChild(div);
    });
  } catch (e) { console.error(e); }
}
document.getElementById('refreshParts').addEventListener('click', refreshParticipants);

// Chat using session storage
async function pollChat(){
  try {
    const res = await post({ action:'get_chat' });
    const log = document.getElementById('chatLog');
    log.innerHTML = '';
    if (!Array.isArray(res)) return;
    res.forEach(m => {
      const b = document.createElement('div'); b.className = 'bubble' + (m.user_id == studentId ? ' me' : '');
      b.innerHTML = `<strong>${escapeHtml(m.user_name)}</strong><div style="margin-top:4px">${escapeHtml(m.message)}</div>`;
      log.appendChild(b);
    });
    log.scrollTop = log.scrollHeight;
  } catch (e) { console.error(e); }
}
document.getElementById('sendChatBtn').addEventListener('click', async () => {
  const t = document.getElementById('chatMsg'); const txt = t.value.trim();
  if (!txt) return;
  try {
    await post({ action:'send_chat', message: txt });
    t.value = ''; pollChat();
  } catch (e) { console.error(e); }
});
document.getElementById('clearChatBtn').addEventListener('click', async () => {
  try { await post({ action:'clear_chat' }); pollChat(); } catch(e){/*ignore*/} 
});

/* -------------------------
   Request screen sharing workflow (Google Meet style)
   - Student clicks "Request screen share"
   - Client inserts a 'screen-request' signal (to_lecturer_id optionally)
   - Lecturer must respond by inserting 'screen-approve' signal with to_student_id = this student
   - Student polls get_signals; on receiving 'screen-approve' we call getDisplayMedia and set local preview to screen
   ------------------------- */
const statusArea = document.getElementById('statusArea');
let pendingScreenRequestId = null;

function showStatus(msg, timeout=7000) {
  statusArea.textContent = msg;
  if (timeout) setTimeout(()=> { if (statusArea.textContent === msg) statusArea.textContent = ''; }, timeout);
}

document.getElementById('requestScreen').addEventListener('click', async () => {
  try {
    // send a screen-request signal to lecturer (lecturer will know which meeting; they should reply with screen-approve to this student's id)
    const sig = await post({ action:'send_signal', to_lecturer_id: 0, to_student_id: 0, type:'screen-request', data: JSON.stringify({ student_id: studentId, name: userNameStr }) });
    if (sig && sig.success) {
      pendingScreenRequestId = Date.now(); // local marker
      showStatus('Screen-share request sent. Waiting for lecturer approval...');
    } else {
      showStatus('Failed to send request.');
    }
  } catch (e) {
    console.error(e);
    showStatus('Request failed: ' + e.message);
  }
});

// Poll signals: handle screen-approve and other messages
async function pollSignals(){
  try {
    const rows = await post({ action:'get_signals' });
    if (!Array.isArray(rows) || rows.length === 0) return;
    for (const r of rows) {
      // r.type might be 'screen-approve' with data { approved: true, nonce:... }
      if (r.type === 'screen-approve') {
        // optional parse
        let payload = null;
        try { payload = JSON.parse(r.data || '{}'); } catch(e){}
        // if the approval is for this student (server side should set to_student_id), proceed
        showStatus('Screen-share approved. Opening screen capture now...');
        // now actually start screen capture
        try {
          const s = await navigator.mediaDevices.getDisplayMedia({ video: true });
          // show in local video element
          document.getElementById('localVideo').srcObject = s;
          // notify lecturer that screen share started
          await post({ action:'send_signal', type:'screen-shared', data: JSON.stringify({ student_id: studentId, name: userNameStr }) });
          // when screen sharing stops, revert preview back to camera
          const track = s.getVideoTracks()[0];
          track.onended = async () => {
            // revert to camera if available
            if (localStream) document.getElementById('localVideo').srcObject = localStream;
            // notify lecturer that screen share ended
            try { await post({ action:'send_signal', type:'screen-ended', data: JSON.stringify({ student_id: studentId }) }); } catch(e){/*ignore*/ }
          };
        } catch (e) {
          showStatus('Failed to capture screen: ' + e.message);
        }
      } else if (r.type === 'message') {
        // general message — show as status
        try {
          const p = JSON.parse(r.data||'{}'); showStatus(p.text || 'Message');
        } catch(e) { showStatus(r.data || 'Signal'); }
      }
      // other signal types can be handled here
    }
  } catch (e) {
    console.error('pollSignals', e);
  }
}

/* -------------------------
   Local recording (client-side), simple download
   ------------------------- */
document.getElementById('recordBtn').addEventListener('click', () => {
  if (!localStream) { alert('No local stream'); return; }
  if (recording) { mediaRecorder.stop(); recording = false; return; }
  recordedChunks = [];
  try {
    mediaRecorder = new MediaRecorder(localStream, { mimeType: 'video/webm' });
  } catch (e) {
    alert('Recording not supported: ' + e.message); return;
  }
  mediaRecorder.ondataavailable = e => { if (e.data && e.data.size) recordedChunks.push(e.data); };
  mediaRecorder.onstop = () => {
    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `meeting_${meetingId}_student_${studentId}.webm`; a.click();
    recordedChunks = [];
  };
  mediaRecorder.start(1000);
  recording = true;
});

/* -------------------------
   Leave meeting
   ------------------------- */
document.getElementById('leaveBtn').addEventListener('click', async () => {
  if (!confirm('Leave meeting?')) return;
  // mark attendance status = 'left'
  try {
    await fetch(location.pathname + '?meeting_id=' + meetingId, { method:'POST', body: new URLSearchParams({ action: 'log_attendance' }) });
  } catch(e){/* ignore */}
  // redirect to dashboard or previous page
  window.location.href = '../dashboard.php';
});

/* -------------------------
   My attendance retrieval (for student attendance panel)
   ------------------------- */
async function refreshMyAttendance(){
  try {
    const my = await post({ action:'get_my_attendance' });
    const area = document.getElementById('myAttendanceArea');
    if (!my || Object.keys(my).length === 0) {
      area.innerHTML = '<div>No record yet. Click "Log attendance" to register join.</div>';
      return;
    }
    area.innerHTML = `<div class="att-row"><div><strong>Joined:</strong><br>${escapeHtml(my.joined_at || '')}</div><div><strong>Duration:</strong><br>${escapeHtml(String(my.duration || '0'))} min</div></div>
                      <div style="margin-top:8px"><strong>Status:</strong> ${escapeHtml(my.status || '')}</div>`;
  } catch (e) { console.error(e); }
}
document.getElementById('logMe').addEventListener('click', async () => {
  try {
    const r = await post({ action:'log_attendance' });
    if (r.success) {
      alert('Attendance logged.');
      refreshMyAttendance();
    } else alert('Failed to log attendance');
  } catch (e) { alert('Error logging'); }
});

/* -------------------------
   Polling loops & init
   ------------------------- */
setInterval(refreshParticipants, 5000);
setInterval(pollChat, 3000);
setInterval(pollSignals, 3000);

initLocalMedia();
refreshParticipants();
pollChat();
refreshMyAttendance();

/* -------------------------
   Utilities
   ------------------------- */
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

</script>
</body>
</html>
