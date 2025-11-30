<?php
ob_start();
session_start();

// Must be logged in as student
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit;
}

require_once '../config/db.php';
require_once '../lecturer/attendance_functions.php';

$token = $_GET['token'] ?? '';

// Decode and validate token
$decoded = base64_decode(urldecode($token));
if (!$decoded || substr_count($decoded, '|') !== 2) {
    die("Invalid or expired link.");
}

list($session_id, $student_id, $hash) = explode('|', $decoded, 3);

// Security check
if ($student_id != $_SESSION['user_id'] || 
    $hash !== hash('sha256', $session_id . $student_id . 'UNILIS2025')) {
    die("Unauthorized access.");
}

$session_id = (int)$session_id;

// Check if session is still active
$stmt = $conn->prepare("SELECT session_code FROM attendance_sessions WHERE id = ? AND deadline >= NOW()");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    die("Attendance session expired or invalid.");
}

$stmt->bind_result($code);
$stmt->fetch();
$stmt->close();

// Mark attendance
$result = submitAttendance($session_id, $student_id, $code);

if ($result['success']) {
    $msg = "Attendance marked successfully!";
} else {
    $msg = "Already marked or error occurred.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Marked</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #f59e0b, #f97316); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .card { 
            max-width: 500px; 
            border-radius: 1.5rem; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card text-center p-5">
        <div class="card-body">
            <h1 class="display-4 text-white mb-4">Success!</h1>
            <p class="fs-3 text-white"><?= htmlspecialchars($msg) ?></p>
            <a href="student_dashboard.php" class="btn btn-light btn-lg mt-4 px-5">
                Back to Dashboard
            </a>
        </div>
    </div>
</div>

<script>
    // Auto redirect after 3 seconds
    setTimeout(() => window.location = 'student_dashboard.php', 3000);
</script>
</body>
</html>

<?php ob_end_flush(); ?>