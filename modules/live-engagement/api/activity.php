<?php
/**
 * Live Engagement Module - Activity API
 * 
 * RESTful API endpoints for word cloud, open responses, whiteboard, and reactions.
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
$userId = le_current_user_id();

$wcModel = new \LE\Models\WordCloudModel();
$orModel = new \LE\Models\OpenResponseModel();
$wbModel = new \LE\Models\WhiteboardModel();

try {
    switch ($method) {
        case 'GET':
            $sessionId = (int)le_get('session_id', 0, true);
            $id = (int)le_get('id', 0, true);

            switch ($action) {
                // Word Cloud
                case 'wordcloud_list':
                    if (!$sessionId) le_error_response('Session ID required');
                    le_success_response($wcModel->findBy('session_id', $sessionId));
                    break;
                case 'wordcloud_words':
                    if (!$id) le_error_response('Word cloud ID required');
                    le_success_response($wcModel->getWords($id));
                    break;

                // Open Responses
                case 'responses_list':
                    if (!$sessionId) le_error_response('Session ID required');
                    le_success_response($orModel->findBy('session_id', $sessionId));
                    break;
                case 'responses_view':
                    if (!$id) le_error_response('Open response ID required');
                    $approvedOnly = le_get('approved', '1') === '1';
                    le_success_response($orModel->getResponses($id, $approvedOnly));
                    break;

                // Whiteboard
                case 'whiteboard_list':
                    if (!$sessionId) le_error_response('Session ID required');
                    le_success_response($wbModel->findBy('session_id', $sessionId));
                    break;
                case 'whiteboard_objects':
                    if (!$id) le_error_response('Whiteboard ID required');
                    le_success_response($wbModel->getObjects($id));
                    break;

                default:
                    le_error_response('Unknown action', 400);
            }
            break;

        case 'POST':
            le_require_csrf();
            $input = le_get_json_input() ?? $_POST;
            $sessionId = (int)$input['session_id'];
            $id = (int)($input['id'] ?? 0);
            $participantId = (int)($input['participant_id'] ?? 0);

            switch ($action) {
                // Word Cloud
                case 'wordcloud_create':
                    if (empty($input['prompt']) || !$sessionId) le_error_response('Prompt and session_id required');
                    $input['created_by'] = $userId;
                    $wcId = $wcModel->create($input);
                    if (!$wcId) le_error_response('Failed to create word cloud');
                    le_success_response($wcModel->find($wcId), 'Word cloud created');
                    break;

                case 'wordcloud_submit':
                    if (!$id || empty($input['word'])) le_error_response('Missing parameters');
                    $result = $wcModel->submitWord($id, $input['word'], $userId, $participantId ?: null);
                    if (!$result) le_error_response('Failed to submit word');
                    le_success_response(null, 'Word submitted');
                    break;

                case 'wordcloud_activate':
                    if (!$id) le_error_response('Word cloud ID required');
                    if ($wcModel->activate($id)) le_success_response(null, 'Word cloud activated');
                    le_error_response('Failed to activate');
                    break;

                case 'wordcloud_close':
                    if (!$id) le_error_response('Word cloud ID required');
                    if ($wcModel->close($id)) le_success_response(null, 'Word cloud closed');
                    le_error_response('Failed to close');
                    break;

                // Open Responses
                case 'responses_create':
                    if (empty($input['prompt']) || !$sessionId) le_error_response('Prompt and session_id required');
                    $input['created_by'] = $userId;
                    $orId = $orModel->create($input);
                    if (!$orId) le_error_response('Failed to create open response');
                    le_success_response($orModel->find($orId), 'Open response created');
                    break;

                case 'responses_submit':
                    if (!$id || empty($input['response_text'])) le_error_response('Missing parameters');
                    $anonymous = ($input['anonymous'] ?? 'false') === 'true';
                    $result = $orModel->submitResponse($id, $input['response_text'], $userId, $participantId ?: null, $anonymous);
                    if (!$result) le_error_response('Failed to submit response');
                    le_success_response(null, 'Response submitted');
                    break;

                case 'responses_moderate':
                    $submissionId = (int)($input['submission_id'] ?? 0);
                    $approve = ($input['approve'] ?? 'true') === 'true';
                    if (!$submissionId) le_error_response('Submission ID required');
                    if ($orModel->moderateResponse($submissionId, $approve)) {
                        le_success_response(null, $approve ? 'Response approved' : 'Response rejected');
                    }
                    le_error_response('Failed to moderate');
                    break;

                // Whiteboard
                case 'whiteboard_create':
                    if (!$sessionId) le_error_response('Session ID required');
                    $input['created_by'] = $userId;
                    $wbId = $wbModel->create($input);
                    if (!$wbId) le_error_response('Failed to create whiteboard');
                    le_success_response($wbModel->find($wbId), 'Whiteboard created');
                    break;

                case 'whiteboard_add_object':
                    if (!$id || empty($input['object_type']) || empty($input['object_data'])) {
                        le_error_response('Missing parameters');
                    }
                    $objectData = is_string($input['object_data']) ? json_decode($input['object_data'], true) : $input['object_data'];
                    $styleData = isset($input['style_data']) ? (is_string($input['style_data']) ? json_decode($input['style_data'], true) : $input['style_data']) : [];
                    
                    $objId = $wbModel->addObject($id, $input['object_type'], $objectData, $styleData, $userId);
                    if (!$objId) le_error_response('Failed to add object');
                    le_success_response(['object_id' => $objId], 'Object added');
                    break;

                case 'whiteboard_clear':
                    if (!$id) le_error_response('Whiteboard ID required');
                    if ($wbModel->clearObjects($id)) le_success_response(null, 'Whiteboard cleared');
                    le_error_response('Failed to clear whiteboard');
                    break;

                default:
                    le_error_response('Unknown action', 400);
            }
            break;

        case 'PUT':
            le_require_csrf();
            $input = le_get_json_input() ?? $_POST;
            $type = le_get('type', '');
            $id = (int)le_get('id', 0, true);

            switch ($type) {
                case 'wordcloud':
                    if ($id && !empty($input)) {
                        $allowed = ['prompt', 'max_words', 'min_word_length'];
                        $updateData = array_intersect_key($input, array_flip($allowed));
                        if ($wcModel->update($id, $updateData)) {
                            le_success_response($wcModel->find($id), 'Word cloud updated');
                        }
                        le_error_response('Failed to update');
                    }
                    break;
                case 'responses':
                    if ($id && !empty($input)) {
                        $allowed = ['prompt', 'response_type', 'is_anonymous', 'is_moderated', 'max_characters'];
                        $updateData = array_intersect_key($input, array_flip($allowed));
                        if ($orModel->update($id, $updateData)) {
                            le_success_response($orModel->find($id), 'Open response updated');
                        }
                        le_error_response('Failed to update');
                    }
                    break;
                case 'whiteboard':
                    if ($id && !empty($input)) {
                        $allowed = ['title', 'background_color', 'is_collaborative'];
                        $updateData = array_intersect_key($input, array_flip($allowed));
                        if ($wbModel->update($id, $updateData)) {
                            le_success_response($wbModel->find($id), 'Whiteboard updated');
                        }
                        le_error_response('Failed to update');
                    }
                    break;
                default:
                    le_error_response('Invalid type', 400);
            }
            le_error_response('Missing parameters', 400);
            break;

        default:
            le_error_response('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Activity API error: " . $e->getMessage());
    le_error_response('Internal server error', 500);
}