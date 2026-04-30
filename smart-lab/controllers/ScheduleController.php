<?php
// Environment detection and proper config loading
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/../config/app_production.php';
    require_once __DIR__.'/../config/database_production.php';
} else {
    require_once __DIR__.'/../config/app.php';
    require_once __DIR__.'/../config/database.php';
}

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
}
