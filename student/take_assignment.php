<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];
$assignment_id = intval($_GET['id'] ?? 0);

if ($assignment_id <= 0) {
    $_SESSION['error'] = "Invalid assignment ID.";
    header("Location: dashboard.php");
    exit;
}

// Check if already submitted
$already_submitted = false;
$previous_score = 0;
try {
    $check_stmt = $conn->prepare("SELECT id, score FROM interactive_submissions WHERE assignment_id = ? AND student_id = ?");
    $check_stmt->bind_param("ii", $assignment_id, $student_id);
    $check_stmt->execute();
    $submission = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($submission) {
        $already_submitted = true;
        $previous_score = $submission['score'];
    }
} catch (Exception $e) {
    error_log("Error checking submission: " . $e->getMessage());
    $_SESSION['error'] = "Error checking submission status.";
    header("Location: dashboard.php");
    exit;
}

// Handle form submission
$results = [];
if (!$already_submitted && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $answers = $_POST['answers'] ?? [];

    if (!empty($answers)) {
        $conn->begin_transaction();
        try {
            // Insert submission record
            $stmt = $conn->prepare("INSERT INTO interactive_submissions (student_id, assignment_id, submitted_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $student_id, $assignment_id);
            $stmt->execute();
            $submission_id = $conn->insert_id;
            $stmt->close();

            $total_score = 0;

            foreach ($answers as $question_id => $answer) {
                $marks_awarded = 0;
                $is_correct = 0;

                // Determine question type
                $q_stmt = $conn->prepare("SELECT question_type, points FROM interactive_questions WHERE id = ?");
                $q_stmt->bind_param("i", $question_id);
                $q_stmt->execute();
                $q_data = $q_stmt->get_result()->fetch_assoc();
                $q_stmt->close();

                if (!$q_data) continue;

                $points = (float)$q_data['points'];
                $question_type = $q_data['question_type'];

                if ($question_type === 'multiple_choice') {
                    // Check if option is correct
                    $opt_stmt = $conn->prepare("SELECT is_correct FROM interactive_options WHERE id = ?");
                    $opt_stmt->bind_param("i", $answer);
                    $opt_stmt->execute();
                    $is_correct = (int)$opt_stmt->get_result()->fetch_assoc()['is_correct'];
                    $opt_stmt->close();

                    $marks_awarded = $is_correct ? $points : 0;
                } else {
                    // For short answer or others, optionally auto-grade exact match
                    $correct_stmt = $conn->prepare("SELECT option_text FROM interactive_options WHERE question_id = ? AND is_correct=1 LIMIT 1");
                    $correct_stmt->bind_param("i", $question_id);
                    $correct_stmt->execute();
                    $correct_ans = $correct_stmt->get_result()->fetch_assoc()['option_text'] ?? '';
                    $correct_stmt->close();

                    $is_correct = (strtolower(trim($answer)) === strtolower(trim($correct_ans))) ? 1 : 0;
                    $marks_awarded = $is_correct ? $points : 0;
                }

                $total_score += $marks_awarded;

                // Insert answer
                $ins_stmt = $conn->prepare("INSERT INTO interactive_answers (submission_id, question_id, option_id, answer_text, marks_awarded, is_correct) VALUES (?, ?, ?, ?, ?, ?)");
                $opt_id = ($question_type === 'multiple_choice') ? $answer : null;
                $text_answer = ($question_type !== 'multiple_choice') ? $answer : null;
                $ins_stmt->bind_param("iiisdi", $submission_id, $question_id, $opt_id, $text_answer, $marks_awarded, $is_correct);
                $ins_stmt->execute();
                $ins_stmt->close();

                $results[$question_id] = [
                    'marks_awarded' => $marks_awarded,
                    'points' => $points,
                    'is_correct' => $is_correct,
                    'answer' => $answer
                ];
            }

            // Update submission total score
            $conn->query("UPDATE interactive_submissions SET score=$total_score, graded=1 WHERE id=$submission_id");
            $conn->commit();
            $already_submitted = true;
            $previous_score = $total_score;

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error submitting assignment: " . $e->getMessage());
            $_SESSION['error'] = "Error submitting assignment. Try again.";
            header("Location: take_assignment.php?id=$assignment_id");
            exit;
        }
    }
}

// Get assignment details
$assignment_stmt = $conn->prepare("
    SELECT a.id, a.title, a.description, a.due_date, u.name AS unit_name
    FROM interactive_assignments a
    JOIN units u ON a.unit_id = u.id
    WHERE a.id = ?
");
$assignment_stmt->bind_param("i", $assignment_id);
$assignment_stmt->execute();
$assignment = $assignment_stmt->get_result()->fetch_assoc();
$assignment_stmt->close();

if (!$assignment) {
    $_SESSION['error'] = "Assignment not found.";
    header("Location: dashboard.php");
    exit;
}

// Get questions and options
$questions = [];
$q_stmt = $conn->prepare("SELECT * FROM interactive_questions WHERE interactive_assignment_id=? ORDER BY id ASC");
$q_stmt->bind_param("i", $assignment_id);
$q_stmt->execute();
$q_res = $q_stmt->get_result();
while ($q = $q_res->fetch_assoc()) {
    $q['options'] = [];
    if ($q['question_type'] === 'multiple_choice') {
        $opt_stmt = $conn->prepare("SELECT id, option_text FROM interactive_options WHERE question_id=?");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Take Assignment - <?= htmlspecialchars($assignment['title']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
/* Add all your previous CSS styles here */
body{font-family:Segoe UI,sans-serif;margin:0;background:#ecf0f1;color:#333}
.header{background:#2c3e50;color:#fff;padding:15px 30px;display:flex;justify-content:space-between;align-items:center}
.header h1{margin:0;font-size:1.8em;font-weight:400}
.back-btn{background:#3498db;color:#fff;padding:10px 20px;border:none;border-radius:5px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:0.2s}
.back-btn:hover{background:#2ecc71}
.container{max-width:1000px;margin:30px auto;padding:0 20px}
.assignment-header,.questions-container,.submit-section{background:#fff;border-radius:12px;padding:30px;margin-bottom:30px;box-shadow:0 4px 15px rgba(0,0,0,0.08)}
.assignment-title{color:#2c3e50;font-size:2.2em;margin-bottom:15px;border-bottom:2px solid #3498db;padding-bottom:15px}
.assignment-description{background:#f8f9fa;padding:20px;border-radius:8px;border-left:4px solid #3498db}
.question-card{background:#f8f9fa;border-radius:8px;padding:25px;margin-bottom:25px;border-left:4px solid #3498db}
.question-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
.question-number{background:#3498db;color:#fff;padding:8px 15px;border-radius:20px;font-weight:bold;font-size:0.9em}
.question-points{background:#2ecc71;color:#fff;padding:8px 15px;border-radius:20px;font-weight:bold;font-size:0.9em}
.options-container{display:flex;flex-direction:column;gap:12px}
.option-item{display:flex;align-items:center;padding:15px;background:#fff;border:2px solid #ddd;border-radius:8px;cursor:pointer;transition:0.2s}
.option-item:hover{border-color:#3498db;background:#f0f8ff}
.option-item input[type=radio]{margin-right:15px;transform:scale(1.2)}
.option-item input[type=radio]:checked + .option-text{font-weight:bold;color:#3498db}
.option-item:has(input[type=radio]:checked){border-color:#3498db;background:#e3f2fd}
.submit-btn{background:#28a745;color:#fff;padding:15px 40px;border:none;border-radius:8px;font-size:1.1em;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:10px;transition:0.2s}
.submit-btn:hover{background:#218838}
.error-message{background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb}
.success-message{background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb}
</style>
</head>
<body>
<header class="header">
<h1>Take Assignment</h1>
<a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</header>

<div class="container">
<div class="assignment-header">
<h2 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h2>
<div class="assignment-meta">
<div class="meta-item"><div class="meta-label">Unit</div><div class="meta-value"><?= htmlspecialchars($assignment['unit_name']) ?></div></div>
<div class="meta-item"><div class="meta-label">Due Date</div><div class="meta-value"><?= date("d M Y, h:i A", strtotime($assignment['due_date'])) ?></div></div>
<div class="meta-item"><div class="meta-label">Status</div><div class="meta-value"><?= (new DateTime() > new DateTime($assignment['due_date'])) ? '<span style="color:red;">Expired</span>' : '<span style="color:green;">Active</span>' ?></div></div>
</div>
<?php if(!empty($assignment['description'])): ?>
<div class="assignment-description"><?= nl2br(htmlspecialchars($assignment['description'])) ?></div>
<?php endif; ?>
</div>

<?php
if(isset($_SESSION['error'])){
    echo "<div class='error-message'>".htmlspecialchars($_SESSION['error'])."</div>";
    unset($_SESSION['error']);
}
if($already_submitted){
    echo "<div class='success-message'>Assignment submitted successfully! Your score: $previous_score</div>";
}
?>

<?php if(!$already_submitted): ?>
<form method="POST">
<div class="questions-container">
<h3>Questions</h3>
<?php if(empty($questions)): ?>
<div class="error-message">No questions found for this assignment.</div>
<?php else: ?>
<?php foreach($questions as $index=>$q): ?>
<div class="question-card">
<div class="question-header">
<div class="question-number">Question <?= $index+1 ?></div>
<div class="question-points"><?= $q['points'] ?> points</div>
</div>
<div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>
<div class="options-container">
<?php if($q['question_type']==='multiple_choice'): ?>
<?php foreach($q['options'] as $opt): ?>
<label class="option-item">
<input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt['id'] ?>" required>
<span class="option-text"><?= htmlspecialchars($opt['option_text']) ?></span>
</label>
<?php endforeach; ?>
<?php else: ?>
<input type="text" name="answers[<?= $q['id'] ?>]" placeholder="Type your answer" required style="padding:10px;border:1px solid #ddd;border-radius:5px;width:100%">
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="submit-section">
<button type="submit" name="submit_assignment" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit Assignment</button>
</div>
</form>
<?php else: ?>
<!-- Show detailed results -->
<div class="questions-container">
<h3>Assignment Results</h3>
<?php
foreach($questions as $index=>$q){
    $res = $results[$q['id']] ?? null;
    $student_ans = '';
    $is_correct = '';
    if($q['question_type']==='multiple_choice'){
        foreach($q['options'] as $opt){
            if(isset($_POST['answers'][$q['id']]) && $_POST['answers'][$q['id']] == $opt['id']){
                $student_ans = htmlspecialchars($opt['option_text']);
                $is_correct = ($res['is_correct'] ?? 0) ? 'Correct' : 'Incorrect';
                break;
            }
        }
    } else {
        $student_ans = $_POST['answers'][$q['id']] ?? '';
        $is_correct = ($res['is_correct'] ?? 0) ? 'Correct' : 'Incorrect';
    }
    echo "<div class='question-card'>
        <div class='question-header'>
            <div class='question-number'>Question ".($index+1)."</div>
            <div class='question-points'>".($res['marks_awarded']??0)." / ".$q['points']." points</div>
        </div>
        <div class='question-text'>".htmlspecialchars($q['question_text'])."</div>
        <div><strong>Your answer:</strong> $student_ans</div>
        <div><strong>Status:</strong> $is_correct</div>
    </div>";
}
?>
</div>
<?php endif; ?>
</div>
</body>
</html>
