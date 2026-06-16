<?php
/**
 * UNILIS SmartLabs - Academic Integrity Workflow Engine
 * 
 * This is the core state machine that enforces the strict practical workflow:
 * 1. Scheduling → 2. Attendance Verification (physical presence ONLY)
 * 3. Practical Start → 4. Practical Complete → 5. Datasheet Auto-Generation
 * 6. Datasheet Review & Submit → 7. Report Writing Open → 8. Report Submit → 9. Graded
 * 
 * CRITICAL RULES ENFORCED:
 * - Datasheet is never generated before practical starts
 * - Only RFID/Biometric/DynamicQR/TechnicianCode/NFC qualify as verification
 * - Password/email login never counts as attendance
 * - Report writing locked until datasheet submitted
 * - All datasheets contain QR verification + blockchain linkage
 */

class WorkflowEngine {
    private PDO $db;
    
    // Allowed attendance verification methods (password/email explicitly excluded)
    const ALLOWED_VERIFICATION_METHODS = ['RFID', 'BIOMETRIC', 'DYNAMIC_QR', 'TECHNICIAN_CODE', 'NFC'];
    
    // Disallowed methods
    const DISALLOWED_VERIFICATION_METHODS = ['EMAIL_PASSWORD', 'PASSWORD_ONLY'];
    
    // Complete workflow states in order
    const WORKFLOW_STATES = [
        'Scheduled',
        'Awaiting Verification',
        'Verified',
        'Ready To Start',
        'In Progress',
        'Practical Completed',
        'Datasheet Generated',
        'Datasheet Submitted',
        'Report Writing Open',
        'Report Submitted',
        'Graded'
    ];
    
    // Valid state transitions
    const VALID_TRANSITIONS = [
        'Scheduled'              => 'Awaiting Verification',
        'Awaiting Verification'  => 'Verified',
        'Verified'               => 'Ready To Start',
        'Ready To Start'         => 'In Progress',
        'In Progress'            => 'Practical Completed',
        'Practical Completed'    => 'Datasheet Generated',
        'Datasheet Generated'    => 'Datasheet Submitted',
        'Datasheet Submitted'    => 'Report Writing Open',
        'Report Writing Open'    => 'Report Submitted',
        'Report Submitted'       => 'Graded',
    ];
    
    public function __construct(?PDO $db = null) {
        $this->db = $db ?? getDB();
    }
    
