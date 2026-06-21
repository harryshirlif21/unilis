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
     * Download datasheet as HTML (prints nicely as PDF).
     */
    public function download($reportId = null) {
        Auth::guard('student');

        if (!$reportId) {
            echo 'Report ID is required';
            exit;
        }

        $studentId = Auth::id();

        try {
            // Get report with practical details
            $stmt = $this->db->prepare("
                SELECT r.*, p.title as practical_title, p.course_code,
                       p.scheduled_date, p.start_time, p.end_time,
                       p.objective, p.theory, p.description,
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
                echo 'Datasheet not found';
                exit;
            }

            // Get student info
            $stmt = $this->db->prepare("SELECT full_name, reg_number FROM users WHERE id = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();

            $observations = json_decode($report['observations_json'] ?? '{}', true);

            $logoUrl = APP_URL . '/jkuatlogo.jpg';
            $blockchainHash = hash('sha256', $reportId . $studentId . $report['submitted_at']);
            $signatureHash = hash('sha256', $reportId . $studentId . date('Y-m-d') . 'UNILIS');

            // Generate HTML datasheet with JKUAT branding
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            $html .= '<title>Datasheet - ' . htmlspecialchars($report['practical_title']) . '</title>';
            $html .= '<style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
                .univ-header { display:flex; align-items:center; gap:15px; padding-bottom:15px; border-bottom:3px solid #1e3a5f; margin-bottom:20px; }
                .univ-header img { width:70px; height:auto; }
                .univ-header h1 { margin:0; font-size:18px; color:#1e3a5f; }
                .univ-header p { margin:2px 0 0; font-size:13px; color:#64748b; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h2 { color: #1e40af; margin: 0; font-size:20px; }
                .header p { color: #64748b; margin: 3px 0; }
                .section { margin-bottom: 20px; page-break-inside: avoid; }
                .section h3 { background: #1e3a5f; color: #fff; padding: 8px 15px; border-radius: 6px; font-size: 15px; margin: 0 0 10px; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { padding: 8px 12px; border: 1px solid #e2e8f0; text-align: left; font-size: 13px; }
                th { background: #f1f5f9; font-weight: 600; color:#1e3a5f; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; background: #f8fafc; padding: 12px; border-radius: 6px; margin-bottom: 15px; border-left:4px solid #1e3a5f; }
                .info-grid div { font-size: 13px; }
                .info-grid strong { color: #1e3a5f; }
                .footer { text-align: center; border-top: 2px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; }
                .badge { display: inline-block; background: #dcfce7; color: #166534; padding: 2px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
                .content-text { padding: 12px; background: #f9fafb; border-radius: 4px; font-size: 13px; line-height: 1.6; white-space: pre-wrap; border:1px solid #e2e8f0; }
                .qr-section { text-align:center; margin-top:20px; padding:15px; border:1px dashed #d1d5db; border-radius:8px; background:#f8fafc; }
                .qr-section img { width:100px; height:100px; } .qr-section p { font-size:11px; color:#64748b; margin:5px 0 0; }
                .sig { font-size:11px; color:#94a3b8; word-break:break-all; margin-top:5px; }
                @media print { body { margin: 20px; } .no-print { display: none; } }
            </style></head><body>';

            $html .= '<div class="no-print" style="margin-bottom:15px;">
                <button onclick="window.print()" style="padding:10px 20px;background:#1e3a5f;color:white;border:none;border-radius:6px;cursor:pointer;">🖨 Print / Save PDF</button>
                <button onclick="history.back()" style="padding:10px 20px;background:#e2e8f0;border:none;border-radius:6px;cursor:pointer;margin-left:8px;">← Back</button>
            </div>';

            // JKUAT Header
            $html .= '<div class="univ-header">';
            $html .= '<img src="' . $logoUrl . '" alt="JKUAT Logo" />';
            $html .= '<div><h1>Jomo Kenyatta University of Agriculture and Technology</h1>';
            $html .= '<p>JKUAT SmartLab — Official Lab Datasheet</p></div></div>';

            // Datasheet title
            $html .= '<div class="header">';
            $html .= '<h2>' . htmlspecialchars($report['practical_title']) . '</h2>';
            $html .= '<p>' . htmlspecialchars($report['course_code']) . ' — ' . htmlspecialchars($report['lab_name']) . ' (' . htmlspecialchars($report['lab_code']) . ')</p>';
            $html .= '<p><span class="badge">✅ Submitted: ' . date('M j, Y H:i', strtotime($report['submitted_at'])) . '</span></p>';
            $html .= '</div>';

            // Student & Practical Info
            $html .= '<div class="info-grid">';
            $html .= '<div><strong>Student:</strong><br>' . htmlspecialchars($student['full_name'] ?? 'N/A') . ' (' . htmlspecialchars($student['reg_number'] ?? 'N/A') . ')</div>';
            $html .= '<div><strong>Lecturer:</strong><br>' . htmlspecialchars($report['lecturer_name'] ?? 'N/A') . '</div>';
            $html .= '<div><strong>Date:</strong><br>' . htmlspecialchars($report['scheduled_date']) . ' ' . substr($report['start_time'] ?? '', 0, 5) . ' - ' . substr($report['end_time'] ?? '', 0, 5) . '</div>';
            $html .= '</div>';

            // Observations
            if (!empty($observations)) {
                $html .= '<div class="section"><h3>📊 Observations / Readings</h3>';
                $html .= '<table><thead><tr>';
                $firstRow = reset($observations);
                if (is_array($firstRow)) {
                    foreach ($firstRow as $colName => $val) {
                        $html .= '<th>' . htmlspecialchars($colName) . '</th>';
                    }
                }
                $html .= '</tr></thead><tbody>';
                foreach ($observations as $row) {
                    if (!is_array($row)) continue;
                    $html .= '<tr>';
                    foreach ($row as $val) {
                        $html .= '<td>' . htmlspecialchars($val) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table></div>';
            }

            // Calculations
            if (!empty($report['calculations'])) {
                $html .= '<div class="section"><h3>📝 Calculations</h3>';
                $html .= '<div class="content-text">' . htmlspecialchars($report['calculations']) . '</div></div>';
            }

            // Result
            if (!empty($report['result'])) {
                $html .= '<div class="section"><h3>✅ Result</h3>';
                $html .= '<div class="content-text">' . htmlspecialchars($report['result']) . '</div></div>';
            }

            // Conclusion
            if (!empty($report['conclusion'])) {
                $html .= '<div class="section"><h3>📌 Conclusion</h3>';
                $html .= '<div class="content-text">' . htmlspecialchars($report['conclusion']) . '</div></div>';
            }

            // QR Code + Verification
            $qrData = APP_URL . '/verify/datasheet/' . $reportId;
            $html .= '<div class="qr-section">';
            $html .= '<img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($qrData) . '" alt="QR Code" />';
            $html .= '<p>Scan this QR code to verify the authenticity of this datasheet</p>';
            $html .= '<div class="sig"><strong>Datasheet ID:</strong> ' . htmlspecialchars($reportId) . '<br>';
            $html .= '<strong>Blockchain Hash:</strong> ' . htmlspecialchars($blockchainHash) . '<br>';
            $html .= '<strong>Digital Signature:</strong> ' . htmlspecialchars($signatureHash) . '</div>';
            $html .= '</div>';

            $html .= '<div class="footer">';
            $html .= 'Generated by UNILIS SmartLabs &bull; ' . date('M j, Y H:i:s') . '<br>';
            $html .= 'Datasheet ID: ' . htmlspecialchars($reportId) . ' &bull; Status: Submitted on ' . date('M j, Y H:i:s', strtotime($report['submitted_at']));
            $html .= '</div></body></html>';

            // Output as HTML download
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="datasheet-' . $reportId . '.html"');
            echo $html;
            exit;

        } catch (Exception $e) {
            error_log("StudentTestPracticalController::download - Error: " . $e->getMessage());
            echo 'Error generating datasheet: ' . $e->getMessage();
        }
    }
}