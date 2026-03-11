<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id   = (int)$_SESSION['user_id'];
$submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
$status        = trim($_POST['status'] ?? 'graded'); // 'graded' | 'flagged'

// Per-question marks: posted as marks[question_id] = value
$marks_map = isset($_POST['marks']) && is_array($_POST['marks']) ? $_POST['marks'] : [];

if (!$submission_id) {
    echo json_encode(['success' => false, 'message' => 'submission_id required']); exit;
}

$valid_statuses = ['graded', 'flagged'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']); exit;
}

// Verify lecturer owns the assessment this submission belongs to
$chk = $conn->prepare("
    SELECT sub.id, a.total_marks
    FROM assessment_submissions sub
    JOIN assessments a ON sub.assessment_id = a.id
    WHERE sub.id=? AND a.lecturer_id=?
");
$chk->bind_param("ii", $submission_id, $lecturer_id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']); exit;
}

$conn->begin_transaction();
try {
    $total_awarded = 0.0;

    if (!empty($marks_map)) {
        $upd = $conn->prepare("
            UPDATE submission_answers
            SET marks_awarded=?
            WHERE submission_id=? AND question_id=?
        ");
        foreach ($marks_map as $qId => $awarded) {
            $qId     = (int)$qId;
            $awarded = max(0, (float)$awarded);
            $total_awarded += $awarded;
            $upd->bind_param("dii", $awarded, $submission_id, $qId);
            $upd->execute();
        }
        $upd->close();
    } else {
        // Auto-sum existing marks_awarded
        $sum = $conn->prepare("
            SELECT COALESCE(SUM(marks_awarded),0) AS total
            FROM submission_answers WHERE submission_id=?
        ");
        $sum->bind_param("i", $submission_id);
        $sum->execute();
        $total_awarded = (float)$sum->get_result()->fetch_assoc()['total'];
        $sum->close();
    }

    // Update submission
    $upd_sub = $conn->prepare("
        UPDATE assessment_submissions
        SET score=?, status=?, graded_by=?, graded_at=NOW()
        WHERE id=?
    ");
    $upd_sub->bind_param("dsii", $total_awarded, $status, $lecturer_id, $submission_id);
    $upd_sub->execute();
    $upd_sub->close();

    $conn->commit();
    echo json_encode([
        'success'       => true,
        'message'       => 'Submission graded',
        'score'         => $total_awarded,
        'status'        => $status,
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Grading failed: ' . $e->getMessage()]);
}
