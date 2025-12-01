<?php
require_once '../config/db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid attendance link.");
}

// Decode the token: format is base64_encode("$session_id|$student_id|" . hash(...))
$decoded = base64_decode($token);
if (!$decoded) {
    die("Invalid token.");
}

list($session_id, $student_id, $hash) = explode('|', $decoded);

// Verify hash to prevent tampering
$expected_hash = hash('sha256', $session_id . $student_id . 'UNILIS2025');
if ($hash !== $expected_hash) {
    die("Invalid token hash.");
}

// Check if the student already marked attendance
$stmt = $conn->prepare("SELECT attended FROM attendance_records WHERE session_id=? AND student_id=? LIMIT 1");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if (!$record) {
    die("Attendance record not found.");
}

if ($record['attended'] == 1) {
    die("You have already marked attendance for this session.");
}

// Mark attendance
$stmt = $conn->prepare("UPDATE attendance_records SET attended=1, attended_at=NOW() WHERE session_id=? AND student_id=?");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$stmt->close();

echo "Attendance marked successfully!";
?>
