<?php
require_once '../config/db.php';
session_start();

// Check if lecturer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];

// Get lecturer's units
$units = [];
$stmt = $conn->prepare("SELECT u.id, u.name FROM units u JOIN lecturer_units lu ON u.id = lu.unit_id WHERE lu.lecturer_id = ?");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --danger-color: #e74c3c;
            --border-color: #ddd;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f6fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        h1 {
            color: var(--secondary-color);
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .question-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            position: relative;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .question-content {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--secondary-color);
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .options-container {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .ai-marking-section {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .confidence-slider {
            width: 100%;
            margin: 10px 0;
        }

        .feedback-section {
            margin-top: 20px;
            padding: 15px;
            background: #e8f5e9;
            border-radius: 8px;
            display: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .question-box {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Assignment</h1>
        
        <form id="assignmentForm" action="../actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_assignment">
            
            <div class="form-section">
                <div class="form-group">
                    <label for="unit_id">Select Unit:</label>
                    <select name="unit_id" id="unit_id" required>
                        <option value="">-- Select Unit --</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Assignment Title:</label>
                    <input type="text" name="title" id="title" required>
                </div>

                <div class="form-group">
                    <label for="instructions">Instructions:</label>
                    <textarea name="instructions" id="instructions" required></textarea>
                </div>

                <div class="form-group">
                    <label for="deadline">Deadline:</label>
                    <input type="datetime-local" name="deadline" id="deadline" required>
                </div>
            </div>

            <div class="form-section">
                <h2>Questions</h2>
                <div id="questionsContainer"></div>
                <button type="button" class="btn btn-primary" onclick="addQuestion()">
                    <i class="fas fa-plus"></i> Add Question
                </button>
            </div>

            <div class="form-section ai-marking-section">
                <h2>AI Marking Settings</h2>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="enable_ai" id="enableAI" value="1">
                        Enable AI-assisted marking
                    </label>
                </div>

                <div id="aiOptions" style="display: none;">
                    <div class="form-group">
                        <label for="aiConfidence">AI Confidence Threshold:</label>
                        <input type="range" name="ai_confidence" id="aiConfidence" 
                               class="confidence-slider" min="70" max="100" value="85">
                        <span id="confidenceValue">85%</span>
                    </div>

                    <div class="form-group">
                        <label for="aiPolicy">When AI confidence is below threshold:</label>
                        <select name="ai_policy" id="aiPolicy">
                            <option value="review">Send for lecturer review</option>
                            <option value="partial">Award partial marks</option>
                            <option value="zero">Award zero marks</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="aiFeedback">AI Feedback Style:</label>
                        <select name="ai_feedback" id="aiFeedback">
                            <option value="detailed">Detailed (Point by point feedback)</option>
                            <option value="summary">Summary (Brief overview)</option>
                            <option value="minimal">Minimal (Score only)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Assignment
                </button>
            </div>
        </form>
    </div>

    <!-- Question Template -->
    <template id="questionTemplate">
        <div class="question-box" data-question-index="{index}">
            <div class="question-header">
                <h3>Question {number}</h3>
                <button type="button" class="btn btn-danger" onclick="removeQuestion(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="question-content">
                <div class="form-group">
                    <label>Question Type:</label>
                    <select name="questions[{index}][type]" onchange="handleQuestionTypeChange(this)" required>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="short_answer">Short Answer</option>
                        <option value="speech">Speech Answer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Question Text:</label>
                    <textarea name="questions[{index}][text]" required></textarea>
                </div>

                <div class="form-group">
                    <label>Marks:</label>
                    <input type="number" name="questions[{index}][marks]" min="1" value="1" required>
                </div>

                <div class="options-container" style="display: block;">
                    <label>Options:</label>
                    <div class="options-list">
                        <div class="option-item">
                            <input type="radio" name="questions[{index}][correct]" value="0" required>
                            <input type="text" name="questions[{index}][options][]" placeholder="Option 1" required>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="addOption(this)">
                        <i class="fas fa-plus"></i> Add Option
                    </button>
                </div>

                <div class="answer-rubric" style="display: none;">
                    <div class="form-group">
                        <label>Model Answer / Grading Rubric:</label>
                        <textarea name="questions[{index}][rubric]" 
                                placeholder="Enter the correct answer or detailed grading rubric for AI marking"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Key Points (One per line):</label>
                        <textarea name="questions[{index}][key_points]" 
                                placeholder="Enter key points that should be present in the answer"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        let questionCount = 0;

        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const template = document.getElementById('questionTemplate').innerHTML;
            const questionDiv = document.createElement('div');
            questionCount++;
            
            questionDiv.innerHTML = template
                .replace(/{index}/g, questionCount)
                .replace(/{number}/g, questionCount);
            
            container.appendChild(questionDiv.firstElementChild);
        }

        function removeQuestion(button) {
            if (confirm('Are you sure you want to remove this question?')) {
                const questionBox = button.closest('.question-box');
                questionBox.remove();
            }
        }

        function handleQuestionTypeChange(select) {
            const questionBox = select.closest('.question-box');
            const optionsContainer = questionBox.querySelector('.options-container');
            const answerRubric = questionBox.querySelector('.answer-rubric');
            
            if (select.value === 'multiple_choice') {
                optionsContainer.style.display = 'block';
                answerRubric.style.display = 'none';
            } else {
                optionsContainer.style.display = 'none';
                answerRubric.style.display = 'block';
            }
        }

        function addOption(button) {
            const optionsList = button.previousElementSibling;
            const optionCount = optionsList.children.length + 1;
            const questionIndex = button.closest('.question-box').dataset.questionIndex;
            
            const optionDiv = document.createElement('div');
            optionDiv.className = 'option-item';
            optionDiv.innerHTML = `
                <input type="radio" name="questions[${questionIndex}][correct]" value="${optionCount - 1}" required>
                <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option ${optionCount}" required>
                <button type="button" class="btn btn-danger" onclick="removeOption(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            optionsList.appendChild(optionDiv);
        }

        function removeOption(button) {
            const optionItem = button.closest('.option-item');
            optionItem.remove();
        }

        // AI Marking Controls
        document.getElementById('enableAI').addEventListener('change', function() {
            const aiOptions = document.getElementById('aiOptions');
            aiOptions.style.display = this.checked ? 'block' : 'none';
        });

        document.getElementById('aiConfidence').addEventListener('input', function() {
            document.getElementById('confidenceValue').textContent = this.value + '%';
        });

        // Form Validation
        document.getElementById('assignmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Check if there are questions
            const questions = document.querySelectorAll('.question-box');
            if (questions.length === 0) {
                alert('Please add at least one question to the assignment.');
                return;
            }

            // Validate each question
            let isValid = true;
            questions.forEach((question, index) => {
                const type = question.querySelector('select[name*="[type]"]').value;
                if (type === 'multiple_choice') {
                    const options = question.querySelectorAll('input[name*="[options]"]');
                    const correctAnswer = question.querySelector('input[name*="[correct]"]:checked');
                    
                    if (options.length < 2) {
                        alert(`Question ${index + 1} must have at least 2 options.`);
                        isValid = false;
                    }
                    if (!correctAnswer) {
                        alert(`Please select the correct answer for question ${index + 1}.`);
                        isValid = false;
                    }
                } else {
                    const rubric = question.querySelector('textarea[name*="[rubric]"]').value.trim();
                    if (!rubric) {
                        alert(`Please provide a grading rubric for question ${index + 1}.`);
                        isValid = false;
                    }
                }
            });

            if (isValid) {
                this.submit();
            }
        });

        // Add first question automatically
        addQuestion();
    </script>
</body>
</html>
