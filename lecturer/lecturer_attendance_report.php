<?php
session_start();
require_once '../config/db.php';

// Ensure lecturer is logged in
$lecturer_id = $_SESSION['user_id'] ?? 0;
if (!$lecturer_id) {
    header("Location: ../login.php");
    exit;
}

// =========================
// AJAX: Fetch attendance for a session
// =========================
if (isset($_GET['ajax_session'])) {
    $session_id = intval($_GET['ajax_session']);
    $res = $conn->query("
        SELECT st.name, st.reg_no, ar.attended
        FROM attendance_records ar
        JOIN students st ON ar.student_id = st.id
        WHERE ar.session_id = $session_id
        ORDER BY st.name
    ");
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $row['status'] = $row['attended'] ? 'Present' : 'Absent';
        $data[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// =========================
// GET ALL UNITS FOR LECTURER
// =========================
$units_query = $conn->query("
    SELECT u.id, u.name, c.name AS course_name
    FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE lu.lecturer_id = $lecturer_id
    ORDER BY u.name
");

// =========================
// DETERMINE CURRENT UNIT
// =========================
$unit_id = isset($_GET['unit']) ? intval($_GET['unit']) : 0;
if ($unit_id <= 0) {
    $first_unit = $units_query->fetch_assoc();
    if ($first_unit) { 
        $unit_id = $first_unit['id']; 
    }
}

// Get selected unit details
$unit_res = $conn->query("
    SELECT u.id, u.name, c.name AS course_name
    FROM units u
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE u.id = $unit_id
");
$unit_data = $unit_res->fetch_assoc();
$unit_name = $unit_data['name'] ?? "—";

// =========================
// LESSON NUMBER
// =========================
$prev_sessions_res = $conn->query("
    SELECT COUNT(*) AS count 
    FROM attendance_sessions 
    WHERE unit_id = $unit_id
");
$prev_sessions = $prev_sessions_res->fetch_assoc()['count'] ?? 0;
$lesson_number = $prev_sessions + 1;

// =========================
// CURRENT (LIVE) SESSION
// =========================
$live_session_res = $conn->query("
    SELECT s.id, s.session_code, s.deadline,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id AND ar.attended = 1) AS attended_count,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id) AS total_students
    FROM attendance_sessions s
    WHERE s.unit_id = $unit_id AND s.deadline >= NOW()
    ORDER BY s.created_at DESC LIMIT 1
");
$current_session = $live_session_res->fetch_assoc();
$is_live = !empty($current_session);

// =========================
// PREVIOUS SESSIONS
// =========================
$prev_sessions_list = $conn->query("
    SELECT id, session_code, created_at,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id AND ar.attended = 1) AS attended_count,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id) AS total_students
    FROM attendance_sessions s
    WHERE s.unit_id = $unit_id
    ORDER BY s.created_at DESC
");
$previous_sessions = [];
while ($row = $prev_sessions_list->fetch_assoc()) {
    $previous_sessions[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lecturer Attendance Report</title>
<link rel="stylesheet" href="../assets/styles.css">
<style>
.tile { background:#f3f4f6; padding:15px; border-radius:15px; margin:10px; display:inline-block; width:220px; vertical-align:top; }
.btn { padding:10px 15px; border-radius:10px; color:white; text-decoration:none; display:inline-block; margin-top:10px; cursor:pointer; }
.btn-view { background:#f59e0b; }
.btn-end { background:#dc2626; }
.modal { display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
.modal-content { background:#fff; margin:10% auto; padding:20px; border-radius:10px; width:80%; max-width:700px; }
.close { float:right; font-size:28px; font-weight:bold; cursor:pointer; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:8px; text-align:left; border-bottom:1px solid #ddd; }
th { background:#f59e0b20; }
</style>
</head>
<body>

<h1>Attendance Report</h1>

<h3>Select Unit</h3>
<select onchange="location.href='?unit=' + this.value" style="padding:10px; font-size:16px;">
<?php
$units_query->data_seek(0);
while ($unit = $units_query->fetch_assoc()):
?>
    <option value="<?= $unit['id'] ?>" <?= ($unit['id'] == $unit_id) ? 'selected' : '' ?>>
        <?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['course_name']) ?>)
    </option>
<?php endwhile; ?>
</select>

<!-- Current Rollcall -->
<div style="background:#f59e0b20; padding:25px; border-radius:15px; margin-top:25px; margin-bottom:25px;">
<h2>Current Rollcall</h2>
<p><strong>Unit:</strong> <?= htmlspecialchars($unit_name) ?></p>
<p><strong>Lesson Number:</strong> <?= $lesson_number ?></p>

<?php if ($is_live): 
    $attended_count = $current_session['attended_count'];
    $total_students = $current_session['total_students'];
    $deadline_ts = strtotime($current_session['deadline']);
?>
<p style="color:green; font-weight:bold;">
    Live Session - Code: <?= $current_session['session_code'] ?>
</p>
<p><strong>Students Attended:</strong> <?= $attended_count ?> / <?= $total_students ?></p>
<p><strong>Time Left:</strong> <span id="countdown"></span></p>

<div>
    <button class="btn btn-view" onclick="openModal(<?= $current_session['id'] ?>)">View Students</button>
    <a href="end_session.php?session=<?= $current_session['id'] ?>" class="btn btn-end">End Session</a>
</div>

<script>
const countdownEl = document.getElementById('countdown');
const deadline = <?= $deadline_ts ?> * 1000;
function updateCountdown() {
    const now = new Date().getTime();
    let distance = deadline - now;
    if(distance < 0){ countdownEl.textContent="Expired"; clearInterval(interval); return; }
    const minutes = Math.floor((distance % (1000*60*60)) / (1000*60));
    const seconds = Math.floor((distance % (1000*60)) / 1000);
    countdownEl.textContent = minutes + "m " + seconds + "s";
}
const interval = setInterval(updateCountdown, 1000);
updateCountdown();
</script>

<?php else: ?>
<p style="color:gray;">No active session</p>
<?php endif; ?>
</div>

<!-- Previous Rollcalls -->
<h2>Previous Rollcalls</h2>
<div>
<?php if(!empty($previous_sessions)): ?>
    <?php foreach($previous_sessions as $idx => $session): ?>
        <div class="tile">
            <p><strong>Lesson:</strong> <?= $idx+1 ?></p>
            <p><strong>Code:</strong> <?= $session['session_code'] ?></p>
            <p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($session['created_at'])) ?></p>
            <p><strong>Attended:</strong> <?= $session['attended_count'] ?> / <?= $session['total_students'] ?></p>
            <button class="btn btn-view" onclick="openModal(<?= $session['id'] ?>)">View Details</button>
        </div>
    <?php endforeach; ?>
<?php else: ?>
<p>No previous rollcalls.</p>
<?php endif; ?>
</div>

<!-- Modal -->
<div id="attendanceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Attendance Details</h2>
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Reg No</th><th>Status</th></tr>
            </thead>
            <tbody id="modalBody">
                <!-- Populated via JS -->
            </tbody>
        </table>
    </div>
</div>

<script>
function openModal(sessionId){
    const modal = document.getElementById('attendanceModal');
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
    modal.style.display = 'flex';

    fetch('?ajax_session=' + sessionId)
        .then(res => res.json())
        .then(data => {
            if(data.length === 0){
                modalBody.innerHTML = '<tr><td colspan="4">No records found</td></tr>';
                return;
            }
            let html = '';
            data.forEach((s, idx) => {
                html += `<tr>
                            <td>${idx+1}</td>
                            <td>${s.name}</td>
                            <td>${s.reg_no}</td>
                            <td>${s.status}</td>
                         </tr>`;
            });
            modalBody.innerHTML = html;
        })
        .catch(err => {
            modalBody.innerHTML = '<tr><td colspan="4">Error loading data</td></tr>';
            console.error(err);
        });
}

function closeModal(){
    document.getElementById('attendanceModal').style.display='none';
}

window.onclick = function(event){
    const modal = document.getElementById('attendanceModal');
    if(event.target == modal){ modal.style.display="none"; }
}
</script>

</body>
</html>
