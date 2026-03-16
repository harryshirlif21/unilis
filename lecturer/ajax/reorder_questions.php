<?php
// ajax/reorder_questions.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id   = $_SESSION['user_id'];
$assessment_id = intval($_POST['assessment_id'] ?? 0);
$order         = json_decode($_POST['order'] ?? '[]', true);

if (!$assessment_id || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM assessments WHERE id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $assessment_id, $lecturer_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE assessment_questions SET position = ? WHERE id = ? AND assessment_id = ?");
    foreach ($order as $pos => $qid) {
        $p = intval($pos); $q = intval($qid);
        $stmt->bind_param("iii", $p, $q, $assessment_id);
        $stmt->execute();
    }
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Reordered']);
} catch (mysqli_sql_exception $e) {
    error_log("reorder_questions: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}