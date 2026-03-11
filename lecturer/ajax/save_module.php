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
$unit_id     = isset($_POST['unit_id'])  ? (int)$_POST['unit_id']  : 0;
$module_id   = isset($_POST['module_id'])? (int)$_POST['module_id']: 0;
$title       = trim($_POST['title'] ?? '');

if (!$unit_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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

if ($module_id) {
    // UPDATE existing module
    $stmt = $conn->prepare("
        UPDATE course_modules SET title = ? 
        WHERE id = ? AND unit_id = ?
    ");
    $stmt->bind_param("sii", $title, $module_id, $unit_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        // Could be same title — check existence
        $exists = $conn->prepare("SELECT id FROM course_modules WHERE id = ? AND unit_id = ?");
        $exists->bind_param("ii", $module_id, $unit_id);
        $exists->execute();
        $found = $exists->get_result()->fetch_row();
        $exists->close();
        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'Module not found']);
            exit;
        }
    }
    echo json_encode(['success' => true, 'message' => 'Module updated', 'module_id' => $module_id]);

} else {
    // INSERT new module — position = max + 1
    $posStmt = $conn->prepare("SELECT COALESCE(MAX(position), 0) + 1 AS pos FROM course_modules WHERE unit_id = ?");
    $posStmt->bind_param("i", $unit_id);
    $posStmt->execute();
    $position = (int)$posStmt->get_result()->fetch_assoc()['pos'];
    $posStmt->close();

    $stmt = $conn->prepare("
        INSERT INTO course_modules (unit_id, lecturer_id, title, position)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iisi", $unit_id, $lecturer_id, $title, $position);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Module created', 'module_id' => $new_id]);
}