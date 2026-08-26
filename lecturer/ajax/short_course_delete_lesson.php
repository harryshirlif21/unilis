<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
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

if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

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

// Check granular lesson edit permission for deletions
if (!shortCourseCanEditLesson($conn, $lesson_id)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this lesson']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM public_course_lessons WHERE id = ?");
$stmt->bind_param("i", $lesson_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Lesson deleted']);
