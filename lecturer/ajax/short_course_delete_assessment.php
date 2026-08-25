<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$assessment_id = (int)($_POST['assessment_id'] ?? 0);
if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
    exit;
}

$stmt = $conn->prepare('SELECT course_id, module_id, lesson_id FROM public_course_assessments WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found']);
    exit;
}

$allowed = $row['lesson_id']
    ? shortCourseCanEditLesson($conn, (int)$row['lesson_id'])
    : shortCourseCanEditModule($conn, (int)$row['module_id']);

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM public_course_assessments WHERE id = ?');
$stmt->bind_param('i', $assessment_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
}
$stmt->close();
