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
$description = trim($_POST['description'] ?? '');
$outline     = trim($_POST['outline']     ?? '');

if (!$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid unit']);
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

// UPSERT — update if exists, insert if not
$stmt = $conn->prepare("
    INSERT INTO course_outlines (unit_id, lecturer_id, description, outline, updated_at)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        description = VALUES(description),
        outline     = VALUES(outline),
        updated_at  = NOW()
");
$stmt->bind_param("iiss", $unit_id, $lecturer_id, $description, $outline);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Outline saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();