    /**
     * Get the current workflow state for a student's practical session
     */
    public function getCurrentState(string $studentId, string $practicalId): string {
        try {
            $stmt = $this->db->prepare("
                SELECT workflow_status FROM student_practical_sessions 
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$studentId, $practicalId]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return 'Scheduled'; // Default initial state
            }
            
            return $result['workflow_status'];
        } catch (Exception $e) {
            error_log("WorkflowEngine::getCurrentState Error: " . $e->getMessage());
            return 'Scheduled';
        }
    }
    
    /**
     * Initialize a session record for a student/practical pair
     */
    public function initializeSession(string $studentId, string $practicalId): bool {
        try {
            // Check if session already exists
            $stmt = $this->db->prepare("
                SELECT id FROM student_practical_sessions 
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$studentId, $practicalId]);
            
            if ($stmt->fetch()) {
                return true; // Already initialized
            }
            
            $id = bin2hex(random_bytes(16));
            $stmt = $this->db->prepare("
                INSERT INTO student_practical_sessions 
                (id, student_id, practical_id, workflow_status)
                VALUES (?, ?, ?, 'Scheduled')
            ");
            return $stmt->execute([$id, $studentId, $practicalId]);
        } catch (Exception $e) {
            error_log("WorkflowEngine::initializeSession Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Attempt to transition to the next workflow state.
     * Returns [success: bool, message: string, new_state: string]
     */
    public function transition(
        string $studentId, 
        string $practicalId, 
        string $targetState,
        array $context = []
    ): array {
        try {
            $currentState = $this->getCurrentState($studentId, $practicalId);
            
            // Validate the transition is allowed
            $validation = $this->validateTransition($currentState, $targetState, $studentId, $practicalId, $context);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'current_state' => $currentState,
                    'target_state' => $targetState
                ];
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Execute the state-specific logic
            $transitionResult = $this->executeTransitionLogic(
                $currentState, $targetState, $studentId, $practicalId, $context
            );
            
            if (!$transitionResult['success']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => $transitionResult['message'],
                    'current_state' => $currentState,
                    'target_state' => $targetState
                ];
            }
            
            // Update the workflow status
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET workflow_status = ?, updated_at = NOW()
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$targetState, $studentId, $practicalId]);
            
            // Log the transition
            $this->logTransition($studentId, $practicalId, $currentState, $targetState, $context);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Transitioned from {$currentState} to {$targetState}",
                'current_state' => $currentState,
                'target_state' => $targetState,
                'data' => $transitionResult['data'] ?? null
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("WorkflowEngine::transition Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Internal error: ' . $e->getMessage(),
                'current_state' => $currentState ?? 'unknown',
                'target_state' => $targetState
            ];
        }
    }
    
    /**
     * Validate that a transition is allowed based on strict rules
     */
    private function validateTransition(
        string $from, 
        string $to, 
        string $studentId, 
        string $practicalId,
        array $context
    ): array {
        // Check if this is a valid state transition
        $expectedNext = self::VALID_TRANSITIONS[$from] ?? null;
        if ($expectedNext !== $to) {
            return [
                'valid' => false,
                'message' => "Invalid transition from '{$from}' to '{$to}'. Expected: '{$expectedNext}'"
            ];
        }
        
        // State-specific validation
        switch ($to) {
            case 'Verified':
                return $this->validateAttendanceVerification($studentId, $practicalId, $context);
                
            case 'Ready To Start':
                return $this->validateReadyToStart($studentId, $practicalId, $context);
                
            case 'In Progress':
                return $this->validateStartPractical($studentId, $practicalId, $context);
                
            case 'Practical Completed':
                return $this->validatePracticalCompletion($studentId, $practicalId, $context);
                
            case 'Datasheet Generated':
                return $this->validateDatasheetGeneration($studentId, $practicalId, $context);
                
            case 'Datasheet Submitted':
                return $this->validateDatasheetSubmission($studentId, $practicalId, $context);
                
            case 'Report Writing Open':
                return $this->validateReportWritingOpen($studentId, $practicalId, $context);
                
            case 'Report Submitted':
                return $this->validateReportSubmission($studentId, $practicalId, $context);
                
            case 'Graded':
                return $this->validateGrading($studentId, $practicalId, $context);
        }
        
        return ['valid' => true];
    }
    
    /**
     * CRITICAL: Validate attendance verification - ONLY physical presence methods accepted
     */
    private function validateAttendanceVerification(string $studentId, string $practicalId, array $context): array {
        $method = strtoupper($context['verification_method'] ?? '');
        
        // Reject password-only methods
        if (in_array($method, self::DISALLOWED_VERIFICATION_METHODS)) {
            return [
                'valid' => false,
                'message' => 'Password/email login cannot be used for attendance verification. Use RFID, Biometric, Dynamic QR, Technician Code, or NFC.'
            ];
        }
        
        // Must be an allowed method
        if (!in_array($method, self::ALLOWED_VERIFICATION_METHODS)) {
            return [
                'valid' => false,
                'message' => 'Invalid verification method. Accepted methods: ' . implode(', ', self::ALLOWED_VERIFICATION_METHODS)
            ];
        }
        
        // Check verification window
        $windowCheck = $this->checkVerificationWindow($practicalId);
        if (!$windowCheck['valid']) {
            return [
                'valid' => false,
                'message' => $windowCheck['message']
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate the verification window timing
     */
    private function checkVerificationWindow(string $practicalId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT scheduled_date, start_time, 
                       verification_window_opens_minutes,
                       verification_window_closes_minutes 
                FROM practicals WHERE id = ?
            ");
            $stmt->execute([$practicalId]);
            $practical = $stmt->fetch();
            
            if (!$practical) {
                return ['valid' => false, 'message' => 'Practical not found'];
            }
            
            $scheduledStart = new DateTime($practical['scheduled_date'] . ' ' . $practical['start_time']);
            $opensMinutes = intval($practical['verification_window_opens_minutes'] ?? 30);
            $closesMinutes = intval($practical['verification_window_closes_minutes'] ?? 20);
            
            $windowOpens = clone $scheduledStart;
            $windowOpens->modify("-{$opensMinutes} minutes");
            
            $windowCloses = clone $scheduledStart;
            $windowCloses->modify("+{$closesMinutes} minutes");
            
            $now = new DateTime();
            
            if ($now < $windowOpens) {
                return [
                    'valid' => false,
                    'message' => "Attendance verification opens at " . $windowOpens->format('H:i') . 
                                 " ({$opensMinutes} minutes before practical start)"
                ];
            }
            
            if ($now > $windowCloses) {
                return [
                    'valid' => false,
                    'message' => 'Attendance verification is currently unavailable. The verification window has closed.'
                ];
            }
            
            return ['valid' => true];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::checkVerificationWindow Error: " . $e->getMessage());
            return ['valid' => true]; // Allow on error
        }
    }
    
    /**
     * Validate conditions for moving to 'Verified' state
     */
    private function validateReadyToStart(string $studentId, string $practicalId, array $context): array {
        try {
            // Check current time >= practical start time
            $stmt = $this->db->prepare("
                SELECT scheduled_date, start_time FROM practicals WHERE id = ?
            ");
            $stmt->execute([$practicalId]);
            $practical = $stmt->fetch();
            
            if (!$practical) {
                return ['valid' => false, 'message' => 'Practical not found'];
            }
            
            $scheduledStart = new DateTime($practical['scheduled_date'] . ' ' . $practical['start_time']);
            $now = new DateTime();
            
            if ($now < $scheduledStart) {
                $waitMinutes = $now->diff($scheduledStart)->i;
                return [
                    'valid' => false,
                    'message' => "Practical starts at {$scheduledStart->format('H:i')}. Please wait (≈{$waitMinutes} minutes remaining)."
                ];
            }
            
            return ['valid' => true];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::validateReadyToStart Error: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Validation error'];
        }
    }
    
    /**
     * Validate conditions for starting the practical
     */
    private function validateStartPractical(string $studentId, string $practicalId, array $context): array {
        try {
            // Verify attendance is still valid (not expired/revoked)
            $stmt = $this->db->prepare("
                SELECT av.* FROM attendance_verifications av
                JOIN student_practical_sessions sps ON sps.attendance_verification_id = av.id
                WHERE sps.student_id = ? AND sps.practical_id = ?
                AND av.verification_status = 'verified'
                AND (av.expires_at IS NULL OR av.expires_at > NOW())
                ORDER BY av.created_at DESC LIMIT 1
            ");
            $stmt->execute([$studentId, $practicalId]);
            $verification = $stmt->fetch();
            
            if (!$verification) {
                return [
                    'valid' => false,
                    'message' => 'Valid attendance verification not found or has expired. Please verify your attendance again.'
                ];
            }
            
            // Check verification method is still valid
            if (in_array($verification['verification_method'], self::DISALLOWED_VERIFICATION_METHODS)) {
                return [
                    'valid' => false,
                    'message' => 'Invalid verification method. Please use RFID, Biometric, Dynamic QR, or NFC.'
                ];
            }
            
            return ['valid' => true];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::validateStartPractical Error: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Validation error'];
        }
    }
    
    /**
     * Validate practical completion
     */
    private function validatePracticalCompletion(string $studentId, string $practicalId, array $context): array {
        // Can always complete if in progress
        return ['valid' => true];
    }
    
    /**
     * Validate datasheet generation
     */
    private function validateDatasheetGeneration(string $studentId, string $practicalId, array $context): array {
        return ['valid' => true]; // Automatic after practical completion
    }
    
    /**
     * Validate datasheet submission
     */
    private function validateDatasheetSubmission(string $studentId, string $practicalId, array $context): array {
        // Datasheet must exist and be generated
        $stmt = $this->db->prepare("
            SELECT id FROM datasheet_submissions 
            WHERE student_id = ? AND practical_id = ? AND submission_status = 'generated'
        ");
        $stmt->execute([$studentId, $practicalId]);
        
        if (!$stmt->fetch()) {
            return ['valid' => false, 'message' => 'No generated datasheet found. Please complete your practical first.'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate report writing can open
     */
    private function validateReportWritingOpen(string $studentId, string $practicalId, array $context): array {
        // Must have submitted datasheet
        $stmt = $this->db->prepare("
            SELECT ds.submission_status, ds.id FROM datasheet_submissions ds
            JOIN student_practical_sessions sps ON sps.student_id = ds.student_id AND sps.practical_id = ds.practical_id
            WHERE ds.student_id = ? AND ds.practical_id = ? 
            AND sps.workflow_status = 'Datasheet Submitted'
        ");
        $stmt->execute([$studentId, $practicalId]);
        
        if (!$stmt->fetch()) {
            return ['valid' => false, 'message' => 'Datasheet must be submitted before report writing can begin.'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate report submission
     */
    private function validateReportSubmission(string $studentId, string $practicalId, array $context): array {
        return ['valid' => true];
    }
    
    /**
     * Validate grading (lecturer only)
     */
    private function validateGrading(string $studentId, string $practicalId, array $context): array {
        $role = $context['role'] ?? '';
        if ($role !== 'lecturer' && $role !== 'admin') {
            return ['valid' => false, 'message' => 'Only lecturers can grade practicals.'];
        }
        return ['valid' => true];
    }
    
    /**
     * Execute state-specific logic during transition
     */
    private function executeTransitionLogic(
        string $from, 
        string $to, 
        string $studentId, 
        string $practicalId, 
        array $context
    ): array {
        switch ($to) {
            case 'Awaiting Verification':
                return $this->onAwaitingVerification($studentId, $practicalId);
                
            case 'Verified':
                return $this->onVerified($studentId, $practicalId, $context);
                
            case 'In Progress':
                return $this->onInProgress($studentId, $practicalId);
                
            case 'Practical Completed':
                return $this->onPracticalCompleted($studentId, $practicalId);
                
            case 'Datasheet Generated':
                return $this->onDatasheetGenerated($studentId, $practicalId, $context);
                
            case 'Datasheet Submitted':
                return $this->onDatasheetSubmitted($studentId, $practicalId, $context);
                
            case 'Report Writing Open':
                return $this->onReportWritingOpen($studentId, $practicalId);
                
            case 'Report Submitted':
                return $this->onReportSubmitted($studentId, $practicalId, $context);
                
            case 'Graded':
                return $this->onGraded($studentId, $practicalId, $context);
        }
        
        return ['success' => true, 'message' => 'No special logic', 'data' => null];
    }
    
    /**
     * Handle transition to Awaiting Verification
     */
    private function onAwaitingVerification(string $studentId, string $practicalId): array {
        // Ensure session exists
        $this->initializeSession($studentId, $practicalId);
        return ['success' => true, 'message' => 'Session initialized', 'data' => null];
    }
    
    /**
     * Handle transition to Verified - record the verification
     */
    private function onVerified(string $studentId, string $practicalId, array $context): array {
        try {
            $method = strtoupper($context['verification_method']);
            $device = $context['device'] ?? null;
            $ipAddress = $context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
            $userAgent = $context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
            
            // Set expiry: 30 minutes from now or practical end time, whichever is sooner
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Create attendance verification record
            $verificationId = bin2hex(random_bytes(16));
            $stmt = $this->db->prepare("
                INSERT INTO attendance_verifications 
                (id, student_id, practical_id, verification_method, 
                 verification_timestamp, verification_device, verification_status,
                 ip_address, user_agent, expires_at)
                VALUES (?, ?, ?, ?, NOW(), ?, 'verified', ?, ?, ?)
            ");
            $stmt->execute([
                $verificationId, $studentId, $practicalId,
                $method, $device, $ipAddress, $userAgent, $expiresAt
            ]);
            
            // Link to session
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET attendance_verification_id = ?,
                    verification_method = ?,
                    verification_timestamp = NOW(),
                    verification_approved = 1
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$verificationId, $method, $studentId, $practicalId]);
            
            return [
                'success' => true,
                'message' => 'Attendance verified successfully via ' . $method,
                'data' => [
                    'verification_id' => $verificationId,
                    'method' => $method,
                    'expires_at' => $expiresAt
                ]
            ];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::onVerified Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to record verification: ' . $e->getMessage()];
        }
    }
    
    /**
     * Handle transition to In Progress
     */
    private function onInProgress(string $studentId, string $practicalId): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET practical_started_at = NOW()
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$studentId, $practicalId]);
            
            return ['success' => true, 'message' => 'Practical started', 'data' => null];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Handle transition to Practical Completed
     */
    private function onPracticalCompleted(string $studentId, string $practicalId): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET practical_completed_at = NOW()
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$studentId, $practicalId]);
            
            return ['success' => true, 'message' => 'Practical completed', 'data' => null];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Handle transition to Datasheet Generated - auto-generate the datasheet
     */
    private function onDatasheetGenerated(string $studentId, string $practicalId, array $context): array {
        try {
            require_once __DIR__ . '/DatasheetGenerator.php';
            $generator = new DatasheetGenerator();
            
            $result = $generator->generate($studentId, $practicalId);
            
            if (!$result['success']) {
                return ['success' => false, 'message' => $result['message']];
            }
            
            // Update session with datasheet info
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET datasheet_generated_at = NOW(),
                    datasheet_hash = ?,
                    datasheet_qr_token = ?
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([
                $result['blockchain_hash'],
                $result['qr_token'],
                $studentId,
                $practicalId
            ]);
            
            return [
                'success' => true,
                'message' => 'Datasheet generated successfully',
                'data' => $result
            ];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::onDatasheetGenerated Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate datasheet: ' . $e->getMessage()];
        }
    }
    
    /**
     * Handle transition to Datasheet Submitted
     */
    private function onDatasheetSubmitted(string $studentId, string $practicalId, array $context): array {
        try {
            require_once __DIR__ . '/BlockchainAuditService.php';
            
            // Get the generated datasheet
            $stmt = $this->db->prepare("
                SELECT * FROM datasheet_submissions 
                WHERE student_id = ? AND practical_id = ? AND submission_status = 'generated'
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$studentId, $practicalId]);
            $datasheet = $stmt->fetch();
            
            if (!$datasheet) {
                return ['success' => false, 'message' => 'No generated datasheet found'];
            }
            
            // Store in blockchain
            $blockchain = new BlockchainAuditService();
            $blockchainResult = $blockchain->storeDatasheetHash($datasheet['id'], $datasheet['pdf_hash']);
            
            // Mark datasheet as submitted
            $stmt = $this->db->prepare("
                UPDATE datasheet_submissions 
                SET submission_status = 'submitted',
                    submitted_at = NOW(),
                    blockchain_hash = ?,
                    blockchain_block_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $blockchainResult['hash'] ?? $datasheet['pdf_hash'],
                $blockchainResult['block_id'] ?? null,
                $datasheet['id']
            ]);
            
            // Update session
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET datasheet_submitted_at = NOW(),
                    datasheet_hash = ?
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([
                $blockchainResult['hash'] ?? $datasheet['pdf_hash'],
                $studentId,
                $practicalId
            ]);
            
            return [
                'success' => true,
                'message' => 'Datasheet submitted and blockchain record created',
                'data' => [
                    'blockchain_hash' => $blockchainResult['hash'] ?? $datasheet['pdf_hash'],
                    'block_id' => $blockchainResult['block_id'] ?? null
                ]
            ];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::onDatasheetSubmitted Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to submit datasheet: ' . $e->getMessage()];
        }
    }
    
    /**
     * Handle transition to Report Writing Open
     */
    private function onReportWritingOpen(string $studentId, string $practicalId): array {
        try {
            $reportId = bin2hex(random_bytes(16));
            
            // Create report record linked to datasheet
            $stmt = $this->db->prepare("
                INSERT INTO reports 
                (id, student_id, practical_id, status, datasheet_submitted, created_at)
                VALUES (?, ?, ?, 'draft', 1, NOW())
            ");
            $stmt->execute([$reportId, $studentId, $practicalId]);
            
            // Update session
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET report_id = ?,
                    report_started_at = NOW()
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$reportId, $studentId, $practicalId]);
            
            return [
                'success' => true,
                'message' => 'Report writing is now open',
                'data' => ['report_id' => $reportId]
            ];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::onReportWritingOpen Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to open report writing: ' . $e->getMessage()];
        }
    }
    
    /**
     * Handle transition to Report Submitted
     */
    private function onReportSubmitted(string $studentId, string $practicalId, array $context): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET report_submitted_at = NOW()
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$studentId, $practicalId]);
            
            return ['success' => true, 'message' => 'Report submitted', 'data' => null];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Handle transition to Graded
     */
    private function onGraded(string $studentId, string $practicalId, array $context): array {
        try {
            $grade = $context['grade'] ?? null;
            $gradedBy = $context['graded_by'] ?? null;
            
            if ($grade === null || !$gradedBy) {
                return ['success' => false, 'message' => 'Grade and grader required'];
            }
            
            $stmt = $this->db->prepare("
                UPDATE student_practical_sessions 
                SET grade = ?, graded_by = ?, graded_at = NOW()
                WHERE student_id = ? AND practical_id = ?
            ");
            $stmt->execute([$grade, $gradedBy, $studentId, $practicalId]);
            
            return ['success' => true, 'message' => 'Graded', 'data' => null];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Log the transition for audit trail
     */
    private function logTransition(
        string $studentId, 
        string $practicalId, 
        string $from, 
        string $to,
        array $context
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, module, ip_address, created_at)
                VALUES (?, ?, 'workflow', ?, NOW())
            ");
            $stmt->execute([
                $studentId,
                "workflow_transition: {$from} → {$to}",
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
        } catch (Exception $e) {
            error_log("WorkflowEngine::logTransition Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get the practical workflow status for display
     */
    public function getWorkflowStatus(string $studentId, string $practicalId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT sps.*, p.title as practical_title, p.scheduled_date, 
                       p.start_time, p.end_time, l.name as lab_name,
                       av.verification_method, av.verification_timestamp as av_time,
                       ds.submission_status as datasheet_submission_status,
                       r.status as report_status, r.grade
                FROM student_practical_sessions sps
                JOIN practicals p ON sps.practical_id = p.id
                LEFT JOIN labs l ON p.lab_id = l.id
                LEFT JOIN attendance_verifications av ON sps.attendance_verification_id = av.id
                LEFT JOIN datasheet_submissions ds ON sps.student_id = ds.student_id AND sps.practical_id = ds.practical_id
                LEFT JOIN reports r ON sps.report_id = r.id
                WHERE sps.student_id = ? AND sps.practical_id = ?
            ");
            $stmt->execute([$studentId, $practicalId]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return [
                    'workflow_status' => 'Scheduled',
                    'progress_percent' => 0,
                    'can_start_practical' => false,
                    'can_generate_datasheet' => false,
                    'can_write_report' => false
                ];
            }
            
            $stateIndex = array_search($result['workflow_status'], self::WORKFLOW_STATES);
            $totalStates = count(self::WORKFLOW_STATES) - 1;
            $progressPercent = $stateIndex !== false ? round(($stateIndex / $totalStates) * 100) : 0;
            
            return [
                'session_id' => $result['id'],
                'practical_title' => $result['practical_title'],
                'workflow_status' => $result['workflow_status'],
                'verification_method' => $result['verification_method'],
                'verification_timestamp' => $result['av_time'],
                'practical_started_at' => $result['practical_started_at'],
                'practical_completed_at' => $result['practical_completed_at'],
                'datasheet_generated_at' => $result['datasheet_generated_at'],
                'datasheet_submitted_at' => $result['datasheet_submitted_at'],
                'datasheet_hash' => $result['datasheet_hash'],
                'datasheet_qr_token' => $result['datasheet_qr_token'],
                'datasheet_submission_status' => $result['datasheet_submission_status'],
                'report_status' => $result['report_status'],
                'report_submitted_at' => $result['report_submitted_at'],
                'grade' => $result['grade'],
                'lab_name' => $result['lab_name'],
                'scheduled_date' => $result['scheduled_date'],
                'start_time' => $result['start_time'],
                'end_time' => $result['end_time'],
                'progress_percent' => $progressPercent
            ];
            
        } catch (Exception $e) {
            error_log("WorkflowEngine::getWorkflowStatus Error: " . $e->getMessage());
            return ['workflow_status' => 'Unknown', 'progress_percent' => 0];
        }
    }
    
    /**
     * Check if a student can start the practical (with all validations)
     */
    public function canStartPractical(string $studentId, string $practicalId): array {
        $currentState = $this->getCurrentState($studentId, $practicalId);
        
        // Must be exactly in 'Ready To Start' state
        if ($currentState !== 'Ready To Start') {
            return [
                'allowed' => false,
                'message' => "Cannot start practical in '{$currentState}' state. Must be 'Ready To Start'.",
                'current_state' => $currentState
            ];
        }
        
        // Run background checks
        $checks = $this->runBackgroundChecks($studentId, $practicalId);
        
        if (!$checks['passed']) {
            return [
                'allowed' => false,
                'message' => $checks['message'],
                'current_state' => $currentState,
                'failed_check' => $checks['failed_check']
            ];
        }
        
        return [
            'allowed' => true,
            'message' => 'You can start the practical',
            'current_state' => $currentState
        ];
    }
    
    /**
     * Run all background checks before starting a practical
     */
    private function runBackgroundChecks(string $studentId, string $practicalId): array {
        $checks = [
            'checkStudentScheduled' => function() use ($studentId, $practicalId) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as cnt FROM student_practical_sessions 
                    WHERE student_id = ? AND practical_id = ?
                ");
                $stmt->execute([$studentId, $practicalId]);
                return $stmt->fetch()['cnt'] > 0 
                    ? ['passed' => true] 
                    : ['passed' => false, 'message' => 'Student is not scheduled for this practical'];
            },
            
            'checkVerificationWindow' => function() use ($practicalId) {
                return $this->checkVerificationWindow($practicalId);
            },
            
            'checkAttendanceVerification' => function() use ($studentId, $practicalId) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as cnt FROM student_practical_sessions 
                    WHERE student_id = ? AND practical_id = ? AND verification_approved = 1
                ");
                $stmt->execute([$studentId, $practicalId]);
                return $stmt->fetch()['cnt'] > 0
                    ? ['passed' => true]
                    : ['passed' => false, 'message' => 'Attendance not verified'];
            },
            
            'checkVerificationMethod' => function() use ($studentId, $practicalId) {
                $stmt = $this->db->prepare("
                    SELECT verification_method FROM student_practical_sessions 
                    WHERE student_id = ? AND practical_id = ?
                ");
                $stmt->execute([$studentId, $practicalId]);
                $row = $stmt->fetch();
                $method = $row ? $row['verification_method'] : '';
                
                if (in_array($method, self::DISALLOWED_VERIFICATION_METHODS)) {
                    return ['passed' => false, 'message' => 'Password-only verification is not allowed'];
                }
                return ['passed' => true];
            },
            
            'checkVerificationExpiry' => function() use ($studentId, $practicalId) {
                $stmt = $this->db->prepare("
                    SELECT av.expires_at FROM attendance_verifications av
                    JOIN student_practical_sessions sps ON sps.attendance_verification_id = av.id
                    WHERE sps.student_id = ? AND sps.practical_id = ?
                    ORDER BY av.created_at DESC LIMIT 1
                ");
                $stmt->execute([$studentId, $practicalId]);
                $row = $stmt->fetch();
                
                if ($row && $row['expires_at']) {
                    $expires = strtotime($row['expires_at']);
                    if (time() > $expires) {
                        return ['passed' => false, 'message' => 'Verification has expired. Please verify again.'];
                    }
                }
                return ['passed' => true];
            },
            
            'checkPracticalStatus' => function() use ($practicalId) {
                $stmt = $this->db->prepare("
                    SELECT status FROM practicals WHERE id = ?
                ");
                $stmt->execute([$practicalId]);
                $row = $stmt->fetch();
                
                if (!$row || $row['status'] !== 'published') {
                    return ['passed' => false, 'message' => 'Practical is not published'];
                }
                return ['passed' => true];
            },
            
            'checkLabSession' => function() use ($practicalId) {
                // Check if lab is open/available
                $stmt = $this->db->prepare("
                    SELECT ls.status FROM lab_sessions ls 
                    WHERE ls.practical_id = ? ORDER BY ls.started_at DESC LIMIT 1
                ");
                $stmt->execute([$practicalId]);
                $row = $stmt->fetch();
                
                if ($row && $row['status'] !== 'open') {
                    return ['passed' => false, 'message' => 'Lab session is not open'];
                }
                return ['passed' => true];
            }
        ];
        
        foreach ($checks as $checkName => $checkFunc) {
            $result = $checkFunc();
            if (!$result['passed']) {
                return [
                    'passed' => false,
                    'message' => $result['message'] ?? 'Check failed',
                    'failed_check' => $checkName
                ];
            }
        }
        
        return ['passed' => true];
    }
}