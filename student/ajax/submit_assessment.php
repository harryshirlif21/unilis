<?php
// student/ajax/submit_assessment.php
// Accepts JSON body, auto-grades MCQ/TF/Matching, stores all answers
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$student_id = $_SESSION['user_id'];
$input      = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']); exit;
}

$assessment_id = intval($input['assessment_id'] ?? 0);
$answers       = $input['answers']   ?? [];
$violations    = $input['violations'] ?? [];

if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'assessment_id required']); exit;
}

try {
    // Fetch assessment
    $stmt = $conn->prepare("SELECT id, total_marks, pass_mark, type, unit_id FROM assessments WHERE id = ? AND is_published = 1");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$assessment) { echo json_encode(['success' => false, 'message' => 'Assessment not found']); exit; }

    // Check duplicate submission
    $stmt = $conn->prepare("SELECT id FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assessment_id, $student_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Already submitted']); exit;
    }
    $stmt->close();

    // Fetch all questions + correct answers
    $stmt = $conn->prepare("SELECT id, question_type, marks, auto_grade FROM assessment_questions WHERE assessment_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $questions_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch correct options for auto-gradable questions
    $correct_map = []; // question_id => correct option_id (MCQ/TF) or [optId => match_pair] (matching)
    foreach ($questions_raw as $q) {
        if (!$q['auto_grade']) continue;
        if (in_array($q['question_type'], ['mcq', 'true_false'])) {
            $os = $conn->prepare("SELECT id FROM question_options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
            $os->bind_param("i", $q['id']);
            $os->execute();
            $opt = $os->get_result()->fetch_assoc();
            $os->close();
            if ($opt) $correct_map[$q['id']] = $opt['id'];
        }
        if ($q['question_type'] === 'matching') {
            $os = $conn->prepare("SELECT id, match_pair FROM question_options WHERE question_id = ?");
            $os->bind_param("i", $q['id']);
            $os->execute();
            $pairs = [];
            $or = $os->get_result();
            while ($p = $or->fetch_assoc()) $pairs[$p['id']] = $p['match_pair'];
            $os->close();
            $correct_map[$q['id']] = $pairs;
        }
    }

    // Auto-grade
    $total_auto_marks = 0;
    $total_possible   = 0;
    $has_manual       = false;
    $graded_answers   = []; // [question_id => {marks_awarded, is_correct, answer_text, selected_option}]

    // Build question lookup by index
    $q_by_index = array_values($questions_raw);

    foreach ($q_by_index as $idx => $q) {
        $ans    = $answers[$idx] ?? null;
        $qid    = $q['id'];
        $marks  = $q['marks'];
        $type   = $q['question_type'];
        $auto   = $q['auto_grade'];

        $marks_awarded  = null;
        $is_correct     = null;
        $answer_text    = null;
        $selected_option = null;
        $file_path      = null;

        if (!$ans) {
            // Not answered — 0 marks for auto-graded
            if ($auto) { $marks_awarded = 0; $is_correct = 0; }
            $graded_answers[$qid] = compact('marks_awarded','is_correct','answer_text','selected_option','file_path');
            if ($auto) $total_possible += $marks;
            else $has_manual = true;
            continue;
        }

        if (in_array($type, ['mcq', 'true_false'])) {
            $selected_option = intval($ans['optionId'] ?? 0);
            if ($auto && isset($correct_map[$qid])) {
                $is_correct    = ($selected_option === $correct_map[$qid]) ? 1 : 0;
                $marks_awarded = $is_correct ? $marks : 0;
                $total_auto_marks += $marks_awarded;
                $total_possible   += $marks;
            }
        }

        elseif ($type === 'matching') {
            if ($auto && isset($correct_map[$qid])) {
                $matchAnswers = $ans['matchAnswers'] ?? [];
                $correct_pairs = $correct_map[$qid]; // optId => match_pair
                $correct_count = 0;
                $total_pairs   = count($correct_pairs);

                foreach ($correct_pairs as $optId => $correctPair) {
                    $studentAnswer = trim($matchAnswers[$optId] ?? '');
                    if (strtolower($studentAnswer) === strtolower(trim($correctPair))) {
                        $correct_count++;
                    }
                }
                // Partial marks: marks_per_pair * correct
                $marks_awarded = $total_pairs > 0 ? round(($marks / $total_pairs) * $correct_count, 2) : 0;
                $is_correct    = ($correct_count === $total_pairs) ? 1 : 0;
                $answer_text   = json_encode($matchAnswers);
                $total_auto_marks += $marks_awarded;
                $total_possible   += $marks;
            }
        }

        elseif ($type === 'short_answer') {
            $answer_text = trim($ans['value'] ?? '');
            $has_manual  = true;
            // Short answer: auto_grade = 0, leave marks_awarded = null
        }

        elseif ($type === 'essay') {
            $answer_text = trim($ans['value'] ?? '');
            $has_manual  = true;
        }

        elseif ($type === 'file_upload') {
            $file_path  = $ans['filePath'] ?? null;
            $has_manual = true;
        }

        if (!$auto) {
            $has_manual = true;
            $total_possible += $marks;
        }

        $graded_answers[$qid] = compact('marks_awarded','is_correct','answer_text','selected_option','file_path');
    }

    // Calculate score percentage (only from auto-gradable questions for now)
    $auto_possible = 0;
    foreach ($questions_raw as $q) {
        if ($q['auto_grade']) $auto_possible += $q['marks'];
    }

    $score_pct = null;
    if (!$has_manual && $auto_possible > 0) {
        $score_pct = round(($total_auto_marks / $auto_possible) * 100, 2);
    } elseif ($auto_possible > 0) {
        // Partial auto score — will be finalised after manual grading
        $score_pct = null;
    }

    $violations_json = !empty($violations) ? json_encode($violations) : null;
    $status          = !empty($violations) && count($violations) >= 5 ? 'flagged' : 'submitted';

    $conn->begin_transaction();

    // Create submission record
    $stmt = $conn->prepare("
        INSERT INTO assessment_submissions (assessment_id, student_id, score, status, violations_json)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iidss", $assessment_id, $student_id, $score_pct, $status, $violations_json);
    $stmt->execute();
    $submission_id = $stmt->insert_id;
    $stmt->close();

    // Store individual answers
    $stmt = $conn->prepare("
        INSERT INTO submission_answers (submission_id, question_id, answer_text, selected_option, file_path, marks_awarded, is_correct)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($graded_answers as $qid => $a) {
        $stmt->bind_param("iisisdii",
            $submission_id,
            $qid,
            $a['answer_text'],
            $a['selected_option'],
            $a['file_path'],
            $a['marks_awarded'],
            $a['is_correct']
        );
        // Fix bind for nullable int
        $sid = $submission_id;
        $ma  = $a['marks_awarded'];
        $ic  = $a['is_correct'];
        $so  = $a['selected_option'];
        $stmt2 = $conn->prepare("
            INSERT INTO submission_answers (submission_id, question_id, answer_text, selected_option, file_path, marks_awarded, is_correct)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt2->bind_param("iisisdi", $sid, $qid, $a['answer_text'], $so, $a['file_path'], $ma, $ic);
        $stmt2->execute();
        $stmt2->close();
    }
    $stmt->close();

    // Log violations to exam_violations table
    foreach ($violations as $v) {
        $vtype    = $v['type']    ?? 'unknown';
        $vdetails = $v['details'] ?? '';
        $vs = $conn->prepare("INSERT INTO exam_violations (submission_id, student_id, violation_type, details) VALUES (?, ?, ?, ?)");
        $vs->bind_param("iiss", $submission_id, $student_id, $vtype, $vdetails);
        $vs->execute();
        $vs->close();
    }

    // Record progress
    $event_map = ['quiz' => 'quiz_score', 'assignment' => 'assignment_score', 'cat' => 'cat_score', 'exam' => 'exam_score'];
    $event_type = $event_map[$assessment['type']] ?? 'quiz_score';
    $ps = $conn->prepare("
        INSERT IGNORE INTO student_progress (student_id, unit_id, assessment_id, event_type, score)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ps->bind_param("iisd", $student_id, $assessment['unit_id'], $assessment_id, $event_type, $score_pct);
    $ps->execute();
    $ps->close();

    $conn->commit();

    $passed = ($score_pct !== null) ? ($score_pct >= $assessment['pass_mark']) : null;

    echo json_encode([
        'success'     => true,
        'message'     => 'Submitted successfully',
        'submission_id'=> $submission_id,
        'score'       => $score_pct,
        'raw_score'   => $total_auto_marks,
        'total_marks' => $assessment['total_marks'],
        'passed'      => $passed,
        'unit_id'     => $assessment['unit_id'],
        'has_manual'  => $has_manual,
        'flagged'     => ($status === 'flagged')
    ]);

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("submit_assessment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
