<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$option_id   = isset($_POST['option_id'])   ? (int)$_POST['option_id']   : 0;
$question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
$option_text = trim($_POST['option_text'] ?? '');
$is_correct  = isset($_POST['is_correct'])  ? (int)(bool)$_POST['is_correct'] : 0;
$match_pair  = trim($_POST['match_pair'] ?? '') ?: null;
$position    = isset($_POST['position'])    ? (int)$_POST['position'] : 0;

if (!$question_id || !$option_text) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit;
}

// Verify lecturer owns this question via assessment
$chk = $conn->prepare("
    SELECT 1 FROM questions q
    JOIN assessments a ON q.assignment_id = a.id
    WHERE q.id=? AND a.lecturer_id=?
");
$chk->bind_param("ii", $question_id, $lecturer_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

// If marking this option correct on a single-answer question,
// optionally clear other correct flags first
if ($is_correct) {
    $clear = $conn->prepare("UPDATE question_options SET is_correct=0 WHERE question_id=?");
    $clear->bind_param("i", $question_id);
    $clear->execute();
    $clear->close();
}

if ($option_id) {
    $stmt = $conn->prepare("
        UPDATE question_options
        SET option_text=?, is_correct=?, match_pair=?, position=?
        WHERE id=? AND question_id=?
    ");
    $stmt->bind_param("sisiii", $option_text, $is_correct, $match_pair, $position, $option_id, $question_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Option updated', 'option_id' => $option_id]);
} else {
    // auto-position if not supplied
    if (!$position) {
        $ps = $conn->prepare("SELECT COALESCE(MAX(position),0)+1 AS p FROM question_options WHERE question_id=?");
        $ps->bind_param("i", $question_id);
        $ps->execute();
        $position = (int)$ps->get_result()->fetch_assoc()['p'];
        $ps->close();
    }
    $stmt = $conn->prepare("
        INSERT INTO question_options (question_id, option_text, is_correct, match_pair, position)
        VALUES (?,?,?,?,?)
    ");
    $stmt->bind_param("isisi", $question_id, $option_text, $is_correct, $match_pair, $position);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Option added', 'option_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
}
