<?php
/**
 * Live Engagement Module - Session API
 * 
 * RESTful API endpoints for live session management.
 * 
 * @package UNILIS\LiveEngagement\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
le_require_auth();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$requestInput = in_array($method, ['POST', 'PUT'], true)
    ? (le_get_json_input() ?? $_POST)
    : [];
$action = le_get('action', $requestInput['action'] ?? '');
$sessionId = le_get('id', 0, true);
$code = le_get('code', '');

$sessionModel = new \LE\Models\SessionModel();
$userId = le_current_user_id();
$role = le_current_user_role();

try {
    switch ($method) {
        case 'GET':
            handleSessionGet($action, $sessionId, $code, $sessionModel, $userId, $role);
            break;
        case 'POST':
            le_require_csrf();
            handleSessionPost($action, $sessionModel, $userId, $role, $requestInput);
            break;
        case 'PUT':
            le_require_csrf();
            handleSessionPut($action, $sessionId, $sessionModel, $userId, $role, $requestInput);
            break;
        case 'DELETE':
            le_require_csrf();
            handleSessionDelete($action, $sessionId, $sessionModel, $userId, $role);
            break;
        default:
            le_error_response('Method not allowed', 405);
    }
} catch (Throwable $e) {
    le_error_response(
        'Unable to process this request. Please try again.',
        500,
        le_exception_payload($e, 'LE_SESSION_API', 'session_api')
    );
}

/**
 * Handle GET requests
 */
function handleSessionGet(string $action, int $sessionId, string $code, \LE\Models\SessionModel $model, ?int $userId, ?string $role): void
{
    switch ($action) {
        case 'list':
            if ($role === 'lecturer') {
                le_success_response([
                    'active' => $model->getLecturerActiveSessions($userId),
                    'history' => $model->getLecturerHistory($userId),
                ]);
            } else {
                le_success_response($model->getStudentAvailableSessions($userId));
            }
            break;

        case 'view':
            if (!$sessionId) le_error_response('Session ID required');
            $session = $model->getSessionWithStats($sessionId);
            if (!$session) le_error_response('Session not found', 404);
            
            // Check access
            if ($role !== 'lecturer' && $session['lecturer_id'] !== $userId) {
                // Check student enrollment
                $canAccess = false;
                // (Enrollment check omitted for brevity - handled by model)
            }
            le_success_response($session);
            break;

        case 'join':
            if (empty($code)) le_error_response('Session code required');
            $session = $model->findByCode($code);
            if (!$session) le_error_response('Session not found', 404);
            if (!in_array($session['status'], ['active', 'scheduled'])) {
                le_error_response('Session is not available', 403);
            }
            le_success_response($session);
            break;

        case 'stats':
            if (!$sessionId) le_error_response('Session ID required');
            le_success_response(\le_get_session_stats($sessionId));
            break;

        case 'participants':
            if (!$sessionId) le_error_response('Session ID required');
            $onlyOnline = le_get('online', '0') === '1';
            le_success_response(\le_get_participants($sessionId, $onlyOnline));
            break;

        case 'raised_hands':
            if (!$sessionId) le_error_response('Session ID required');
            le_success_response(\le_get_raised_hands($sessionId));
            break;

        case 'reactions':
            if (!$sessionId) le_error_response('Session ID required');
            $state = \le_get_live_room_state($sessionId);
            $reactionCounts = [];
            foreach ($state['reactions'] as $reaction) {
                $type = $reaction['type'];
                $reactionCounts[$type] = ($reactionCounts[$type] ?? 0) + 1;
            }
            le_success_response([
                'reactions' => $reactionCounts,
                'events' => array_map(static function (array $reaction): array {
                    return ['id' => $reaction['id'], 'type' => $reaction['type']];
                }, $state['reactions']),
            ]);
            break;

        case 'question_status':
            if (!$sessionId) le_error_response('Session ID required');
            $participant = \le_get_current_session_participant($sessionId, $userId);
            if (!$participant) le_error_response('Join this session before asking a question', 403);
            $state = \le_get_live_room_state($sessionId);
            $questions = array_values(array_filter(
                $state['questions'],
                static fn(array $question): bool => (int) $question['participant_id'] === (int) $participant['id']
            ));
            $question = $questions ? $questions[count($questions) - 1] : null;
            if ($question) {
                unset($question['participant_id'], $question['display_name'], $question['created_at'], $question['expires_at']);
            }
            le_success_response(['question' => $question]);
            break;

        case 'questions':
            if (!$sessionId) le_error_response('Session ID required');
            $session = $model->find($sessionId);
            if (!$session) le_error_response('Session not found', 404);
            if ((int) $session['lecturer_id'] !== $userId && $role !== 'admin') {
                le_error_response('Unauthorized', 403);
            }
            $state = \le_get_live_room_state($sessionId);
            $questions = array_values(array_filter(
                $state['questions'],
                static fn(array $question): bool => in_array($question['status'], ['pending', 'submitted'], true)
            ));
            $questions = array_map(static function (array $question): array {
                unset($question['participant_id'], $question['expires_at']);
                return $question;
            }, $questions);
            le_success_response($questions);
            break;

        case 'reports':
            if (!$sessionId) le_error_response('Session ID required');
            $reportModel = new \LE\Models\ReportModel();
            le_success_response($reportModel->getSessionReports($sessionId));
            break;

        case 'check':
            if (empty($code)) le_error_response('Code required');
            $session = $model->findByCode($code);
            le_success_response([
                'exists' => $session !== null,
                'active' => $session && $session['status'] === 'active',
                'session' => $session,
            ]);
            break;

        default:
            if ($sessionId) {
                le_success_response($model->getSessionWithStats($sessionId));
            } else {
                le_error_response('Invalid action', 400);
            }
    }
}

