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
$module_id   = (int)($_POST['module_id'] ?? 0);

if (!$course_id || !$module_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Verify module belongs to course
$stmt = $conn->prepare("SELECT id FROM public_course_modules WHERE id = ? AND course_id = ?");
$stmt->bind_param("ii", $module_id, $course_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Module not found']);
    $stmt->close();
    exit;
}
$stmt->close();

// Check granular module edit permission for deletions
if (!shortCourseCanEditModule($conn, $module_id)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this module']);
    exit;
}

// Delete lessons first (cascade may not be set)
$conn->query("DELETE FROM public_course_lessons WHERE module_id = $module_id");
// Delete module
$stmt = $conn->prepare("DELETE FROM public_course_modules WHERE id = ? AND course_id = ?");
$stmt->bind_param("ii", $module_id, $course_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Module deleted']);
