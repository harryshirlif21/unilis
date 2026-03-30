<?php
require_once '../config/db.php';
require_once __DIR__ . '/../includes/student_attendance.php';
session_start();

header('Content-Type: application/json');

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];

try {
    $sessions = getStudentActiveAttendanceSessions($conn, $student_id);
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions
    ]);
    
} catch (Exception $e) {
    error_log("Error getting attendance sessions: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load attendance sessions',
        'sessions' => []
    ]);
}
?>
