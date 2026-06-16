<?php
/**
 * UNILIS SmartLabs - Workflow Controller
 * 
 * Handles all academic-integrity workflow transitions:
 * - Attendance verification (physical presence only)
 * - Practical start/complete
 * - Datasheet generation/submission
 * - Report writing/submission
 * - QR-based datasheet verification
 */

require_once __DIR__ . '/../includes/WorkflowEngine.php';
require_once __DIR__ . '/../includes/DatasheetGenerator.php';
require_once __DIR__ . '/../includes/BlockchainAuditService.php';
require_once __DIR__ . '/../includes/DynamicQRService.php';

class WorkflowController {
    private WorkflowEngine $workflow;
    private DatasheetGenerator $datasheetGenerator;
    private DynamicQRService $qrService;
    private BlockchainAuditService $blockchain;
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->workflow = new WorkflowEngine($this->db);
        $this->datasheetGenerator = new DatasheetGenerator($this->db);
        $this->qrService = new DynamicQRService($this->db);
        $this->blockchain = new BlockchainAuditService($this->db);
    }
    
    /**
     * Get workflow status for a student's practical
     * GET /workflow/status/{practical_id}
     */
    public function status($practicalId = null) {
        Auth::guard();
        header('Content-Type: application/json');
        
        if (!$practicalId) {
            $practicalId = sanitize($_GET['practical_id'] ?? '');
        }
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Practical ID is required']);
            exit;
        }
        
        $userId = Auth::id();
        $role = Auth::role();
        
        // Initialize session if not exists
        $this->workflow->initializeSession($userId, $practicalId);
        
        $status = $this->workflow->getWorkflowStatus($userId, $practicalId);
        $canStart = $this->workflow->canStartPractical($userId, $practicalId);
        
        $status['can_start_practical'] = $canStart['allowed'];
        $status['start_denied_reason'] = $canStart['message'] ?? '';
        $status['user_role'] = $role;
        
        echo json_encode($status);
    }
    
    /**
     * Verify attendance via approved method (RFID, Biometric, Dynamic QR, etc.)
     * POST /workflow/verify-attendance
     */
    public function verifyAttendance($param = null) {
        Auth::guard('student');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = [];
        $inputRaw = file_get_contents('php://input');
        if ($inputRaw) {
            $payload = json_decode($inputRaw, true) ?? [];
        }
        
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        $verificationMethod = sanitize(strtoupper($_POST['verification_method'] ?? $payload['verification_method'] ?? ''));
        $qrData = sanitize($_POST['qr_data'] ?? $payload['qr_data'] ?? '');
        $deviceId = sanitize($_POST['device_id'] ?? $payload['device_id'] ?? '');
        $rfidTag = sanitize($_POST['rfid_tag'] ?? $payload['rfid_tag'] ?? '');
        $biometricHash = sanitize($_POST['biometric_hash'] ?? $payload['biometric_hash'] ?? '');
        $technicianCode = sanitize($_POST['technician_code'] ?? $payload['technician_code'] ?? '');
        
        if (empty($practicalId) || empty($verificationMethod)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Practical ID and verification method required']);
            exit;
        }
        
        // Validate method is allowed
        if (in_array($verificationMethod, ['EMAIL_PASSWORD', 'PASSWORD_ONLY', 'PASSWORD'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Password/email login cannot be used for attendance verification']);
            exit;
        }
        
        if (!in_array($verificationMethod, ['RFID', 'BIOMETRIC', 'DYNAMIC_QR', 'TECHNICIAN_CODE', 'NFC'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid verification method. Accepted: RFID, BIOMETRIC, DYNAMIC_QR, TECHNICIAN_CODE, NFC']);
            exit;
        }
        
        $studentId = Auth::id();
        
        // Step 1: First transition to Awaiting Verification
        $initResult = $this->workflow->transition($studentId, $practicalId, 'Awaiting Verification');
        if (!$initResult['success']) {
            // May already be in this state, that's okay
        }
        
        // Step 2: For Dynamic QR, verify the QR payload first
        if ($verificationMethod === 'DYNAMIC_QR') {
            if (empty($qrData)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'QR data required for Dynamic QR verification']);
                exit;
            }
            
            $qrResult = $this->qrService->verifyQRPayload($qrData, $studentId);
            if (!$qrResult['valid']) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => $qrResult['message'],
                    'redirect' => 'verify-attendance'
                ]);
                exit;
            }
        }
        
        // Step 3: Transition to Verified
        $context = [
            'verification_method' => $verificationMethod,
            'device' => $deviceId ?: ($rfidTag ?: $biometricHash ?: ''),
            'qr_data' => $qrData,
            'technician_code' => $technicianCode
        ];
        
        $result = $this->workflow->transition($studentId, $practicalId, 'Verified', $context);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => "Attendance verified via {$verificationMethod}",
                'verification_method' => $verificationMethod,
                'workflow_status' => 'Verified',
                'verification_id' => $result['data']['verification_id'] ?? null,
                'expires_at' => $result['data']['expires_at'] ?? null,
                'next_step' => 'Wait for practical start time to begin the practical'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
    
    /**
     * Start the practical (after verification and start time)
     * POST /workflow/start-practical
     */
    public function startPractical($param = null) {
        Auth::guard('student');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = [];
        $inputRaw = file_get_contents('php://input');
        if ($inputRaw) {
            $payload = json_decode($inputRaw, true) ?? [];
        }
        
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Practical ID is required']);
            exit;
        }
        
        $studentId = Auth::id();
        
        // Check if can start
        $canStart = $this->workflow->canStartPractical($studentId, $practicalId);
        if (!$canStart['allowed']) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => $canStart['message'],
                'failed_check' => $canStart['failed_check'] ?? null,
                'redirect' => $canStart['failed_check'] === 'checkAttendanceVerification' ? 'verify-attendance' : null
            ]);
            exit;
        }
        
        // Transition to 'Ready To Start' first if needed
        $currentState = $this->workflow->getCurrentState($studentId, $practicalId);
        if ($currentState === 'Verified') {
            $readyResult = $this->workflow->transition($studentId, $practicalId, 'Ready To Start');
            if (!$readyResult['success']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $readyResult['message']]);
                exit;
            }
        }
        
        // Transition to 'In Progress'
        $result = $this->workflow->transition($studentId, $practicalId, 'In Progress');
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Practical started successfully',
                'workflow_status' => 'In Progress',
                'practical_started_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
    
    /**
     * Complete the practical
     * POST /workflow/complete-practical
     */
    public function completePractical($param = null) {
        Auth::guard('student');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = [];
        $inputRaw = file_get_contents('php://input');
        if ($inputRaw) {
            $payload = json_decode($inputRaw, true) ?? [];
        }
        
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Practical ID is required']);
            exit;
        }
        
        $studentId = Auth::id();
        
        // Transition to 'Practical Completed'
        $result = $this->workflow->transition($studentId, $practicalId, 'Practical Completed');
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Practical completed successfully. Datasheet generation will begin shortly.',
                'workflow_status' => 'Practical Completed',
                'next_step' => 'Datasheet will be auto-generated'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
    
    /**
     * Auto-generate datasheet after practical completion
     * POST /workflow/generate-datasheet
     */
    public function generateDatasheet($param = null) {
        Auth::guard('student');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = [];
        $inputRaw = file_get_contents('php://input');
        if ($inputRaw) {
            $payload = json_decode($inputRaw, true) ?? [];
        }
        
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Practical ID is required']);
            exit;
        }
        
        $studentId = Auth::id();
        
        // Transition to 'Datasheet Generated' - this auto-generates the datasheet
        $result = $this->workflow->transition($studentId, $practicalId, 'Datasheet Generated');
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Datasheet generated successfully',
                'workflow_status' => 'Datasheet Generated',
                'datasheet_id' => $result['data']['datasheet_id'] ?? null,
                'qr_token' => $result['data']['qr_token'] ?? null,
                'blockchain_hash' => $result['data']['blockchain_hash'] ?? null,
                'pdf_path' => $result['data']['pdf_path'] ?? null,
                'next_step' => 'Review and submit your datasheet'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
    
    /**
     * Submit datasheet (lock it, generate blockchain record, finalize QR)
     * POST /workflow/submit-datasheet
     */
    public function submitDatasheet($param = null) {
        Auth::guard('student');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = [];
        $inputRaw = file_get_contents('php://input');
        if ($inputRaw) {
            $payload = json_decode($inputRaw, true) ?? [];
        }
        
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Practical ID is required']);
            exit;
        }
        
        $studentId = Auth::id();
        
        // Transition to 'Datasheet Submitted' - this creates blockchain record
        $result = $this->workflow->transition($studentId, $practicalId, 'Datasheet Submitted');
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Datasheet submitted securely. Blockchain record created. Report writing is now open.',
                'workflow_status' => 'Datasheet Submitted',
                'datasheet_status' => 'submitted',
                'blockchain_hash' => $result['data']['blockchain_hash'] ?? null,
                'blockchain_block_id' => $result['data']['block_id'] ?? null,
                'next_step' => 'You can now write your report'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
    
    /**
     * Open report writing (after datasheet submitted)
     * POST /workflow/open-report-writing
     */
    public function openReportWriting($param = null) {
        Auth::guard('student');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = [];
        $inputRaw = file_get_contents('php://input');
        if ($inputRaw) {
            $payload = json_decode($inputRaw, true) ?? [];
        }
        
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Practical ID is required']);
            exit;
        }
        
        $studentId = Auth::id();
        
        // Transition to 'Report Writing Open'
        $result = $this->workflow->transition($studentId, $practicalId, 'Report Writing Open');
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Report writing is now open. You can create your report.',
                'workflow_status' => 'Report Writing Open',
                'report_id' => $result['data']['report_id'] ?? null
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
    
    /**
     * Submit report
     * POST /workflow/submit-report
     */
    public function submitReport($param = null) {
        Auth::guard('student');
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = [];
        $inputRaw = file_get_contents('php://input');
        if ($inputRaw) {
            $payload = json_decode($inputRaw, true) ?? [];
        }
        
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Practical ID is required']);
            exit;
        }
        
        $studentId = Auth::id();
        
        $result = $this->workflow->transition($studentId, $practicalId, 'Report Submitted');
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Report submitted successfully',
                'workflow_status' => 'Report Submitted'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
    
    /**
     * Verify datasheet by QR token
     * GET /workflow/verify-datasheet/{token}
     */
    public function verifyDatasheet($token = null) {
        header('Content-Type: application/json');
        
        if (!$token) {
            $token = sanitize($_GET['token'] ?? '');
        }
        
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['valid' => false, 'status' => 'INVALID', 'message' => 'Verification token required']);
            exit;
        }
        
        $result = $this->datasheetGenerator->verifyByToken($token);
        
        echo json_encode($result);
    }
    
    /**
     * Display the practical workflow dashboard (student-facing HTML)
     * GET /workflow/dashboard/{practical_id}
     */
    public function dashboard($practicalId = null) {
        Auth::guard('student');
        
        if (!$practicalId) {
            $practicalId = sanitize($_GET['practical_id'] ?? '');
        }
        
        if (empty($practicalId)) {
            echo 'Practical ID is required';
            exit;
        }
        
        $studentId = Auth::id();
        
        // Get practical info
        $stmt = $this->db->prepare("
            SELECT p.*, l.name as lab_name, l.lab_code,
                   u.full_name as lecturer_name
            FROM practicals p
            LEFT JOIN labs l ON p.lab_id = l.id
            LEFT JOIN users u ON p.lecturer_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$practicalId]);
        $practical = $stmt->fetch();
        
        if (!$practical) {
            echo 'Practical not found';
            exit;
        }
        
        // Initialize session and get workflow status
        $this->workflow->initializeSession($studentId, $practicalId);
        $status = $this->workflow->getWorkflowStatus($studentId, $practicalId);
        $canStart = $this->workflow->canStartPractical($studentId, $practicalId);
        $status['can_start_practical'] = $canStart['allowed'];
        $status['start_denied_reason'] = $canStart['message'] ?? '';
        $status['user_role'] = Auth::role();
        
        renderView('workflow/practical_dashboard', [
            'practical' => $practical,
            'status' => $status
        ]);
    }
    
    /**
     * Verify attendance view page (student-facing HTML)
     * GET /workflow/verify-view/{practical_id}
     */
    public function verifyView($practicalId = null) {
        Auth::guard('student');
        
        if (!$practicalId) {
            $practicalId = sanitize($_GET['practical_id'] ?? '');
        }
        
        if (empty($practicalId)) {
            echo 'Practical ID is required';
            exit;
        }
        
        $stmt = $this->db->prepare("
            SELECT p.*, l.name as lab_name, l.lab_code,
                   u.full_name as lecturer_name
            FROM practicals p
            LEFT JOIN labs l ON p.lab_id = l.id
            LEFT JOIN users u ON p.lecturer_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$practicalId]);
        $practical = $stmt->fetch();
        
        if (!$practical) {
            echo 'Practical not found';
            exit;
        }
        
        // Initialize workflow session
        $this->workflow->initializeSession(Auth::id(), $practicalId);
        
        renderView('workflow/verify_attendance', [
            'practical' => $practical,
            'user_id' => Auth::id()
        ]);
    }
    
    /**
     * Get datasheet verification page (HTML view for QR scan result)
     * GET /datasheets/verify/{token}
     */
    public function datasheetVerifyPage($token = null) {
        if (!$token) {
            http_response_code(404);
            echo 'Verification token required';
            exit;
        }
        
        $result = $this->datasheetGenerator->verifyByToken($token);
        
        renderView('workflow/datasheet_verify', [
            'result' => $result,
            'token' => $token
        ]);
    }
    
    /**
     * Get current dynamic QR for lab display
     * GET /workflow/lab-qr
     */
    public function labQR($param = null) {
        Auth::guard();
        header('Content-Type: application/json');
        
        $labId = sanitize($_GET['lab_id'] ?? '');
        $practicalId = sanitize($_GET['practical_id'] ?? '');
        
        if (empty($labId) || empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Lab ID and Practical ID required']);
            exit;
        }
        
        $qrResult = $this->qrService->getCurrentLabQR($labId, $practicalId);
        
        echo json_encode($qrResult);
    }
    
    /**
     * Get blockchain audit trail for a datasheet
     * GET /workflow/blockchain-audit/{datasheet_id}
     */
    public function blockchainAudit($datasheetId = null) {
        Auth::guard(['lecturer', 'admin']);
        header('Content-Type: application/json');
        
        if (!$datasheetId) {
            $datasheetId = sanitize($_GET['datasheet_id'] ?? '');
        }
        
        if (empty($datasheetId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Datasheet ID required']);
            exit;
        }
        
        $trail = $this->blockchain->getAuditTrail($datasheetId);
        
        echo json_encode([
            'datasheet_id' => $datasheetId,
            'block_count' => count($trail),
            'blocks' => $trail
        ]);
    }
    
    /**
     * Check for tampering on a datasheet
     * POST /workflow/check-tamper
     */
    public function checkTamper($param = null) {
        Auth::guard();
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $inputRaw = file_get_contents('php://input');
        $payload = json_decode($inputRaw, true) ?? [];
        
        $datasheetId = sanitize($_POST['datasheet_id'] ?? $payload['datasheet_id'] ?? '');
        $currentHash = sanitize($_POST['current_hash'] ?? $payload['current_hash'] ?? '');
        
        if (empty($datasheetId) || empty($currentHash)) {
            http_response_code(400);
            echo json_encode(['error' => 'Datasheet ID and current hash required']);
            exit;
        }
        
        $result = $this->blockchain->detectTampering($datasheetId, $currentHash);
        
        echo json_encode($result);
    }
    
    /**
     * Get all pending verifications for a practical (lecturer view)
     * GET /workflow/pending-verifications/{practical_id}
     */
    public function pendingVerifications($practicalId = null) {
        Auth::guard(['lecturer', 'admin']);
        header('Content-Type: application/json');
        
        if (!$practicalId) {
            $practicalId = sanitize($_GET['practical_id'] ?? '');
        }
        
        if (empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Practical ID required']);
            exit;
        }
        
        $stmt = $this->db->prepare("
            SELECT sps.*, u.full_name as student_name, u.reg_number,
                   av.verification_method, av.verification_timestamp,
                   av.verification_status as av_status
            FROM student_practical_sessions sps
            JOIN users u ON sps.student_id = u.id
            LEFT JOIN attendance_verifications av ON sps.attendance_verification_id = av.id
            WHERE sps.practical_id = ?
            ORDER BY sps.created_at DESC
        ");
        $stmt->execute([$practicalId]);
        $students = $stmt->fetchAll();
        
        echo json_encode([
            'practical_id' => $practicalId,
            'student_count' => count($students),
            'students' => $students
        ]);
    }
    
    /**
     * Grade a student's practical (lecturer only)
     * POST /workflow/grade
     */
    public function grade($param = null) {
        Auth::guard(['lecturer', 'admin']);
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $studentId = sanitize($_POST['student_id'] ?? $payload['student_id'] ?? '');
        $practicalId = sanitize($_POST['practical_id'] ?? $payload['practical_id'] ?? '');
        $grade = floatval($_POST['grade'] ?? $payload['grade'] ?? 0);
        
        if (empty($studentId) || empty($practicalId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Student ID and Practical ID required']);
            exit;
        }
        
        $result = $this->workflow->transition($studentId, $practicalId, 'Graded', [
            'grade' => $grade,
            'graded_by' => Auth::id(),
            'role' => Auth::role()
        ]);
        
        echo json_encode($result);
    }
}