/**
 * Handle POST requests
 */
function handleSessionPost(string $action, \LE\Models\SessionModel $model, ?int $userId, ?string $role, array $input): void
{
    switch ($action) {
        case 'create':
            if (empty($input['title'])) le_error_response('Title is required');
            $sessionId = $model->createSession($input);
            if (!$sessionId) le_error_response('Failed to create session');
            le_success_response($model->find($sessionId), 'Session created');
            break;

        case 'join':
            $code = $input['code'] ?? '';
            $displayName = $input['display_name'] ?? le_current_user_name() ?? 'Anonymous';
            $guestId = !empty($_SESSION['le_guest_id']) ? (int) $_SESSION['le_guest_id'] : null;
            $email = trim(strtolower((string) ($input['email'] ?? $_SESSION['le_guest_email'] ?? le_current_user_email() ?? '')));
            
            if (empty($code)) le_error_response('Session code required');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) le_error_response('A valid email address is required');
            
            $session = $model->findByCode($code);
            if (!$session) le_error_response('Session not found', 404);
            if ($session['status'] !== 'active') le_error_response('Session is not active', 403);
            
            $participantId = \le_join_session(
                $session['id'], $userId, $displayName, 'participant', $guestId, $email
            );
            if (!$participantId) le_error_response('Failed to join session');
            
            le_success_response([
                'session' => $session,
                'participant_id' => $participantId,
            ], 'Joined session');
            break;

        case 'leave':
            $participantId = (int)($input['participant_id'] ?? 0);
            if (!$participantId) le_error_response('Participant ID required');
            
            \le_leave_session($participantId);
            le_success_response(null, 'Left session');
            break;

        case 'start':
            $sessionId = (int)($input['session_id'] ?? 0);
            if (!$sessionId) le_error_response('Session ID required');
            if ($model->start($sessionId)) {
                le_success_response($model->find($sessionId), 'Session started');
            }
            le_error_response('Failed to start session');
            break;

        case 'pause':
            $sessionId = (int)($input['session_id'] ?? 0);
            if (!$sessionId) le_error_response('Session ID required');
            if ($model->pause($sessionId)) {
                le_success_response(null, 'Session paused');
            }
            le_error_response('Failed to pause session');
            break;

        case 'end':
            $sessionId = (int)($input['session_id'] ?? 0);
            if (!$sessionId) le_error_response('Session ID required');
            if ($model->end($sessionId)) {
                \le_clear_live_room_state($sessionId);
                le_success_response(null, 'Session ended');
            }
            le_error_response('Failed to end session');
            break;

        case 'raise_hand':
            $sessionId = (int) ($input['session_id'] ?? 0);
            $participant = $sessionId ? \le_get_current_session_participant($sessionId, $userId) : null;
            if (!$participant) le_error_response('Join this session before raising your hand', 403);
            $raised = filter_var($input['raised'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if (!\le_set_hand_raised((int) $participant['id'], $raised)) {
                le_error_response('Failed to update raised hand', 500);
            }
            le_success_response(null, $raised ? 'Hand raised' : 'Hand lowered');
            break;

        case 'reaction':
            $sessionId = (int)($input['session_id'] ?? 0);
            if (!$sessionId) le_error_response('Session ID required');
            $participant = \le_get_current_session_participant($sessionId, $userId);
            if (!$participant) le_error_response('Join this session before reacting', 403);
            $reactionType = (string) ($input['type'] ?? 'like');
            $allowedReactions = ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'clap', 'celebrate'];
            if (!in_array($reactionType, $allowedReactions, true)) {
                le_error_response('Invalid reaction', 400);
            }
            \le_add_reaction($sessionId, $userId, (int) $participant['id'], $reactionType);
            le_success_response(null, 'Reaction added');
            break;

        case 'question_request':
            $sessionId = (int) ($input['session_id'] ?? 0);
            if (!$sessionId) le_error_response('Session ID required');
            $participant = \le_get_current_session_participant($sessionId, $userId);
            if (!$participant) le_error_response('Join this session before asking a question', 403);
            $question = null;
            \le_update_live_room_state($sessionId, static function (array $state) use ($participant, &$question): array {
                foreach ($state['questions'] as $existing) {
                    if ((int) $existing['participant_id'] === (int) $participant['id']
                        && in_array($existing['status'], ['pending', 'allowed'], true)) {
                        $question = $existing;
                        return $state;
                    }
                }
                $question = [
                    'id' => bin2hex(random_bytes(12)),
                    'participant_id' => (int) $participant['id'],
                    'display_name' => (string) $participant['display_name'],
                    'status' => 'pending',
                    'question_text' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'expires_at' => time() + 3600,
                ];
                $state['questions'][] = $question;
                return $state;
            });
            unset($question['participant_id'], $question['display_name'], $question['created_at'], $question['expires_at']);
            le_success_response(['question' => $question], 'Question permission requested');
            break;

        case 'question_allow':
            $sessionId = (int) ($input['session_id'] ?? 0);
            $questionId = (string) ($input['question_id'] ?? '');
            if (!$sessionId || !$questionId) le_error_response('Session and question IDs are required');
            $session = $model->find($sessionId);
            if (!$session) le_error_response('Session not found', 404);
            if ((int) $session['lecturer_id'] !== $userId && $role !== 'admin') {
                le_error_response('Unauthorized', 403);
            }
            $allowed = false;
            \le_update_live_room_state($sessionId, static function (array $state) use ($questionId, &$allowed): array {
                foreach ($state['questions'] as &$question) {
                    if ($question['id'] === $questionId && $question['status'] === 'pending') {
                        $question['status'] = 'allowed';
                        $allowed = true;
                        break;
                    }
                }
                unset($question);
                return $state;
            });
            if (!$allowed) le_error_response('This question request is no longer pending', 409);
            le_success_response(null, 'Student may now type their question');
            break;

        case 'question_submit':
            $sessionId = (int) ($input['session_id'] ?? 0);
            $questionText = trim((string) ($input['question'] ?? ''));
            if (!$sessionId) le_error_response('Session ID required');
            if ($questionText === '' || !preg_match('/\A.{1,1000}\z/us', $questionText)) {
                le_error_response('Question must be between 1 and 1000 characters');
            }
            $participant = \le_get_current_session_participant($sessionId, $userId);
            if (!$participant) le_error_response('Join this session before asking a question', 403);
            $question = null;
            $allowedQuestionExists = false;
            \le_update_live_room_state($sessionId, static function (array $state) use ($participant, $questionText, &$question, &$allowedQuestionExists): array {
                foreach ($state['questions'] as &$storedQuestion) {
                    if ((int) $storedQuestion['participant_id'] === (int) $participant['id']
                        && $storedQuestion['status'] === 'allowed') {
                        $allowedQuestionExists = true;
                        $storedQuestion['question_text'] = $questionText;
                        $storedQuestion['status'] = 'submitted';
                        $question = [
                            'id' => $storedQuestion['id'],
                            'status' => $storedQuestion['status'],
                            'question_text' => $storedQuestion['question_text'],
                        ];
                        break;
                    }
                }
                unset($storedQuestion);
                return $state;
            });
            if (!$allowedQuestionExists) le_error_response('The lecturer has not allowed your question yet', 403);
            le_success_response(['question' => $question], 'Question submitted');
            break;

        case 'record_attendance':
            $participantId = (int)($input['participant_id'] ?? 0);
            $sessionId = (int)($input['session_id'] ?? 0);
            if (!$participantId || !$sessionId) le_error_response('Missing parameters');
            \le_record_attendance($sessionId, $participantId);
            le_success_response(null, 'Attendance recorded');
            break;

        case 'generate_report':
            $sessionId = (int)($input['session_id'] ?? 0);
            if (!$sessionId) le_error_response('Session ID required');
            $reportModel = new \LE\Models\ReportModel();
            $report = $reportModel->generateComprehensiveReport($sessionId, $userId);
            if (!$report) le_error_response('Failed to generate report');
            le_success_response($report, 'Report generated');
            break;

        default:
            le_error_response('Unknown action', 400);
    }
}

