<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$assessment_id = (int)($_GET['assessment_id'] ?? 0);
if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
    exit;
}

$stmt = $conn->prepare('SELECT course_id, module_id, title FROM public_course_assessments WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assessment) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found']);
    exit;
}

$canView = shortCourseCanView($conn, (int)$assessment['course_id']);
if (!$canView) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
// Viewing the question list is allowed for anyone who can view the course at
// all (readonly for contributors without edit rights); only saving/deleting
// is gated to can_edit.
$canEdit = (int)$assessment['module_id']
    ? shortCourseCanEditModule($conn, (int)$assessment['module_id'])
    : shortCourseCanManage($conn, (int)$assessment['course_id']);

$stmt = $conn->prepare('SELECT id, question, type, options, correct_answer, marks, position FROM public_course_questions WHERE assessment_id = ? ORDER BY position ASC, id ASC');
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$result = $stmt->get_result();
$questions = [];
while ($row = $result->fetch_assoc()) {
    $row['options'] = $row['options'] ? json_decode($row['options'], true) : [];
    $questions[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'assessment' => ['title' => $assessment['title'], 'type' => 'quiz'],
    'can_view' => $canView,
    'can_edit' => $canEdit,
    'questions' => $questions,
]);
