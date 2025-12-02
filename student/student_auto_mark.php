<?php
require_once '../config/db.php';

// Get code and student ID from URL
$code = $_GET['code'] ?? '';
$student_id = intval($_GET['student_id'] ?? 0);

if (!$code || !$student_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid link or student ID.']);
    exit;
}

// Lookup the attendance session using the 6-digit code
$stmt = $conn->prepare("SELECT id, deadline FROM attendance_sessions WHERE session_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Attendance session not found.']);
    exit;
}

// Check if the session has expired
$current_time = new DateTime();
$deadline = new DateTime($session['deadline']);
if ($current_time > $deadline) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Attendance session has expired.']);
    exit;
}

$session_id = $session['id'];

// Check if the student already marked attendance
$stmt = $conn->prepare("SELECT attended FROM attendance_records WHERE session_id = ? AND student_id = ? LIMIT 1");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if (!$record) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Attendance record not found.']);
    exit;
}

if ($record['attended'] == 1) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'You have already marked attendance for this session.']);
    exit;
}

// Mark attendance
$stmt = $conn->prepare("UPDATE attendance_records SET attended = 1, attended_at = NOW() WHERE session_id = ? AND student_id = ?");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$stmt->close();

// Success response
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Attendance marked successfully!']);
exit;
?>
