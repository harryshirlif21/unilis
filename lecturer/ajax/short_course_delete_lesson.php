<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$course_id   = (int)($_POST['course_id'] ?? 0);
$lesson_id   = (int)($_POST['lesson_id'] ?? 0);

if (!$course_id || !$lesson_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Verify access
$check = $conn->prepare("SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? AND is_active = 1");
$check->bind_param("ii", $lecturer_id, $course_id);
$check->execute();
if (!$check->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$check->close();

// Verify lesson belongs to a module in this course
$stmt = $conn->prepare("
    SELECT l.id FROM public_course_lessons l
    JOIN public_course_modules m ON m.id = l.module_id
    WHERE l.id = ? AND m.course_id = ?
");
$stmt->bind_param("ii", $lesson_id, $course_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Lesson not found']);
    $stmt->close();
    exit;
}
$stmt->close();

$stmt = $conn->prepare("DELETE FROM public_course_lessons WHERE id = ?");
$stmt->bind_param("i", $lesson_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Lesson deleted']);