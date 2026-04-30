<?php
require_once __DIR__.'/../config/app.php';

class ReportModel {
    private PDO $db;
    
    public function __construct() {
        try {
            $this->db = getDB();
        } catch (Exception $e) {
            error_log("ReportModel::__construct Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function create(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO reports 
             (id, notebook_id, student_id, practical_id, title, 
              file_path, submission_notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', NOW())"
        );
        
        return $stmt->execute([
            $data['id'],
            $data['notebook_id'],
            $data['student_id'],
            $data['practical_id'],
            $data['title'],
            $data['file_path'],
            $data['submission_notes']
        ]);
    }
    
    public function getAll(): array {
        $stmt = $this->db->query(
            "SELECT r.*, s.full_name as student_name, s.reg_number,
                   p.title as practical_title, lec.full_name as lecturer_name
             FROM reports r
             LEFT JOIN notebooks n ON r.notebook_id = n.id
             LEFT JOIN lab_sessions ls ON n.session_id = ls.id
             LEFT JOIN practicals p ON ls.practical_id = p.id
             LEFT JOIN users s ON r.student_id = s.id
             LEFT JOIN users lec ON p.lecturer_id = lec.id
             ORDER BY r.created_at DESC"
        );
        return $stmt->fetchAll();
    }
    
    public function getById(string $reportId): ?array {
        $stmt = $this->db->prepare(
            "SELECT r.*, n.title as notebook_title, p.title as practical_title,
                   s.full_name as student_name, s.reg_number,
                   l.full_name as lecturer_name, l.email as lecturer_email
             FROM reports r
             LEFT JOIN notebooks n ON r.notebook_id = n.id
             LEFT JOIN practicals p ON r.practical_id = p.id
             LEFT JOIN users s ON r.student_id = s.id
             LEFT JOIN users l ON p.lecturer_id = l.id
             WHERE r.id = ? LIMIT 1"
        );
        $stmt->execute([$reportId]);
        return $stmt->fetch() ?: null;
    }
    
    public function getByStudent(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, p.title as practical_title, l.name as lab_name,
                   lec.full_name as lecturer_name
             FROM reports r
             LEFT JOIN practicals p ON r.practical_id = p.id
             LEFT JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users lec ON p.lecturer_id = lec.id
             WHERE r.student_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    public function getByLecturer(string $lecturerId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, s.full_name as student_name, s.reg_number,
                   p.title as practical_title, p.scheduled_date
             FROM reports r
             LEFT JOIN practicals p ON r.practical_id = p.id
             LEFT JOIN users s ON r.student_id = s.id
             WHERE p.lecturer_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([$lecturerId]);
        return $stmt->fetchAll();
    }
    
    public function getByPractical(string $practicalId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, s.full_name as student_name, s.reg_number
             FROM reports r
             LEFT JOIN users s ON r.student_id = s.id
             WHERE r.practical_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([$practicalId]);
        return $stmt->fetchAll();
    }
    
    public function getPendingGrading(string $lecturerId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, s.full_name as student_name, s.reg_number,
                   p.title as practical_title, p.scheduled_date
             FROM reports r
             LEFT JOIN practicals p ON r.practical_id = p.id
             LEFT JOIN users s ON r.student_id = s.id
             WHERE p.lecturer_id = ? AND r.status = 'submitted'
             ORDER BY r.submitted_at ASC"
        );
        $stmt->execute([$lecturerId]);
        return $stmt->fetchAll();
    }
    
    public function submit(string $reportId): bool {
        $stmt = $this->db->prepare(
            "UPDATE reports 
             SET status = 'submitted', submitted_at = NOW() 
             WHERE id = ?"
        );
        return $stmt->execute([$reportId]);
    }
    
    public function grade(string $reportId, array $gradingData): bool {
        $this->db->beginTransaction();
        
        try {
            // Update report with grade
            $stmt = $this->db->prepare(
                "UPDATE reports 
                 SET grade = ?, feedback = ?, graded_by = ?, 
                     graded_at = NOW(), status = 'graded' 
                 WHERE id = ?"
            );
            $stmt->execute([
                $gradingData['grade'],
                $gradingData['feedback'],
                $gradingData['graded_by'],
                $reportId
            ]);
            
            // Create approval record
            $approvalStmt = $this->db->prepare(
                "INSERT INTO approvals 
                 (id, document_type, document_id, reviewer_id, action, comments, reviewed_at)
                 VALUES (?, ?, ?, ?, 'graded', ?, NOW())"
            );
            $approvalStmt->execute([
                bin2hex(random_bytes(16)),
                'report',
                $reportId,
                $gradingData['graded_by'],
                $gradingData['feedback']
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function returnForRevision(string $reportId, string $lecturerId, string $feedback): bool {
        $this->db->beginTransaction();
        
        try {
            // Update report status
            $stmt = $this->db->prepare(
                "UPDATE reports 
                 SET status = 'returned', feedback = ?, graded_by = ?, 
                     graded_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([$feedback, $lecturerId, $reportId]);
            
            // Create approval record
            $approvalStmt = $this->db->prepare(
                "INSERT INTO approvals 
                 (id, document_type, document_id, reviewer_id, action, comments, reviewed_at)
                 VALUES (?, ?, ?, ?, 'revision_requested', ?, NOW())"
            );
            $approvalStmt->execute([
                bin2hex(random_bytes(16)),
                'report',
                $reportId,
                $lecturerId,
                $feedback
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function updateFile(string $reportId, string $filePath): bool {
        $stmt = $this->db->prepare(
            "UPDATE reports 
             SET file_path = ?, updated_at = NOW() 
             WHERE id = ?"
        );
        return $stmt->execute([$filePath, $reportId]);
    }
    
    public function getGradingStats(string $lecturerId): array {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total_reports,
                COUNT(CASE WHEN r.status = 'submitted' THEN 1 END) as pending,
                COUNT(CASE WHEN r.status = 'graded' THEN 1 END) as graded,
                COUNT(CASE WHEN r.status = 'returned' THEN 1 END) as returned,
                AVG(CASE WHEN r.grade IS NOT NULL THEN r.grade END) as average_grade,
                MAX(CASE WHEN r.grade IS NOT NULL THEN r.grade END) as highest_grade,
                MIN(CASE WHEN r.grade IS NOT NULL THEN r.grade END) as lowest_grade
             FROM reports r
             LEFT JOIN practicals p ON r.practical_id = p.id
             WHERE p.lecturer_id = ?"
        );
        $stmt->execute([$lecturerId]);
        return $stmt->fetch();
    }
    
    public function getStudentReportStats(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total_reports,
                COUNT(CASE WHEN status = 'graded' THEN 1 END) as graded_reports,
                AVG(CASE WHEN grade IS NOT NULL THEN grade END) as average_grade,
                MAX(CASE WHEN grade IS NOT NULL THEN grade END) as highest_grade
             FROM reports 
             WHERE student_id = ?"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetch();
    }
    
    public function search(array $filters): array {
        $sql = "SELECT r.*, s.full_name as student_name, s.reg_number,
                       p.title as practical_title, lec.full_name as lecturer_name
                FROM reports r
                LEFT JOIN practicals p ON r.practical_id = p.id
                LEFT JOIN users s ON r.student_id = s.id
                LEFT JOIN users lec ON p.lecturer_id = lec.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['student_id'])) {
            $sql .= " AND r.student_id = ?";
            $params[] = $filters['student_id'];
        }
        
        if (!empty($filters['lecturer_id'])) {
            $sql .= " AND p.lecturer_id = ?";
            $params[] = $filters['lecturer_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND r.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND r.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $limit = intval($filters['limit']);
            if ($limit > 0) {
                $sql .= " LIMIT " . $limit;
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getStudentReports(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, p.title as practical_title, p.description as practical_description,
                   l.name as lab_name, l.lab_code,
                   u.full_name as grader_name, u.email as grader_email,
                   rd.deadline_date, rd.extended, rd.extended_until
             FROM reports r
             JOIN practicals p ON r.practical_id = p.id
             JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON r.graded_by = u.id
             LEFT JOIN report_deadlines rd ON r.practical_id = rd.practical_id AND r.student_id = rd.student_id
             WHERE r.student_id = ?
             ORDER BY r.submitted_at DESC"
        );
        $stmt->execute([$studentId]);
        $reports = $stmt->fetchAll();
        
        foreach ($reports as &$report) {
            // Add deadline status
            if ($report['deadline_date']) {
                $deadlineModel = new DeadlineModel();
                $deadline = [
                    'deadline_date' => $report['deadline_date'],
                    'extended' => $report['extended'],
                    'extended_until' => $report['extended_until']
                ];
                $report['deadline_status'] = $deadlineModel->getDeadlineStatus($deadline);
                $report['days_remaining'] = $deadlineModel->getDaysRemaining($deadline);
            }
        }
        
        return $reports;
    }
    
    public function getStudentReportForPractical(string $studentId, string $practicalId): ?array {
        $stmt = $this->db->prepare(
            "SELECT r.* FROM reports r 
             WHERE r.student_id = ? AND r.practical_id = ?"
        );
        $stmt->execute([$studentId, $practicalId]);
        return $stmt->fetch() ?: null;
    }
    
    public function update(string $reportId, array $data): bool {
        $setClauses = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $setClauses[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $reportId;
        
        $stmt = $this->db->prepare(
            "UPDATE reports SET " . implode(', ', $setClauses) . " WHERE id = ?"
        );
        
        return $stmt->execute($values);
    }
}
