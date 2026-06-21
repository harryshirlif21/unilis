<?php
/**
 * Temporary controller for testing — takes a practical directly
 * without requiring QR / RFID / code / biometric / attendance.
 * 
 * Route: /start-practical-test/{id}
 * Add this to routes/web.php
 */

require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class StudentTestPracticalController {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Directly start a practical session — NO attendance check.
     */
    public function start($practicalId = null) {
        Auth::guard('student');

        if (!$practicalId) {
            echo 'Practical ID is required';
            exit;
        }

        $studentId = Auth::id();

        try {
            // Get practical details
            $stmt = $this->db->prepare("
                SELECT p.*, l.name as lab_name, l.lab_code
                FROM practicals p
                LEFT JOIN labs l ON p.lab_id = l.id
                WHERE p.id = ? AND p.status IN ('published', 'draft')
            ");
            $stmt->execute([$practicalId]);
            $practical = $stmt->fetch();

            if (!$practical) {
                echo 'Practical not found or not available';
                exit;
            }

            // Check if report already exists
            $stmt = $this->db->prepare("
                SELECT id, status FROM lab_reports 
                WHERE practical_id = ? AND student_id = ? 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$practicalId, $studentId]);
            $existingReport = $stmt->fetch();

            if ($existingReport) {
                if ($existingReport['status'] === 'submitted') {
                    // Already submitted — redirect to view
                    header('Location: ' . APP_URL . '/student/view_practical/' . $practicalId);
                    exit;
                }
                // In progress — use existing report
                $reportId = $existingReport['id'];
            } else {
                // Create new report (NO attendance check)
                $reportId = bin2hex(random_bytes(16));
                $stmt = $this->db->prepare("
                    INSERT INTO lab_reports (id, practical_id, student_id, status, created_at)
                    VALUES (?, ?, ?, 'in_progress', NOW())
                ");
                $stmt->execute([$reportId, $practicalId, $studentId]);
            }

            // Redirect to the practical view page
            header('Location: ' . APP_URL . '/student/view_practical/' . $practicalId . '?report_id=' . $reportId);
            exit;

        } catch (Exception $e) {
            error_log("StudentTestPracticalController::start - Error: " . $e->getMessage());
            echo 'Internal server error: ' . $e->getMessage();
        }
    }
}