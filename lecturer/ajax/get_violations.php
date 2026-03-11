<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id   = (int)$_SESSION['user_id'];
$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'assessment_id required']); exit;
}

// Verify lecturer owns the assessment
$chk = $conn->prepare("SELECT 1 FROM assessments WHERE id=? AND lecturer_id=?");
$chk->bind_param("ii", $assessment_id, $lecturer_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

if ($submission_id) {
    // Violations for a specific submission
    $stmt = $conn->prepare("
        SELECT ev.id, ev.submission_id, ev.student_id,
               ev.violation_type, ev.occurred_at, ev.details,
               CONCAT(u.first_name, ' ', u.last_name) AS student_name
        FROM exam_violations ev
        JOIN users u ON ev.student_id = u.id
        WHERE ev.submission_id = ?
        ORDER BY ev.occurred_at ASC
    ");
    $stmt->bind_param("i", $submission_id);
} else {
    // All violations for every submission of this assessment
    $stmt = $conn->prepare("
        SELECT ev.id, ev.submission_id, ev.student_id,
               ev.violation_type, ev.occurred_at, ev.details,
               CONCAT(u.first_name, ' ', u.last_name) AS student_name
        FROM exam_violations ev
        JOIN assessment_submissions sub ON ev.submission_id = sub.id
        JOIN users u ON ev.student_id = u.id
        WHERE sub.assessment_id = ?
        ORDER BY ev.occurred_at DESC
    ");
    $stmt->bind_param("i", $assessment_id);
}

$stmt->execute();
$result     = $stmt->get_result();
$violations = [];
while ($row = $result->fetch_assoc()) $violations[] = $row;
$stmt->close();

// Summary counts by type
$summary = [];
foreach ($violations as $v) {
    $type = $v['violation_type'];
    $summary[$type] = ($summary[$type] ?? 0) + 1;
}

echo json_encode([
    'success'    => true,
    'violations' => $violations,
    'summary'    => $summary,
    'total'      => count($violations),
]);
