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
$order       = $_POST['order'] ?? '[]';

if (!$course_id || !$module_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$ids = json_decode($order, true);
if (!is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order data']);
    exit;
}

// Check module edit permission (reordering lessons within a module requires module edit access)
if (!shortCourseCanEditModule($conn, $module_id)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to reorder lessons in this module']);
    exit;
}

// Update positions
$stmt = $conn->prepare("UPDATE public_course_lessons SET position = ? WHERE id = ? AND module_id = ?");
foreach ($ids as $index => $id) {
    $stmt->bind_param("iii", $index, $id, $module_id);
    $stmt->execute();
}
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Lessons reordered']);
