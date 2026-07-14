<?php
/**
 * Live Engagement Module - Quiz API
 * 
 * RESTful API endpoints for quiz management.
 * 
 * @package UNILIS\LiveEngagement\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = le_get('action', '');
$quizId = (int)le_get('id', 0, true);
$sessionId = (int)le_get('session_id', 0, true);
$userId = le_current_user_id();
$role = le_current_user_role();

$quizModel = new \LE\Models\QuizModel();

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list':
                    if (!$sessionId) le_error_response('Session ID required');
                    $quizzes = $quizModel->findBy('session_id', $sessionId);
                    le_success_response($quizzes);
                    break;

                case 'view':
                    if (!$quizId) le_error_response('Quiz ID required');
                    $includeCorrect = $role === 'lecturer';
                    le_success_response($quizModel->getQuizWithQuestions($quizId, $includeCorrect));
                    break;

                case 'leaderboard':
                    if (!$quizId) le_error_response('Quiz ID required');
                    le_success_response($quizModel->getLeaderboard($quizId));
                    break;

                case 'stats':
                    if (!$quizId) le_error_response('Quiz ID required');
                    le_success_response($quizModel->getQuizStats($quizId));
                    break;

                default:
                    le_error_response('Unknown action', 400);
            }
            break;

        case 'POST':
            le_require_csrf();
            $input = le_get_json_input() ?? $_POST;

            switch ($action) {
                case 'create':
                    if (empty($input['title']) || empty($input['session_id'])) {
                        le_error_response('Title and session_id required');
                    }
                    $questions = $input['questions'] ?? [];
                    $quizId = $quizModel->createWithQuestions($input, $questions);
                    if (!$quizId) le_error_response('Failed to create quiz');
                    le_success_response($quizModel->getQuizWithQuestions($quizId, true), 'Quiz created');
                    break;

                case 'activate':
                    if (!$quizId) le_error_response('Quiz ID required');
                    if ($quizModel->activate($quizId)) {
                        le_success_response(null, 'Quiz activated');
                    }
                    le_error_response('Failed to activate quiz');
                    break;

                case 'lock':
                    if (!$quizId) le_error_response('Quiz ID required');
                    if ($quizModel->lock($quizId)) {
                        le_success_response(null, 'Quiz locked');
                    }
                    le_error_response('Failed to lock quiz');
                    break;

                case 'start_attempt':
                    if (!$quizId) le_error_response('Quiz ID required');
                    $participantId = (int)($input['participant_id'] ?? 0);
                    $attemptId = $quizModel->startAttempt($quizId, $userId, $participantId ?: null);
                    if (!$attemptId) le_error_response('Cannot start attempt');
                    
                    $quiz = $quizModel->getQuizWithQuestions($quizId, false);
                    le_success_response([
                        'attempt_id' => $attemptId,
                        'quiz' => $quiz,
                    ], 'Attempt started');
                    break;

                case 'submit_answer':
                    $attemptId = (int)($input['attempt_id'] ?? 0);
                    $questionId = (int)($input['question_id'] ?? 0);
                    $answerId = isset($input['answer_id']) ? (int)$input['answer_id'] : null;
                    $answerText = $input['answer_text'] ?? null;
                    
                    if (!$attemptId || !$questionId) le_error_response('Missing parameters');
                    
                    $quizModel->submitAnswer($attemptId, $questionId, $answerId, $answerText);
                    le_success_response(null, 'Answer submitted');
                    break;

                case 'complete_attempt':
                    $attemptId = (int)($input['attempt_id'] ?? 0);
                    if (!$attemptId) le_error_response('Attempt ID required');
                    $result = $quizModel->completeAttempt($attemptId);
                    if (!$result) le_error_response('Failed to complete attempt');
                    le_success_response($result, 'Attempt completed');
                    break;

                default:
                    le_error_response('Unknown action', 400);
            }
            break;

        case 'PUT':
            le_require_csrf();
            $input = le_get_json_input() ?? $_POST;
            if ($quizId) {
                $allowed = ['title', 'description', 'time_limit_minutes', 'passing_score', 'shuffle_questions', 'show_results', 'max_attempts'];
                $updateData = array_intersect_key($input, array_flip($allowed));
                if ($quizModel->update($quizId, $updateData)) {
                    le_success_response($quizModel->find($quizId), 'Quiz updated');
                }
                le_error_response('Failed to update quiz');
            }
            le_error_response('Quiz ID required', 400);
            break;

        default:
            le_error_response('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Quiz API error: " . $e->getMessage());
    le_error_response('Internal server error', 500);
}