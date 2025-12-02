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

// If no unit selected via URL, default to first assigned unit
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
    SELECT s.id, s.session_code, s.deadline
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
    SELECT s.id, s.session_code, s.created_at
    FROM attendance_sessions s
    WHERE s.unit_id = $unit_id
    ORDER BY s.created_at DESC
");

$previous_sessions = [];
while ($row = $prev_sessions_list->fetch_assoc()) {
    // Fetch students for this session
    $student_res = $conn->query("
        SELECT st.name, st.registration_no, ar.attended
        FROM attendance_records ar
        JOIN students st ON ar.student_id = st.id
        WHERE ar.session_id = {$row['id']}
        ORDER BY st.name
    ");
    $students = [];
    while ($s = $student_res->fetch_assoc()) {
        $students[] = $s;
    }
    $row['students'] = $students;
    $previous_sessions[] = $row;
}

// Fetch students for live session
$live_students = [];
if ($is_live) {
    $live_student_res = $conn->query("
        SELECT st.name, st.registration_no, ar.attended
        FROM attendance_records ar
        JOIN students st ON ar.student_id = st.id
        WHERE ar.session_id = {$current_session['id']}
        ORDER BY st.name
    ");
    while ($s = $live_student_res->fetch_assoc()) {
        $live_students[] = $s;
    }
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

    /* Modal Styles */
    #attendanceModal {
        display:none;
        position: fixed; 
        z-index: 9999; 
        left: 0; top: 0;
        width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5);
    }
    #attendanceModal .modal-content {
        background: #fff; margin: 10% auto; padding: 20px; border-radius: 10px; width: 80%; max-width: 600px;
    }
    #attendanceModal .close { float: right; font-size: 24px; font-weight: bold; cursor: pointer; }
    #attendanceModal table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    #attendanceModal table th, #attendanceModal table td { border:1px solid #ccc; padding:8px; text-align:left; }
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

    <?php if ($is_live): ?>
        <p style="color:green; font-weight:bold;">Live Session - Code: <?= $current_session['session_code'] ?></p>
        <div>
            <span class="btn btn-view" onclick="openSessionModal(<?= $current_session['id'] ?>)">View Students</span>
            <a href="end_session.php?session=<?= $current_session['id'] ?>" class="btn btn-end">End Session</a>
        </div>
    <?php else: ?>
        <p style="color:gray;">No active session</p>
    <?php endif; ?>
</div>

<!-- Previous Rollcalls -->
<h2>Previous Rollcalls</h2>
<div>
    <?php if (!empty($previous_sessions)): ?>
        <?php foreach ($previous_sessions as $idx => $session): ?>
            <div class="tile">
                <p><strong>Lesson:</strong> <?= $idx + 1 ?></p>
                <p><strong>Code:</strong> <?= $session['session_code'] ?></p>
                <p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($session['created_at'])) ?></p>
                <span class="btn btn-view" onclick="openSessionModal(<?= $session['id'] ?>)">View Details</span>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No previous rollcalls.</p>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="attendanceModal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3 id="modalTitle"></h3>
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Reg No</th><th>Status</th></tr>
            </thead>
            <tbody id="modalStudentList"></tbody>
        </table>
    </div>
</div>

<script>
const sessions = {
    <?php if($is_live): ?>
    <?= $current_session['id'] ?>: <?= json_encode($live_students) ?>,
    <?php endif; ?>
    <?php foreach($previous_sessions as $s): ?>
    <?= $s['id'] ?>: <?= json_encode($s['students']) ?>,
    <?php endforeach; ?>
};

const modal = document.getElementById('attendanceModal');
const modalTitle = document.getElementById('modalTitle');
const modalStudentList = document.getElementById('modalStudentList');

function openSessionModal(sessionId) {
    const students = sessions[sessionId] || [];
    modalTitle.textContent = `Session ID: ${sessionId}`;
    modalStudentList.innerHTML = '';
    students.forEach((s, i) => {
        const row = `<tr>
            <td>${i+1}</td>
            <td>${s.name}</td>
            <td>${s.registration_no}</td>
            <td>${s.attended == 1 ? 'Present' : 'Absent'}</td>
        </tr>`;
        modalStudentList.innerHTML += row;
    });
    modal.style.display = 'block';
}

modal.querySelector('.close').onclick = function() {
    modal.style.display = 'none';
};

window.onclick = function(e) {
    if (e.target == modal) modal.style.display = 'none';
};
</script>

</body>
</html>
