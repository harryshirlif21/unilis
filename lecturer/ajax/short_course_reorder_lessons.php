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
$order       = $_POST['order'] ?? '[]';

if (!$course_id || !$module_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
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

$ids = json_decode($order, true);
if (!is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order data']);
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