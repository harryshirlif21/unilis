<?php
// ajax/publish_assessment.php — toggle is_published flag
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id   = $_SESSION['user_id'];
$assessment_id = intval($_POST['assessment_id'] ?? 0);
if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing assessment_id']); exit;
}

try {
    // Get current state
    $stmt = $conn->prepare("SELECT is_published FROM assessments WHERE id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $assessment_id, $lecturer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }

    $new_state = $row['is_published'] ? 0 : 1;
    $stmt = $conn->prepare("UPDATE assessments SET is_published = ? WHERE id = ? AND lecturer_id = ?");
    $stmt->bind_param("iii", $new_state, $assessment_id, $lecturer_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'published' => (bool)$new_state,
                      'message' => $new_state ? 'Published' : 'Unpublished']);
} catch (mysqli_sql_exception $e) {
    error_log("publish_assessment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}