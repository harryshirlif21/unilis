<?php
// student/ajax/submit_assessment.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$student_id = intval($_SESSION['user_id']);
$input      = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request body']); exit;
}

$assessment_id = intval($input['assessment_id'] ?? 0);
$answers       = $input['answers']   ?? [];
$violations    = $input['violations'] ?? [];

if (!$assessment_id) {
    echo json_encode(['success' => false, 'message' => 'assessment_id required']); exit;
}

try {
    // ── Fetch assessment ───────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT id, total_marks, pass_mark, type, unit_id
        FROM assessments
        WHERE id = ? AND is_published = 1
    ");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $assessment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$assessment) {
        echo json_encode(['success' => false, 'message' => 'Assessment not found or not published']); exit;
    }

    // ── Prevent duplicate submission ───────────────────────────────
    $stmt = $conn->prepare("SELECT id FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assessment_id, $student_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Already submitted']); exit;
    }
    $stmt->close();

    // ── Fetch questions ────────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT id, question_type, marks, auto_grade
        FROM assessment_questions
        WHERE assessment_id = ?
        ORDER BY position ASC, id ASC
    ");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $questions_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ── Build correct-answer map ───────────────────────────────────
    $correct_map = [];
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
            $r = $os->get_result();
            while ($p = $r->fetch_assoc()) $pairs[$p['id']] = $p['match_pair'];
            $os->close();
            $correct_map[$q['id']] = $pairs;
        }
    }

    // ── Auto-grade ─────────────────────────────────────────────────
    $total_auto_marks = 0;
    $auto_possible    = 0;
    $has_manual       = false;
    $graded_answers   = [];  // qid => [marks_awarded, is_correct, answer_text, selected_option, file_path]

    foreach (array_values($questions_raw) as $idx => $q) {
        $ans   = $answers[$idx] ?? null;
        $qid   = $q['id'];
        $marks = $q['marks'];
        $type  = $q['question_type'];
        $auto  = $q['auto_grade'];

        $marks_awarded   = null;
        $is_correct      = null;
        $answer_text     = null;
        $selected_option = null;
        $file_path       = null;

        if ($auto) $auto_possible += $marks;
        else       $has_manual = true;

        if (!$ans) {
            if ($auto) { $marks_awarded = 0; $is_correct = 0; }
            $graded_answers[$qid] = compact('marks_awarded','is_correct','answer_text','selected_option','file_path');
            continue;
        }

        if (in_array($type, ['mcq', 'true_false'])) {
            $selected_option = intval($ans['optionId'] ?? 0);
            if ($auto && isset($correct_map[$qid])) {
                $is_correct       = ($selected_option === $correct_map[$qid]) ? 1 : 0;
                $marks_awarded    = $is_correct ? $marks : 0;
                $total_auto_marks += $marks_awarded;
            }

        } elseif ($type === 'matching') {
            if ($auto && isset($correct_map[$qid])) {
                $match_answers  = $ans['matchAnswers'] ?? [];
                $correct_pairs  = $correct_map[$qid];
                $correct_count  = 0;
                $total_pairs    = count($correct_pairs);
                foreach ($correct_pairs as $optId => $correctPair) {
                    if (strtolower(trim($match_answers[$optId] ?? '')) === strtolower(trim($correctPair))) {
                        $correct_count++;
                    }
                }
                $marks_awarded    = $total_pairs > 0 ? round(($marks / $total_pairs) * $correct_count, 2) : 0;
                $is_correct       = ($correct_count === $total_pairs) ? 1 : 0;
                $answer_text      = json_encode($match_answers);
                $total_auto_marks += $marks_awarded;
            }

        } elseif (in_array($type, ['short_answer', 'essay'])) {
            $answer_text = trim($ans['value'] ?? '');
            $has_manual  = true;

        } elseif ($type === 'file_upload') {
            $file_path  = $ans['filePath'] ?? null;
            $has_manual = true;
        }

        $graded_answers[$qid] = compact('marks_awarded','is_correct','answer_text','selected_option','file_path');
    }

    // ── Calculate score ────────────────────────────────────────────
    $score_pct = null;
    if (!$has_manual && $auto_possible > 0) {
        $score_pct = round(($total_auto_marks / $auto_possible) * 100, 2);
    }
    // If has manual questions, score stays null until lecturer grades

    $violations_json = !empty($violations) ? json_encode($violations) : null;
    $status          = (!empty($violations) && count($violations) >= 5) ? 'flagged' : 'submitted';
    $graded_flag     = (!$has_manual && $score_pct !== null) ? 1 : 0;

    $conn->begin_transaction();

    // ── Create submission ──────────────────────────────────────────
    $stmt = $conn->prepare("
        INSERT INTO assessment_submissions (assessment_id, student_id, score, graded, status, violations_json)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iidiss", $assessment_id, $student_id, $score_pct, $graded_flag, $status, $violations_json);
    $stmt->execute();
    $submission_id = $conn->insert_id;
    $stmt->close();

    // ── Store answers ──────────────────────────────────────────────
    $stmt = $conn->prepare("
        INSERT INTO submission_answers
            (submission_id, question_id, answer_text, selected_option, file_path, marks_awarded, is_correct)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($graded_answers as $qid => $a) {
        $stmt->bind_param("iisisdi",
            $submission_id,
            $qid,
            $a['answer_text'],
            $a['selected_option'],
            $a['file_path'],
            $a['marks_awarded'],
            $a['is_correct']
        );
        $stmt->execute();
    }
    $stmt->close();

    // ── Log violations (graceful — table may not exist) ────────────
    if (!empty($violations)) {
        try {
            $vs = $conn->prepare("
                INSERT INTO exam_violations (submission_id, student_id, violation_type, details)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($violations as $v) {
                $vtype    = $v['type']    ?? 'unknown';
                $vdetails = $v['details'] ?? '';
                $vs->bind_param("iiss", $submission_id, $student_id, $vtype, $vdetails);
                $vs->execute();
            }
            $vs->close();
        } catch (mysqli_sql_exception $e) {
            // exam_violations table not yet created — skip silently
            error_log("exam_violations insert skipped: " . $e->getMessage());
        }
    }

    // ── Log to student_progress ────────────────────────────────────
    $event_map  = ['quiz' => 'quiz_score', 'assignment' => 'assignment_score', 'cat' => 'cat_score', 'exam' => 'exam_score'];
    $event_type = $event_map[$assessment['type']] ?? 'quiz_score';
    $unit_id    = intval($assessment['unit_id']);

    $ps = $conn->prepare("
        INSERT INTO student_progress (student_id, unit_id, assessment_id, event_type, score, completed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE score = VALUES(score), completed_at = NOW()
    ");
    $ps->bind_param("iiiss", $student_id, $unit_id, $assessment_id, $event_type, $score_pct);
    $ps->execute();
    $ps->close();

    $conn->commit();

    $passed = ($score_pct !== null && $assessment['pass_mark'])
            ? ($score_pct >= $assessment['pass_mark'])
            : null;

    echo json_encode([
        'success'       => true,
        'message'       => 'Submitted successfully',
        'submission_id' => $submission_id,
        'score'         => $score_pct,
        'passed'        => $passed,
        'unit_id'       => $unit_id,
        'has_manual'    => $has_manual,
        'flagged'       => ($status === 'flagged'),
    ]);

} catch (mysqli_sql_exception $e) {
    if (isset($conn) && $conn->inTransaction ?? false) $conn->rollback();
    error_log("submit_assessment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}