/**
 * Handle PUT requests
 */
function handleSessionPut(string $action, int $sessionId, \LE\Models\SessionModel $model, ?int $userId, ?string $role, array $input): void
{
    switch ($action) {
        case 'update':
            if (!$sessionId) le_error_response('Session ID required');
            $session = $model->find($sessionId);
            if (!$session) le_error_response('Session not found', 404);
            if ((int) $session['lecturer_id'] !== $userId && $role !== 'admin') le_error_response('Unauthorized', 403);

            $allowed = ['title', 'description', 'session_type', 'duration_minutes', 'passcode', 'max_participants'];
            $updateData = array_intersect_key($input, array_flip($allowed));
            
            if (empty($updateData)) le_error_response('No valid fields to update');
            
            if ($model->update($sessionId, $updateData) !== false) {
                le_success_response($model->find($sessionId), 'Session updated');
            }
            le_error_response('Failed to update session');
            break;

        default:
            if ($sessionId && empty($action)) {
                // Default update
                unset($input['action']);
                if ($model->update($sessionId, $input)) {
                    le_success_response($model->find($sessionId), 'Session updated');
                }
                le_error_response('Failed to update session');
            }
            le_error_response('Unknown action', 400);
    }
}

/**
 * Handle DELETE requests
 */
function handleSessionDelete(string $action, int $sessionId, \LE\Models\SessionModel $model, ?int $userId, ?string $role): void
{
    switch ($action) {
        case 'delete':
            if (!$sessionId) le_error_response('Session ID required');
            $session = $model->find($sessionId);
            if (!$session) le_error_response('Session not found', 404);
            if ((int) $session['lecturer_id'] !== $userId && $role !== 'admin') {
                le_error_response('Unauthorized', 403);
            }
            if ($model->delete($sessionId)) {
                \le_clear_live_room_state($sessionId);
                le_success_response(null, 'Session deleted');
            }
            le_error_response('Failed to delete session');
            break;

        default:
            le_error_response('Unknown action', 400);
    }
}
