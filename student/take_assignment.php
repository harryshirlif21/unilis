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

// Check if already submitted
try {
    $check_stmt = $conn->prepare("SELECT id, score FROM interactive_submissions WHERE assignment_id = ? AND student_id = ?");
    $check_stmt->bind_param("ii", $assignment_id, $student_id);
    $check_stmt->execute();
    $submission = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($submission) {
        $_SESSION['submission_success'] = "You have already submitted this assignment. Your score: " . $submission['score'];
        header("Location: dashboard.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error checking submission: " . $e->getMessage());
    $_SESSION['error'] = "Error checking submission status.";
    header("Location: dashboard.php");
    exit;
}

// Get assignment details
try {
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
} catch (Exception $e) {
    error_log("Error fetching assignment: " . $e->getMessage());
    $_SESSION['error'] = "Error loading assignment.";
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Assignment - <?= htmlspecialchars($assignment['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/take_assignment.css">
</head>

<body>
    <header class="header">
        <h1>Take Assignment</h1>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </header>

    <div class="container">
        <!-- Assignment Header -->
        <div class="assignment-header">
            <h2 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h2>

            <div class="assignment-meta">
                <div class="meta-item">
                    <div class="meta-label">Unit</div>
                    <div class="meta-value"><?= htmlspecialchars($assignment['unit_name']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Due Date</div>
                    <div class="meta-value"><?= date("d M Y, h:i A", strtotime($assignment['due_date'])) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Status</div>
                    <div class="meta-value">
                        <?php
                        $now = new DateTime();
                        $due_date = new DateTime($assignment['due_date']);
                        if ($now > $due_date) {
                            echo '<span style="color: var(--danger-color);">Expired</span>';
                        } else {
                            echo '<span style="color: var(--success-color);">Active</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($assignment['description'])): ?>
                <div class="assignment-description">
                    <strong>Description:</strong><br>
                    <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Messages -->
        <?php
        if (isset($_SESSION['error'])) {
            echo "<div class='error-message'>" . htmlspecialchars($_SESSION['error']) . "</div>";
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo "<div class='success-message'>" . htmlspecialchars($_SESSION['success']) . "</div>";
            unset($_SESSION['success']);
        }
        ?>

        <!-- Questions Form -->
        <form id="assignment-form" method="POST" action="../actions.php">
            <input type="hidden" name="action" value="submit_interactive_answers">
            <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">

            <div class="questions-container">
                <h3>Questions</h3>
                <div id="questions-list">
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary-color);"></i>
                        <p>Loading questions...</p>
                    </div>
                </div>
            </div>

            <div class="submit-section">
                <button type="submit" class="submit-btn" id="submit-btn" disabled>
                    <i class="fas fa-paper-plane"></i>
                    Submit Assignment
                </button>
                <p style="margin-top: 15px; color: #666; font-size: 0.9em;">
                    Make sure to answer all questions before submitting.
                </p>
            </div>
        </form>
    </div>

    <script>
        let questionsLoaded = false;
        let totalQuestions = 0;
        let answeredQuestions = 0;

        // Load questions
        function loadQuestions() {
            fetch(`../actions.php?action=get_mc_questions&assignment_id=<?= $assignment_id ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('questions-list').innerHTML =
                            '<div class="error-message">Error loading questions: ' + data.error + '</div>';
                        return;
                    }

                    const questionsList = document.getElementById('questions-list');
                    questionsList.innerHTML = '';

                    if (!data.questions || data.questions.length === 0) {
                        questionsList.innerHTML = '<div class="error-message">No questions found for this assignment.</div>';
                        return;
                    }

                    totalQuestions = data.questions.length;
                    questionsLoaded = true;

                    data.questions.forEach((question, index) => {
                        const questionCard = document.createElement('div');
                        questionCard.className = 'question-card';
                        questionCard.innerHTML = `
                            <div class="question-header">
                                <div class="question-number">Question ${index + 1}</div>
                                <div class="question-points">${question.points} points</div>
                            </div>
                            <div class="question-text">${question.question_text}</div>
                            <div class="options-container">
                                ${question.options.map(option => `
                                    <label class="option-item">
                                        <input type="radio" name="answers[${question.id}]" value="${option.id}" required>
                                        <span class="option-text">${option.option_text}</span>
                                    </label>
                                `).join('')}
                            </div>
                        `;
                        questionsList.appendChild(questionCard);
                    });

                    // Add event listeners for radio buttons
                    addRadioListeners();
                    updateSubmitButton();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('questions-list').innerHTML =
                        '<div class="error-message">Error loading questions. Please try again.</div>';
                });
        }

        function addRadioListeners() {
            const radioButtons = document.querySelectorAll('input[type="radio"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    updateSubmitButton();
                });
            });
        }

        function updateSubmitButton() {
            if (!questionsLoaded) return;

            const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
            answeredQuestions = checkedRadios.length;

            const submitBtn = document.getElementById('submit-btn');
            if (answeredQuestions === totalQuestions) {
                submitBtn.disabled = false;
                submitBtn.style.backgroundColor = 'var(--success-color)';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.backgroundColor = '#ccc';
            }
        }

        // Form submission
        document.getElementById('assignment-form').addEventListener('submit', function(e) {
            if (answeredQuestions < totalQuestions) {
                e.preventDefault();
                alert('Please answer all questions before submitting.');
                return;
            }

            if (!confirm('Are you sure you want to submit this assignment? You cannot change your answers after submission.')) {
                e.preventDefault();
                return;
            }

            // Disable submit button to prevent double submission
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        });

        // Load questions on page load
        loadQuestions();
    </script>
</body>

</html>