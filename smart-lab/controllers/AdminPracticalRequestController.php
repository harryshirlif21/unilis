<?php
require_once __DIR__.'/../models/PracticalRequestModel.php';
require_once __DIR__.'/../models/PracticalModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class AdminPracticalRequestController {
    private PracticalRequestModel $requestModel;
    private PracticalModel $practicalModel;
    
    public function __construct() {
        $this->requestModel = new PracticalRequestModel();
        $this->practicalModel = new PracticalModel();
    }
    
    public function index($param = null) {
        Auth::guard(['admin', 'lecturer']);
        
        $requests = $this->requestModel->getAllRequests();
        $stats = $this->requestModel->getRequestStats();
        
        renderView('admin/practical-requests/index', [
            'requests' => $requests,
            'stats' => $stats,
            'userRole' => Auth::role()
        ]);
    }
    
    public function view($requestId = null) {
        Auth::guard(['admin', 'lecturer']);
        
        if (!$requestId) {
            redirect('admin/practical-requests');
        }
        
        $request = $this->requestModel->getById($requestId);
        
        if (!$request) {
            http_response_code(404);
            echo '404 — Request not found';
            exit;
        }
        
        renderView('admin/practical-requests/view', [
            'request' => $request,
            'userRole' => Auth::role()
        ]);
    }
    
    public function approve($requestId = null) {
        Auth::guard(['admin', 'lecturer']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$requestId) {
                $error = 'Request ID is required.';
            } else {
                $request = $this->requestModel->getById($requestId);
                
                if (!$request) {
                    $error = 'Request not found.';
                } elseif ($request['status'] !== 'pending') {
                    $error = 'This request cannot be approved.';
                } else {
                    // Get form data
                    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
                    $assignedLab = sanitize($_POST['assigned_lab'] ?? '');
                    $scheduledDate = sanitize($_POST['scheduled_date'] ?? '');
                    $scheduledTime = sanitize($_POST['scheduled_time'] ?? '');
                    
                    // Update request status
                    if ($this->requestModel->updateStatus($requestId, 'approved')) {
                        // Update admin notes (in real system, this would be a separate method)
                        $this->updateAdminNotes($requestId, $adminNotes);
                        
                        // Enroll student in the practical
                        $this->enrollStudent($request['student_id'], $request['practical_id']);
                        
                        // Create lab session if specified
                        if ($assignedLab && $scheduledDate && $scheduledTime) {
                            $this->createLabSession($request['practical_id'], $assignedLab, $scheduledDate, $scheduledTime);
                        }
                        
                        logActivity(Auth::id(), 'practical_request_approved', 'practicals');
                        $success = 'Practical request has been approved and student has been enrolled.';
                    } else {
                        $error = 'Failed to approve request. Please try again.';
                    }
                }
            }
            
            renderView('admin/practical-requests/approve', [
                'request' => $request ?? null,
                'error' => $error ?? '',
                'success' => $success ?? '',
                'labs' => $this->practicalModel->getLabs()
            ]);
        } else {
            redirect('admin/practical-requests');
        }
    }
    
    public function reject($requestId = null) {
        Auth::guard(['admin', 'lecturer']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$requestId) {
                $error = 'Request ID is required.';
            } else {
                $request = $this->requestModel->getById($requestId);
                
                if (!$request) {
                    $error = 'Request not found.';
                } elseif ($request['status'] !== 'pending') {
                    $error = 'This request cannot be rejected.';
                } else {
                    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
                    
                    // Update request status
                    if ($this->requestModel->updateStatus($requestId, 'rejected')) {
                        // Update admin notes
                        $this->updateAdminNotes($requestId, $adminNotes);
                        
                        logActivity(Auth::id(), 'practical_request_rejected', 'practicals');
                        $success = 'Practical request has been rejected.';
                    } else {
                        $error = 'Failed to reject request. Please try again.';
                    }
                }
            }
            
            renderView('admin/practical-requests/reject', [
                'request' => $request ?? null,
                'error' => $error ?? '',
                'success' => $success ?? ''
            ]);
        } else {
            redirect('admin/practical-requests');
        }
    }
    
    private function updateAdminNotes(string $requestId, string $notes): void {
        $db = getDB();
        $stmt = $db->prepare(
            "UPDATE practical_requests 
             SET admin_notes = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$notes, $requestId]);
    }
    
    private function enrollStudent(string $studentId, string $practicalId): void {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT IGNORE INTO student_practicals 
             (student_id, practical_id, enrolled_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([$studentId, $practicalId]);
    }
    
    private function createLabSession(string $practicalId, string $labId, string $date, string $time): void {
        $sessionId = bin2hex(random_bytes(16));
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO lab_sessions 
             (id, practical_id, lab_id, scheduled_date, start_time, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'scheduled', NOW())"
        );
        $stmt->execute([$sessionId, $practicalId, $labId, $date, $time]);
    }
}
