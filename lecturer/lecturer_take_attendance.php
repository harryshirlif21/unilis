<?php
session_start();
require_once '../config/db.php';
require_once 'attendance_functions.php'; // your updated functions
require_once __DIR__ . '/../includes/mailer.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Get unit_id safely
$unit_id = 0;
if (!empty($_POST['unit_id']) && is_numeric($_POST['unit_id'])) {
    $unit_id = (int)$_POST['unit_id'];
} elseif (!empty($_GET['unit']) && is_numeric($_GET['unit'])) {
    $unit_id = (int)$_GET['unit'];
}

if ($unit_id <= 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Invalid unit selected.</h3>");
}

// Verify lecturer teaches this unit
$stmt = $conn->prepare("
    SELECT u.name FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE u.id = ? AND lu.lecturer_id = ?
");
$stmt->bind_param("ii", $unit_id, $lecturer_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Unauthorized access.</h3>");
}
$unit_name = htmlspecialchars($res->fetch_assoc()['name']);
$stmt->close();

// Process AJAX request for registered students
if (isset($_GET['action']) && $_GET['action'] === 'get_registered' && isset($_GET['session_id'])) {
    $session_id = (int)$_GET['session_id'];
    $students = $conn->query("SELECT s.name, s.email, asr.marked_at 
                              FROM attendance_students_records asr
                              JOIN students s ON asr.student_id = s.id
                              WHERE asr.session_id = $session_id
                              ORDER BY asr.marked_at ASC");
    $result = [];
    while ($row = $students->fetch_assoc()) {
        $result[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Start attendance session
$success = false;
$code = $deadline = '';
$session_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_attendance'])) {
    $duration = max(1, min(120, (int)($_POST['duration'] ?? 10)));
    $send_email = !empty($_POST['send_email']);

    $session_data = createAttendanceSession($unit_id, $lecturer_id, $duration, $send_email);

    if ($session_data) {
        $success = true;
        $code = $session_data['code'];
        $deadline = $session_data['deadline'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Code • UNILIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { background: linear-gradient(135deg, #f59e0b, #f97316); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
.card { background: rgba(255,255,255,0.95); border-radius: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
.code-display { font-size: 6rem; font-weight: 900; letter-spacing: 0.3em; color: #f59e0b; text-shadow: 0 4px 10px rgba(245,158,11,0.3); }
@media (max-width: 576px) { .code-display { font-size: 4rem; } }
</style>
</head>
<body class="d-flex align-items-center justify-content-center">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card p-5 text-center">
                <?php if ($success): ?>
                    <h1 class="display-5 fw-bold text-dark mb-2">Attendance Started!</h1>
                    <p class="text-muted fs-5"><?= $unit_name ?></p>
                    <div class="code-display my-5"><?= $code ?></div>
                    <p class="fs-3 text-success fw-bold">Active Now</p>
                    <p class="fs-4 text-muted">Time Remaining: <span id="attendanceDeadline"></span></p>
                    <button class="btn btn-outline-warning btn-lg px-5" onclick="location.reload()">New Code</button>
                    <button class="btn btn-primary btn-lg px-5" data-bs-toggle="modal" data-bs-target="#attendanceModal">View Registrations</button>
                <?php else: ?>
                    <form id="attendanceForm" method="POST">
                        <h3 class="mb-4">Start Attendance for <?= $unit_name ?></h3>
                        <div class="mb-3">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control" value="10" min="1" max="120">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="send_email" class="form-check-input" id="sendEmail">
                            <label class="form-check-label" for="sendEmail">Send Email to Students</label>
                        </div>
                        <button type="submit" name="start_attendance" class="btn btn-success btn-lg px-5">Start Attendance</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Students Registered Attendance</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <ul class="list-group" id="studentList"></ul>
        </div>
    </div>
</div>
</div>

<script>
<?php if($success): ?>
let sessionId = <?= $session_data['session_id'] ?>;
let deadline = new Date("<?= $deadline ?>");
let countdownInterval = null;

function startCountdown() {
    clearInterval(countdownInterval);
    function updateTimer() {
        const now = new Date();
        const distance = deadline - now;
        if(distance <= 0){
            $('#attendanceDeadline').text('Expired');
            clearInterval(countdownInterval);
            return;
        }
        const minutes = Math.floor(distance/1000/60);
        const seconds = Math.floor((distance/1000)%60);
        $('#attendanceDeadline').text(`${minutes}m ${seconds}s remaining`);
    }
    updateTimer();
    countdownInterval = setInterval(updateTimer, 1000);
}
startCountdown();

// Fetch registered students every 5 seconds
function fetchStudents(){
    $.get('', { action:'get_registered', session_id: sessionId }, function(students){
        $('#studentList').empty();
        if(students.length===0){
            $('#studentList').append('<li class="list-group-item">No students yet</li>');
        } else {
            students.forEach(s=>{
                $('#studentList').append('<li class="list-group-item">'+s.name+' ('+s.email+') • '+new Date(s.marked_at).toLocaleTimeString()+'</li>');
            });
        }
    }, 'json');
}
fetchStudents();
setInterval(fetchStudents, 5000);
<?php endif; ?>
</script>
</body>
</html>
