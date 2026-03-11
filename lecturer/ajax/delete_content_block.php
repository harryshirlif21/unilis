<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$block_id    = isset($_POST['block_id'])  ? (int)$_POST['block_id']  : 0;
$lesson_id   = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
$unit_id     = isset($_POST['unit_id'])   ? (int)$_POST['unit_id']   : 0;

if (!$block_id || !$lesson_id || !$unit_id) {
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

// If block has a file (image/audio/video), optionally delete it from disk
$file_check = $conn->prepare("SELECT block_type, content FROM lesson_content_blocks WHERE id=? AND lesson_id=?");
$file_check->bind_param("ii", $block_id, $lesson_id);
$file_check->execute();
$block = $file_check->get_result()->fetch_assoc();
$file_check->close();

if ($block && in_array($block['block_type'], ['image','audio','video','diagram'])) {
    $file_path = $block['content'];
    if ($file_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($file_path, '/'))) {
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($file_path, '/'));
    }
}

$stmt = $conn->prepare("DELETE FROM lesson_content_blocks WHERE id=? AND lesson_id=?");
$stmt->bind_param("ii", $block_id, $lesson_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    echo json_encode(['success' => true, 'message' => 'Block deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Block not found']);
}
