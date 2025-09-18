<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

// Fetch lecturer's units
$stmt = $conn->prepare("
    SELECT u.* 
    FROM units u 
    JOIN lecturer_units lu ON u.id = lu.unit_id 
    WHERE lu.lecturer_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create CAT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f6fa;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
        }

        .cat-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .input-group input[type="text"],
        .input-group input[type="number"],
        .input-group input[type="datetime-local"],
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .questions-container {
            margin-top: 30px;
        }

        .question-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            position: relative;
        }

        .question-number {
            position: absolute;
            top: -10px;
            left: -10px;
            background: var(--secondary-color);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .voice-input {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .record-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .record-btn.recording {
            background: var(--danger-color);
            animation: pulse 1.5s infinite;
        }

        .submit-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            width: 100%;
        }

        .solution-input {
            margin-top: 15px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Create New CAT</h1>
        </div>

        <form id="catForm" action="../actions.php" method="POST">
            <input type="hidden" name="action" value="create_cat">
            
            <div class="cat-info">
                <div class="input-group">
                    <label for="unit">Unit:</label>
                    <select name="unit_id" id="unit" required>
                        <option value="">Select Unit</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label for="title">CAT Title:</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="input-group">
                    <label for="duration">Duration (minutes):</label>
                    <input type="number" id="duration" name="duration" min="15" max="180" value="60" required>
                </div>

                <div class="input-group">
                    <label for="start_time">Start Time:</label>
                    <input type="datetime-local" id="start_time" name="start_time" required>
                </div>

                <div class="input-group">
                    <label for="instructions">Instructions:</label>
                    <div class="voice-input">
                        <button type="button" class="record-btn" onclick="toggleRecording('instructions')">
                            <i class="fas fa-microphone"></i> Record Instructions
                        </button>
                        <audio id="audio_instructions" controls style="display: none;"></audio>
                    </div>
                    <textarea id="instructions" name="instructions" rows="4" required></textarea>
                </div>

                <div class="input-group">
                    <label for="numQuestions">Number of Questions:</label>
                    <input type="number" id="numQuestions" min="1" max="20" value="1">
                </div>
            </div>

            <div id="questionsContainer" class="questions-container">
                <!-- Questions will be dynamically added here -->
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> Create CAT
            </button>
        </form>
    </div>

    <script>
        let mediaRecorders = {};
        let audioChunks = {};

        function createQuestionCard(number) {
            const card = document.createElement('div');
            card.className = 'question-card';
            card.innerHTML = `
                <div class="question-number">${number}</div>
                <div class="input-group">
                    <label>Question ${number}:</label>
                    <div class="voice-input">
                        <button type="button" class="record-btn" onclick="toggleRecording('q${number}')">
                            <i class="fas fa-microphone"></i> Record Question
                        </button>
                        <audio id="audio_q${number}" controls style="display: none;"></audio>
                    </div>
                    <textarea class="question-input" 
                            name="questions[${number}][text]" 
                            placeholder="Type or record your question here..."
                            required></textarea>
                </div>
                <div class="input-group">
                    <label for="points_${number}">Points:</label>
                    <input type="number" name="questions[${number}][points]" id="points_${number}" 
                           min="1" value="1" required>
                </div>
                <div class="solution-input">
                    <label>Solution/Marking Guide:</label>
                    <div class="voice-input">
                        <button type="button" class="record-btn" onclick="toggleRecording('sol${number}')">
                            <i class="fas fa-microphone"></i> Record Solution
                        </button>
                        <audio id="audio_sol${number}" controls style="display: none;"></audio>
                    </div>
                    <textarea name="questions[${number}][solution]" 
                            placeholder="Provide the solution or marking guide for this question..."
                            required></textarea>
                </div>
            `;
            return card;
        }

        async function toggleRecording(id) {
            const button = event.target.closest('.record-btn');
            const audio = document.getElementById(`audio_${id}`);
            const textarea = button.parentElement.nextElementSibling;

            if (!mediaRecorders[id]) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorders[id] = new MediaRecorder(stream);
                    audioChunks[id] = [];

                    mediaRecorders[id].ondataavailable = (event) => {
                        audioChunks[id].push(event.data);
                    };

                    mediaRecorders[id].onstop = async () => {
                        const audioBlob = new Blob(audioChunks[id], { type: 'audio/wav' });
                        const audioUrl = URL.createObjectURL(audioBlob);
                        audio.src = audioUrl;
                        audio.style.display = 'block';

                        // Convert speech to text
                        const formData = new FormData();
                        formData.append('audio', audioBlob);

                        try {
                            const response = await fetch('../actions.php?action=speech_to_text', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();
                            if (data.success) {
                                textarea.value = data.text;
                            }
                        } catch (error) {
                            console.error('Error converting speech to text:', error);
                        }
                    };

                    mediaRecorders[id].start();
                    button.classList.add('recording');
                    button.innerHTML = '<i class="fas fa-stop"></i> Stop Recording';
                } catch (err) {
                    console.error('Error accessing microphone:', err);
                    alert('Could not access microphone. Please check permissions.');
                }
            } else {
                mediaRecorders[id].stop();
                mediaRecorders[id] = null;
                button.classList.remove('recording');
                button.innerHTML = '<i class="fas fa-microphone"></i> Record Question';
            }
        }

        // Initialize questions based on input
        document.getElementById('numQuestions').addEventListener('change', function(e) {
            const container = document.getElementById('questionsContainer');
            const numQuestions = parseInt(e.target.value) || 0;
            
            // Clear existing questions
            container.innerHTML = '';
            
            // Add new question cards
            for (let i = 1; i <= numQuestions; i++) {
                container.appendChild(createQuestionCard(i));
            }
        });

        // Initialize first question
        document.getElementById('questionsContainer').appendChild(createQuestionCard(1));

        // Set minimum start time to current time
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('start_time').min = now.toISOString().slice(0, 16);

        // Form validation
        document.getElementById('catForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'red';
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields.');
                return;
            }

            this.submit();
        });
    </script>
</body>
</html>
