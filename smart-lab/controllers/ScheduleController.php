<?php
require_once __DIR__.'/../config/app.php';

require_once __DIR__.'/../models/ScheduleModel.php';
require_once __DIR__.'/../models/PracticalModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';
require_once __DIR__.'/../utils/Mailer.php';

class ScheduleController {
    private ScheduleModel $model;
    
    public function __construct() {
        $this->model = new ScheduleModel();
    }
    
    public function index($param = null) {
        Auth::guard();
        
        $currentDate = $_GET['date'] ?? date('Y-m-d');
        $labFilter = $_GET['lab'] ?? '';
        
        $todaySchedule = $this->model->getTodaySchedule();
        $weekSchedule = $this->model->getWeekSchedule();
        $monthSchedule = $this->model->getMonthSchedule();
        $allSchedule = $this->model->getAllSchedule();
        $stats = $this->model->getScheduleStats();
        $labs = $this->model->getLabs();
        
        renderView('schedule/index', [
            'todaySchedule' => $todaySchedule,
            'weekSchedule' => $weekSchedule,
            'monthSchedule' => $monthSchedule,
            'allSchedule' => $allSchedule,
            'stats' => $stats,
            'labs' => $labs,
            'currentDate' => $currentDate
        ]);
    }
    
    /**
     * Create a new lab session and email auth codes to enrolled students
     */
    public function createSession($param = null) {
        Auth::guard(['lecturer', 'technician', 'admin']);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $practicalId = sanitize($_POST['practical_id'] ?? '');
            $labId = sanitize($_POST['lab_id'] ?? '');
            $sessionDate = sanitize($_POST['session_date'] ?? '');
            $confirmationCode = sanitize($_POST['confirmation_code'] ?? '');
            
            if (empty($practicalId) || empty($labId) || empty($sessionDate)) {
                $_SESSION['error'] = 'All fields are required.';
                redirect('schedule');
                return;
            }
            
            $db = getDB();
            
            // Generate confirmation code if not provided
            if (empty($confirmationCode)) {
                $confirmationCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            }
            
            try {
                $db->beginTransaction();
                
                // Create lab session
                $sessionId = bin2hex(random_bytes(16));
                $stmt = $db->prepare("INSERT INTO lab_sessions (id, practical_id, lab_id, started_at, status, confirmation_code) VALUES (?, ?, ?, ?, 'open', ?)");
                $stmt->execute([$sessionId, $practicalId, $labId, $sessionDate . ' 09:00:00', $confirmationCode]);
                
                // Get practical details and enrolled students
                $practicalModel = new PracticalModel();
                $practical = $practicalModel->getById($practicalId);
                
                if (!$practical) {
                    throw new Exception('Practical not found');
                }
                
                // Get enrolled students for this practical
                $stmt = $db->prepare("
                    SELECT u.id, u.full_name, u.email, u.reg_number
                    FROM users u
                    JOIN student_practicals sp ON u.id = sp.student_id
                    WHERE sp.practical_id = ? AND u.is_active = 1
                    ORDER BY u.full_name
                ");
                $stmt->execute([$practicalId]);
                $students = $stmt->fetchAll();
                
                // Get lab details
                $stmt = $db->prepare("SELECT name, room_number FROM labs WHERE id = ? LIMIT 1");
                $stmt->execute([$labId]);
                $lab = $stmt->fetch();
                
                $labName = $lab ? ($lab['name'] . ' (' . $lab['room_number'] . ')') : 'Unknown Lab';
                
                // Email auth codes to all enrolled students
                $emailsSent = 0;
                $emailsFailed = 0;
                
                foreach ($students as $student) {
                    $emailSent = Mailer::sendAuthCode(
                        $student['email'],
                        $student['full_name'],
                        $confirmationCode,
                        $labName,
                        $practical['title'],
                        $sessionDate
                    );
                    
                    if ($emailSent) {
                        $emailsSent++;
                    } else {
                        $emailsFailed++;
                        error_log("Failed to send auth code email to student: " . $student['email']);
                    }
                }
                
                $db->commit();
                
                // Log activity
                logActivity(Auth::id(), 'lab_session_created', 'schedule', [
                    'session_id' => $sessionId,
                    'practical_id' => $practicalId,
                    'confirmation_code' => $confirmationCode,
                    'students_emailed' => $emailsSent,
                    'students_failed' => $emailsFailed
                ]);
                
                $_SESSION['success'] = "Lab session created successfully! Auth code '{$confirmationCode}' sent to {$emailsSent} students." . 
                    ($emailsFailed > 0 ? " {$emailsFailed} emails failed." : "");
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Error creating lab session: " . $e->getMessage());
                $_SESSION['error'] = 'Failed to create lab session. Please try again.';
            }
            
            redirect('schedule');
        }
    }
    
