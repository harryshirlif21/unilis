<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once '../config/db.php';
require_once '../lecturer/attendance_functions.php';

$token = $_GET['token'] ?? '';

// If coming back from login, get token from session
if (!$token && isset($_SESSION['pending_auto_mark_token'])) {
    $token = $_SESSION['pending_auto_mark_token'];
    unset($_SESSION['pending_auto_mark_token']);
}

if (!$token) {
    die("Invalid or expired link.");
}

// Decode token
$decoded = base64_decode(urldecode($token));
if (!$decoded || substr_count($decoded, '|') !== 2) {
    die("Invalid token format.");
}

list($session_id, $student_id, $hash) = explode('|', $decoded);

if ($hash !== hash('sha256', $session_id . $student_id . 'UNILIS2025')) {
    die("Unauthorized access.");
}

$session_id = (int)$session_id;
$student_id = (int)$student_id;

// SECURITY: Must be logged in as correct student
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $student_id || $_SESSION['user_role'] !== 'student') {
    $_SESSION['pending_auto_mark_token'] = $token;
    $return_url = urlencode($_SERVER['REQUEST_URI']);
    header("Location: ../login.php?return=$return_url");
    exit;
}

// Check session still active
$stmt = $conn->prepare("SELECT session_code FROM attendance_sessions WHERE id = ? AND deadline >= NOW()");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    die("Session expired or invalid.");
}

$stmt->bind_result($code);
$stmt->fetch();
$stmt->close();

// MARK ATTENDANCE
$result = submitAttendance($session_id, $student_id, $code);

$success = $result['success'] ?? false;
$msg = $result['message'] ?? "Error processing attendance.";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Marked • UNILIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { 
    background: linear-gradient(135deg, #f59e0b, #f97316); 
    min-height: 100vh; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-family: 'Segoe UI', sans-serif;
}
.card { 
    max-width: 500px; 
    border-radius: 1.5rem; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
}
.status-icon { font-size: 4rem; margin-bottom: 1rem; }
.success { color: #22c55e; }
.failure { color: #ef4444; }
</style>
</head>
<body>
<div class="container">
    <div class="card text-center p-5">
        <div class="status-icon <?= $success ? 'success' : 'failure' ?>">
            <?= $success ? '&#10004;' : '&#10006;' ?>
        </div>
        <h1 class="display-5 mb-3 text-white"><?= $success ? 'Success!' : 'Failed' ?></h1>
        <p class="fs-5 text-white"><?= htmlspecialchars($msg) ?></p>
        <a href="student_dashboard.php" class="btn btn-light btn-lg mt-4 px-5">
            Back to Dashboard
        </a>
    </div>
</div>

<?php if ($success): ?>
<script>
    // Auto redirect after 3 seconds
    setTimeout(() => window.location.href = 'student_dashboard.php', 3000);
</script>
<?php endif; ?>
</body>
</html>

<?php ob_end_flush(); ?>
