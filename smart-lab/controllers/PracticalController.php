<?php
require_once __DIR__.'/../models/PracticalModel.php';
require_once __DIR__.'/../models/ReportModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class PracticalController {
    private PracticalModel $model;
    private ReportModel $reportModel;
    private PDO $db;
    
    public function __construct() {
        $this->model = new PracticalModel();
        $this->reportModel = new ReportModel();
        $this->db = getDB();
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

        $studentReportMap = [];

        if ($userRole === 'student') {
            foreach ($practicals as &$practical) {
                $practical['access_window'] = getPracticalAccessWindow($practical);
                $report = $this->reportModel->getStudentReportForPractical($userId, $practical['id']);
                $studentReportMap[$practical['id']] = $report;
            }
            unset($practical);
        } else {
            foreach ($practicals as &$practical) {
                $practical['access_window'] = getPracticalAccessWindow($practical);
            }
            unset($practical);
        }
        
        $stats = $this->model->getPracticalStats();
        
        renderView('practicals/index', [
            'practicals' => $practicals,
            'stats' => $stats,
            'userRole' => $userRole,
            'studentReportMap' => $studentReportMap
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
            // Generate UUID-format ID (36 chars with dashes, matching char(36) column)
            $rawId = random_bytes(16);
            $rawId[6] = chr((ord($rawId[6]) & 0x0f) | 0x40);
            $rawId[8] = chr((ord($rawId[8]) & 0x3f) | 0x80);
            $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($rawId), 4));
            
            $data = [
                'id' => $uuid,
                'title' => sanitize($_POST['title'] ?? ''),
                'objective' => sanitizeHTML($_POST['objective'] ?? ''),
                'theory' => sanitizeHTML($_POST['theory'] ?? ''),
                'description' => sanitizeHTML($_POST['description'] ?? ''),
                'lab_id' => sanitize($_POST['lab_id'] ?? ''),
                'lecturer_id' => Auth::id(),
                'course_code' => sanitize($_POST['course_code'] ?? ''),
                'scheduled_date' => $_POST['scheduled_date'] ?? '',
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'max_students' => intval($_POST['max_students'] ?? 30),
                'required_equipment' => sanitize($_POST['required_equipment'] ?? ''),
                'required_chemicals' => sanitize($_POST['required_chemicals'] ?? ''),
                'procedure_json' => $_POST['procedure_json'] ?? '',
                'observations_table_structure' => $_POST['observations_table_structure'] ?? '',
                'safety_notes' => sanitize($_POST['safety_notes'] ?? ''),
                'results_template' => sanitizeHTML($_POST['results_template'] ?? ''),
                'calculations_template' => sanitizeHTML($_POST['calculations_template'] ?? ''),
                'status' => 'draft'
            ];
            
            // Validate required fields
            if (empty($data['title']) || empty($data['lab_id']) || 
                empty($data['scheduled_date']) || empty($data['start_time']) || 
                empty($data['end_time'])) {
                $error = 'Title, lab, date, and times are required.';
            } else {
                // Validate date and times first
                $dateTimeErrors = $this->model->validateDateTime(
                    $data['scheduled_date'],
                    $data['start_time'],
                    $data['end_time']
                );
                
                if (!empty($dateTimeErrors)) {
                    $error = implode(' ', $dateTimeErrors);
                } else {
                    // Check lab availability
                    $isAvailable = $this->model->checkLabAvailability(
                        $data['lab_id'], 
                        $data['scheduled_date'], 
                        $data['start_time'], 
                        $data['end_time']
                    );
                    
                    if (!$isAvailable) {
                        // Get available slots for better error message
                        $availableSlots = $this->model->getAvailableSlots($data['lab_id'], $data['scheduled_date']);
                        $freeSlots = array_filter($availableSlots, fn($slot) => $slot['available']);
                        
                        // Check if it's a daily limit issue or time conflict
                        $totalCount = $this->model->getDailyPracticalCount($data['lab_id'], $data['scheduled_date']);
                        error_log("Daily practical count for error message: $totalCount");
                        
                        if ($totalCount >= 3) {
                            $error = 'Lab fully booked - Maximum of 3 practicals per day allowed. Please select a different date.';
                        } elseif (!empty($freeSlots)) {
                            $slotList = array_slice($freeSlots, 0, 3);
                            $slotTimes = implode(', ', array_map(fn($s) => substr($s['start'], 0, 5) . '-' . substr($s['end'], 0, 5), $slotList));
                            $error = "Time slot conflict. Available slots: $slotTimes";
                        } else {
                            $error = 'Lab is not available at the requested time. No slots available on this date. Please select a different date or time.';
                        }
                    } elseif ($this->model->create($data)) {
                        logActivity(Auth::id(), 'practical_created', 'practicals');
                        $success = 'Practical created successfully!';
                        
                        // Clear form for new entry
                        $data = array_fill_keys(array_keys($data), '');
                        $data['max_students'] = 30;
                        $data['status'] = 'draft';
                    } else {
                        // Get the actual error from the logs or model
                        $error = 'Database error: Failed to create practical. Please check the error logs for details.';
                        error_log("PracticalController::create - Model create returned false for data: " . json_encode($data, JSON_UNESCAPED_SLASHES));
                        
                        // Check if lab exists
                        if (!$this->model->labExists($data['lab_id'])) {
                            $error = 'Invalid lab selected. Please choose a valid lab.';
                            error_log("Lab ID {$data['lab_id']} does not exist");
                        }
                    }
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
    
    public function checkAvailability() {
        header('Content-Type: application/json');
        Auth::guard();
        
        // Only lecturers and admins can check availability
        if (!in_array(Auth::role(), ['lecturer', 'admin'])) {
            http_response_code(403);
            echo json_encode(['available' => false, 'message' => 'Access denied']);
            exit;
        }
        
        $labId = sanitize($_GET['lab_id'] ?? '');
        $date = sanitize($_GET['date'] ?? '');
        $startTime = sanitize($_GET['start_time'] ?? '');
        $endTime = sanitize($_GET['end_time'] ?? '');
        
        if (empty($labId) || empty($date)) {
            echo json_encode(['available' => false, 'message' => 'Lab ID and date are required']);
            exit;
        }
        
        $isAvailable = $this->model->checkLabAvailability($labId, $date, $startTime, $endTime);
        $availableSlots = $this->model->getAvailableSlots($labId, $date);
        
        echo json_encode([
            'available' => $isAvailable,
            'message' => $isAvailable ? 'Lab is available' : 'Lab is not available at this time',
            'slots' => $availableSlots
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
    
    public function edit($practicalId = null) {
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
        
        // Check if user can edit this practical
        $userRole = Auth::role();
        $canEdit = ($userRole === 'lecturer' && $practical['lecturer_id'] === Auth::id()) || $userRole === 'admin';
        
        if (!$canEdit) {
            http_response_code(403);
            echo '403 Forbidden - You do not have permission to edit this practical';
            exit;
        }
        
        $error = '';
        $success = '';
        $labs = $this->model->getLabs();
        
        // Handle status change (publish)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
            $newStatus = sanitize($_POST['status'] ?? '');
            if (in_array($newStatus, ['draft', 'published'])) {
                if ($this->model->updateStatus($practicalId, $newStatus)) {
                    logActivity(Auth::id(), 'practical_status_changed', 'practicals', $newStatus);
                    redirect('practicals/view/' . $practicalId);
                } else {
                    $error = 'Failed to update status';
                }
            }
        }
        
        // Handle full edit
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['status'])) {
            $data = [
                'title' => sanitize($_POST['title'] ?? ''),
                'objective' => sanitizeHTML($_POST['objective'] ?? ''),
                'theory' => sanitizeHTML($_POST['theory'] ?? ''),
                'description' => sanitizeHTML($_POST['description'] ?? ''), // HTML from TinyMCE - sanitized
                'lab_id' => sanitize($_POST['lab_id'] ?? ''),
                'course_code' => sanitize($_POST['course_code'] ?? ''),
                'scheduled_date' => $_POST['scheduled_date'] ?? '',
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'max_students' => intval($_POST['max_students'] ?? 30),
                'required_equipment' => sanitize($_POST['required_equipment'] ?? ''),
                'required_chemicals' => sanitize($_POST['required_chemicals'] ?? ''),
                'procedure_json' => $_POST['procedure_json'] ?? '',
                'observations_table_structure' => $_POST['observations_table_structure'] ?? '',
                'safety_notes' => sanitize($_POST['safety_notes'] ?? ''),
                'results_template' => sanitizeHTML($_POST['results_template'] ?? ''), // HTML from TinyMCE - sanitized
                'calculations_template' => sanitizeHTML($_POST['calculations_template'] ?? '') // HTML from TinyMCE - sanitized
            ];
            
            // Validate required fields
            if (empty($data['title']) || empty($data['lab_id']) || 
                empty($data['scheduled_date']) || empty($data['start_time']) || 
                empty($data['end_time'])) {
                $error = 'Title, lab, date, and times are required.';
            } else {
                // Validate date and times first
                $dateTimeErrors = $this->model->validateDateTime(
                    $data['scheduled_date'],
                    $data['start_time'],
                    $data['end_time']
                );
                
                if (!empty($dateTimeErrors)) {
                    $error = implode(' ', $dateTimeErrors);
                } else {
                    // Check lab availability (skip if same lab and time)
                    $isSameTime = ($data['lab_id'] == $practical['lab_id'] && 
                                   $data['scheduled_date'] == $practical['scheduled_date'] && 
                                   $data['start_time'] == $practical['start_time'] && 
                                   $data['end_time'] == $practical['end_time']);
                    
                    if (!$isSameTime) {
                        $isAvailable = $this->model->checkLabAvailability(
                            $data['lab_id'], 
                            $data['scheduled_date'], 
                            $data['start_time'], 
                            $data['end_time'],
                            $practicalId // exclude current practical from check
                        );
                        
                        if (!$isAvailable) {
                            // Get available slots for better error message
                            $availableSlots = $this->model->getAvailableSlots($data['lab_id'], $data['scheduled_date']);
                            $freeSlots = array_filter($availableSlots, fn($slot) => $slot['available']);
                            
                            if (!empty($freeSlots)) {
                                $slotList = array_slice($freeSlots, 0, 3);
                                $slotTimes = implode(', ', array_map(fn($s) => substr($s['start'], 0, 5) . '-' . substr($s['end'], 0, 5), $slotList));
                                $error = "Lab is not available at the requested time. Available slots: $slotTimes";
                            } else {
                                $error = 'Lab is not available at the requested time. No slots available on this date. Please select a different date or time.';
                            }
                        }
                    }
                    
                    if (empty($error)) {
                        if ($this->model->update($practicalId, $data)) {
                            logActivity(Auth::id(), 'practical_updated', 'practicals');
                            $success = 'Practical updated successfully!';
                            $practical = array_merge($practical, $data);
                        } else {
                            $error = 'Failed to update practical.';
                        }
                    }
                }
            }
        }
        
        renderView('practicals/edit', [
            'practical' => $practical,
            'error' => $error,
            'success' => $success,
            'labs' => $labs,
            'userRole' => $userRole
        ]);
    }
    
    public function startPractical() {
        header('Content-Type: application/json');
        Auth::guard();

        $studentId = sanitize($_POST['student_id'] ?? '');
        $practicalId = sanitize($_POST['practical_id'] ?? '');

        if (empty($studentId) || empty($practicalId)) {
            echo json_encode(['status' => 'error', 'message' => 'Student ID and Practical ID are required']);
            exit;
        }

        // Check if attendance exists
        if (!$this->model->attendanceExists($studentId, $practicalId)) {
            echo json_encode(['status' => 'error', 'message' => 'Attendance not found']);
            exit;
        }

        // Check if a report already exists
        $report = $this->model->getReport($studentId, $practicalId);

        if ($report) {
            echo json_encode([
                'status' => 'success',
                'report_id' => $report['id'],
                'practical' => $this->model->getPracticalDetails($practicalId)
            ]);
            exit;
        }

        // Create a new report
        $reportId = $this->model->createReport([
            'student_id' => $studentId,
            'practical_id' => $practicalId,
            'status' => 'in_progress',
            'verified' => 1,
            'started_at' => date('Y-m-d H:i:s')
        ]);

        if ($reportId) {
            echo json_encode([
                'status' => 'success',
                'report_id' => $reportId,
                'practical' => $this->model->getPracticalDetails($practicalId)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create report']);
        }
    }

    /**
     * Get submission stats for a practical (AJAX).
     */
    public function submissionStats($practicalId = null) {
        header('Content-Type: application/json');
        Auth::guard(['lecturer', 'technician', 'admin']);

        if (!$practicalId) {
            echo json_encode(['error' => 'Practical ID required']);
            exit;
        }

        try {
            // Get enrolled students (users who are linked to this practical's course)
            $stmt = $this->db->prepare("
                SELECT u.id, u.full_name, u.reg_number
                FROM users u
                JOIN enrollments e ON u.id = e.user_id
                JOIN practicals p ON e.course_code = p.course_code
                WHERE p.id = ? AND u.role = 'student'
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$practicalId]);
            $students = $stmt->fetchAll();

            // Get submitted reports
            $stmt = $this->db->prepare("
                SELECT lr.student_id, lr.status, lr.submitted_at, lr.id as report_id,
                       lr.result as grade_text,
                       CASE WHEN lr.grade IS NOT NULL THEN 1 ELSE 0 END as graded,
                       lr.grade
                FROM lab_reports lr
                WHERE lr.practical_id = ?
            ");
            $stmt->execute([$practicalId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Map reports by student_id
            $reportMap = [];
            foreach ($reports as $r) {
                $reportMap[$r['student_id']] = $r;
            }

            // Build response
            $submittedCount = 0;
            $studentList = [];
            foreach ($students as $s) {
                $hasReport = isset($reportMap[$s['id']]);
                $report = $hasReport ? $reportMap[$s['id']] : null;
                if ($hasReport && $report['status'] === 'submitted') {
                    $submittedCount++;
                }
                $studentList[] = [
                    'full_name' => $s['full_name'],
                    'reg_number' => $s['reg_number'],
                    'status' => $hasReport ? $report['status'] : 'not_started',
                    'submitted_at' => $hasReport && $report['submitted_at']
                        ? date('M j, Y H:i', strtotime($report['submitted_at']))
                        : null,
                    'report_id' => $hasReport ? $report['report_id'] : null,
                    'graded' => $hasReport ? (bool)$report['graded'] : false,
                    'grade' => $hasReport ? $report['grade'] : null,
                ];
            }

            echo json_encode([
                'total_students' => count($students),
                'submitted' => $submittedCount,
                'students' => $studentList,
            ]);

        } catch (Exception $e) {
            error_log("submissionStats error: " . $e->getMessage());
            echo json_encode(['error' => 'Error loading submission data']);
        }
    }

    public function markAttendance() {
        header('Content-Type: application/json');
        Auth::guard();
        
        $payload = [];
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        $verificationMethod = sanitize($_POST['verification_method'] ?? $payload['verification_method'] ?? 'qr'); // qr, rfid, fingerprint
        $studentId = sanitize($_POST['student_id'] ?? $payload['student_id'] ?? Auth::id());
        
        // Check if practical exists and is published
        $practical = $this->model->getById($practicalId);
        if (!$practical || $practical['status'] !== 'published') {
            echo json_encode(['status' => 'error', 'message' => 'Practical not found or not available']);
            exit;
        }
        
        // Check if attendance already exists
        if ($this->model->attendanceExists($studentId, $practicalId)) {
            echo json_encode(['status' => 'error', 'message' => 'Attendance already marked']);
            exit;
        }
        
        // Mark attendance
        if ($this->model->markAttendance($studentId, $practicalId, $verificationMethod)) {
            logActivity($studentId, 'practical_attendance_marked', 'practicals', $practicalId);
            echo json_encode([
                'status' => 'success',
                'message' => 'Attendance marked successfully',
                'practical_id' => $practicalId
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to mark attendance']);
        }
    }
    
    private function startPracticalSession($studentId, $practicalId) {
        // Check if a report already exists
        $report = $this->model->getReport($studentId, $practicalId);
        
        if ($report) {
            return [
                'report_id' => $report['id'],
                'practical' => $this->model->getPracticalDetails($practicalId)
            ];
        }
        
        // Create a new report
        $reportId = $this->model->createReport([
            'student_id' => $studentId,
            'practical_id' => $practicalId,
            'status' => 'in_progress',
            'verified' => 1,
            'started_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($reportId) {
            return [
                'report_id' => $reportId,
                'practical' => $this->model->getPracticalDetails($practicalId)
            ];
        }
        
        return null;
    }
}
