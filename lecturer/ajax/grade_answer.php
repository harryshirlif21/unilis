<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/validation.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success'=>false,'message'=>'Unauthorised']); exit;
}

$lecturer_id   = intval($_SESSION['user_id']);

// Validate and sanitize inputs
$validation_rules = [
    'submission_id' => ['type' => 'int', 'min' => 1],
    'question_id' => ['type' => 'int', 'min' => 1],
    'marks_awarded' => ['type' => 'string', 'max_length' => 10]
];

$sanitized = sanitize_array($_POST, $validation_rules);

if (!$sanitized || !isset($sanitized['submission_id']) || !isset($sanitized['question_id']) || !isset($sanitized['marks_awarded'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid or missing fields']); exit;
}

$submission_id = $sanitized['submission_id'];
$question_id   = $sanitized['question_id'];
$marks_awarded_input = $sanitized['marks_awarded'];

// Validate marks awarded is a valid number
if (!is_numeric($marks_awarded_input)) {
    echo json_encode(['success'=>false,'message'=>'Invalid marks value']); exit;
}

$marks_awarded = floatval($marks_awarded_input);
if ($marks_awarded < 0) {
    echo json_encode(['success'=>false,'message'=>'Marks cannot be negative']); exit;
}

try {
    $stmt = $conn->prepare("SELECT aq.marks FROM assessment_submissions asub JOIN assessments a ON a.id=asub.assessment_id JOIN assessment_questions aq ON aq.id=? AND aq.assessment_id=a.id WHERE asub.id=? AND a.lecturer_id=? LIMIT 1");
    if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]); exit; }
    $stmt->bind_param("iii", $question_id, $submission_id, $lecturer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found','debug'=>"sub=$submission_id q=$question_id lec=$lecturer_id"]); exit; }
    if ($marks_awarded > floatval($row['marks'])) { echo json_encode(['success'=>false,'message'=>'Exceeds max '.$row['marks']]); exit; }
    $stmt = $conn->prepare("SELECT id FROM submission_answers WHERE submission_id=? AND question_id=? LIMIT 1");
    $stmt->bind_param("ii", $submission_id, $question_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        $stmt = $conn->prepare("UPDATE submission_answers SET marks_awarded=? WHERE submission_id=? AND question_id=?");
        $stmt->bind_param("dii", $marks_awarded, $submission_id, $question_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO submission_answers (submission_id, question_id, marks_awarded) VALUES (?,?,?)");
        $stmt->bind_param("iid", $submission_id, $question_id, $marks_awarded);
    }
    if (!$stmt->execute()) { echo json_encode(['success'=>false,'message'=>'Execute: '.$stmt->error]); exit; }
    $stmt->close();
    echo json_encode(['success'=>true,'marks_awarded'=>$marks_awarded]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}