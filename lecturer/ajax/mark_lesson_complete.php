<?php
// student/ajax/mark_lesson_complete.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$student_id = $_SESSION['user_id'];
$lesson_id  = intval($_POST['lesson_id'] ?? 0);
$unit_id    = intval($_POST['unit_id']   ?? 0);

if (!$lesson_id || !$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']); exit;
}

try {
    // Verify lesson exists in unit
    $stmt = $conn->prepare("SELECT id FROM course_lessons WHERE id = ? AND unit_id = ?");
    $stmt->bind_param("ii", $lesson_id, $unit_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']); exit;
    }
    $stmt->close();

    // Upsert completed event — insert or update existing viewed row to completed
    $stmt = $conn->prepare("
        INSERT INTO student_progress (student_id, unit_id, lesson_id, event_type, completed_at)
        VALUES (?, ?, ?, 'lesson_completed', NOW())
        ON DUPLICATE KEY UPDATE event_type = 'lesson_completed', completed_at = NOW()
    ");
    $stmt->bind_param("iii", $student_id, $unit_id, $lesson_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Lesson marked complete']);
} catch (mysqli_sql_exception $e) {
    error_log("mark_lesson_complete: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}