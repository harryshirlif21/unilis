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

try {
    switch ($method) {
        case 'GET':
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
                
                // Check ownership - use created_by if available, otherwise check via session
                if (isset($presentation['created_by'])) {
                    if ((int)$presentation['created_by'] !== $userId && $role !== 'admin') {
                        le_error_response('Unauthorized', 403);
                    }
                } else {
                    // Fallback: check if user owns the session this presentation belongs to
                    $sessionModel = new \LE\Models\SessionModel();
                    $session = $sessionModel->find($presentation['session_id']);
                    if (!$session || ((int)$session['lecturer_id'] !== $userId && $role !== 'admin')) {
                        le_error_response('Unauthorized', 403);
                    }
                }
                
                le_success_response($presentation);
            } else {
                le_error_response('Missing parameters', 400);
            }
            break;

        case 'POST':
            le_require_csrf();
            
            switch ($action) {
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
                    
                    // Check ownership - use created_by if available, otherwise check via session
                    if (isset($original['created_by'])) {
                        if ((int)$original['created_by'] !== $userId && $role !== 'admin') {
                            le_error_response('Unauthorized', 403);
                        }
                    } else {
                        // Fallback: check if user owns the session this presentation belongs to
                        $sessionModel = new \LE\Models\SessionModel();
                        $session = $sessionModel->find($original['session_id']);
                        if (!$session || ((int)$session['lecturer_id'] !== $userId && $role !== 'admin')) {
                            le_error_response('Unauthorized', 403);
                        }
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
            
            // Check ownership - use created_by if available, otherwise check via session
            if (isset($presentation['created_by'])) {
                if ((int)$presentation['created_by'] !== $userId && $role !== 'admin') {
                    le_error_response('Unauthorized', 403);
                }
            } else {
                // Fallback: check if user owns the session this presentation belongs to
                $sessionModel = new \LE\Models\SessionModel();
                $session = $sessionModel->find($presentation['session_id']);
                if (!$session || ((int)$session['lecturer_id'] !== $userId && $role !== 'admin')) {
                    le_error_response('Unauthorized', 403);
                }
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
            
            // Check ownership - use created_by if available, otherwise check via session
            if (isset($presentation['created_by'])) {
                if ((int)$presentation['created_by'] !== $userId && $role !== 'admin') {
                    le_error_response('Unauthorized', 403);
                }
            } else {
                // Fallback: check if user owns the session this presentation belongs to
                $sessionModel = new \LE\Models\SessionModel();
                $session = $sessionModel->find($presentation['session_id']);
                if (!$session || ((int)$session['lecturer_id'] !== $userId && $role !== 'admin')) {
                    le_error_response('Unauthorized', 403);
                }
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
