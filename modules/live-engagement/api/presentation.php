<?php
/**
 * Live Engagement Module - Presentation API
 * 
 * RESTful API endpoints for presentation management.
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
$presId = le_get('id', 0, true);
$userId = le_current_user_id();
$role = le_current_user_role();

$presModel = new \LE\Models\PresentationModel();

/**
 * Ownership test used by every presentation action.
 *
 * created_by was added to live_presentations later than the table itself, so
 * installs that predate that migration have no such column. Those fall back to
 * the owner of the session the presentation hangs off, which is how ownership
 * was expressed before the column existed.
 *
 * The role gate is not redundant. Both created_by and lecturer_id hold lecturer
 * ids, and lecturer ids come from a different auto-increment sequence than
 * student ids - so student 1 and lecturer 1 are different people who share an
 * integer. Comparing ids alone let any student whose id happened to match the
 * owning lecturer's id edit, delete and drive that lecturer's deck.
 */
function le_pres_user_owns(array $presentation, int $userId, ?string $role): bool
{
    if ($role === 'admin') {
        return true;
    }
    if ($role !== 'lecturer') {
        return false;
    }

    if (isset($presentation['created_by']) && $presentation['created_by'] !== null) {
        return (int) $presentation['created_by'] === $userId;
    }

    $sessionModel = new \LE\Models\SessionModel();
    $session = $sessionModel->find((int) $presentation['session_id']);

    return $session && (int) $session['lecturer_id'] === $userId;
}

