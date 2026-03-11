<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$lecturer_id   = (int)$_SESSION['user_id'];
$assessment_id = isset($_POST['assessment_id']) ? (int)$_POST['assessment_id'] : 0;
$unit_id       = isset($_POST['unit_id'])       ? (int)$_POST['unit_id']       : 0;
$module_id     = isset($_POST['module_id'])     ? (int)$_POST['module_id']     : null;
$lesson_id     = isset($_POST['lesson_id'])     ? (int)$_POST['lesson_id']     : null;
$title         = trim($_POST['title']        ?? '');
$type          = trim($_POST['type']         ?? '');
$instructions  = trim($_POST['instructions'] ?? '');
$time_limit    = isset($_POST['time_limit_mins']) && $_POST['time_limit_mins'] !== '' ? (int)$_POST['time_limit_mins'] : null;
$total_marks   = isset($_POST['total_marks']) ? (int)$_POST['total_marks'] : 0;
$pass_mark     = isset($_POST['pass_mark'])   ? (int)$_POST['pass_mark']   : 0;
$due_date      = !empty($_POST['due_date'])   ? $_POST['due_date']         : null;

$valid_types = ['quiz', 'assignment', 'cat', 'exam'];
if (!$unit_id || !$title || !in_array($type, $valid_types)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid required fields']); exit;
}

// Verify lecturer owns unit
$chk = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id=? AND unit_id=?");
$chk->bind_param("ii", $lecturer_id, $unit_id);
$chk->execute();
if (!$chk->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}
$chk->close();

if ($assessment_id) {
    $stmt = $conn->prepare("
        UPDATE assessments
        SET title=?, type=?, instructions=?, time_limit_mins=?,
            total_marks=?, pass_mark=?, due_date=?, module_id=?, lesson_id=?
        WHERE id=? AND unit_id=? AND lecturer_id=?
    ");
    $stmt->bind_param("sssiiisiiiii",
        $title, $type, $instructions, $time_limit,
        $total_marks, $pass_mark, $due_date, $module_id, $lesson_id,
        $assessment_id, $unit_id, $lecturer_id
    );
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Assessment updated', 'assessment_id' => $assessment_id]);
} else {
    $stmt = $conn->prepare("
        INSERT INTO assessments
            (unit_id, lecturer_id, module_id, lesson_id, title, type,
             instructions, time_limit_mins, total_marks, pass_mark, due_date)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param("iiissssiiss",
        $unit_id, $lecturer_id, $module_id, $lesson_id, $title, $type,
        $instructions, $time_limit, $total_marks, $pass_mark, $due_date
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Assessment created', 'assessment_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
}
