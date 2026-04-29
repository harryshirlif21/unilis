<?php
require_once __DIR__.'/../models/PracticalModel.php';
require_once __DIR__.'/../models/PracticalRequestModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class PracticalRequestController {
    private PracticalModel $practicalModel;
    private PracticalRequestModel $requestModel;
    
    public function __construct() {
        $this->practicalModel = new PracticalModel();
        $this->requestModel = new PracticalRequestModel();
    }
    
    public function index($param = null) {
        Auth::guard('student');
        
        $studentId = Auth::id();
        
        // Get student's practical requests
        $requests = $this->requestModel->getStudentRequests($studentId);
        
        renderView('practical-requests/index', [
            'requests' => $requests,
            'userRole' => 'student'
        ]);
    }
    
    public function create($param = null) {
        Auth::guard('student');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $practicalId = sanitize($_POST['practical_id'] ?? '');
            $reason = sanitize($_POST['reason'] ?? '');
            $preferredLab = sanitize($_POST['preferred_lab'] ?? '');
            $urgency = sanitize($_POST['urgency'] ?? 'normal');
            
            if (empty($practicalId) || empty($reason)) {
                $error = 'Practical ID and reason are required.';
            } else {
                // Check if practical exists
                $practical = $this->practicalModel->getById($practicalId);
                
                if (!$practical) {
                    $error = 'Practical not found.';
                } else {
                    // Check if student is already enrolled
                    if ($this->practicalModel->isStudentEnrolled(Auth::id(), $practicalId)) {
                        $error = 'You are already enrolled in this practical.';
                    } else {
                        // Create the request
                        $requestData = [
                            'id' => bin2hex(random_bytes(16)),
                            'student_id' => Auth::id(),
                            'practical_id' => $practicalId,
                            'reason' => $reason,
                            'preferred_lab' => $preferredLab,
                            'urgency' => $urgency,
                            'status' => 'pending',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        if ($this->requestModel->create($requestData)) {
                            logActivity(Auth::id(), 'practical_request_created', 'practicals');
                            $success = 'Your practical request has been submitted for review.';
                            
                            // Notify lecturer (in real system, this would send email/notification)
                        } else {
                            $error = 'Failed to submit practical request. Please try again.';
                        }
                    }
                }
            }
            
            renderView('practical-requests/create', [
                'error' => $error ?? '',
                'success' => $success ?? '',
                'practicals' => $this->practicalModel->getAvailablePracticals()
            ]);
        } else {
            renderView('practical-requests/create', [
                'practicals' => $this->practicalModel->getAvailablePracticals(),
                'error' => '',
                'success' => ''
            ]);
        }
    }
    
    public function view($requestId = null) {
        Auth::guard('student');
        
        if (!$requestId) {
            redirect('practical-requests');
        }
        
        $request = $this->requestModel->getById($requestId);
        
        if (!$request) {
            http_response_code(404);
            echo '404 — Request not found';
            exit;
        }
        
        renderView('practical-requests/view', [
            'request' => $request,
            'userRole' => 'student'
        ]);
    }
    
    public function cancel($requestId = null) {
        Auth::guard('student');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$requestId) {
                $error = 'Request ID is required.';
            } else {
                $request = $this->requestModel->getById($requestId);
                
                if (!$request) {
                    $error = 'Request not found.';
                } elseif ($request['student_id'] !== Auth::id()) {
                    $error = 'You can only cancel your own requests.';
                } elseif ($request['status'] !== 'pending') {
                    $error = 'This request cannot be cancelled.';
                } else {
                    // Update request status
                    if ($this->requestModel->updateStatus($requestId, 'cancelled')) {
                        logActivity(Auth::id(), 'practical_request_cancelled', 'practicals');
                        $success = 'Your request has been cancelled.';
                    } else {
                        $error = 'Failed to cancel request. Please try again.';
                    }
                }
            }
            
            renderView('practical-requests/cancel', [
                'request' => $request ?? null,
                'error' => $error ?? '',
                'success' => $success ?? ''
            ]);
        } else {
            redirect('practical-requests');
        }
    }
}
