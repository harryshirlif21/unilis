<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/student_attendance.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$student_id = (int) $_SESSION['user_id'];
$unit_id    = $_POST['unit_id'] ?? 0;
$code       = $_POST['attendance_code'] ?? '';

if (!$student_id || !$unit_id || !$code) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Find active attendance session for the unit
$stmt = $conn->prepare("SELECT id, session_code FROM attendance_sessions 
                        WHERE unit_id = ? AND deadline >= NOW() 
                        ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($session_id, $session_code);
if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No active session for this unit.']);
    exit;
}
$stmt->fetch();
$stmt->close();

// Submit attendance using existing function
$result = submitAttendance($conn, $session_id, $student_id, $code);

echo json_encode($result);
?>
