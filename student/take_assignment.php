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
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --text-color: #333;
            --light-bg: #ecf0f1;
            --white: #ffffff;
            --border-color: #ddd;
            --danger-color: #e74c3c;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .header {
            background-color: var(--secondary-color);
            color: var(--white);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 400;
        }
        
        .back-btn {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s ease;
        }
        
        .back-btn:hover {
            background-color: var(--accent-color);
        }
        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .assignment-header {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .assignment-title {
            color: var(--secondary-color);
            font-size: 2.2em;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 15px;
        }
        
        .assignment-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .meta-value {
            color: #666;
        }
        
        .assignment-description {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .questions-container {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 30px;
        }
        
        .question-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .question-number {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .question-points {
            background-color: var(--accent-color);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .question-text {
            font-size: 1.1em;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .options-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: var(--white);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .option-item:hover {
            border-color: var(--primary-color);
            background-color: #f0f8ff;
        }
        
        .option-item input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
        }
        
        .option-item input[type="radio"]:checked + .option-text {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .option-item:has(input[type="radio"]:checked) {
            border-color: var(--primary-color);
            background-color: #e3f2fd;
        }
        
        .submit-section {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            margin-top: 30px;
            text-align: center;
        }
        
        .submit-btn {
            background-color: var(--success-color);
            color: var(--white);
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .submit-btn:hover {
            background-color: #218838;
        }
        
        .submit-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .timer {
            background-color: var(--warning-color);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .assignment-header, .questions-container, .submit-section {
                padding: 20px;
            }
            
            .assignment-title {
                font-size: 1.8em;
            }
            
            .question-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
</style>
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
