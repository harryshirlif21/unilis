<?php
// lecturer/ajax/finalize_grade.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success'=>false,'message'=>'Unauthorised']); exit;
}

$lecturer_id   = intval($_SESSION['user_id']);
$submission_id = intval($_POST['submission_id'] ?? 0);
$score         = floatval($_POST['score']        ?? -1);
$status        = in_array($_POST['status'] ?? '', ['graded','flagged']) ? $_POST['status'] : 'graded';

if (!$submission_id || $score < 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid data']); exit;
}

try {
    // Verify ownership
    $stmt = $conn->prepare("
        SELECT a.id AS assessment_id, a.type, a.unit_id, asub.student_id
        FROM assessment_submissions asub
        JOIN assessments a ON a.id = asub.assessment_id
        WHERE asub.id = ? AND a.lecturer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $submission_id, $lecturer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'Not found or access denied']); exit; }

    // Update submission
    $stmt = $conn->prepare("
        UPDATE assessment_submissions
        SET score = ?, status = ?, graded_by = ?, graded_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("dsii", $score, $status, $lecturer_id, $submission_id);
    $stmt->execute();
    $stmt->close();

    // Update student_progress
    $event_map  = ['quiz'=>'quiz_score','assignment'=>'assignment_score','cat'=>'cat_score','exam'=>'exam_score'];
    $event_type = $event_map[$row['type']] ?? 'quiz_score';
    $student_id = intval($row['student_id']);
    $unit_id    = intval($row['unit_id']);
    $assess_id  = intval($row['assessment_id']);

    $stmt = $conn->prepare("
        INSERT INTO student_progress (student_id, unit_id, assessment_id, event_type, score, completed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE score = VALUES(score), event_type = VALUES(event_type)
    ");
    $stmt->bind_param("iiisd", $student_id, $unit_id, $assess_id, $event_type, $score);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success'=>true, 'score'=>$score, 'status'=>$status]);
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}
