<?php
function handle_assignment_creation($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['error' => 'Invalid request method'];
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Insert assignment
        $stmt = $conn->prepare("INSERT INTO assignments (unit_id, lecturer_id, title, description, deadline, mode) VALUES (?, ?, ?, ?, ?, ?)");
        $lecturer_id = $_SESSION['user_id'];
        $mode = isset($_POST['enable_ai']) ? 'ai_assisted' : 'manual';
        $stmt->bind_param("iissss", 
            $_POST['unit_id'],
            $lecturer_id,
            $_POST['title'],
            $_POST['instructions'],
            $_POST['deadline'],
            $mode
        );
        $stmt->execute();
        $assignment_id = $stmt->insert_id;

        // Save AI settings if enabled
        if (isset($_POST['enable_ai'])) {
            $stmt = $conn->prepare("INSERT INTO assignment_settings (assignment_id, ai_confidence, ai_policy, ai_feedback) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss",
                $assignment_id,
                $_POST['ai_confidence'],
                $_POST['ai_policy'],
                $_POST['ai_feedback']
            );
            $stmt->execute();
        }

        // Process questions
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $index => $question) {
                // Insert question
                $stmt = $conn->prepare("INSERT INTO questions (assignment_id, question_text, question_type, marks, ai_rubric) VALUES (?, ?, ?, ?, ?)");
                $rubric = isset($question['rubric']) ? $question['rubric'] : null;
                $stmt->bind_param("issss",
                    $assignment_id,
                    $question['text'],
                    $question['type'],
                    $question['marks'],
                    $rubric
                );
                $stmt->execute();
                $question_id = $stmt->insert_id;

                // For multiple choice questions, save options
                if ($question['type'] === 'multiple_choice' && isset($question['options'])) {
                    foreach ($question['options'] as $optionIndex => $optionText) {
                        $stmt = $conn->prepare("INSERT INTO multiple_choice_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                        $isCorrect = (int)($question['correct'] == $optionIndex);
                        $stmt->bind_param("isi",
                            $question_id,
                            $optionText,
                            $isCorrect
                        );
                        $stmt->execute();
                    }
                }

                // Save key points for AI marking
                if (isset($question['key_points']) && !empty($question['key_points'])) {
                    $keyPoints = explode("\n", trim($question['key_points']));
                    foreach ($keyPoints as $point) {
                        if (!empty($point)) {
                            $stmt = $conn->prepare("INSERT INTO question_key_points (question_id, key_point) VALUES (?, ?)");
                            $stmt->bind_param("is",
                                $question_id,
                                trim($point)
                            );
                            $stmt->execute();
                        }
                    }
                }
            }
        }

        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Assignment created successfully',
            'assignment_id' => $assignment_id
        ];

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error creating assignment: " . $e->getMessage());
        return [
            'error' => 'Failed to create assignment',
            'details' => $e->getMessage()
        ];
    }
}
