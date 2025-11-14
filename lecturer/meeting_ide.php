<?php
session_start();
require_once '../config/db.php'; // expects $conn (mysqli)

// Lecturer-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(403);
    echo "Access denied. Only lecturers can access this page.";
    exit;
}

$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id) {
    http_response_code(400);
    echo "Meeting ID is required.";
    exit;
}

// Ensure schema (non-destructive)
function ensure_schema($conn) {
    // recordings table
    $conn->query("
        CREATE TABLE IF NOT EXISTS recordings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            lecturer_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (meeting_id),
            INDEX (lecturer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ensure meetings ended column exists
    $check = $conn->prepare("
        SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meetings' AND COLUMN_NAME = 'ended'
    ");
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();
    if ((int)$row['cnt'] === 0) {
        @$conn->query("ALTER TABLE meetings ADD COLUMN ended TINYINT(1) NOT NULL DEFAULT 0");
    }

    // optionally ensure meeting_attendance table exists (basic)
    $conn->query("
        CREATE TABLE IF NOT EXISTS meeting_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            student_id INT NULL,
            guest_name VARCHAR(255) NULL,
            reg_no VARCHAR(100) NULL,
            joined_at DATETIME NULL,
            duration_minutes INT NULL DEFAULT 0,
            status ENUM('joined','left','absent') DEFAULT 'joined',
            active TINYINT(1) DEFAULT 1,
            marks DECIMAL(6,2) NULL,
            INDEX (meeting_id),
            INDEX (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // meeting_signals (lightweight)
    $conn->query("
        CREATE TABLE IF NOT EXISTS meeting_signals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id INT NOT NULL,
            from_lecturer_id INT NULL,
            from_student_id INT NULL,
            to_lecturer_id INT NULL,
            to_student_id INT NULL,
            type VARCHAR(50),
            data TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (meeting_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
ensure_schema($conn);

// Fetch meeting
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

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Lecturer';

// Session chat store per meeting (not persisted)
if (!isset($_SESSION['meeting_chat'])) $_SESSION['meeting_chat'] = [];
if (!isset($_SESSION['meeting_chat'][$meeting_id])) $_SESSION['meeting_chat'][$meeting_id] = [];

// AJAX handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    function out($data) { echo json_encode($data); exit; }

    switch ($action) {
        case 'get_participants':
            // Participants: joined meeting_attendance rows joined to students (if present)
            $sql = "SELECT ma.id AS attendance_id, ma.student_id AS student_id, COALESCE(s.name, ma.guest_name) AS name, COALESCE(s.reg_no, ma.reg_no, '') AS reg_no, ma.joined_at
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON s.id = ma.student_id
                    WHERE ma.meeting_id = ? AND (ma.status = 'joined' OR ma.status IS NULL)";
            $st = $conn->prepare($sql);
            $st->bind_param("i", $meeting_id);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            out($rows);
            break;

        case 'get_chat':
            $ch = $_SESSION['meeting_chat'][$meeting_id] ?? [];
            out($ch);
            break;

        case 'send_chat':
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') out(['success' => false, 'error' => 'Empty message']);
            $entry = [
                'user_id' => $userId,
                'user_name' => $userName,
                'message' => htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $_SESSION['meeting_chat'][$meeting_id][] = $entry;
            // trim to last 500 messages
            if (count($_SESSION['meeting_chat'][$meeting_id]) > 500) {
                $_SESSION['meeting_chat'][$meeting_id] = array_slice($_SESSION['meeting_chat'][$meeting_id], -500);
            }
            out(['success' => true, 'entry' => $entry]);
            break;

        case 'clear_chat':
            $_SESSION['meeting_chat'][$meeting_id] = [];
            out(['success' => true]);
            break;

        case 'get_attendance':
            $sql = "SELECT ma.id, COALESCE(s.name, ma.guest_name) AS name, COALESCE(s.reg_no, ma.reg_no) AS reg_no, ma.joined_at, ma.duration_minutes AS duration, ma.active, ma.marks
                    FROM meeting_attendance ma
                    LEFT JOIN students s ON s.id = ma.student_id
                    WHERE ma.meeting_id = ?
                    ORDER BY ma.joined_at ASC";
            $st = $conn->prepare($sql);
            $st->bind_param("i", $meeting_id);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            out($rows);
            break;

        case 'award_participation':
            $attendance_id = (int)($_POST['attendance_id'] ?? 0);
            $marks = floatval($_POST['marks'] ?? 0);
            if ($attendance_id <= 0) out(['success' => false, 'error' => 'invalid id']);
            $st = $conn->prepare("UPDATE meeting_attendance SET marks = ? WHERE id = ? AND meeting_id = ?");
            $st->bind_param("dii", $marks, $attendance_id, $meeting_id);
            $ok = $st->execute();
            $st->close();
            out(['success' => (bool)$ok]);
            break;

        case 'take_attendance':
            $students_json = $_POST['students'] ?? null;
            $inserted = 0;
            if ($students_json) {
                $arr = json_decode($students_json, true);
                if (is_array($arr)) {
                    foreach ($arr as $s) {
                        $student_id = isset($s['student_id']) ? (int)$s['student_id'] : null;
                        $name = trim($s['name'] ?? '');
                        $reg_no = trim($s['reg_no'] ?? '');
                        $duration = isset($s['duration']) ? (int)$s['duration'] : 0;
                        if ($name === '' && $student_id === null) continue;
                        // avoid duplicates
                        $q = $conn->prepare("SELECT id FROM meeting_attendance WHERE meeting_id = ? AND (student_id = ? OR (reg_no = ? AND reg_no <> '') OR guest_name = ?) LIMIT 1");
                        $q->bind_param("iiss", $meeting_id, $student_id, $reg_no, $name);
                        $q->execute();
                        $du = $q->get_result()->fetch_assoc();
                        $q->close();
                        if ($du) continue;
                        $ins = $conn->prepare("INSERT INTO meeting_attendance (meeting_id, student_id, guest_name, reg_no, joined_at, duration_minutes, status, active) VALUES (?, ?, ?, ?, NOW(), ?, 'joined', 1)");
                        $ins->bind_param("iissi", $meeting_id, $student_id, $name, $reg_no, $duration);
                        if ($ins->execute()) $inserted++;
                        $ins->close();
                    }
                }
            } else {
                // No students provided: return count of current joined rows
                $r = $conn->prepare("SELECT COUNT(*) AS cnt FROM meeting_attendance WHERE meeting_id = ? AND (status='joined' OR status IS NULL)");
                $r->bind_param("i", $meeting_id);
                $r->execute();
                $c = $r->get_result()->fetch_assoc();
                $r->close();
                $inserted = (int)$c['cnt'];
            }
            out(['success' => true, 'inserted' => $inserted]);
            break;

        case 'send_signal':
            $to_user_id = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
            $type = $_POST['type'] ?? '';
            $data = $_POST['data'] ?? '';
            $st = $conn->prepare("INSERT INTO meeting_signals (meeting_id, from_lecturer_id, to_student_id, type, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $st->bind_param("iiiss", $meeting_id, $userId, $to_user_id, $type, $data);
            $ok = $st->execute();
            $st->close();
            out(['success' => (bool)$ok]);
            break;

        case 'get_signals':
            $st = $conn->prepare("SELECT id, COALESCE(from_lecturer_id, from_student_id) AS from_user_id, type, data FROM meeting_signals WHERE meeting_id = ? AND (to_lecturer_id = ? OR to_student_id = ?) ORDER BY id ASC");
            $st->bind_param("iii", $meeting_id, $userId, $userId);
            $st->execute();
            $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            $st->close();
            if (!empty($rows)) {
                $ids = array_column($rows, 'id');
                $in = implode(',', array_map('intval', $ids));
                $conn->query("DELETE FROM meeting_signals WHERE id IN ($in)");
            }
            out($rows);
            break;

        case 'upload_recording':
            if (!isset($_FILES['recording']) || $_FILES['recording']['error'] !== UPLOAD_ERR_OK) out(['success' => false, 'error' => 'No file uploaded or upload error.']);
            $uploadDir = __DIR__ . '/uploads/recordings';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $tmp = $_FILES['recording']['tmp_name'];
            $orig = basename($_FILES['recording']['name']);
            $ext = pathinfo($orig, PATHINFO_EXTENSION) ?: 'webm';
            $filename = 'meeting_' . $meeting_id . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . '/' . $filename;
            if (move_uploaded_file($tmp, $dest)) {
                $rel = 'uploads/recordings/' . $filename;
                $ins = $conn->prepare("INSERT INTO recordings (meeting_id, lecturer_id, file_path, recorded_at) VALUES (?, ?, ?, NOW())");
                $ins->bind_param("iis", $meeting_id, $userId, $rel);
                $ok = $ins->execute();
                $ins->close();
                out(['success' => (bool)$ok, 'file' => $rel]);
            } else {
                out(['success' => false, 'error' => 'Failed to move file.']);
            }
            break;

        case 'end_meeting':
            $st = $conn->prepare("UPDATE meetings SET ended = 1 WHERE id = ?");
            $st->bind_param("i", $meeting_id);
            $ok = $st->execute();
            $st->close();
            out(['success' => (bool)$ok]);
            break;

        default:
            out(['success' => false, 'error' => 'Unknown action']);
    }
}

// Render page (GET)
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= htmlspecialchars($meeting['title']) ?> — Lecturer</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --bg:#0b0b0d; --accent:#06b6d4; --green:#10b981; --red:#ef4444; --orange:#f97316; --purple:#8b5cf6; --cyan:#22d3ee;
}
html,body{height:100%;margin:0;background:var(--bg);color:#fff;font-family:Inter,system-ui,Arial;overflow:hidden}
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
.control.mute{color:var(--red)}
.control.record{color:var(--red)}
.control.end{color:#7f1d1d}
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

/* attendance modal */
#attendancePanel{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:820px;max-width:95%;max-height:80vh;overflow:auto;background:#07161c;border-radius:12px;z-index:200;padding:16px;display:none}
#attendancePanel header{display:flex;justify-content:space-between;align-items:center;gap:12px}
table.att{width:100%;border-collapse:collapse;margin-top:10px}
table.att th,table.att td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.03);text-align:left;font-size:13px}
.smallBtn{padding:6px 8px;border-radius:6px;background:#0b1220;color:#dff6fb;border:1px solid rgba(255,255,255,0.03);cursor:pointer}
.disabledOverlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;z-index:500;align-items:center;justify-content:center;color:#fff;font-size:18px}
.disabledOverlay.active{display:flex}
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
  <div class="control cam" id="toggleCam" title="Camera" data-state="on"><i class="fa fa-video"></i></div>
  <div class="control mic" id="toggleMic" title="Mic" data-state="on"><i class="fa fa-microphone"></i></div>
  <div class="control screen" id="shareScreen" title="Share screen"><i class="fa fa-desktop"></i></div>
  <div class="control mute" id="muteAll" title="Mute all"><i class="fa fa-volume-mute"></i></div>
  <div class="control record" id="recordBtn" title="Record"><i class="fa fa-circle"></i></div>
  <div class="control end" id="endBtn" title="End meeting"><i class="fa fa-sign-out-alt"></i></div>
  <div class="control attendance" id="attendanceBtn" title="Attendance"><i class="fa fa-list-check"></i></div>
  <div class="control chat" id="toggleChatBtn" title="Toggle sidebar"><i class="fa fa-comments"></i></div>
</div>

<div id="sidebar">
  <header>
    <div>Participants</div>
    <div style="display:flex;gap:8px;align-items:center">
      <button id="btnRefresh" class="smallBtn">Refresh</button>
      <button id="btnTakeInline" class="smallBtn">Take</button>
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

<div id="attendancePanel" role="dialog" aria-modal="true">
  <header>
    <div><strong>Attendance — Meeting #<?= htmlspecialchars($meeting['id']) ?></strong></div>
    <div><button id="closeAttendance" class="smallBtn">Close</button></div>
  </header>
  <div style="margin-top:12px">
    <button id="refreshAttendance" class="smallBtn">Refresh</button>
    <button id="autoMarkPresent" class="smallBtn">Auto-mark present</button>
  </div>
  <table class="att" id="attendanceTable">
    <thead><tr><th>#</th><th>Name</th><th>Reg No</th><th>Joined</th><th>Duration (min)</th><th>Active</th><th>Award</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<div class="disabledOverlay" id="overlay"><div>Meeting ended — controls disabled.</div></div>

<script>
/* ===========================
   Config & state
   =========================== */
const meetingId = <?= (int)$meeting['id'] ?>;
const userId = <?= (int)$userId ?>;
const userName = <?= json_encode($userName) ?>;

let localStream = null;
let mediaRecorder = null;
let recordedChunks = [];
let recording = false;

/* Helper: POST to this same file and return JSON */
async function postAction(data, isForm=false) {
  const opts = { method:'POST' };
  if (isForm) {
    opts.body = data;
  } else {
    opts.headers = {'Content-Type':'application/x-www-form-urlencoded'};
    opts.body = new URLSearchParams({...data});
  }
  const url = location.pathname + '?meeting_id=' + meetingId;
  const res = await fetch(url, opts);
  if (!res.ok) {
    const text = await res.text();
    console.error('Server returned', res.status, text);
    throw new Error('Server error ' + res.status);
  }
  return res.json();
}

/* ===========================
   Local media + toggles (Option A)
   =========================== */
async function initLocalMedia(){
  try {
    localStream = await navigator.mediaDevices.getUserMedia({ video:{ width:1280 }, audio:true });
    document.getElementById('localVideo').srcObject = localStream;
  } catch (e) {
    alert('Camera/mic error: ' + e.message);
  }
}

// Camera toggle
document.getElementById('toggleCam').addEventListener('click', () => {
  const btn = document.getElementById('toggleCam');
  const state = btn.getAttribute('data-state') === 'on';
  if (!localStream) return;
  localStream.getVideoTracks().forEach(t => t.enabled = !state);
  const icon = btn.querySelector('i');
  if (state) { icon.className = 'fa fa-video-slash'; btn.setAttribute('data-state','off'); }
  else { icon.className = 'fa fa-video'; btn.setAttribute('data-state','on'); }
});

// Mic toggle
document.getElementById('toggleMic').addEventListener('click', () => {
  const btn = document.getElementById('toggleMic');
  const state = btn.getAttribute('data-state') === 'on';
  if (!localStream) return;
  localStream.getAudioTracks().forEach(t => t.enabled = !state);
  const icon = btn.querySelector('i');
  if (state) { icon.className = 'fa fa-microphone-slash'; btn.setAttribute('data-state','off'); }
  else { icon.className = 'fa fa-microphone'; btn.setAttribute('data-state','on'); }
});

/* ===========================
   Sidebar toggle (Option 1)
   =========================== */
const sidebar = document.getElementById('sidebar');
const toggleChatBtn = document.getElementById('toggleChatBtn');
toggleChatBtn.addEventListener('click', () => {
  const hidden = sidebar.classList.toggle('hidden');
  toggleChatBtn.style.opacity = hidden ? 0.6 : 1;
});

/* ===========================
   Screen share
   =========================== */
document.getElementById('shareScreen').addEventListener('click', async () => {
  try {
    const s = await navigator.mediaDevices.getDisplayMedia({video:true});
    document.getElementById('localVideo').srcObject = s;
    s.getVideoTracks()[0].onended = () => {
      if (localStream) document.getElementById('localVideo').srcObject = localStream;
    };
  } catch (e) {
    alert('Screen share failed: ' + e.message);
  }
});

/* ===========================
   Mute All (client-side)
   =========================== */
document.getElementById('muteAll').addEventListener('click', () => {
  document.querySelectorAll('video').forEach(v => v.muted = true);
  alert('All videos muted locally.');
});

/* ===========================
   Recording
   =========================== */
document.getElementById('recordBtn').addEventListener('click', async () => {
  if (!localStream) { alert('No local stream'); return; }
  if (recording) { mediaRecorder.stop(); return; }
  recordedChunks = [];
  try { mediaRecorder = new MediaRecorder(localStream, { mimeType: 'video/webm' }); }
  catch (e) { alert('Recording not supported: ' + e.message); return; }
  mediaRecorder.ondataavailable = e => { if (e.data && e.data.size) recordedChunks.push(e.data); };
  mediaRecorder.onstop = async () => {
    recording = false;
    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    const fd = new FormData();
    fd.append('action', 'upload_recording');
    fd.append('recording', blob, `meeting_${meetingId}_${Date.now()}.webm`);
    try {
      const res = await postAction(fd, true);
      if (res.success) alert('Recording uploaded: ' + (res.file || 'saved'));
      else alert('Recording upload failed: ' + (res.error || 'unknown'));
    } catch (err) { alert('Upload error: ' + err.message); }
    recordedChunks = [];
  };
  mediaRecorder.start(1000);
  recording = true;
});

/* ===========================
   End meeting
   =========================== */
document.getElementById('endBtn').addEventListener('click', async () => {
  if (!confirm('End meeting for everyone?')) return;
  try {
    const res = await postAction({ action:'end_meeting' });
    if (res.success) {
      alert('Meeting ended.');
      document.querySelectorAll('.control').forEach(c => c.classList.add('disabled'));
      document.getElementById('overlay').classList.add('active');
    } else alert('Failed to end meeting');
  } catch (e) { alert('Error: ' + e.message); }
});

/* ===========================
   Participants
   =========================== */
async function refreshParticipants() {
  try {
    const res = await postAction({ action:'get_participants' });
    const list = document.getElementById('participantsList');
    list.innerHTML = '';
    if (!Array.isArray(res)) return;
    res.forEach(p => {
      const div = document.createElement('div');
      div.className = 'participant';
      const avatar = document.createElement('div'); avatar.className = 'avatar'; avatar.textContent = (p.name||'U').charAt(0).toUpperCase();
      const meta = document.createElement('div'); meta.innerHTML = `<strong>${escapeHtml(p.name||'')}</strong><br><small>${escapeHtml(p.reg_no||'')}</small>`;
      div.appendChild(avatar); div.appendChild(meta);
      list.appendChild(div);
    });
  } catch (e) { console.error(e); }
}
document.getElementById('btnRefresh').addEventListener('click', refreshParticipants);

/* ===========================
   Chat (session-only)
   =========================== */
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function pollChat(){
  try {
    const res = await postAction({ action:'get_chat' });
    if (!Array.isArray(res)) return;
    const log = document.getElementById('chatLog');
    log.innerHTML = '';
    res.forEach(m => {
      const b = document.createElement('div');
      b.className = 'bubble' + (m.user_id == userId ? ' me' : '');
      b.innerHTML = `<strong>${escapeHtml(m.user_name)}</strong><div style="margin-top:4px">${escapeHtml(m.message)}</div>`;
      log.appendChild(b);
    });
    log.scrollTop = log.scrollHeight;
  } catch (e) { console.error(e); }
}

document.getElementById('sendChatBtn').addEventListener('click', async () => {
  const t = document.getElementById('chatMsg');
  const txt = t.value.trim();
  if (!txt) return;
  try {
    await postAction({ action:'send_chat', message: txt });
    t.value = '';
    pollChat();
  } catch (e) { console.error(e); }
});

document.getElementById('clearChatBtn').addEventListener('click', async () => {
  try {
    await postAction({ action:'clear_chat' });
    pollChat();
  } catch (e) { console.error(e); }
});

/* ===========================
   Attendance panel toggle + logic
   (click attendance to show/hide)
   =========================== */
const attendancePanel = document.getElementById('attendancePanel');
const attendanceBtn = document.getElementById('attendanceBtn');
const attendanceClose = document.getElementById('closeAttendance');

attendanceBtn.addEventListener('click', async () => {
  // toggle
  if (attendancePanel.style.display === 'block') {
    attendancePanel.style.display = 'none';
    return;
  }
  // show + load
  attendancePanel.style.display = 'block';
  await refreshAttendance();
});

attendanceClose.addEventListener('click', () => {
  attendancePanel.style.display = 'none';
});

async function refreshAttendance(){
  try {
    const res = await postAction({ action:'get_attendance' });
    const tbody = document.querySelector('#attendanceTable tbody');
    tbody.innerHTML = '';
    (res||[]).forEach((r,i) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${i+1}</td>
                      <td>${escapeHtml(r.name)}</td>
                      <td>${escapeHtml(r.reg_no)}</td>
                      <td>${r.joined_at || ''}</td>
                      <td>${r.duration || ''}</td>
                      <td><input type="checkbox" ${r.active==1 ? 'checked' : ''} disabled></td>
                      <td><input type="number" min="0" max="100" style="width:60px" data-id="${r.id}" class="awardInput"><button class="smallBtn awardBtn" data-id="${r.id}">Award</button></td>`;
      tbody.appendChild(tr);
    });

    // attach award listeners
    document.querySelectorAll('.awardBtn').forEach(btn=>{
      btn.addEventListener('click', async (ev)=>{
        const id = btn.getAttribute('data-id');
        const input = document.querySelector(`.awardInput[data-id="${id}"]`);
        const marks = input ? Number(input.value||0) : 0;
        try {
          const res = await postAction({ action:'award_participation', attendance_id:id, marks:marks });
          if (res.success) alert('Awarded');
          else alert('Failed to award');
        } catch (e) { alert('Error awarding'); }
      });
    });

  } catch (e) { console.error(e); }
}
document.getElementById('refreshAttendance').addEventListener('click', refreshAttendance);

/* Auto-mark present: snapshot current participants */
document.getElementById('autoMarkPresent').addEventListener('click', async ()=>{
  try {
    const parts = await postAction({ action:'get_participants' });
    const arr = (parts||[]).map(p => ({ student_id: p.student_id || null, name: p.name, reg_no: p.reg_no || '', duration: 0 }));
    const res = await postAction({ action:'take_attendance', students: JSON.stringify(arr) });
    if (res.success) { alert('Attendance snapshot taken: ' + (res.inserted || 0)); refreshAttendance(); }
    else alert('Failed to take attendance');
  } catch (e) { console.error(e); alert('Error'); }
});

/* Inline 'Take' button */
document.getElementById('btnTakeInline').addEventListener('click', async ()=>{
  try {
    const res = await postAction({ action:'take_attendance' });
    alert('Attendance snapshot: ' + (res.inserted || 0));
    refreshAttendance();
  } catch (e) { console.error(e); }
});

/* ===========================
   Polling loops
   =========================== */
setInterval(refreshParticipants, 5000);
setInterval(pollChat, 3000);

/* Init */
initLocalMedia();
refreshParticipants();
pollChat();
</script>
</body>
</html>
