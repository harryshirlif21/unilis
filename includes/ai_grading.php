<?php
require_once '../config/db.php';
require_once 'auth.php';

class AIGrading {
    private $conn;
    private $openai_key;

    public function __construct($conn) {
        $this->conn = $conn;
        // You should store this securely in environment variables
        $this->openai_key = getenv('OPENAI_API_KEY');
    }

    public function gradeAssignment($submission_id) {
        // Fetch submission details
        $stmt = $this->conn->prepare("
            SELECT s.*, a.grading_rubric, q.question_type, q.question_text, q.correct_answer, q.points
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN questions q ON s.question_id = q.id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $submission = $stmt->get_result()->fetch_assoc();

        if (!$submission) {
            return false;
        }

        $grade = 0;
        $feedback = '';

        switch ($submission['question_type']) {
            case 'multiple_choice':
                list($grade, $feedback) = $this->gradeMultipleChoice(
                    $submission['answer'],
                    $submission['correct_answer'],
                    $submission['points']
                );
                break;

            case 'short_answer':
                list($grade, $feedback) = $this->gradeShortAnswer(
                    $submission['answer'],
                    $submission['question_text'],
                    $submission['correct_answer'],
                    $submission['points'],
                    json_decode($submission['grading_rubric'], true)
                );
                break;

            case 'speech':
                list($grade, $feedback) = $this->gradeSpeech(
                    $submission['answer'],
                    $submission['question_text'],
                    $submission['correct_answer'],
                    $submission['points']
                );
                break;
        }

        // Update submission with grade and feedback
        $stmt = $this->conn->prepare("
            UPDATE submissions 
            SET grade = ?, feedback = ?, graded_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("dsi", $grade, $feedback, $submission_id);
        return $stmt->execute();
    }

    private function gradeMultipleChoice($student_answer, $correct_answer, $points) {
        $grade = ($student_answer === $correct_answer) ? $points : 0;
        $feedback = ($grade > 0) 
            ? "Correct! You've earned full points."
            : "Incorrect. The correct answer was: " . $correct_answer;
        
        return [$grade, $feedback];
    }

    private function gradeShortAnswer($answer, $question, $model_answer, $points, $rubric) {
        $prompt = $this->buildShortAnswerPrompt(
            $answer,
            $question,
            $model_answer,
            $rubric
        );

        $response = $this->callOpenAI($prompt);
        $evaluation = json_decode($response, true);

        if (!$evaluation) {
            return [0, "Error in AI grading process"];
        }

        $grade = $points * ($evaluation['score'] / 100);
        return [$grade, $evaluation['feedback']];
    }

    private function gradeSpeech($audioData, $question, $expected_content, $points) {
        // First, convert speech to text using OpenAI Whisper API
        $transcription = $this->speechToText($audioData);
        
        if (!$transcription) {
            return [0, "Error in speech processing"];
        }

        // Now grade the transcribed text similar to short answer
        return $this->gradeShortAnswer(
            $transcription,
            $question,
            $expected_content,
            $points,
            ['clarity' => 40, 'content' => 60]
        );
    }

    private function buildShortAnswerPrompt($answer, $question, $model_answer, $rubric) {
        return json_encode([
            'role' => 'system',
            'content' => "You are an AI grading assistant. Grade the following answer based on the provided rubric and question context.",
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Question: $question\n\nStudent Answer: $answer\n\nModel Answer: $model_answer\n\nRubric: " . json_encode($rubric)
                ]
            ]
        ]);
    }

    private function speechToText($audioData) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->openai_key,
            'Content-Type: multipart/form-data'
        ]);

        $post_data = [
            'file' => new CURLFile($audioData),
            'model' => 'whisper-1',
            'language' => 'en'
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $result = json_decode($response, true);
        return $result['text'] ?? null;
    }

    private function callOpenAI($prompt) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->openai_key,
            'Content-Type: application/json'
        ]);

        $post_data = [
            'model' => 'gpt-4',
            'messages' => json_decode($prompt, true)['messages'],
            'temperature' => 0.3,
            'max_tokens' => 500
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? null;
    }
}