try {
    switch ($method) {
        case 'GET':
            // ---- Slide deck for the presenter runtime -------------------
            // Read-only and available to any authenticated participant: a
            // student needs the deck to render the slide the presenter is on.
            if ($action === 'slides') {
                if (!$presId) le_error_response('Missing presentation id', 400);

                $presentation = $presModel->find($presId);
                if (!$presentation) le_error_response('Presentation not found', 404);

                $slideModel = new \LE\Models\SlideModel();
                $slides = $slideModel->findBy('presentation_id', $presId);

                le_success_response([
                    'presentation' => [
                        'id'            => (int) $presentation['id'],
                        'session_id'    => (int) $presentation['session_id'],
                        'title'         => $presentation['title'],
                        'total_slides'  => (int) $presentation['total_slides'],
                        'current_slide' => (int) $presentation['current_slide'],
                        'is_active'     => (int) $presentation['is_active'],
                        'notes'         => $presentation['presenter_notes'] ?? '',
                    ],
                    'slides' => $slides,
                ]);
            }

            // ---- Live position, polled by students ----------------------
            // Deliberately tiny: this is fetched on a timer by every attendee,
            // so it returns the cursor and nothing else.
            if ($action === 'state') {
                $sessionId = (int) le_get('session_id', 0, true);
                if (!$sessionId && !$presId) le_error_response('Missing session_id or id', 400);

                $presentation = $presId
                    ? $presModel->find($presId)
                    : ($presModel->findBy('session_id', $sessionId)[0] ?? null);

                if (!$presentation) {
                    // Not an error: a session can run with no deck attached.
                    le_success_response(['active' => false, 'current_slide' => 0]);
                }

                le_success_response([
                    'active'         => (int) $presentation['is_active'] === 1,
                    'presentation_id'=> (int) $presentation['id'],
                    'current_slide'  => (int) $presentation['current_slide'],
                    'total_slides'   => (int) $presentation['total_slides'],
                    'title'          => $presentation['title'],
                ]);
            }

            if ($action === 'list') {
                $search = le_get('search', '');
                $courseFilter = (int)le_get('course_id', 0, true);
                $sort = le_get('sort', 'newest');
                $currentPage = max(1, (int)le_get('p', 1, true));
                $perPage = 20;
                
                le_success_response($presModel->getUserPresentations($userId, $search, $courseFilter, $sort, $currentPage, $perPage));
            } elseif ($presId) {
                $presentation = $presModel->find($presId);
                if (!$presentation) le_error_response('Presentation not found', 404);
                
                if (!le_pres_user_owns($presentation, $userId, $role)) {
                    le_error_response('Unauthorized', 403);
                }
                
                le_success_response($presentation);
            } else {
                le_error_response('Missing parameters', 400);
            }
            break;

        case 'POST':
            le_require_csrf();

            switch ($action) {
                // ---- Move the deck cursor (presenter only) --------------
                // Students poll ?action=state, so writing here is what makes
                // every attendee's screen follow the presenter.
                case 'goto_slide':
                    $targetPres = (int) ($requestInput['presentation_id'] ?? $presId);
                    if (!$targetPres) le_error_response('presentation_id required');

                    $presentation = $presModel->find($targetPres);
                    if (!$presentation) le_error_response('Presentation not found', 404);
                    if (!le_pres_user_owns($presentation, $userId, $role)) {
                        le_error_response('Unauthorized', 403);
                    }

                    $slide = (int) ($requestInput['slide'] ?? 0);
                    $total = (int) $presentation['total_slides'];
                    // Clamp rather than reject: holding the arrow key at either
                    // end of the deck should stop, not raise a wall of errors.
                    if ($total > 0) {
                        $slide = max(1, min($slide, $total));
                    } else {
                        $slide = max(0, $slide);
                    }

                    $presModel->update($targetPres, ['current_slide' => $slide]);
                    le_success_response(['current_slide' => $slide, 'total_slides' => $total]);
                    break;

                // ---- Mark the deck live / not live ----------------------
                case 'set_active':
                    $targetPres = (int) ($requestInput['presentation_id'] ?? $presId);
                    if (!$targetPres) le_error_response('presentation_id required');

                    $presentation = $presModel->find($targetPres);
                    if (!$presentation) le_error_response('Presentation not found', 404);
                    if (!le_pres_user_owns($presentation, $userId, $role)) {
                        le_error_response('Unauthorized', 403);
                    }

                    $makeActive = !empty($requestInput['active']) ? 1 : 0;
                    $presModel->update($targetPres, ['is_active' => $makeActive]);
                    le_success_response(['is_active' => $makeActive]);
                    break;

                case 'create':
                    if (empty($requestInput['title']) || empty($requestInput['session_id'])) {
                        le_error_response('Title and session_id required');
                    }
                    
                    $requestInput['created_by'] = $userId;
                    
                    // Try to create with created_by, fall back without it if column doesn't exist
                    try {
                        $presId = $presModel->create($requestInput);
                    } catch (Exception $e) {
                        // Column might not exist, try without created_by
                        unset($requestInput['created_by']);
                        $presId = $presModel->create($requestInput);
                    }
                    
                    if (!$presId) le_error_response('Failed to create presentation');
                    le_success_response($presModel->find($presId), 'Presentation created');
                    break;

                case 'duplicate':
                    if (!$presId) le_error_response('Presentation ID required');
                    
                    $original = $presModel->find($presId);
                    if (!$original) le_error_response('Presentation not found', 404);
                    
                    if (!le_pres_user_owns($original, $userId, $role)) {
                        le_error_response('Unauthorized', 403);
                    }
                    
                    $duplicateData = $original;
                    unset($duplicateData['id'], $duplicateData['created_at']);
                    $duplicateData['title'] = $original['title'] . ' (Copy)';
                    $duplicateData['created_by'] = $userId;
                    
                    // Try to create with created_by, fall back without it if column doesn't exist
                    try {
                        $newPresId = $presModel->create($duplicateData);
                    } catch (Exception $e) {
                        // Column might not exist, try without created_by
                        unset($duplicateData['created_by']);
                        $newPresId = $presModel->create($duplicateData);
                    }
                    
                    if (!$newPresId) le_error_response('Failed to duplicate presentation');
                    
                    // Duplicate slides
                    $slides = $presModel->getSlides($presId);
                    $slideModel = new \LE\Models\SlideModel();
                    foreach ($slides as $slide) {
                        unset($slide['id'], $slide['created_at']);
                        $slide['presentation_id'] = $newPresId;
                        $slideModel->create($slide);
                    }
                    
                    le_success_response($presModel->find($newPresId), 'Presentation duplicated');
                    break;

                default:
                    le_error_response('Unknown action', 400);
            }
            break;

        case 'PUT':
            le_require_csrf();
            
            if (!$presId) le_error_response('Presentation ID required');
            
            $presentation = $presModel->find($presId);
            if (!$presentation) le_error_response('Presentation not found', 404);
            
            if (!le_pres_user_owns($presentation, $userId, $role)) {
                le_error_response('Unauthorized', 403);
            }
            
            $allowed = ['title', 'description', 'is_active', 'allow_download', 'allow_annotations'];
            $updateData = array_intersect_key($requestInput, array_flip($allowed));
            
            if (empty($updateData)) le_error_response('No valid fields to update');
            
            if ($presModel->update($presId, $updateData) !== false) {
                le_success_response($presModel->find($presId), 'Presentation updated');
            }
            le_error_response('Failed to update presentation');
            break;

        case 'DELETE':
            le_require_csrf();
            
            if (!$presId) le_error_response('Presentation ID required');
            
            $presentation = $presModel->find($presId);
            if (!$presentation) le_error_response('Presentation not found', 404);
            
            if (!le_pres_user_owns($presentation, $userId, $role)) {
                le_error_response('Unauthorized', 403);
            }
            
            if ($presModel->delete($presId)) {
                le_success_response(null, 'Presentation deleted');
            }
            le_error_response('Failed to delete presentation');
            break;

        default:
            le_error_response('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Presentation API error: " . $e->getMessage());
    le_error_response('Internal server error', 500);
}
