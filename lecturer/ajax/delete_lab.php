<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$lab_id      = isset($_POST['lab_id'])  ? (int)$_POST['lab_id']  : 0;
$unit_id     = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;

if (!$lab_id || !$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

// Verify ownership
$chk = $conn->prepare("SELECT file_path FROM labs WHERE id=? AND unit_id=? AND lecturer_id=?");
$chk->bind_param("iii", $lab_id, $unit_id, $lecturer_id);
$chk->execute();
$lab = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$lab) {
    echo json_encode(['success' => false, 'message' => 'Lab not found or access denied']); exit;
}

// Delete associated file from disk if it exists
if (!empty($lab['file_path'])) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($lab['file_path'], '/');
    if (file_exists($full_path)) @unlink($full_path);
}

// Delete lab submissions
$stmt = $conn->prepare("DELETE FROM lab_submissions WHERE lab_id=?");
$stmt->bind_param("i", $lab_id);
$stmt->execute();
$stmt->close();

// Delete the lab
$stmt = $conn->prepare("DELETE FROM labs WHERE id=? AND unit_id=? AND lecturer_id=?");
$stmt->bind_param("iii", $lab_id, $unit_id, $lecturer_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Lab deleted']);
