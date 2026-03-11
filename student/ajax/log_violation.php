<?php
// student/ajax/log_violation.php
// Logs a single proctoring violation in real-time during exam
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false]); exit;
}

$student_id    = $_SESSION['user_id'];
$assessment_id = intval($_POST['assessment_id'] ?? 0);
$vtype         = trim($_POST['violation_type']  ?? '');
$details       = trim($_POST['details']         ?? '');

if (!$assessment_id || !$vtype) {
    echo json_encode(['success' => false]); exit;
}

try {
    // Get or create a pending submission record for this student
    $stmt = $conn->prepare("SELECT id FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assessment_id, $student_id);
    $stmt->execute();
    $sub = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sub) {
        // Create a placeholder submission that will be updated on final submit
        $stmt = $conn->prepare("INSERT INTO assessment_submissions (assessment_id, student_id, status) VALUES (?, ?, 'submitted')");
        $stmt->bind_param("ii", $assessment_id, $student_id);
        $stmt->execute();
        $submission_id = $stmt->insert_id;
        $stmt->close();
    } else {
        $submission_id = $sub['id'];
    }

    // Insert violation
    $stmt = $conn->prepare("INSERT INTO exam_violations (submission_id, student_id, violation_type, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $submission_id, $student_id, $vtype, $details);
    $stmt->execute();
    $stmt->close();

    // Count violations — flag if >= 5
    $stmt = $conn->prepare("SELECT COUNT(*) FROM exam_violations WHERE submission_id = ?");
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $stmt->bind_result($vcount);
    $stmt->fetch();
    $stmt->close();

    if ($vcount >= 5) {
        $flag = 'flagged';
        $us = $conn->prepare("UPDATE assessment_submissions SET status = ? WHERE id = ?");
        $us->bind_param("si", $flag, $submission_id);
        $us->execute();
        $us->close();
    }

    echo json_encode(['success' => true, 'violation_count' => $vcount]);

} catch (mysqli_sql_exception $e) {
    error_log("log_violation: " . $e->getMessage());
    echo json_encode(['success' => false]);
}
