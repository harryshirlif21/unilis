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
$question_text = trim($_POST['question_text'] ?? '');
$question_type = trim($_POST['question_type'] ?? '');
$marks         = isset($_POST['marks']) ? (int)$_POST['marks'] : 1;
$ai_rubric     = trim($_POST['ai_rubric']     ?? '');
$correct_answer= trim($_POST['correct_answer']?? '');

$valid_types = ['multiple_choice', 'short_answer', 'speech'];
if (!$assessment_id || !$question_text || !in_array($question_type, $valid_types)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']); exit;
}

// Verify lecturer owns the assessment
$chk = $conn->prepare("SELECT 1 FROM assessments WHERE id=? AND lecturer_id=?");
$chk->bind_param("ii", $assessment_id, $lecturer_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

$ai_rubric_val      = $ai_rubric      ?: null;
$correct_answer_val = $correct_answer ?: null;

if ($question_id) {
    $stmt = $conn->prepare("
        UPDATE questions
        SET question_text=?, question_type=?, marks=?, ai_rubric=?, correct_answer=?
        WHERE id=? AND assignment_id=?
    ");
    $stmt->bind_param("ssissii",
        $question_text, $question_type, $marks,
        $ai_rubric_val, $correct_answer_val,
        $question_id, $assessment_id
    );
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Question updated', 'question_id' => $question_id]);
} else {
    $stmt = $conn->prepare("
        INSERT INTO questions (assignment_id, question_text, question_type, marks, ai_rubric, correct_answer)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ississ",
        $assessment_id, $question_text, $question_type,
        $marks, $ai_rubric_val, $correct_answer_val
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Question added', 'question_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
}
