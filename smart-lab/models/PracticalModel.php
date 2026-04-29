<?php
require_once __DIR__.'/../config/database.php';

class PracticalModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function create(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO practicals 
             (id, title, description, lab_id, lecturer_id, course_code, 
              scheduled_date, start_time, end_time, max_students, 
              required_equipment, required_chemicals, safety_notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        return $stmt->execute([
            $data['id'],
            $data['title'],
            $data['description'],
            $data['lab_id'],
            $data['lecturer_id'],
            $data['course_code'],
            $data['scheduled_date'],
            $data['start_time'],
            $data['end_time'],
            $data['max_students'],
            $data['required_equipment'],
            $data['required_chemicals'],
            $data['safety_notes']
        ]);
    }
    
    public function getAll(?string $lecturerId = null): array {
        $sql = "SELECT p.*, l.name as lab_name, l.lab_code, 
                       u.full_name as lecturer_name, u.email as lecturer_email,
                       COUNT(ls.id) as session_count
                FROM practicals p
                LEFT JOIN labs l ON p.lab_id = l.id
                LEFT JOIN users u ON p.lecturer_id = u.id
                LEFT JOIN lab_sessions ls ON p.id = ls.practical_id
                GROUP BY p.id
                ORDER BY p.scheduled_date DESC, p.start_time DESC";
        
        if ($lecturerId) {
            $sql = "SELECT p.*, l.name as lab_name, l.lab_code, 
                           u.full_name as lecturer_name, u.email as lecturer_email,
                           COUNT(ls.id) as session_count
                    FROM practicals p
                    LEFT JOIN labs l ON p.lab_id = l.id
                    LEFT JOIN users u ON p.lecturer_id = u.id
                    LEFT JOIN lab_sessions ls ON p.id = ls.practical_id
                    WHERE p.lecturer_id = ?
                    GROUP BY p.id
                    ORDER BY p.scheduled_date DESC, p.start_time DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$lecturerId]);
        } else {
            $stmt = $this->db->query($sql);
        }
        
        return $stmt->fetchAll();
    }
    
    public function getById(string $practicalId): ?array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code, l.max_capacity as lab_capacity,
                   u.full_name as lecturer_name, u.email as lecturer_email
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$practicalId]);
        return $stmt->fetch() ?: null;
    }
    
    public function update(string $practicalId, array $data): bool {
        $stmt = $this->db->prepare(
            "UPDATE practicals 
             SET title = ?, description = ?, lab_id = ?, course_code = ?,
                 scheduled_date = ?, start_time = ?, end_time = ?, 
                 max_students = ?, required_equipment = ?, 
                 required_chemicals = ?, safety_notes = ?, status = ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        
        return $stmt->execute([
            $data['title'],
            $data['description'],
            $data['lab_id'],
            $data['course_code'],
            $data['scheduled_date'],
            $data['start_time'],
            $data['end_time'],
            $data['max_students'],
            $data['required_equipment'],
            $data['required_chemicals'],
            $data['safety_notes'],
            $data['status'],
            $practicalId
        ]);
    }
    
    public function delete(string $practicalId): bool {
        $this->db->beginTransaction();
        
        try {
            // Delete related sessions first
            $sessionStmt = $this->db->prepare(
                "DELETE FROM lab_sessions WHERE practical_id = ?"
            );
            $sessionStmt->execute([$practicalId]);
            
            // Delete practical
            $practicalStmt = $this->db->prepare(
                "DELETE FROM practicals WHERE id = ?"
            );
            $practicalStmt->execute([$practicalId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getLabs(): array {
        $stmt = $this->db->query(
            "SELECT id, name, lab_code, type, max_capacity, current_count 
             FROM labs WHERE is_active = 1 
             ORDER BY name"
        );
        return $stmt->fetchAll();
    }
    
    public function getLecturers(): array {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, email, department 
             FROM users 
             WHERE role IN ('lecturer', 'admin') AND is_active = 1 
             ORDER BY full_name"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function checkLabAvailability(string $labId, string $date, string $startTime, string $endTime, ?string $excludePractical = null): bool {
        $sql = "SELECT COUNT(*) as conflicts 
                FROM practicals p 
                WHERE p.lab_id = ? AND p.scheduled_date = ? 
                AND p.status IN ('published', 'ongoing')
                AND ((p.start_time <= ? AND p.end_time > ?) 
                     OR (p.start_time < ? AND p.end_time >= ?))";
        
        $params = [$labId, $date, $startTime, $startTime, $endTime, $endTime];
        
        if ($excludePractical) {
            $sql .= " AND p.id != ?";
            $params[] = $excludePractical;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['conflicts'] == 0;
    }
    
    public function getSchedule(string $labId, string $date): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, u.full_name as lecturer_name
             FROM practicals p
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.lab_id = ? AND p.scheduled_date = ? 
             AND p.status IN ('published', 'ongoing')
             ORDER BY p.start_time"
        );
        $stmt->execute([$labId, $date]);
        return $stmt->fetchAll();
    }
    
    public function getUpcomingPracticals(?string $studentId = null): array {
        $sql = "SELECT p.*, l.name as lab_name, l.lab_code,
                       u.full_name as lecturer_name
                FROM practicals p
                LEFT JOIN labs l ON p.lab_id = l.id
                LEFT JOIN users u ON p.lecturer_id = u.id
                WHERE p.scheduled_date >= CURDATE() 
                AND p.status = 'published'
                ORDER BY p.scheduled_date ASC, p.start_time ASC";
        
        if ($studentId) {
            // Filter by student's lab access
            $sql = "SELECT p.*, l.name as lab_name, l.lab_code,
                           u.full_name as lecturer_name
                    FROM practicals p
                    LEFT JOIN labs l ON p.lab_id = l.id
                    LEFT JOIN users u ON p.lecturer_id = u.id
                    LEFT JOIN users s ON s.lab_id = l.id
                    WHERE p.scheduled_date >= CURDATE() 
                    AND p.status = 'published'
                    AND s.id = ?
                    ORDER BY p.scheduled_date ASC, p.start_time ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId]);
        } else {
            $stmt = $this->db->query($sql);
        }
        
        return $stmt->fetchAll();
    }
    
    public function getPracticalStats(): array {
        $stmt = $this->db->query(
            "SELECT 
                COUNT(*) as total_practicals,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published,
                COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN scheduled_date >= CURDATE() THEN 1 END) as upcoming
             FROM practicals"
        );
        return $stmt->fetch();
    }
    
    public function getLabUtilization(string $labId, string $startDate, string $endDate): array {
        $stmt = $this->db->prepare(
            "SELECT p.scheduled_date, 
                   SUM(TIMESTAMPDIFF(MINUTE, p.start_time, p.end_time)) as total_minutes
             FROM practicals p
             WHERE p.lab_id = ? 
             AND p.scheduled_date BETWEEN ? AND ?
             AND p.status IN ('published', 'ongoing', 'completed')
             GROUP BY p.scheduled_date
             ORDER BY p.scheduled_date"
        );
        $stmt->execute([$labId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    public function getEnrolledStudents(string $practicalId): array {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.full_name, u.email, u.reg_number
             FROM users u
             JOIN student_practicals sp ON u.id = sp.student_id
             WHERE sp.practical_id = ? AND u.is_active = 1
             ORDER BY u.full_name"
        );
        $stmt->execute([$practicalId]);
        return $stmt->fetchAll();
    }
    
    public function getLabSessions(string $practicalId): array {
        $stmt = $this->db->prepare(
            "SELECT ls.*, 
                   COUNT(lss.student_id) as enrolled_count,
                   ls.status as session_status
             FROM lab_sessions ls
             LEFT JOIN lab_session_students lss ON ls.id = lss.session_id
             WHERE ls.practical_id = ?
             GROUP BY ls.id
             ORDER BY ls.started_at DESC"
        );
        $stmt->execute([$practicalId]);
        return $stmt->fetchAll();
    }
    
    public function isStudentEnrolled(string $studentId, string $practicalId): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as enrolled 
             FROM student_practicals 
             WHERE student_id = ? AND practical_id = ?"
        );
        $stmt->execute([$studentId, $practicalId]);
        $result = $stmt->fetch();
        return $result['enrolled'] > 0;
    }
    
    public function getAvailablePracticals(): array {
        $stmt = $this->db->query(
            "SELECT p.*, l.name as lab_name, l.lab_code,
                   u.full_name as lecturer_name
             FROM practicals p
             JOIN labs l ON p.lab_id = l.id
             JOIN users u ON p.lecturer_id = u.id
             WHERE p.status IN ('completed', 'published')
             ORDER BY p.title"
        );
        return $stmt->fetchAll();
    }
    
    public function getStudentCompletedPracticals(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code,
                   ls.started_at as session_date, ls.status as session_status
             FROM practicals p
             JOIN lab_sessions ls ON p.id = ls.practical_id
             JOIN labs l ON p.lab_id = l.id
             JOIN student_practicals sp ON p.id = sp.practical_id
             WHERE sp.student_id = ? AND ls.status = 'closed'
             ORDER BY ls.started_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    public function createDeadlineForCompletedPractical(string $practicalId, string $studentId): void {
        // Check if deadline already exists
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM report_deadlines 
             WHERE practical_id = ? AND student_id = ?"
        );
        $stmt->execute([$practicalId, $studentId]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            return; // Deadline already exists
        }
        
        // Get practical date
        $stmt = $this->db->prepare(
            "SELECT ls.started_at FROM lab_sessions ls 
             WHERE ls.practical_id = ? AND ls.status = 'closed'
             ORDER BY ls.started_at DESC LIMIT 1"
        );
        $stmt->execute([$practicalId]);
        $session = $stmt->fetch();
        
        if ($session) {
            $deadlineModel = new DeadlineModel();
            $deadlineModel->createDeadlineForPractical($practicalId, $studentId);
        }
    }
}
