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

if (!$assessment_id || !$unit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

// Verify ownership
$chk = $conn->prepare("SELECT 1 FROM assessments WHERE id=? AND lecturer_id=? AND unit_id=?");
$chk->bind_param("iii", $assessment_id, $lecturer_id, $unit_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found or access denied']); exit;
}
$chk->close();

// Delete submission answers → submissions → assessment_questions → assessment
$conn->begin_transaction();
try {
    // submission answers
    $conn->query("
        DELETE sa FROM submission_answers sa
        JOIN assessment_submissions sub ON sa.submission_id = sub.id
        WHERE sub.assessment_id = $assessment_id
    ");
    // exam violations
    $conn->query("
        DELETE ev FROM exam_violations ev
        JOIN assessment_submissions sub ON ev.submission_id = sub.id
        WHERE sub.assessment_id = $assessment_id
    ");
    // submissions
    $s = $conn->prepare("DELETE FROM assessment_submissions WHERE assessment_id=?");
    $s->bind_param("i", $assessment_id); $s->execute(); $s->close();
    // question options
    $conn->query("
        DELETE qo FROM question_options qo
        JOIN questions q ON qo.question_id = q.id
        WHERE q.assignment_id = $assessment_id
    ");
    // questions
    $s = $conn->prepare("DELETE FROM questions WHERE assignment_id=?");
    $s->bind_param("i", $assessment_id); $s->execute(); $s->close();
    // assessment
    $s = $conn->prepare("DELETE FROM assessments WHERE id=?");
    $s->bind_param("i", $assessment_id); $s->execute(); $s->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Assessment deleted']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
}
