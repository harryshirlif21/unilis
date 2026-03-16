<?php
// ajax/save_question.php — save question + replace its options atomically
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id   = $_SESSION['user_id'];
$assessment_id = intval($_POST['assessment_id'] ?? 0);
$question_id   = intval($_POST['question_id']   ?? 0);
$question_text = trim($_POST['question_text']   ?? '');
$question_type = trim($_POST['question_type']   ?? '');
$marks         = intval($_POST['marks']         ?? 1);
$auto_grade    = intval($_POST['auto_grade']    ?? 0);
$position      = intval($_POST['position']      ?? 0);
$options_json  = $_POST['options']              ?? '[]';
$options       = json_decode($options_json, true) ?: [];

$allowed_types = ['mcq','true_false','matching','short_answer','essay','file_upload'];

if (!$assessment_id || !$question_text || !in_array($question_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit;
}

// Verify lecturer owns this assessment
try {
    $stmt = $conn->prepare("SELECT id FROM assessments WHERE id = ? AND lecturer_id = ?");
    $stmt->bind_param("ii", $assessment_id, $lecturer_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
    }
    $stmt->close();

    $conn->begin_transaction();

    if ($question_id) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE assessment_questions
            SET question_text = ?, question_type = ?, marks = ?, auto_grade = ?, position = ?
            WHERE id = ? AND assessment_id = ?
        ");
        $stmt->bind_param("ssiiiii", $question_text, $question_type, $marks, $auto_grade, $position, $question_id, $assessment_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO assessment_questions (assessment_id, question_text, question_type, marks, auto_grade, position)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issiii", $assessment_id, $question_text, $question_type, $marks, $auto_grade, $position);
        $stmt->execute();
        $question_id = $stmt->insert_id;
        $stmt->close();
    }

    // Replace options: delete existing, re-insert
    $has_options = in_array($question_type, ['mcq', 'true_false', 'matching']);
    $option_ids  = [];

    if ($has_options && !empty($options)) {
        // Delete all existing options for this question
        $stmt = $conn->prepare("DELETE FROM question_options WHERE question_id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->close();

        // Re-insert options in order
        $stmt = $conn->prepare("
            INSERT INTO question_options (question_id, option_text, is_correct, match_pair, position)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($options as $pos => $opt) {
            $opt_text   = trim($opt['text']       ?? '');
            $is_correct = intval($opt['is_correct'] ?? 0);
            $match_pair = trim($opt['match_pair'] ?? '');
            $pos_int    = intval($pos);

            $stmt->bind_param("isisi", $question_id, $opt_text, $is_correct, $match_pair, $pos_int);
            $stmt->execute();
            $option_ids[] = $stmt->insert_id;
        }
        $stmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success'     => true,
        'message'     => 'Question saved',
        'question_id' => $question_id,
        'option_ids'  => $option_ids
    ]);

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("save_question: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}