<?php
/**
 * Live Engagement Module - Poll API
 * 
 * RESTful API endpoints for poll management.
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
$pollId = (int)le_get('id', 0, true);
$sessionId = (int)le_get('session_id', 0, true);
$userId = le_current_user_id();

$pollModel = new \LE\Models\PollModel();

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list' && $sessionId) {
                le_success_response($pollModel->getSessionPolls($sessionId));
            } elseif ($action === 'results' && $pollId) {
                le_success_response($pollModel->getResults($pollId));
            } elseif ($action === 'active' && $sessionId) {
                $polls = $pollModel->getSessionPolls($sessionId);
                $active = array_filter($polls, fn($p) => $p['is_active']);
                le_success_response(array_values($active));
            } elseif ($pollId) {
                le_success_response($pollModel->getResults($pollId));
            } else {
                le_error_response('Missing parameters', 400);
            }
            break;

        case 'POST':
            le_require_csrf();
            $input = le_get_json_input() ?? $_POST;

            switch ($action) {
                case 'create':
                    if (empty($input['question']) || empty($input['session_id'])) {
                        le_error_response('Question and session_id required');
                    }
                    $options = $input['options'] ?? [];
                    if (count($options) < 2) le_error_response('At least 2 options required');
                    
                    $pollId = $pollModel->createWithOptions($input, $options);
                    if (!$pollId) le_error_response('Failed to create poll');
                    le_success_response($pollModel->getResults($pollId), 'Poll created');
                    break;

                case 'activate':
                    if (!$pollId) le_error_response('Poll ID required');
                    if ($pollModel->activate($pollId)) {
                        le_success_response($pollModel->getResults($pollId), 'Poll activated');
                    }
                    le_error_response('Failed to activate poll');
                    break;

                case 'close':
                    if (!$pollId) le_error_response('Poll ID required');
                    if ($pollModel->close($pollId)) {
                        le_success_response($pollModel->getResults($pollId), 'Poll closed');
                    }
                    le_error_response('Failed to close poll');
                    break;

                case 'vote':
                    if (!$pollId) le_error_response('Poll ID required');
                    $optionId = (int)($input['option_id'] ?? 0);
                    $ratingValue = isset($input['rating']) ? (int)$input['rating'] : null;
                    $responseText = $input['response_text'] ?? null;
                    
                    if ($pollModel->hasResponded($pollId, $userId)) {
                        le_error_response('Already responded');
                    }
                    
                    $result = $pollModel->submitResponse(
                        $pollId, $optionId ?: null, $userId, null, $ratingValue, $responseText
                    );
                    if ($result) {
                        le_success_response(null, 'Response recorded');
                    }
                    le_error_response('Failed to record response');
                    break;

                default:
                    le_error_response('Unknown action', 400);
            }
            break;

        case 'PUT':
            le_require_csrf();
            $input = le_get_json_input() ?? $_POST;
            if ($pollId) {
                $allowed = ['question', 'poll_type', 'is_anonymous', 'is_multiple_answer', 'time_limit_seconds'];
                $updateData = array_intersect_key($input, array_flip($allowed));
                if ($pollModel->update($pollId, $updateData)) {
                    le_success_response($pollModel->find($pollId), 'Poll updated');
                }
                le_error_response('Failed to update poll');
            }
            le_error_response('Poll ID required', 400);
            break;

        default:
            le_error_response('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Poll API error: " . $e->getMessage());
    le_error_response('Internal server error', 500);
}