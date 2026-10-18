<?php
require_once '../config/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];
$assignment_id = intval($_GET['id'] ?? 0);

if ($assignment_id <= 0) {
    die("Invalid assignment ID.");
}

$already_submitted = false;
$previous_score = 0;
$results = [];
$error_message = "";
$submission_id = null;

/* ===============================
   FETCH ASSIGNMENT
================================= */
$assignment_stmt = $conn->prepare("
    SELECT a.*, u.name AS unit_name
    FROM interactive_assignments a
    JOIN units u ON a.unit_id = u.id
    WHERE a.id=?");
$assignment_stmt->bind_param("i", $assignment_id);
$assignment_stmt->execute();
$assignment = $assignment_stmt->get_result()->fetch_assoc();
$assignment_stmt->close();

if (!$assignment) die("Assignment not found.");

/* ===============================
   FETCH QUESTIONS
================================= */
$questions = [];
$q_stmt = $conn->prepare("SELECT * FROM interactive_questions 
                          WHERE interactive_assignment_id=? ORDER BY id ASC");
$q_stmt->bind_param("i", $assignment_id);
$q_stmt->execute();
$q_res = $q_stmt->get_result();

while ($q = $q_res->fetch_assoc()) {

    $q['options'] = [];

    if ($q['question_type'] === 'multiple_choice') {
        $opt_stmt = $conn->prepare("SELECT id, option_text, is_correct 
                                    FROM interactive_options WHERE question_id=?");
        $opt_stmt->bind_param("i", $q['id']);
        $opt_stmt->execute();
        $opt_res = $opt_stmt->get_result();
        while ($opt = $opt_res->fetch_assoc()) {
            $q['options'][] = $opt;
        }
        $opt_stmt->close();
    }

    $questions[] = $q;
}
$q_stmt->close();

/* ===============================
   CHECK IF ALREADY SUBMITTED
================================= */
$stmt = $conn->prepare("SELECT id, score FROM interactive_submissions 
                        WHERE assignment_id=? AND student_id=?");
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {

    $already_submitted = true;
    $previous_score = $existing['score'];
    $submission_id = $existing['id'];

    $ans_stmt = $conn->prepare("
        SELECT question_id, option_id, answer_text, marks_awarded, is_correct
        FROM interactive_answers
        WHERE submission_id=?");
    $ans_stmt->bind_param("i", $submission_id);
    $ans_stmt->execute();
    $ans_res = $ans_stmt->get_result();

    while ($row = $ans_res->fetch_assoc()) {
        $results[$row['question_id']] = [
            'answer' => $row['option_id'] ?? $row['answer_text'],
            'marks_awarded' => $row['marks_awarded'],
            'is_correct' => $row['is_correct']
        ];
    }
    $ans_stmt->close();
}

/* ===============================
   HANDLE SUBMISSION
================================= */
if (!$already_submitted && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $answers = $_POST['answers'] ?? [];

    if (count($answers) !== count($questions)) {
        $error_message = "Please answer all questions.";
    } else {

        try {
            $conn->begin_transaction();

            /* Insert submission */
            $stmt = $conn->prepare("
                INSERT INTO interactive_submissions
                (student_id, assignment_id, submitted_at)
                VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $student_id, $assignment_id);
            $stmt->execute();
            $submission_id = $stmt->insert_id;
            $stmt->close();

            $total_score = 0;

            foreach ($questions as $q) {

                $question_id = $q['id'];
                $answer = $answers[$question_id];
                $points = (float)$q['points'];
                $marks_awarded = 0;
                $is_correct = 0;
                $correct_answer_text = '';

                if ($q['question_type'] === 'multiple_choice') {

                    foreach ($q['options'] as $opt) {
                        if ($opt['is_correct']) {
                            $correct_answer_text = $opt['option_text'];
                        }
                        if ($opt['id'] == $answer && $opt['is_correct']) {
                            $is_correct = 1;
                            $marks_awarded = $points;
                        }
                    }

                    $opt_id = intval($answer);
                    $text_answer = null;

                } else {

                    $correct_stmt = $conn->prepare("
                        SELECT option_text FROM interactive_options
                        WHERE question_id=? AND is_correct=1 LIMIT 1");
                    $correct_stmt->bind_param("i", $question_id);
                    $correct_stmt->execute();
                    $row = $correct_stmt->get_result()->fetch_assoc();
                    $correct_stmt->close();

                    $correct_answer_text = $row ? $row['option_text'] : '';

                    if (strtolower(trim($answer)) === strtolower(trim($correct_answer_text))) {
                        $is_correct = 1;
                        $marks_awarded = $points;
                    }

                    $opt_id = null;
                    $text_answer = $answer;
                }

                $total_score += $marks_awarded;

                /* Insert answer */
                $ins_stmt = $conn->prepare("
                    INSERT INTO interactive_answers
                    (submission_id, question_id, option_id, answer_text, marks_awarded, is_correct)
                    VALUES (?, ?, ?, ?, ?, ?)");

                $ins_stmt->bind_param(
                    "iiisdi",
                    $submission_id,
                    $question_id,
                    $opt_id,
                    $text_answer,
                    $marks_awarded,
                    $is_correct
                );
                $ins_stmt->execute();
                $ins_stmt->close();

                $results[$question_id] = [
                    'answer' => $answer,
                    'marks_awarded' => $marks_awarded,
                    'is_correct' => $is_correct,
                    'correct_answer' => $correct_answer_text,
                    'points' => $points
                ];
            }

            /* Update total */
            $update_stmt = $conn->prepare("
                UPDATE interactive_submissions
                SET score=?, graded=1
                WHERE id=?");
            $update_stmt->bind_param("di", $total_score, $submission_id);
            $update_stmt->execute();
            $update_stmt->close();

            $conn->commit();

            $already_submitted = true;
            $previous_score = $total_score;

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Submission failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title><?= htmlspecialchars($assignment['title']) ?></title>
<style>
body{font-family:Arial;margin:40px;background:#f4f6f9}
.card{background:#fff;padding:20px;margin-bottom:20px;border-radius:8px}
.correct{color:green;font-weight:bold}
.incorrect{color:red;font-weight:bold}
button{padding:10px 20px;background:#28a745;color:#fff;border:none;border-radius:5px}
.error{color:red;margin-bottom:15px}
.score-box{background:#d4edda;padding:15px;border-radius:8px;margin-bottom:20px}
</style>
</head>
<body>

<h2><?= htmlspecialchars($assignment['title']) ?></h2>
<p><strong>Unit:</strong> <?= htmlspecialchars($assignment['unit_name']) ?></p>
<p><strong>Due:</strong> <?= date("d M Y, h:i A", strtotime($assignment['due_date'])) ?></p>

<?php if ($error_message): ?>
<div class="error"><?= $error_message ?></div>
<?php endif; ?>

<?php if ($already_submitted): ?>
<div class="score-box">
    <h3>Your Score: <?= $previous_score ?></h3>
</div>
<?php endif; ?>

<form method="POST">

<?php foreach ($questions as $index => $q): 
    $qid = $q['id'];
    $res = $results[$qid] ?? null;
?>

<div class="card">
<h4>Question <?= $index+1 ?> (<?= $q['points'] ?> marks)</h4>
<p><?= htmlspecialchars($q['question_text']) ?></p>

<?php if (!$already_submitted): ?>

    <?php if ($q['question_type'] === 'multiple_choice'): ?>
        <?php foreach ($q['options'] as $opt): ?>
            <label>
                <input type="radio" name="answers[<?= $qid ?>]" 
                       value="<?= $opt['id'] ?>" required>
                <?= htmlspecialchars($opt['option_text']) ?>
            </label><br>
        <?php endforeach; ?>
    <?php else: ?>
        <input type="text" name="answers[<?= $qid ?>]" required style="width:100%;padding:8px;">
    <?php endif; ?>

<?php else: ?>

    <p><strong>Your Answer:</strong>
    <?php
        if ($q['question_type'] === 'multiple_choice') {
            foreach ($q['options'] as $opt) {
                if ($opt['id'] == $res['answer']) {
                    echo htmlspecialchars($opt['option_text']);
                }
            }
        } else {
            echo htmlspecialchars($res['answer']);
        }
    ?>
    </p>

    <p class="<?= $res['is_correct'] ? 'correct' : 'incorrect' ?>">
        <?= $res['is_correct'] ? 'Correct' : 'Incorrect' ?>
        (<?= $res['marks_awarded'] ?> / <?= $q['points'] ?>)
    </p>

<?php endif; ?>

</div>

<?php endforeach; ?>

<?php if (!$already_submitted): ?>
<button type="submit">Submit Assignment</button>
<?php endif; ?>

</form>

</body>
</html>
