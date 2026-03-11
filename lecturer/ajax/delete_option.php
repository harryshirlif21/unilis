<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$option_id   = isset($_POST['option_id'])   ? (int)$_POST['option_id']   : 0;
$question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;

if (!$option_id || !$question_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

// Verify ownership
$chk = $conn->prepare("
    SELECT 1 FROM question_options qo
    JOIN questions q  ON qo.question_id  = q.id
    JOIN assessments a ON q.assignment_id = a.id
    WHERE qo.id=? AND qo.question_id=? AND a.lecturer_id=?
");
$chk->bind_param("iii", $option_id, $question_id, $lecturer_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Not found or access denied']); exit;
}
$chk->close();

$stmt = $conn->prepare("DELETE FROM question_options WHERE id=? AND question_id=?");
$stmt->bind_param("ii", $option_id, $question_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Option deleted']);
