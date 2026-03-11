<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id   = (int)$_SESSION['user_id'];
$question_id   = isset($_POST['question_id'])   ? (int)$_POST['question_id']   : 0;
$assessment_id = isset($_POST['assessment_id']) ? (int)$_POST['assessment_id'] : 0;

if (!$question_id || !$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

// Verify ownership via assessment
$chk = $conn->prepare("
    SELECT 1 FROM questions q
    JOIN assessments a ON q.assignment_id = a.id
    WHERE q.id=? AND q.assignment_id=? AND a.lecturer_id=?
");
$chk->bind_param("iii", $question_id, $assessment_id, $lecturer_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Not found or access denied']); exit;
}
$chk->close();

// Delete options first, then question
$del_opts = $conn->prepare("DELETE FROM question_options WHERE question_id=?");
$del_opts->bind_param("i", $question_id);
$del_opts->execute();
$del_opts->close();

$del_q = $conn->prepare("DELETE FROM questions WHERE id=? AND assignment_id=?");
$del_q->bind_param("ii", $question_id, $assessment_id);
$del_q->execute();
$del_q->close();

echo json_encode(['success' => true, 'message' => 'Question deleted']);
