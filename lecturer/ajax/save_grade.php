<?php
// lecturer/ajax/save_grade.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id   = intval($_SESSION['user_id']);
$student_id    = intval($_POST['student_id']    ?? 0);
$assessment_id = intval($_POST['assessment_id'] ?? 0);
$score         = floatval($_POST['score']        ?? -1);

if (!$student_id || !$assessment_id || $score < 0 || $score > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']); exit;
}

// Verify lecturer owns this assessment
try {
    $stmt = $conn->prepare("SELECT id, pass_mark, type, unit_id FROM assessments WHERE id = ? AND lecturer_id = ? LIMIT 1");
    $stmt->bind_param("ii", $assessment_id, $lecturer_id);
    $stmt->execute();
    $assess = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$assess) { echo json_encode(['success' => false, 'message' => 'Assessment not found']); exit; }
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB error']); exit;
}

try {
    // Upsert submission with new score
    $stmt = $conn->prepare("
        INSERT INTO assessment_submissions (assessment_id, student_id, score, status, submitted_at)
        VALUES (?, ?, ?, 'graded', NOW())
        ON DUPLICATE KEY UPDATE score = VALUES(score), status = 'graded'
    ");
    $stmt->bind_param("iid", $assessment_id, $student_id, $score);
    $stmt->execute();
    $stmt->close();

    // Upsert student_progress — uses completed_at not created_at
    $event_map  = ['quiz' => 'quiz_score', 'assignment' => 'assignment_score', 'cat' => 'cat_score', 'exam' => 'exam_score'];
    $event_type = $event_map[$assess['type']] ?? 'quiz_score';
    $unit_id    = intval($assess['unit_id']);

    $stmt = $conn->prepare("
        INSERT INTO student_progress (student_id, unit_id, assessment_id, event_type, score, completed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE score = VALUES(score), event_type = VALUES(event_type)
    ");
    $stmt->bind_param("iiisd", $student_id, $unit_id, $assessment_id, $event_type, $score);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}