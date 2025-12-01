<?php
require_once '../config/db.php';
session_start();

$lecturer_id = $_SESSION['user_id'] ?? 0;
if (!$lecturer_id) {
    header("Location: login.php");
    exit;
}

// Get all units for this lecturer
$units_query = $conn->query("
    SELECT u.id, u.name, c.name AS course_name, u.year, u.semester
    FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE lu.lecturer_id = $lecturer_id
    ORDER BY u.name
");

// For simplicity, we pick the first unit as current
$current_unit = $units_query->fetch_assoc();
$unit_id = $current_unit['id'] ?? 0;
$unit_name = $current_unit['name'] ?? "—";

// Get previous sessions count for this unit
$prev_sessions_res = $conn->query("SELECT COUNT(*) AS count FROM attendance_sessions WHERE unit_id = $unit_id");
$prev_sessions = $prev_sessions_res->fetch_assoc()['count'] ?? 0;
$lesson_number = $prev_sessions + 1;

// Get live session if exists
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

// Get previous sessions for tiles
$prev_sessions_res = $conn->query("
    SELECT id, session_code, created_at,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id AND ar.attended = 1) AS attended_count,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id) AS total_students
    FROM attendance_sessions s
    WHERE s.unit_id = $unit_id
    ORDER BY s.created_at DESC
");
$previous_sessions = [];
while ($row = $prev_sessions_res->fetch_assoc()) {
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
    .btn { padding:10px 15px; border-radius:10px; color:white; text-decoration:none; display:inline-block; margin-top:10px; }
    .btn-view { background:#f59e0b; }
    .btn-end { background:#dc2626; }
</style>
</head>
<body>

<h1>Attendance Report</h1>

<!-- Current Rollcall -->
<div style="background:#f59e0b/10; padding:25px; border-radius:15px; margin-bottom:25px;">
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
            <a href="lecturer_view_session.php?session=<?= $current_session['id'] ?>" class="btn btn-view">View Students</a>
            <a href="end_session.php?session=<?= $current_session['id'] ?>" class="btn btn-end">End Session</a>
        </div>

        <script>
        const countdownEl = document.getElementById('countdown');
        const deadline = <?= $deadline_ts ?> * 1000;

        function updateCountdown() {
            const now = new Date().getTime();
            let distance = deadline - now;
            if (distance < 0) {
                countdownEl.textContent = "Expired";
                clearInterval(interval);
                return;
            }
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
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
    <?php if (!empty($previous_sessions)): ?>
        <?php foreach ($previous_sessions as $idx => $session): ?>
            <div class="tile">
                <p><strong>Lesson:</strong> <?= $idx + 1 ?></p>
                <p><strong>Code:</strong> <?= $session['session_code'] ?></p>
                <p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($session['created_at'])) ?></p>
                <p><strong>Attended:</strong> <?= $session['attended_count'] ?> / <?= $session['total_students'] ?></p>
                <a href="lecturer_view_session.php?session=<?= $session['id'] ?>" class="btn btn-view">View Details</a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No previous rollcalls.</p>
    <?php endif; ?>
</div>

</body>
</html>
