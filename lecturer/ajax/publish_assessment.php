<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id   = (int)$_SESSION['user_id'];
$assessment_id = isset($_POST['assessment_id']) ? (int)$_POST['assessment_id'] : 0;
$unit_id       = isset($_POST['unit_id'])       ? (int)$_POST['unit_id']       : 0;
// publish=1 to publish, publish=0 to unpublish
$publish       = isset($_POST['publish']) ? (int)(bool)$_POST['publish'] : 1;

if (!$assessment_id || !$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

// Verify ownership
$chk = $conn->prepare("SELECT id FROM assessments WHERE id=? AND lecturer_id=? AND unit_id=?");
$chk->bind_param("iii", $assessment_id, $lecturer_id, $unit_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found or access denied']); exit;
}
$chk->close();

// Must have at least one question before publishing
if ($publish) {
    $qchk = $conn->prepare("SELECT COUNT(*) AS cnt FROM questions WHERE assignment_id=?");
    $qchk->bind_param("i", $assessment_id);
    $qchk->execute();
    $cnt = (int)$qchk->get_result()->fetch_assoc()['cnt'];
    $qchk->close();
    if ($cnt === 0) {
        echo json_encode(['success' => false, 'message' => 'Add at least one question before publishing']); exit;
    }
}

$stmt = $conn->prepare("UPDATE assessments SET is_published=? WHERE id=?");
$stmt->bind_param("ii", $publish, $assessment_id);
$stmt->execute();
$stmt->close();

$msg = $publish ? 'Assessment published' : 'Assessment unpublished';
echo json_encode(['success' => true, 'message' => $msg, 'is_published' => $publish]);
