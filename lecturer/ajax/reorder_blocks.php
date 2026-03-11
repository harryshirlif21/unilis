<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$lesson_id   = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
$unit_id     = isset($_POST['unit_id'])   ? (int)$_POST['unit_id']   : 0;
$order       = json_decode($_POST['order'] ?? '[]', true);

if (!$lesson_id || !$unit_id || !is_array($order) || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

// Verify ownership
$chk = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id=? AND unit_id=?");
$chk->bind_param("ii", $lecturer_id, $unit_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

$stmt = $conn->prepare("UPDATE lesson_content_blocks SET position=? WHERE id=? AND lesson_id=?");
foreach ($order as $pos => $blockId) {
    $blockId  = (int)$blockId;
    $position = $pos + 1;
    $stmt->bind_param("iii", $position, $blockId, $lesson_id);
    $stmt->execute();
}
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Block order saved']);
