<?php
// student/ajax/log_violation.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false]); exit;
}

$student_id     = intval($_SESSION['user_id']);
$assessment_id  = intval($_POST['assessment_id']  ?? 0);
$violation_type = substr(trim($_POST['violation_type'] ?? ''), 0, 64);
$details        = substr(trim($_POST['details']        ?? ''), 0, 255);

if (!$assessment_id || !$violation_type) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
}

try {
    // Find existing submission (in_progress or submitted) to attach violation to
    $stmt = $conn->prepare("SELECT id FROM assessment_submissions WHERE assessment_id = ? AND student_id = ? LIMIT 1");
    $stmt->bind_param("ii", $assessment_id, $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $submission_id = null;

    if ($row) {
        $submission_id = intval($row['id']);
    } else {
        // No submission yet — create an in_progress shell so the violation has a parent
        $stmt = $conn->prepare("INSERT INTO assessment_submissions (assessment_id, student_id, status) VALUES (?, ?, 'in_progress')");
        $stmt->bind_param("ii", $assessment_id, $student_id);
        $stmt->execute();
        $submission_id = intval($conn->insert_id);
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO exam_violations (submission_id, student_id, violation_type, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $submission_id, $student_id, $violation_type, $details);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);

} catch (mysqli_sql_exception $e) {
    error_log("log_violation error: " . $e->getMessage());
    // Always return success — violations are secondary to the exam itself
    echo json_encode(['success' => true]);
}