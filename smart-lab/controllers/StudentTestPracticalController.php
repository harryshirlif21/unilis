<?php
/**
 * Test controller — takes a practical directly without QR/RFID/code/biometric.
 * Renders the full practical page with all details + fillable readings form.
 * Submit creates a datasheet (lab_reports).
 *
 * Routes:
 *   /start-practical-test/start/{id}     — Create/load report, show test page
 *   /start-practical-test/save-draft/{id} — AJAX save draft
 *   /start-practical-test/submit/{id}    — AJAX submit & create datasheet
 */

// Auto-detect environment
if ((strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false)) {
    require_once __DIR__.'/../config/app_production.php';
    require_once __DIR__.'/../config/database_production.php';
} else {
    require_once __DIR__.'/../config/app.php';
    require_once __DIR__.'/../config/database.php';
}
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class StudentTestPracticalController {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Start a practical session (NO attendance check).
     * Creates or loads a report, redirects to the test take page.
     */
    public function start($practicalId = null) {
        Auth::guard('student');

        if (!$practicalId) {
            echo 'Practical ID is required';
            exit;
        }

        $studentId = Auth::id();

        try {
            // Get practical with lecturer name
            $stmt = $this->db->prepare("
                SELECT p.*, l.name as lab_name, l.lab_code,
                       u.full_name as lecturer_name
                FROM practicals p
                LEFT JOIN labs l ON p.lab_id = l.id
                LEFT JOIN users u ON p.lecturer_id = u.id
                WHERE p.id = ? AND p.status IN ('published', 'draft')
            ");
            $stmt->execute([$practicalId]);
            $practical = $stmt->fetch();

            if (!$practical) {
                echo 'Practical not found or not available';
                exit;
            }

            // Parse JSON fields
            $practical['procedure'] = json_decode($practical['procedure_json'] ?? '[]', true) ?: [];
            $practical['observations_table'] = json_decode($practical['observations_table_structure'] ?? '[]', true) ?: [];
            $practical['apparatus'] = array_filter(explode("\n", $practical['required_equipment'] ?? ''));
            $practical['chemicals'] = array_filter(explode("\n", $practical['required_chemicals'] ?? ''));

            // Check if report already exists
            $stmt = $this->db->prepare("
                SELECT id, status, calculations, result, conclusion
                FROM lab_reports
                WHERE practical_id = ? AND student_id = ?
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$practicalId, $studentId]);
            $existingReport = $stmt->fetch();

            if ($existingReport) {
                if ($existingReport['status'] === 'submitted') {
                    // Already submitted — redirect to normal view
                    header('Location: ' . APP_URL . '/student/view_practical/' . $practicalId);
                    exit;
                }
                // In progress — use existing report
                $reportId = $existingReport['id'];
                $reportData = [
                    'calculations' => $existingReport['calculations'] ?? '',
                    'result' => $existingReport['result'] ?? '',
                    'conclusion' => $existingReport['conclusion'] ?? '',
                ];
            } else {
                // Create new report (NO attendance check)
                $reportId = bin2hex(random_bytes(16));
                $stmt = $this->db->prepare("
                    INSERT INTO lab_reports (id, practical_id, student_id, status, created_at)
                    VALUES (?, ?, ?, 'in_progress', NOW())
                ");
                $stmt->execute([$reportId, $practicalId, $studentId]);
                $reportData = ['calculations' => '', 'result' => '', 'conclusion' => ''];
            }

            // Render the test take page with the practical details + report form
            renderView('student/test_take_practical', [
                'practical' => $practical,
                'report_id' => $reportId,
                'report_data' => $reportData,
            ]);
            exit;

        } catch (Exception $e) {
            error_log("StudentTestPracticalController::start - Error: " . $e->getMessage());
            echo 'Internal server error: ' . $e->getMessage();
        }
    }

    /**
     * AJAX save draft.
     */
    public function saveDraft($practicalId = null) {
        Auth::guard('student');
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $reportId = $input['report_id'] ?? '';
            $formData = $input['form_data'] ?? [];

            if (!$reportId || !$practicalId) {
                echo json_encode(['success' => false, 'error' => 'Missing data']);
                exit;
            }

            // Save to lab_reports
            $stmt = $this->db->prepare("
                UPDATE lab_reports
                SET calculations = ?, result = ?, conclusion = ?, updated_at = NOW()
                WHERE id = ? AND practical_id = ? AND student_id = ?
            ");
            $stmt->execute([
                $formData['calculations'] ?? '',
                $formData['result'] ?? '',
                $formData['conclusion'] ?? '',
                $reportId,
                $practicalId,
                Auth::id(),
            ]);

            // Save observations JSON if present
            if (!empty($formData['observations'])) {
                $obsJson = json_encode($formData['observations']);
                $stmt = $this->db->prepare("
                    UPDATE lab_reports SET observations_json = ? WHERE id = ?
                ");
                $stmt->execute([$obsJson, $reportId]);
            }

            echo json_encode(['success' => true, 'message' => 'Draft saved']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX submit — saves and marks report as submitted (datasheet created).
     */
    public function submit($practicalId = null) {
        Auth::guard('student');
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $reportId = $input['report_id'] ?? '';
            $formData = $input['form_data'] ?? [];

            if (!$reportId || !$practicalId) {
                echo json_encode(['success' => false, 'error' => 'Missing data']);
                exit;
            }

            $studentId = Auth::id();

            // Update report with data and mark as submitted
            $obsJson = !empty($formData['observations']) ? json_encode($formData['observations']) : null;
            $stmt = $this->db->prepare("
                UPDATE lab_reports
                SET status = 'submitted',
                    calculations = ?,
                    result = ?,
                    conclusion = ?,
                    observations_json = ?,
                    submitted_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND practical_id = ? AND student_id = ? AND status = 'in_progress'
            ");
            $stmt->execute([
                $formData['calculations'] ?? '',
                $formData['result'] ?? '',
                $formData['conclusion'] ?? '',
                $obsJson,
                $reportId,
                $practicalId,
                $studentId,
            ]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'error' => 'Report not found or already submitted']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Datasheet created successfully', 'report_id' => $reportId]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Download datasheet as PDF using DatasheetPDFGenerator (consistent format with
     * JKUAT header, student info, observations table, filled answer pages, QR code).
     */
    public function download($reportId = null) {
        Auth::guard('student');

        if (!$reportId) {
            echo 'Report ID is required';
            exit;
        }

        $studentId = Auth::id();

        try {
            // ---- Fetch submitted report ----
            $stmt = $this->db->prepare("
                SELECT r.*, p.title as practical_title, p.course_code,
                       p.scheduled_date, p.start_time, p.end_time,
                       p.objective, p.description,
                       p.required_equipment, p.required_chemicals,
                       l.name as lab_name, l.lab_code,
                       u.full_name as lecturer_name
                FROM lab_reports r
                JOIN practicals p ON r.practical_id = p.id
                LEFT JOIN labs l ON p.lab_id = l.id
                LEFT JOIN users u ON p.lecturer_id = u.id
                WHERE r.id = ? AND r.student_id = ? AND r.status = 'submitted'
            ");
            $stmt->execute([$reportId, $studentId]);
            $report = $stmt->fetch();

            if (!$report) {
                echo 'Datasheet not found or not yet submitted';
                exit;
            }

            // ---- Student info ----
            try {
                $stmt = $this->db->prepare(
                    "SELECT full_name, reg_number, '' AS course FROM users WHERE id = ? LIMIT 1"
                );
                $stmt->execute([$studentId]);
            } catch (\PDOException $e) {
                $stmt = $this->db->prepare(
                    "SELECT full_name, reg_number, '' AS course FROM users WHERE id = ? LIMIT 1"
                );
                $stmt->execute([$studentId]);
            }
            $student = $stmt->fetch();

            // ---- Load autoloader + dompdf ----
            // Try in order: smart-lab local vendor → root project vendor (production)
            $localVendor  = __DIR__ . '/../vendor/autoload.php';   // smart-lab/vendor/
            $parentVendor = __DIR__ . '/../../vendor/autoload.php'; // unilis/vendor/ — present on production
            if (file_exists($localVendor)) {
                require_once $localVendor;
            }
            if (file_exists($parentVendor)) {
                require_once $parentVendor;
            }
            if (!class_exists('\\Dompdf\\Dompdf')) {
                header('Content-Type: text/plain');
                echo 'PDF library (dompdf) not found. Run: composer install';
                exit;
            }
            require_once __DIR__ . '/../includes/autoloader.php';

            // ---- Build observation rows from JSON ----
            $observationRows = [];
            $obsMixed = json_decode($report['observations_json'] ?? '{}', true);
            if (is_array($obsMixed) && !empty($obsMixed)) {
                $first = reset($obsMixed);
                if (is_array($first)) {
                    // Array of associative rows: [['Col1'=>val, ...], ...]
                    $observationRows = $obsMixed;
                } else {
                    // Flat key->value: convert to single-column rows
                    foreach ($obsMixed as $k => $v) {
                        $observationRows[] = ['Parameter' => $k, 'Value' => (string)$v];
                    }
                }
            }

            // ---- Generate QR code (verification URL) ----
            $verifyUrl = (defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://unilis.jhubafrica.com/smart-lab')
                       . '/verify.php?' . http_build_query([
                           'report_id'  => $reportId,
                           'student_id' => $studentId,
                           'type'       => 'lab_report',
                       ]);

            $qrPath = '';
            try {
                $qrGen = new \SmartLab\QRCodeGenerator();
                $qrPath = $qrGen->generate($verifyUrl, 'report_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $reportId));
            } catch (\Throwable $qrEx) {
                error_log('QR generation failed: ' . $qrEx->getMessage());
            }

            // ---- Build the PDF ----
            // Logo — __DIR__ works correctly on both local XAMPP and Docker
            $logoPath = realpath(__DIR__ . '/../jkuatlogo.jpg') ?: '';
            if (!$logoPath) {
                $logoPath = (defined('DOCUMENT_ROOT') ? DOCUMENT_ROOT : $_SERVER['DOCUMENT_ROOT'])
                          . '/smart-lab/jkuatlogo.jpg';
            }

            $sigHash = hash('sha256', $reportId . $studentId . ($report['submitted_at'] ?? date('Y-m-d H:i:s')));
            $blockchainHash = hash('sha256', $reportId . $sigHash);

            $generator = new \SmartLab\DatasheetPDFGenerator($logoPath);
            $generator
                ->setStudentDetails(
                    $student['full_name'] ?? 'Unknown',
                    $student['reg_number'] ?? 'N/A',
                    $student['course'] ?? ''
                )
                ->setPracticalDetails(
                    $report['practical_title'],
                    $report['lab_code'] ?? 'Lab',
                    $report['course_code'] ?? '',
                    $report['description'] ?? $report['objective'] ?? ''
                )
                ->setExtendedDetails([
                    'course_code'  => $report['course_code'] ?? '',
                    'lab_name'     => $report['lab_name']    ?? $report['lab_code'] ?? '',
                ])
                ->setDatasheetMeta($reportId, $blockchainHash, $report['submitted_at'] ?? '')
                ->setReadings([])
                ->setObservationRows($observationRows)
                ->setQRCode($qrPath)
                ->setSignature($sigHash, 'approved')
                ->setFilledAnswers([
                    'Student Observations & Calculations' => $report['calculations'] ?? '',
                    'Results & Analysis'                  => $report['result'] ?? '',
                    'Discussion'                          => $report['discussion'] ?? '',
                    'Conclusion & Recommendations'        => $report['conclusion'] ?? '',
                ]);

            $filename = 'datasheet-' . $reportId . '.pdf';
            $generator->stream($filename);
            exit;

        } catch (\Throwable $e) {
            // Catch both Exception and Error (e.g. class-not-found fatal)
            error_log('StudentTestPracticalController::download - ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo '<pre style="font-family:monospace;padding:20px;background:#fff3cd;border:1px solid #ffc107;color:#212529;">';
            echo '<strong>PDF generation failed</strong><br><br>';
            echo htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . "\n";
            echo htmlspecialchars('File: ' . $e->getFile() . ' line ' . $e->getLine());
            echo '</pre>';
        }
    }

    /**
     * Download the latest submitted datasheet for a practical (test mode).
     * GET /start-practical-test/download-latest/{practicalId}
     * Finds the most recent submitted report for this student + practical and streams the PDF.
     */
    public function downloadLatest($practicalId = null) {
        Auth::guard('student');

        if (!$practicalId) {
            echo 'Practical ID is required';
            exit;
        }

        $studentId = Auth::id();

        try {
            // Find the latest submitted report for this student + practical
            $stmt = $this->db->prepare("
                SELECT id FROM lab_reports
                WHERE practical_id = ? AND student_id = ? AND status = 'submitted'
                ORDER BY submitted_at DESC LIMIT 1
            ");
            $stmt->execute([$practicalId, $studentId]);
            $report = $stmt->fetch();

            if (!$report) {
                echo 'No datasheet found. Please complete and submit the practical first.';
                exit;
            }

            // Delegate to the existing download method
            $this->download($report['id']);
        } catch (Exception $e) {
            error_log("StudentTestPracticalController::downloadLatest - Error: " . $e->getMessage());
            http_response_code(500);
            echo 'Internal server error';
        }
    }

    /**
     * Upload completed physical report (PDF/image) for a submitted datasheet.
     * POST /start-practical-test/upload/{reportId}
     * Returns JSON.
     */
    public function upload($reportId = null) {
        Auth::guard('student');
        header('Content-Type: application/json');

        if (!$reportId || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit;
        }

        $studentId = Auth::id();

        // Verify report belongs to this student and is submitted
        $stmt = $this->db->prepare(
            "SELECT id FROM lab_reports WHERE id = ? AND student_id = ? AND status = 'submitted'"
        );
        $stmt->execute([$reportId, $studentId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Report not found or not yet submitted']);
            exit;
        }

        if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server size limit',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit',
                UPLOAD_ERR_NO_FILE    => 'No file uploaded',
            ];
            $errCode = $_FILES['report_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            echo json_encode(['success' => false, 'error' => $uploadErrors[$errCode] ?? 'Upload error']);
            exit;
        }

        $file = $_FILES['report_file'];

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            echo json_encode(['success' => false, 'error' => 'Only PDF, JPG, or PNG files are allowed']);
            exit;
        }

        // Validate MIME type (defence-in-depth)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type detected']);
            exit;
        }

        // Validate file size (10 MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File must be under 10 MB']);
            exit;
        }

        // Build destination path
        $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/smart-lab/assets/uploads/reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeId  = preg_replace('/[^a-zA-Z0-9_-]/', '', $reportId);
        $filename = 'report_' . $safeId . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file — check directory permissions']);
            exit;
        }

        $webPath = '/smart-lab/assets/uploads/reports/' . $filename;

        $this->db->prepare(
            "UPDATE lab_reports SET report_file = ?, report_uploaded_at = NOW() WHERE id = ? AND student_id = ?"
        )->execute([$webPath, $reportId, $studentId]);

        echo json_encode(['success' => true, 'message' => 'Report uploaded successfully', 'file' => $webPath]);
    }
}