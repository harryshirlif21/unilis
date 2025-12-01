<?php
require_once '../config/db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid attendance link.");
}

// Find the attendance record by token
$stmt = $conn->prepare("SELECT id, attended, session_id FROM attendance_records WHERE token=? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if (!$record) {
    die("Invalid or expired attendance link.");
}

if ($record['attended'] == 1) {
    die("You have already marked attendance for this session.");
}

// Mark attendance
$stmt = $conn->prepare("UPDATE attendance_records SET attended=1, attended_at=NOW(), token=NULL WHERE id=?");
$stmt->bind_param("i", $record['id']);
$stmt->execute();
$stmt->close();

echo "Attendance marked successfully!";
?>