    /**
     * Get enrolled students for a practical (AJAX endpoint)
     */
    public function getEnrolledStudents($param = null) {
        Auth::guard(['lecturer', 'technician', 'admin']);
        
        $practicalId = sanitize($_GET['practical_id'] ?? '');
        
        if (empty($practicalId)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Practical ID required']);
            return;
        }
        
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.full_name, u.email, u.reg_number
            FROM users u
            JOIN student_practicals sp ON u.id = sp.student_id
            WHERE sp.practical_id = ? AND u.is_active = 1
            ORDER BY u.full_name
        ");
        $stmt->execute([$practicalId]);
        $students = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'students' => $students]);
    }
    
    /**
     * Create new lab schedule with experiment
     */
    public function createSchedule($param = null) {
        Auth::guard('technician');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $experiment_id = intval($_POST['experiment_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $scheduled_date = sanitize($_POST['scheduled_date'] ?? '');
            $start_time = sanitize($_POST['start_time'] ?? '');
            $end_time = sanitize($_POST['end_time'] ?? '');
            $lab_location_lat = floatval($_POST['lab_location_lat'] ?? 0);
            $lab_location_lng = floatval($_POST['lab_location_lng'] ?? 0);
            $lab_location_radius = intval($_POST['lab_location_radius'] ?? 50);
            $max_students = intval($_POST['max_students'] ?? 30);
            
            if (empty($experiment_id) || empty($title) || empty($scheduled_date) || empty($start_time) || empty($end_time)) {
                $_SESSION['error'] = 'Required fields are missing';
                $this->showCreateScheduleForm();
                return;
            }
            
            try {
                $db = getDB();
                $db->beginTransaction();
                
                // Insert lab schedule
                $stmt = $db->prepare("
                    INSERT INTO lab_schedules (
                        experiment_id, technician_id, title, description, scheduled_date, 
                        start_time, end_time, lab_location_lat, lab_location_lng, 
                        lab_location_radius, max_students, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
                ");
                $stmt->execute([
                    $experiment_id, Auth::id(), $title, $description, $scheduled_date,
                    $start_time, $end_time, $lab_location_lat, $lab_location_lng,
                    $lab_location_radius, $max_students
                ]);
                
                $schedule_id = $db->lastInsertId();
                
                $db->commit();
                
                $_SESSION['success'] = 'Lab schedule created successfully';
                header('Location: '.APP_URL.'/schedules');
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Schedule creation error: " . $e->getMessage());
                $_SESSION['error'] = 'Failed to create schedule';
                $this->showCreateScheduleForm();
            }
        } else {
            $this->showCreateScheduleForm();
        }
    }
    
    /**
     * Show create schedule form
     */
    private function showCreateScheduleForm() {
        $db = getDB();
        
        // Get technician's experiments
        $stmt = $db->prepare("
            SELECT id, title, unit_code, unit_name 
            FROM experiments 
            WHERE technician_id = ? AND status = 'published'
            ORDER BY title ASC
        ");
        $stmt->execute([Auth::id()]);
        $experiments = $stmt->fetchAll();
        
        renderView('schedules/create', [
            'experiments' => $experiments
        ]);
    }
    
    /**
     * List schedules for technician
     */
    public function listSchedules($param = null) {
        Auth::guard('technician');
        
        $db = getDB();
        
        $page = intval($_GET['page'] ?? 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare("
            SELECT ls.*, e.title as experiment_title, e.unit_code, e.unit_name,
                   COUNT(sa.id) as attendance_count
            FROM lab_schedules ls
            JOIN experiments e ON ls.experiment_id = e.id
            LEFT JOIN lab_attendance sa ON ls.id = sa.schedule_id
            WHERE ls.technician_id = ?
            GROUP BY ls.id
            ORDER BY ls.scheduled_date DESC, ls.start_time DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([Auth::id(), $limit, $offset]);
        $schedules = $stmt->fetchAll();
        
        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_schedules WHERE technician_id = ?");
        $stmt->execute([Auth::id()]);
        $total = $stmt->fetch()['total'];
        
        $total_pages = ceil($total / $limit);
        
        renderView('schedules/index', [
            'schedules' => $schedules,
            'page' => $page,
            'total_pages' => $total_pages,
            'total' => $total
        ]);
    }
    
    /**
     * Generate authentication code for a schedule
     */
    public function generateAuthCode($param = null) {
        Auth::guard('technician');
        
        $schedule_id = intval($param);
        if (!$schedule_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Schedule ID required']);
            return;
        }
        
        $db = getDB();
        
        // Verify schedule belongs to technician
        $stmt = $db->prepare("
            SELECT id, title FROM lab_schedules 
            WHERE id = ? AND technician_id = ? AND status IN ('scheduled', 'active')
        ");
        $stmt->execute([$schedule_id, Auth::id()]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Schedule not found or not active']);
            return;
        }
        
        try {
            $db->beginTransaction();
            
            // Invalidate any existing codes for this schedule
            $stmt = $db->prepare("
                UPDATE otp_codes 
                SET status = 'expired' 
                WHERE schedule_id = ? AND status = 'active'
            ");
            $stmt->execute([$schedule_id]);
            
            // Generate new 6-digit code
            $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minutes
            
            // Insert new code
            $stmt = $db->prepare("
                INSERT INTO otp_codes (code, schedule_id, technician_id, expires_at, status)
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$code, $schedule_id, Auth::id(), $expires_at]);
            
            $db->commit();
            
            logActivity(Auth::id(), 'auth_code_generated', 'schedule', [
                'schedule_id' => $schedule_id,
                'code' => $code,
                'expires_at' => $expires_at
            ]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'code' => $code,
                'expires_at' => $expires_at,
                'schedule_title' => $schedule['title']
            ]);
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Auth code generation error: " . $e->getMessage());
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to generate auth code']);
        }
    }
    
    /**
     * Verify authentication code (used by student login)
     */
    public function verifyAuthCode($param = null) {
        // This will be called after QR/biometric authentication
        Auth::guard();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            return;
        }
        
        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        $code = sanitize($_POST['code'] ?? '');
        $user_id = Auth::id();
        
        if (empty($schedule_id) || empty($code)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Schedule ID and code required']);
            return;
        }
        
        $db = getDB();
        
        try {
            // Verify code
            $stmt = $db->prepare("
                SELECT id FROM otp_codes 
                WHERE schedule_id = ? AND code = ? AND status = 'active' 
                AND expires_at > NOW()
            ");
            $stmt->execute([$schedule_id, $code]);
            $valid_code = $stmt->fetch();
            
            if (!$valid_code) {
                logActivity($user_id, 'auth_code_failed', 'schedule', [
                    'schedule_id' => $schedule_id,
                    'code' => $code
                ]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid or expired code']);
                return;
            }
            
            // Mark code as used
            $stmt = $db->prepare("
                UPDATE otp_codes 
                SET status = 'used' 
                WHERE id = ?
            ");
            $stmt->execute([$valid_code['id']]);
            
            // Mark attendance
            $stmt = $db->prepare("
                INSERT INTO lab_attendance (user_id, schedule_id, login_method, attendance_time)
                VALUES (?, ?, 'qr_code', NOW())
                ON DUPLICATE KEY UPDATE 
                attendance_time = NOW(),
                login_method = 'qr_code'
            ");
            $stmt->execute([$user_id, $schedule_id]);
            
            logActivity($user_id, 'auth_code_success', 'schedule', [
                'schedule_id' => $schedule_id
            ]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Authentication successful']);
            
        } catch (Exception $e) {
            error_log("Auth code verification error: " . $e->getMessage());
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Verification failed']);
        }
    }
    
    /**
     * Start a schedule (change status to active)
     */
    public function startSchedule($param = null) {
        Auth::guard('technician');
        
        $schedule_id = intval($param);
        if (!$schedule_id) {
            header('Location: '.APP_URL.'/schedules');
            exit;
        }
        
        $db = getDB();
        
        try {
            $stmt = $db->prepare("
                UPDATE lab_schedules 
                SET status = 'active'
                WHERE id = ? AND technician_id = ? AND status = 'scheduled'
            ");
            $stmt->execute([$schedule_id, Auth::id()]);
            
            $_SESSION['success'] = 'Schedule started successfully';
            
        } catch (Exception $e) {
            error_log("Schedule start error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to start schedule';
        }
        
        header('Location: '.APP_URL.'/schedules');
        exit;
    }
    
    /**
     * Complete a schedule (change status to completed)
     */
    public function completeSchedule($param = null) {
        Auth::guard('technician');
        
        $schedule_id = intval($param);
        if (!$schedule_id) {
            header('Location: '.APP_URL.'/schedules');
            exit;
        }
        
        $db = getDB();
        
        try {
            $stmt = $db->prepare("
                UPDATE lab_schedules 
                SET status = 'completed'
                WHERE id = ? AND technician_id = ? AND status = 'active'
            ");
            $stmt->execute([$schedule_id, Auth::id()]);
            
            $_SESSION['success'] = 'Schedule completed successfully';
            
        } catch (Exception $e) {
            error_log("Schedule completion error: " . $e->getMessage());
            $_SESSION['error'] = 'Failed to complete schedule';
        }
        
        header('Location: '.APP_URL.'/schedules');
        exit;
    }
}
