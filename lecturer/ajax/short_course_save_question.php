<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$question_id   = (int)($_POST['question_id'] ?? 0);
$assessment_id = (int)($_POST['assessment_id'] ?? 0);
$question      = trim((string)($_POST['question'] ?? ''));
$type          = $_POST['type'] ?? 'single';
$type          = in_array($type, ['single', 'multiple', 'true_false', 'short_text'], true) ? $type : 'single';
$marks         = max(1, (int)($_POST['marks'] ?? 1));
$optionsRaw    = $_POST['options'] ?? [];
$correctRaw    = $_POST['correct_answer'] ?? '';

if (!$assessment_id || $question === '') {
    echo json_encode(['success' => false, 'message' => 'Assessment and question text are required.']);
    exit;
}

$stmt = $conn->prepare('SELECT course_id, module_id, title FROM public_course_assessments WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assessment) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found.']);
    exit;
}
$allowed = (int)$assessment['module_id']
    ? shortCourseCanEditModule($conn, (int)$assessment['module_id'])
    : shortCourseCanManage($conn, (int)$assessment['course_id']);

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Normalize options/correct_answer by type.
$options = null;
$correct_answer = null;
if ($type === 'single' || $type === 'multiple') {
    $opts = array_values(array_filter(array_map('trim', (array)$optionsRaw), fn($v) => $v !== ''));
    if (count($opts) < 2) {
        echo json_encode(['success' => false, 'message' => 'Provide at least two options.']);
        exit;
    }
    $options = json_encode($opts);
    if ($type === 'single') {
        $correct_answer = trim((string)$correctRaw);
    } else {
        $correctArr = array_values(array_filter(array_map('trim', (array)$correctRaw), fn($v) => $v !== ''));
        $correct_answer = json_encode($correctArr);
    }
} elseif ($type === 'true_false') {
    $correct_answer = ($correctRaw === 'true') ? 'true' : 'false';
} else {
    $correct_answer = trim((string)$correctRaw);
}

if ($question_id) {
    $check = $conn->prepare('SELECT id FROM public_course_questions WHERE id = ? AND assessment_id = ? LIMIT 1');
    $check->bind_param('ii', $question_id, $assessment_id);
    $check->execute();
    $exists = $check->get_result()->fetch_row();
    $check->close();
    if (!$exists) {
        echo json_encode(['success' => false, 'message' => 'Question not found in this assessment.']);
        exit;
    }
    $stmt = $conn->prepare('UPDATE public_course_questions SET question = ?, type = ?, options = ?, correct_answer = ?, marks = ? WHERE id = ?');
    $stmt->bind_param('ssssii', $question, $type, $options, $correct_answer, $marks, $question_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Question updated', 'question_id' => $question_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    $posRes = $conn->prepare('SELECT COALESCE(MAX(position), -1) + 1 AS next_pos FROM public_course_questions WHERE assessment_id = ?');
    $posRes->bind_param('i', $assessment_id);
    $posRes->execute();
    $position = (int)$posRes->get_result()->fetch_assoc()['next_pos'];
    $posRes->close();

    $stmt = $conn->prepare('INSERT INTO public_course_questions (assessment_id, question, type, options, correct_answer, marks, position) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssiii', $assessment_id, $question, $type, $options, $correct_answer, $marks, $position);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Question added', 'question_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    }
    $stmt->close();
}
