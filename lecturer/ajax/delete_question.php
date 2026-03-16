<?php
// ajax/delete_question.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id   = $_SESSION['user_id'];
$question_id   = intval($_POST['question_id']   ?? 0);
$assessment_id = intval($_POST['assessment_id'] ?? 0);

if (!$question_id || !$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']); exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM assessments WHERE id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $assessment_id, $lecturer_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
    }
    $stmt->close();

    // Delete options first (no CASCADE defined on FK)
    $stmt = $conn->prepare("DELETE FROM question_options WHERE question_id = ?");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $stmt->close();

    // Delete question
    $stmt = $conn->prepare("DELETE FROM assessment_questions WHERE id = ? AND assessment_id = ?");
    $stmt->bind_param("ii", $question_id, $assessment_id);
    $stmt->execute();
    $stmt->close();

    // Reorder remaining
    $stmt = $conn->prepare("SELECT id FROM assessment_questions WHERE assessment_id = ? ORDER BY position ASC, id ASC");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pos = 0;
    $upd = $conn->prepare("UPDATE assessment_questions SET position = ? WHERE id = ?");
    while ($row = $result->fetch_assoc()) {
        $upd->bind_param("ii", $pos, $row['id']);
        $upd->execute();
        $pos++;
    }
    $upd->close();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Question deleted']);
} catch (mysqli_sql_exception $e) {
    error_log("delete_question: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}