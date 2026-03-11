<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$unit_id     = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
$order       = json_decode($_POST['order'] ?? '[]', true);

if (!$unit_id || !is_array($order) || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Verify lecturer owns this unit
$check = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
$check->bind_param("ii", $lecturer_id, $unit_id);
$check->execute();
if (!$check->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$check->close();

$stmt = $conn->prepare("UPDATE course_modules SET position = ? WHERE id = ? AND unit_id = ?");
foreach ($order as $pos => $moduleId) {
    $moduleId = (int)$moduleId;
    $position = $pos + 1;
    $stmt->bind_param("iii", $position, $moduleId, $unit_id);
    $stmt->execute();
}
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Order saved']);