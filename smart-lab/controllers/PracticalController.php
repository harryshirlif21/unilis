<?php
require_once __DIR__.'/../models/PracticalModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class PracticalController {
    private PracticalModel $model;
    
    public function __construct() {
        $this->model = new PracticalModel();
    }
    
    public function index($param = null) {
        Auth::guard();
        
        $userRole = Auth::role();
        $userId = Auth::id();
        
        if ($userRole === 'lecturer') {
            $practicals = $this->model->getAll($userId);
        } else {
            $practicals = $this->model->getAll();
        }
        
        $stats = $this->model->getPracticalStats();
        
        renderView('practicals/index', [
            'practicals' => $practicals,
            'stats' => $stats,
            'userRole' => $userRole
        ]);
    }
    
    public function create($param = null) {
        Auth::guard();
        
        // Only lecturers and admins can create practicals
        if (!in_array(Auth::role(), ['lecturer', 'admin'])) {
            http_response_code(403);
            echo '403 Forbidden - Only lecturers and admins can create practicals';
            exit;
        }
        
        $error = '';
        $success = '';
        $labs = $this->model->getLabs();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'id' => bin2hex(random_bytes(16)),
                'title' => sanitize($_POST['title'] ?? ''),
                'description' => sanitize($_POST['description'] ?? ''),
                'lab_id' => sanitize($_POST['lab_id'] ?? ''),
                'lecturer_id' => Auth::id(),
                'course_code' => sanitize($_POST['course_code'] ?? ''),
                'scheduled_date' => $_POST['scheduled_date'] ?? '',
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'max_students' => intval($_POST['max_students'] ?? 30),
                'required_equipment' => sanitize($_POST['required_equipment'] ?? ''),
                'required_chemicals' => sanitize($_POST['required_chemicals'] ?? ''),
                'safety_notes' => sanitize($_POST['safety_notes'] ?? ''),
                'status' => 'draft'
            ];
            
            // Validate required fields
            if (empty($data['title']) || empty($data['lab_id']) || 
                empty($data['scheduled_date']) || empty($data['start_time']) || 
                empty($data['end_time'])) {
                $error = 'Title, lab, date, and times are required.';
            } elseif ($data['start_time'] >= $data['end_time']) {
                $error = 'End time must be after start time.';
            } else {
                // Check lab availability
                $isAvailable = $this->model->checkLabAvailability(
                    $data['lab_id'], 
                    $data['scheduled_date'], 
                    $data['start_time'], 
                    $data['end_time']
                );
                
                if (!$isAvailable) {
                    $error = 'Lab is not available at the requested time.';
                } elseif ($this->model->create($data)) {
                    logActivity(Auth::id(), 'practical_created', 'practicals');
                    $success = 'Practical created successfully!';
                    
                    // Clear form for new entry
                    $data = array_fill_keys(array_keys($data), '');
                    $data['max_students'] = 30;
                    $data['status'] = 'draft';
                } else {
                    $error = 'Failed to create practical.';
                }
            }
        }
        
        renderView('practicals/create', [
            'error' => $error,
            'success' => $success,
            'labs' => $labs,
            'data' => $data ?? []
        ]);
    }
    
    public function view($practicalId = null) {
        Auth::guard();
        
        if (!$practicalId) {
            redirect('practicals');
        }
        
        $practical = $this->model->getById($practicalId);
        
        if (!$practical) {
            http_response_code(404);
            echo '404 — Practical not found';
            exit;
        }
        
        // Get enrolled students for this practical
        $students = $this->model->getEnrolledStudents($practicalId);
        
        // Get lab sessions for this practical
        $sessions = $this->model->getLabSessions($practicalId);
        
        // Check if user can edit this practical
        $userRole = Auth::role();
        $canEdit = ($userRole === 'lecturer' && $practical['lecturer_id'] === Auth::id()) || $userRole === 'admin';
        
        renderView('practicals/view', [
            'practical' => $practical,
            'students' => $students,
            'sessions' => $sessions,
            'userRole' => $userRole,
            'canEdit' => $canEdit
        ]);
    }
    
    public function checkAvailability() {
        Auth::guard();
        
        // Only lecturers and admins can check availability
        if (!in_array(Auth::role(), ['lecturer', 'admin'])) {
            http_response_code(403);
            echo '403 Forbidden - Access denied';
            exit;
        }
        
        $labId = sanitize($_GET['lab_id'] ?? '');
        $date = sanitize($_GET['date'] ?? '');
        $startTime = sanitize($_GET['start_time'] ?? '');
        $endTime = sanitize($_GET['end_time'] ?? '');
        
        if (empty($labId) || empty($date) || empty($startTime) || empty($endTime)) {
            jsonResponse(['available' => false, 'message' => 'Missing parameters']);
        }
        
        $isAvailable = $this->model->checkLabAvailability($labId, $date, $startTime, $endTime);
        
        jsonResponse([
            'available' => $isAvailable,
            'message' => $isAvailable ? 'Lab is available' : 'Lab is not available at this time'
        ]);
    }
}
