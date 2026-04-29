<?php
require_once __DIR__.'/../models/ReportModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class ReportController {
    private ReportModel $model;
    
    public function __construct() {
        $this->model = new ReportModel();
    }
    
    public function index($param = null) {
        Auth::guard();
        
        $userRole = Auth::role();
        $userId = Auth::id();
        
        if ($userRole === 'student') {
            $reports = $this->model->getByStudent($userId);
            $stats = $this->model->getStudentReportStats($userId);
        } elseif ($userRole === 'lecturer') {
            $reports = $this->model->getByLecturer($userId);
            $stats = $this->model->getGradingStats($userId);
        } else {
            $reports = $this->model->search(['limit' => 50]);
            $stats = null;
        }
        
        renderView('reports/index', [
            'reports' => $reports,
            'stats' => $stats,
            'userRole' => $userRole
        ]);
    }
    
    public function create($practicalId = null) {
        Auth::guard('student');
        
        if (!$practicalId) {
            redirect('dashboard');
        }
        
        $error = '';
        $success = '';
        
        // Get practical details
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT p.*, l.name as lab_name 
             FROM practicals p 
             LEFT JOIN labs l ON p.lab_id = l.id 
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$practicalId]);
        $practical = $stmt->fetch();
        
        if (!$practical) {
            redirect('dashboard');
        }
        
        // Check if student has a notebook for this practical
        $notebookStmt = $db->prepare(
            "SELECT id FROM notebooks 
             WHERE student_id = ? AND practical_id = ? LIMIT 1"
        );
        $notebookStmt->execute([Auth::id(), $practicalId]);
        $notebook = $notebookStmt->fetch();
        
        if (!$notebook) {
            $error = 'You must complete a lab notebook before submitting a report.';
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $notebook) {
            $title = sanitize($_POST['title'] ?? '');
            $submissionNotes = sanitize($_POST['submission_notes'] ?? '');
            
            if (empty($title)) {
                $error = 'Report title is required.';
            } else {
                // Handle file upload
                $filePath = '';
                if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = UPLOAD_PATH . 'reports/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = 'report_' . Auth::id() . '_' . time() . '_' . basename($_FILES['report_file']['name']);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['report_file']['tmp_name'], $filePath)) {
                        $filePath = 'uploads/reports/' . $fileName;
                    } else {
                        $error = 'Failed to upload file.';
                    }
                }
                
                if (empty($error)) {
                    $data = [
                        'id' => bin2hex(random_bytes(16)),
                        'notebook_id' => $notebook['id'],
                        'student_id' => Auth::id(),
                        'practical_id' => $practicalId,
                        'title' => $title,
                        'file_path' => $filePath,
                        'submission_notes' => $submissionNotes
                    ];
                    
                    if ($this->model->create($data)) {
                        logActivity(Auth::id(), 'report_created', 'reports');
                        $success = 'Report created successfully!';
                    } else {
                        $error = 'Failed to create report.';
                    }
                }
            }
        }
        
        renderView('reports/create', [
            'practical' => $practical,
            'notebook' => $notebook,
            'error' => $error,
            'success' => $success
        ]);
    }
    
    public function pending($param = null) {
        Auth::guard('lecturer');
        
        $pendingReports = $this->model->getPendingGrading(Auth::id());
        
        renderView('reports/pending', [
            'pendingReports' => $pendingReports
        ]);
    }
    
    public function grade($reportId = null) {
        Auth::guard('lecturer');
        
        if (!$reportId) {
            redirect('reports');
        }
        
        $report = $this->model->getById($reportId);
        if (!$report || $report['lecturer_id'] !== Auth::id() || !in_array($report['status'], ['submitted', 'returned'])) {
            redirect('reports');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $grade = floatval($_POST['grade'] ?? 0);
            $feedback = sanitize($_POST['feedback'] ?? '');
            $action = sanitize($_POST['grading_action'] ?? '');
            
            if ($action === 'grade' && ($grade < 0 || $grade > 100)) {
                $error = 'Grade must be between 0 and 100.';
            } elseif ($action === 'grade' && empty($feedback)) {
                $error = 'Feedback is required when grading.';
            } elseif ($action === 'return' && empty($feedback)) {
                $error = 'Feedback is required when returning for revision.';
            } else {
                if ($action === 'grade') {
                    $gradingData = [
                        'grade' => $grade,
                        'feedback' => $feedback,
                        'graded_by' => Auth::id()
                    ];
                    
                    if ($this->model->grade($reportId, $gradingData)) {
                        logActivity(Auth::id(), 'report_graded', 'reports');
                        $success = 'Report graded successfully!';
                        $report['status'] = 'graded';
                        $report['grade'] = $grade;
                        $report['feedback'] = $feedback;
                    } else {
                        $error = 'Failed to grade report.';
                    }
                } elseif ($action === 'return') {
                    if ($this->model->returnForRevision($reportId, Auth::id(), $feedback)) {
                        logActivity(Auth::id(), 'report_returned', 'reports');
                        $success = 'Report returned for revision!';
                        $report['status'] = 'returned';
                        $report['feedback'] = $feedback;
                    } else {
                        $error = 'Failed to return report.';
                    }
                }
            }
        }
        
        renderView('reports/grade', [
            'report' => $report,
            'error' => $error,
            'success' => $success
        ]);
    }
}
