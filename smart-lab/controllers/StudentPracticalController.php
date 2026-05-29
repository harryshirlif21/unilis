<?php
// Environment detection
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/../config/app_production.php';
    require_once __DIR__.'/../config/database_production.php';
} else {
    require_once __DIR__.'/../config/app.php';
    require_once __DIR__.'/../config/database.php';
}

require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class StudentPracticalController {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Student dashboard - show available schedules and practical status
     */
    public function dashboard($param = null) {
        Auth::guard('student');
        
        $user_id = Auth::id();
        
        // Get available schedules for today
        $stmt = $this->db->prepare("
            SELECT ls.*, e.title as experiment_title, e.unit_code, e.unit_name,
                   sa.id as attendance_id, sa.attendance_time,
                   sub.id as submission_id, sub.status as submission_status
            FROM lab_schedules ls
            JOIN experiments e ON ls.experiment_id = e.id
            LEFT JOIN lab_attendance sa ON ls.id = sa.schedule_id AND sa.user_id = ?
            LEFT JOIN student_submissions sub ON ls.id = sub.schedule_id AND sub.user_id = ?
            WHERE ls.scheduled_date = CURDATE() 
            AND ls.status IN ('scheduled', 'active')
            ORDER BY ls.start_time ASC
        ");
        $stmt->execute([$user_id, $user_id]);
        $today_schedules = $stmt->fetchAll();
        
        // Get recent submissions
        $stmt = $this->db->prepare("
            SELECT sub.*, ls.title as schedule_title, e.title as experiment_title,
                   ls.scheduled_date, ls.start_time
            FROM student_submissions sub
            JOIN lab_schedules ls ON sub.schedule_id = ls.id
            JOIN experiments e ON ls.experiment_id = e.id
            WHERE sub.user_id = ?
            ORDER BY sub.updated_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_submissions = $stmt->fetchAll();
        
        renderView('student/dashboard', [
            'today_schedules' => $today_schedules,
            'recent_submissions' => $recent_submissions
        ]);
    }
    
    /**
     * Start practical execution
     */
    public function start($param = null) {
        Auth::guard('student');
        
        $schedule_id = intval($param);
        if (!$schedule_id) {
            $_SESSION['error'] = 'Schedule ID required';
            header('Location: '.APP_URL.'/student/dashboard');
            exit;
        }
        
        $user_id = Auth::id();
        
        // Verify student has valid attendance for this schedule
        $stmt = $this->db->prepare("
            SELECT ls.*, e.title as experiment_title, e.unit_code, e.unit_name,
                   sa.attendance_time, sa.login_method
            FROM lab_schedules ls
            JOIN experiments e ON ls.experiment_id = e.id
            JOIN lab_attendance sa ON ls.id = sa.schedule_id
            WHERE ls.id = ? AND sa.user_id = ? AND ls.status = 'active'
        ");
        $stmt->execute([$schedule_id, $user_id]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            $_SESSION['error'] = 'You do not have valid attendance for this schedule';
            header('Location: '.APP_URL.'/student/dashboard');
            exit;
        }
        
        // Check if student already has a submission
        $stmt = $this->db->prepare("
            SELECT * FROM student_submissions 
            WHERE user_id = ? AND schedule_id = ?
        ");
        $stmt->execute([$user_id, $schedule_id]);
        $submission = $stmt->fetch();
        
        if ($submission) {
            // Continue existing submission
            header('Location: '.APP_URL.'/student/practical/'.$schedule_id);
            exit;
        }
        
        // Create new submission
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO student_submissions (user_id, experiment_id, schedule_id, status)
                VALUES (?, ?, ?, 'draft')
            ");
            $stmt->execute([$user_id, $schedule['experiment_id'], $schedule_id]);
            
            $submission_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            logActivity($user_id, 'practical_started', 'practical', [
                'schedule_id' => $schedule_id,
                'submission_id' => $submission_id
            ]);
            
            header('Location: '.APP_URL.'/student/practical/'.$schedule_id);
            exit;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Practical start error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to start practical';
            header('Location: '.APP_URL.'/student/dashboard');
            exit;
        }
    }
    
    /**
     * Show practical execution interface
     */
    public function execute($param = null) {
        Auth::guard('student');
        
        $schedule_id = intval($param);
        if (!$schedule_id) {
            header('Location: '.APP_URL.'/student/dashboard');
            exit;
        }
        
        $user_id = Auth::id();
        
        // Get schedule and experiment details
        $stmt = $this->db->prepare("
            SELECT ls.*, e.title as experiment_title, e.unit_code, e.unit_name,
                   sub.id as submission_id, sub.status as submission_status
            FROM lab_schedules ls
            JOIN experiments e ON ls.experiment_id = e.id
            LEFT JOIN student_submissions sub ON ls.id = sub.schedule_id AND sub.user_id = ?
            WHERE ls.id = ? AND ls.status = 'active'
        ");
        $stmt->execute([$user_id, $schedule_id]);
        $schedule = $stmt->fetch();
        
        if (!$schedule || !$schedule['submission_id']) {
            $_SESSION['error'] = 'Practical not found or not started';
            header('Location: '.APP_URL.'/student/dashboard');
            exit;
        }
        
        // Get experiment sections
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_sections 
            WHERE experiment_id = ? 
            ORDER BY display_order ASC
        ");
        $stmt->execute([$schedule['experiment_id']]);
        $sections = $stmt->fetchAll();
        
        // Get apparatus
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_apparatus 
            WHERE experiment_id = ? 
            ORDER BY display_order ASC
        ");
        $stmt->execute([$schedule['experiment_id']]);
        $apparatus = $stmt->fetchAll();
        
        // Get procedure steps
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_procedure_steps 
            WHERE experiment_id = ? 
            ORDER BY step_number ASC
        ");
        $stmt->execute([$schedule['experiment_id']]);
        $procedure_steps = $stmt->fetchAll();
        
        // Get results structure
        $stmt = $this->db->prepare("
            SELECT * FROM experiment_results_structure 
            WHERE experiment_id = ? 
            ORDER BY column_order ASC
        ");
        $stmt->execute([$schedule['experiment_id']]);
        $results_structure = $stmt->fetchAll();
        
        // Get existing submission data
        $submission_data = $this->getSubmissionData($schedule['submission_id']);
        $results_data = $this->getResultsData($schedule['submission_id']);
        
        renderView('student/practical', [
            'schedule' => $schedule,
            'sections' => $sections,
            'apparatus' => $apparatus,
            'procedure_steps' => $procedure_steps,
            'results_structure' => $results_structure,
            'submission_data' => $submission_data,
            'results_data' => $results_data
        ]);
    }
    
    /**
     * Save practical data (auto-save)
     */
    public function save($param = null) {
        Auth::guard('student');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            return;
        }
        
        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        $section_type = sanitize($_POST['section_type'] ?? '');
        $content = $_POST['content'] ?? '';
        
        if (empty($schedule_id) || empty($section_type)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Schedule ID and section type required']);
            return;
        }
        
        $user_id = Auth::id();
        
        try {
            // Get submission ID
            $stmt = $this->db->prepare("
                SELECT id FROM student_submissions 
                WHERE user_id = ? AND schedule_id = ?
            ");
            $stmt->execute([$user_id, $schedule_id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Submission not found']);
                return;
            }
            
            // Save or update section data
            $stmt = $this->db->prepare("
                INSERT INTO submission_data (submission_id, section_type, content)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE content = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$submission['id'], $section_type, $content, $content]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Saved successfully']);
            
        } catch (Exception $e) {
            error_log("Practical save error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to save']);
        }
    }
    
    /**
     * Save results table data
     */
    public function saveResults($param = null) {
        Auth::guard('student');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            return;
        }
        
        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        $results_data = $_POST['results'] ?? [];
        
        if (empty($schedule_id) || !is_array($results_data)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
            return;
        }
        
        $user_id = Auth::id();
        
        try {
            // Get submission ID
            $stmt = $this->db->prepare("
                SELECT id FROM student_submissions 
                WHERE user_id = ? AND schedule_id = ?
            ");
            $stmt->execute([$user_id, $schedule_id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Submission not found']);
                return;
            }
            
            $this->db->beginTransaction();
            
            // Delete existing results
            $stmt = $this->db->prepare("DELETE FROM submission_results WHERE submission_id = ?");
            $stmt->execute([$submission['id']]);
            
            // Insert new results
            foreach ($results_data as $row_number => $row) {
                foreach ($row as $column_name => $value) {
                    $stmt = $this->db->prepare("
                        INSERT INTO submission_results (submission_id, row_number, column_name, value)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$submission['id'], $row_number, $column_name, $value]);
                }
            }
            
            $this->db->commit();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Results saved successfully']);
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Results save error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to save results']);
        }
    }
    
    /**
     * Submit practical
     */
    public function submit($param = null) {
        Auth::guard('student');
        
        $schedule_id = intval($param);
        if (!$schedule_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Schedule ID required']);
            return;
        }
        
        $user_id = Auth::id();
        
        try {
            // Get submission and validate it has required data
            $stmt = $this->db->prepare("
                SELECT sub.id, sub.experiment_id, ls.status as schedule_status
                FROM student_submissions sub
                JOIN lab_schedules ls ON sub.schedule_id = ls.id
                WHERE sub.user_id = ? AND sub.schedule_id = ? AND sub.status = 'draft'
            ");
            $stmt->execute([$user_id, $schedule_id]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Submission not found or already submitted']);
                return;
            }
            
            if ($submission['schedule_status'] !== 'active') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Schedule is not active']);
                return;
            }
            
            // Update submission status
            $stmt = $this->db->prepare("
                UPDATE student_submissions 
                SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$submission['id']]);
            
            logActivity($user_id, 'practical_submitted', 'practical', [
                'schedule_id' => $schedule_id,
                'submission_id' => $submission['id']
            ]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Practical submitted successfully']);
            
        } catch (Exception $e) {
            error_log("Practical submission error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to submit practical']);
        }
    }
    
    /**
     * Get submission data
     */
    private function getSubmissionData($submission_id) {
        $stmt = $this->db->prepare("
            SELECT section_type, content 
            FROM submission_data 
            WHERE submission_id = ?
        ");
        $stmt->execute([$submission_id]);
        $data = [];
        
        while ($row = $stmt->fetch()) {
            $data[$row['section_type']] = $row['content'];
        }
        
        return $data;
    }
    
    /**
     * Get results data
     */
    private function getResultsData($submission_id) {
        $stmt = $this->db->prepare("
            SELECT row_number, column_name, value 
            FROM submission_results 
            WHERE submission_id = ?
            ORDER BY row_number, column_name
        ");
        $stmt->execute([$submission_id]);
        $data = [];
        
        while ($row = $stmt->fetch()) {
            $data[$row['row_number']][$row['column_name']] = $row['value'];
        }
        
        return $data;
    }

    /**
     * View a published practical (new practicals system)
     */
    public function view_practical($param = null) {
        Auth::guard('student');

        $practical_id = $param;
        $student_id = Auth::id();

        if (!$practical_id) {
            header('Location: ' . APP_URL . '/student/dashboard');
            exit;
        }

        // Get practical details
        $stmt = $this->db->prepare("
            SELECT p.*, l.name as lab_name, l.lab_code,
                   u.full_name as lecturer_name
            FROM practicals p
            LEFT JOIN labs l ON p.lab_id = l.id
            LEFT JOIN users u ON p.lecturer_id = u.id
            WHERE p.id = ? AND p.status = 'published'
        ");
        $stmt->execute([$practical_id]);
        $practical = $stmt->fetch();

        if (!$practical) {
            $_SESSION['error'] = 'Practical not found or not available';
            header('Location: ' . APP_URL . '/student/dashboard');
            exit;
        }

        // Parse JSON fields
        $practical['procedure'] = json_decode($practical['procedure_json'] ?? '[]', true) ?: [];
        $practical['observations_table'] = json_decode($practical['observations_table_structure'] ?? '[]', true) ?: [];
        $practical['apparatus'] = array_filter(explode("\n", $practical['required_equipment'] ?? ''));
        $practical['chemicals'] = array_filter(explode("\n", $practical['required_chemicals'] ?? ''));

        // Check student's report status
        $report_status = 'not_started';
        try {
            $stmt = $this->db->prepare("
            SELECT status FROM lab_reports
            WHERE practical_id = ? AND student_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
            $stmt->execute([$practical_id, $student_id]);
            $report = $stmt->fetch();

            if ($report) {
                $report_status = $report['status']; // 'in_progress' or 'submitted'
            }
        } catch (PDOException $e) {
            error_log("StudentPracticalController::view_practical - lab_reports query failed: " . $e->getMessage());
        }

        renderView('student/view_practical', [
            'practical' => $practical,
            'report_status' => $report_status
        ]);
    }

    /**
     * Start practical session after attendance verification
     */
    public function index($param = null) { $this->startPractical($param); }
    public function startPractical($practicalId = null) {
        Auth::guard('student');

        if (!$practicalId) {
            $practicalId = sanitize($_GET['practical_id'] ?? '');
        }

        if (empty($practicalId)) {
            http_response_code(400);
            echo 'Practical ID is required';
            exit;
        }

        $studentId = Auth::id();

        try {
            $db = getDB();

            // Check attendance exists
            $stmt = $db->prepare("SELECT id FROM attendance WHERE student_id = ? AND practical_id = ?");
            $stmt->execute([$studentId, $practicalId]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo 'Attendance not found. Please verify your attendance first.';
                exit;
            }

            // Get practical details
            $stmt = $db->prepare("
                SELECT p.*, l.name as lab_name, l.lab_code,
                       u.full_name as lecturer_name
                FROM practicals p
                LEFT JOIN labs l ON p.lab_id = l.id
                LEFT JOIN users u ON p.lecturer_id = u.id
                WHERE p.id = ? AND p.status = 'published'
            ");
            $stmt->execute([$practicalId]);
            $practical = $stmt->fetch();

            if (!$practical) {
                http_response_code(404);
                echo 'Practical not found or not available';
                exit;
            }

            // Check if report already exists
            $stmt = $db->prepare("SELECT id, status FROM lab_reports WHERE practical_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$practicalId, $studentId]);
            $existingReport = $stmt->fetch();

            if ($existingReport) {
                if ($existingReport['status'] === 'submitted') {
                    http_response_code(400);
                    echo 'You have already submitted a report for this practical';
                    exit;
                }

                $reportId = $existingReport['id'];
            } else {
                // Create new report
                $reportId = bin2hex(random_bytes(16));
                $stmt = $db->prepare("
                    INSERT INTO lab_reports
                    (id, practical_id, student_id, status, created_at)
                    VALUES (?, ?, ?, 'in_progress', NOW())
                ");
                $stmt->execute([$reportId, $practicalId, $studentId]);
            }

            // Redirect to the practical page
            header('Location: ' . APP_URL . '/student/view_practical/' . $practicalId . '?report_id=' . $reportId);
            exit;

        } catch (Exception $e) {
            error_log("StudentPracticalController::startPractical - Error: " . $e->getMessage());
            http_response_code(500);
            echo 'Internal server error';
        }
    }
}


