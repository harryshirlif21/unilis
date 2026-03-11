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
$module_id   = isset($_POST['module_id']) ? (int)$_POST['module_id'] : 0;
$unit_id     = isset($_POST['unit_id'])   ? (int)$_POST['unit_id']   : 0;

if (!$module_id || !$unit_id) {
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

// Delete lessons first (FK safety even if no CASCADE set)
$stmt = $conn->prepare("DELETE FROM lessons WHERE module_id = ?");
$stmt->bind_param("i", $module_id);
$stmt->execute();
$stmt->close();

// Delete the module
$stmt = $conn->prepare("DELETE FROM course_modules WHERE id = ? AND unit_id = ?");
$stmt->bind_param("ii", $module_id, $unit_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    echo json_encode(['success' => true, 'message' => 'Module deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Module not found']);
}