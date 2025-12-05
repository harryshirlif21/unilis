<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$assignment_id = intval($_GET['id'] ?? 0);

if ($assignment_id <= 0) {
    $_SESSION['error'] = "Invalid assignment ID.";
    header("Location: dashboard.php");
    exit;
}

// Initialize variables for results display
$submitted = false;
$total_score = 0;
$graded_answers = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $answers = $_POST['answers'] ?? [];
    $submitted = true;

    if (!empty($answers)) {
        $conn->begin_transaction();
        try {
            // Insert submission record
            $stmt = $conn->prepare("INSERT INTO interactive_submissions (student_id, assignment_id, submitted_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $student_id, $assignment_id);
            $stmt->execute();
            $submission_id = $conn->insert_id;
            $stmt->close();

            foreach ($answers as $question_id => $option_id) {
                // Get question points
                $q_stmt = $conn->prepare("SELECT points, question_text FROM interactive_questions WHERE id = ?");
                $q_stmt->bind_param("i", $question_id);
                $q_stmt->execute();
                $q_row = $q_stmt->get_result()->fetch_assoc();
                $points = $q_row['points'] ?? 0;
                $question_text = $q_row['question_text'] ?? '';
                $q_stmt->close();

                // Get option details
                $opt_stmt = $conn->prepare("SELECT option_text, is_correct FROM interactive_options WHERE id = ?");
                $opt_stmt->bind_param("i", $option_id);
                $opt_stmt->execute();
                $opt_row = $opt_stmt->get_result()->fetch_assoc();
                $selected_text = $opt_row['option_text'] ?? '';
                $is_correct = $opt_row['is_correct'] ?? 0;
                $opt_stmt->close();

                $marks_awarded = $is_correct ? $points : 0;
                $total_score += $marks_awarded;

                // Insert answer
                $ins_stmt = $conn->prepare("INSERT INTO interactive_answers (submission_id, question_id, option_id, marks_awarded) VALUES (?, ?, ?, ?)");
                $ins_stmt->bind_param("iiii", $submission_id, $question_id, $option_id, $marks_awarded);
                $ins_stmt->execute();
                $ins_stmt->close();

                // Prepare results for display
                $graded_answers[] = [
                    'question_text' => $question_text,
                    'selected_text' => $selected_text,
                    'is_correct' => $is_correct,
                    'points' => $points,
                    'marks_awarded' => $marks_awarded
                ];
            }

            // Update submission with total score
            $conn->query("UPDATE interactive_submissions SET score = $total_score WHERE id = $submission_id");
            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            $submitted = false;
            $_SESSION['error'] = "Error submitting assignment. Try again.";
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

// Get questions and options
$questions = [];
$q_stmt = $conn->prepare("SELECT * FROM interactive_questions WHERE assignment_id = ? ORDER BY id ASC");
$q_stmt->bind_param("i", $assignment_id);
$q_stmt->execute();
$q_res = $q_stmt->get_result();
while ($q = $q_res->fetch_assoc()) {
    $q['options'] = [];
    $opt_stmt = $conn->prepare("SELECT id, option_text FROM interactive_options WHERE question_id = ?");
    $opt_stmt->bind_param("i", $q['id']);
    $opt_stmt->execute();
    $opt_res = $opt_stmt->get_result();
    while ($opt = $opt_res->fetch_assoc()) {
        $q['options'][] = $opt;
    }
    $opt_stmt->close();
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
/* Add your previous CSS here */
body {font-family:'Segoe UI',sans-serif; background:#ecf0f1; color:#333; margin:0;}
.header {background:#2c3e50; color:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center;}
.header h1 {margin:0; font-size:1.8em;}
.back-btn {background:#3498db; color:#fff; padding:10px 20px; border-radius:5px; text-decoration:none; display:flex; align-items:center;}
.back-btn:hover {background:#2ecc71;}
.container {max-width:1000px; margin:30px auto; padding:0 20px;}
.assignment-header, .questions-container, .submit-section {background:#fff; border-radius:12px; padding:30px; margin-bottom:30px;}
.assignment-title {font-size:2.2em; margin-bottom:15px; border-bottom:2px solid #3498db; padding-bottom:15px;}
.meta-item {display:flex; flex-direction:column;}
.meta-label {font-weight:bold; color:#2c3e50; margin-bottom:5px;}
.meta-value {color:#666;}
.assignment-description {background:#f8f9fa; padding:20px; border-radius:8px; border-left:4px solid #3498db;}
.question-card {background:#f8f9fa; border-radius:8px; padding:25px; margin-bottom:25px; border-left:4px solid #3498db;}
.option-item {display:flex; align-items:center; padding:15px; background:#fff; border:2px solid #ddd; border-radius:8px; cursor:pointer; margin-bottom:10px;}
.option-item:hover {border-color:#3498db; background:#e3f2fd;}
.option-item input[type="radio"] {margin-right:15px; transform:scale(1.2);}
.submit-btn {background:#28a745; color:#fff; padding:15px 40px; border:none; border-radius:8px; font-size:1.1em; cursor:pointer;}
.submit-btn:disabled {background:#ccc; cursor:not-allowed;}
.question-result {padding:15px; border-radius:8px; margin-bottom:15px; background:#f1f1f1;}
.correct {border-left:5px solid green;}
.wrong {border-left:5px solid red;}
.timer {background:#ffc107; color:#fff; padding:10px 20px; border-radius:8px; font-weight:bold; display:inline-block; margin-bottom:20px;}
</style>
</head>
<body>

<header class="header">
<h1>Take Assignment</h1>
<a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i>&nbsp;Back to Dashboard</a>
</header>

<div class="container">
<div class="assignment-header">
<h2 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h2>
<div class="assignment-meta">
<div class="meta-item"><div class="meta-label">Unit</div><div class="meta-value"><?= htmlspecialchars($assignment['unit_name']) ?></div></div>
<div class="meta-item"><div class="meta-label">Due Date</div><div class="meta-value"><?= date("d M Y, h:i A", strtotime($assignment['due_date'])) ?></div></div>
<div class="meta-item"><div class="meta-label">Status</div>
<div class="meta-value"><?= (new DateTime() > new DateTime($assignment['due_date'])) ? '<span style="color:red;">Expired</span>' : '<span style="color:green;">Active</span>' ?></div></div>
</div>
<?php if(!empty($assignment['description'])): ?>
<div class="assignment-description"><?= nl2br(htmlspecialchars($assignment['description'])) ?></div>
<?php endif; ?>
</div>

<?php if(isset($_SESSION['error'])) { echo "<div class='error-message'>".htmlspecialchars($_SESSION['error'])."</div>"; unset($_SESSION['error']); } ?>

<div class="timer" id="timer">Loading timer...</div>

<form method="POST" id="assignment-form">
<div class="questions-container">
<h3>Questions</h3>
<?php if(empty($questions)): ?>
<div class="error-message">No questions found for this assignment.</div>
<?php else: ?>
<?php foreach($questions as $index => $q): ?>
<div class="question-card">
<div class="question-header">
<div class="question-number">Question <?= $index+1 ?></div>
<div class="question-points"><?= $q['points'] ?> points</div>
</div>
<div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>
<div class="options-container">
<?php foreach($q['options'] as $opt): ?>
<label class="option-item">
<input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt['id'] ?>" required
<?= $submitted && isset($_POST['answers'][$q['id']]) && $_POST['answers'][$q['id']] == $opt['id'] ? 'checked' : '' ?>>
<span class="option-text"><?= htmlspecialchars($opt['option_text']) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php if(!$submitted): ?>
<div class="submit-section">
<button type="submit" name="submit_assignment" class="submit-btn" id="submit-btn"><i class="fas fa-paper-plane"></i> Submit Assignment</button>
</div>
<?php endif; ?>
</form>

<?php if($submitted): ?>
<div class="submit-section">
<h3>Your Results</h3>
<p><strong>Total Score:</strong> <?= $total_score ?></p>
<?php foreach($graded_answers as $ga): ?>
<div class="question-result <?= $ga['is_correct'] ? 'correct' : 'wrong' ?>">
<p><strong>Question:</strong> <?= htmlspecialchars($ga['question_text']) ?></p>
<p><strong>Your Answer:</strong> <?= htmlspecialchars($ga['selected_text']) ?></p>
<p><strong>Marks Awarded:</strong> <?= $ga['marks_awarded'] ?> / <?= $ga['points'] ?></p>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</div>

<script>
// Countdown Timer
let dueDate = new Date("<?= $assignment['due_date'] ?>").getTime();
let timerEl = document.getElementById('timer');
let submitBtn = document.getElementById('submit-btn');

let countdown = setInterval(function() {
    let now = new Date().getTime();
    let distance = dueDate - now;
    if(distance <= 0) {
        clearInterval(countdown);
        timerEl.innerHTML = "Time's up!";
        if(submitBtn) submitBtn.disabled = true;
        document.querySelectorAll('input[type="radio"]').forEach(r => r.disabled = true);
        return;
    }
    let h = Math.floor((distance % (1000*60*60*24))/(1000*60*60));
    let m = Math.floor((distance % (1000*60*60))/(1000*60));
    let s = Math.floor((distance % (1000*60))/1000);
    timerEl.innerHTML = `Time Remaining: ${h}h ${m}m ${s}s`;
}, 1000);
</script>

</body>
</html>
