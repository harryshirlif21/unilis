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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'submit_attendance':
                $session_id = intval($_POST['session_id'] ?? 0);
                $code = trim($_POST['code'] ?? '');
                
                if (!$session_id || !$code) {
                    echo json_encode(['success' => false, 'message' => 'Invalid session or code']);
                    exit;
                }
                
                $result = validateStudentAttendanceCode($conn, $student_id, $code, $session_id);
                echo json_encode($result);
                break;
                
            case 'request_new_code':
                $session_id = intval($_POST['session_id'] ?? 0);
                
                if (!$session_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid session']);
                    exit;
                }
                
                $result = requestNewAttendanceCode($conn, $student_id, $session_id);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        error_log("Attendance submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
