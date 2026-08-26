<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$course_id = (int)($_GET['course_id'] ?? 0);
if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Course ID required']);
    exit;
}

if (!shortCourseCanView($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$stmt = $conn->prepare('
    SELECT id, module_id, lesson_id, title, type, instructions, pass_mark,
           max_attempts, position, time_limit_minutes, submission_type, due_date
    FROM public_course_assessments
    WHERE course_id = ?
    ORDER BY position ASC, id ASC
');
$stmt->bind_param('i', $course_id);
$stmt->execute();
$result = $stmt->get_result();

$assessments = [];
while ($row = $result->fetch_assoc()) {
    // Per-assessment edit permission, same rule as modules/lessons: primary
    // tutor/owner/admin can edit everything, contributors need a grant on
    // the assessment's specific target.
    $row['can_edit'] = $row['lesson_id']
        ? shortCourseCanEditLesson($conn, (int)$row['lesson_id'])
        : shortCourseCanEditModule($conn, (int)$row['module_id']);
    $assessments[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'assessments' => $assessments]);
