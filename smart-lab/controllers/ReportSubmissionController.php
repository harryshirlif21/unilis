<?php
require_once __DIR__.'/../models/ReportModel.php';
require_once __DIR__.'/../models/PracticalModel.php';
require_once __DIR__.'/../models/DeadlineModel.php';
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../utils/helpers.php';

class ReportSubmissionController {
    private ReportModel $reportModel;
    private PracticalModel $practicalModel;
    private DeadlineModel $deadlineModel;
    
    public function __construct() {
        $this->reportModel = new ReportModel();
        $this->practicalModel = new PracticalModel();
        $this->deadlineModel = new DeadlineModel();
    }
    
    public function index($param = null) {
        Auth::guard('student');
        
        $studentId = Auth::id();
        
        // Get student's submitted reports
        $reports = $this->reportModel->getStudentReports($studentId);
        
        // Get available practicals for submission
        $availablePracticals = $this->getAvailablePracticalsForSubmission($studentId);
        
        renderView('report-submission/index', [
            'reports' => $reports,
            'availablePracticals' => $availablePracticals,
            'userRole' => 'student',
            'error' => '',
            'success' => ''
        ]);
    }
    
    public function create($param = null) {
        Auth::guard('student');
        
        $studentId = Auth::id();
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $practicalId = sanitize($_POST['practical_id'] ?? '');
            $title = sanitize($_POST['title'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $summary = sanitize($_POST['summary'] ?? '');
            $methodology = sanitize($_POST['methodology'] ?? '');
            $results = sanitize($_POST['results'] ?? '');
            $conclusions = sanitize($_POST['conclusions'] ?? '');
            $references = sanitize($_POST['references'] ?? '');
            
            if (empty($practicalId) || empty($title) || empty($content)) {
                $error = 'Practical, title, and content are required.';
            } else {
                // Check if deadline has passed
                $deadline = $this->deadlineModel->getDeadlineForPractical($practicalId, $studentId);
                if ($deadline && $deadline['status'] === 'expired' && !$deadline['extended']) {
                    $error = 'Submission deadline has passed. Please contact your lecturer for an extension.';
                } else {
                    // Create report
                    $reportData = [
                        'id' => bin2hex(random_bytes(16)),
                        'student_id' => $studentId,
                        'practical_id' => $practicalId,
                        'title' => $title,
                        'content' => $this->formatReportContent([
                            'main_content' => $content,
                            'summary' => $summary,
                            'methodology' => $methodology,
                            'results' => $results,
                            'conclusions' => $conclusions,
                            'references' => $references
                        ]),
                        'status' => 'submitted',
                        'submitted_at' => date('Y-m-d H:i:s')
                    ];
                    
                    if ($this->reportModel->create($reportData)) {
                        logActivity(Auth::id(), 'report_submitted', 'reports');
                        $success = 'Report submitted successfully!';
                        
                        // Refresh available practicals
                        $availablePracticals = $this->getAvailablePracticalsForSubmission($studentId);
                    } else {
                        $error = 'Failed to submit report. Please try again.';
                    }
                }
            }
        }
        
        $availablePracticals = $this->getAvailablePracticalsForSubmission($studentId);
        
        renderView('report-submission/create', [
            'availablePracticals' => $availablePracticals,
            'error' => $error ?? '',
            'success' => $success ?? ''
        ]);
    }
    
    public function edit($reportId = null) {
        Auth::guard('student');
        
        if (!$reportId) {
            redirect('report-submission');
        }
        
        $report = $this->reportModel->getById($reportId);
        
        if (!$report || $report['student_id'] !== Auth::id()) {
            redirect('report-submission');
        }
        
        // Check if can still edit (not graded yet)
        if ($report['status'] === 'graded') {
            redirect('report-submission');
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = sanitize($_POST['title'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $summary = sanitize($_POST['summary'] ?? '');
            $methodology = sanitize($_POST['methodology'] ?? '');
            $results = sanitize($_POST['results'] ?? '');
            $conclusions = sanitize($_POST['conclusions'] ?? '');
            $references = sanitize($_POST['references'] ?? '');
            
            if (empty($title) || empty($content)) {
                $error = 'Title and content are required.';
            } else {
                // Update report
                $updateData = [
                    'title' => $title,
                    'content' => $this->formatReportContent([
                        'main_content' => $content,
                        'summary' => $summary,
                        'methodology' => $methodology,
                        'results' => $results,
                        'conclusions' => $conclusions,
                        'references' => $references
                    ]),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($this->reportModel->update($reportId, $updateData)) {
                    logActivity(Auth::id(), 'report_updated', 'reports');
                    $success = 'Report updated successfully!';
                    
                    // Refresh report data
                    $report = $this->reportModel->getById($reportId);
                } else {
                    $error = 'Failed to update report. Please try again.';
                }
            }
        }
        
        renderView('report-submission/edit', [
            'report' => $report,
            'error' => $error,
            'success' => $success
        ]);
    }
    
    public function view($reportId = null) {
        Auth::guard('student');
        
        if (!$reportId) {
            redirect('report-submission');
        }
        
        $report = $this->reportModel->getById($reportId);
        
        if (!$report || $report['student_id'] !== Auth::id()) {
            redirect('report-submission');
        }
        
        renderView('report-submission/view', [
            'report' => $report
        ]);
    }
    
    private function getAvailablePracticalsForSubmission(string $studentId): array {
        // Get completed practicals for this student
        $completedPracticals = $this->practicalModel->getStudentCompletedPracticals($studentId);
        
        $availablePracticals = [];
        
        foreach ($completedPracticals as $practical) {
            // Check if student has already submitted a report for this practical
            $existingReport = $this->reportModel->getStudentReportForPractical($studentId, $practical['id']);
            
            if (!$existingReport) {
                // Check deadline
                $deadline = $this->deadlineModel->getDeadlineForPractical($practical['id'], $studentId);
                
                $practical['deadline'] = $deadline;
                $practical['can_submit'] = true;
                
                if ($deadline && $deadline['status'] === 'expired' && !$deadline['extended']) {
                    $practical['can_submit'] = false;
                }
                
                $availablePracticals[] = $practical;
            }
        }
        
        return $availablePracticals;
    }
    
    private function formatReportContent(array $data): string {
        $content = "";
        
        if (!empty($data['main_content'])) {
            $content .= "## Report Content\n" . $data['main_content'] . "\n\n";
        }
        
        if (!empty($data['summary'])) {
            $content .= "## Executive Summary\n" . $data['summary'] . "\n\n";
        }
        
        if (!empty($data['methodology'])) {
            $content .= "## Methodology\n" . $data['methodology'] . "\n\n";
        }
        
        if (!empty($data['results'])) {
            $content .= "## Results\n" . $data['results'] . "\n\n";
        }
        
        if (!empty($data['conclusions'])) {
            $content .= "## Conclusions\n" . $data['conclusions'] . "\n\n";
        }
        
        if (!empty($data['references'])) {
            $content .= "## References\n" . $data['references'] . "\n\n";
        }
        
        return trim($content);
    }
}
