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
    <link rel="stylesheet" href="css/create_cat.css">
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
                    const stream = await navigator.mediaDevices.getUserMedia({
                        audio: true
                    });
                    mediaRecorders[id] = new MediaRecorder(stream);
                    audioChunks[id] = [];

                    mediaRecorders[id].ondataavailable = (event) => {
                        audioChunks[id].push(event.data);
                    };

                    mediaRecorders[id].onstop = async () => {
                        const audioBlob = new Blob(audioChunks[id], {
                            type: 'audio/wav'
                        });
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