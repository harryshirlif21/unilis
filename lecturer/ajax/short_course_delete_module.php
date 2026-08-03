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
$module_id   = (int)($_POST['module_id'] ?? 0);

if (!$course_id || !$module_id) {
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

// Delete lessons first (cascade may not be set)
$conn->query("DELETE FROM public_course_lessons WHERE module_id = $module_id");
// Delete module
$stmt = $conn->prepare("DELETE FROM public_course_modules WHERE id = ? AND course_id = ?");
$stmt->bind_param("ii", $module_id, $course_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Module deleted']);