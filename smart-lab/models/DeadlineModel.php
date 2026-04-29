<?php
class DeadlineModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function createDeadline(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO report_deadlines 
             (id, practical_id, student_id, deadline_date, extended, extended_until, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        return $stmt->execute([
            $data['id'],
            $data['practical_id'],
            $data['student_id'],
            $data['deadline_date'],
            $data['extended'] ?? false,
            $data['extended_until'] ?? null,
            $data['created_by']
        ]);
    }
    
    public function getDeadlineForPractical(string $practicalId, string $studentId): ?array {
        $stmt = $this->db->prepare(
            "SELECT rd.*, p.title as practical_title, p.scheduled_date as practical_date
             FROM report_deadlines rd
             JOIN practicals p ON rd.practical_id = p.id
             WHERE rd.practical_id = ? AND rd.student_id = ?"
        );
        $stmt->execute([$practicalId, $studentId]);
        $deadline = $stmt->fetch();
        
        if ($deadline) {
            // Determine status
            $deadline['status'] = $this->getDeadlineStatus($deadline);
            $deadline['days_remaining'] = $this->getDaysRemaining($deadline);
            $deadline['is_expired'] = $deadline['status'] === 'expired';
        }
        
        return $deadline ?: null;
    }
    
    public function createDeadlineForPractical(string $practicalId, string $studentId): string {
        // Get practical date to calculate deadline (2 weeks after practical)
        $stmt = $this->db->prepare(
            "SELECT scheduled_date FROM practicals WHERE id = ?"
        );
        $stmt->execute([$practicalId]);
        $practical = $stmt->fetch();
        
        if (!$practical) {
            return null;
        }
        
        $practicalDate = $practical['scheduled_date'];
        $deadlineDate = date('Y-m-d H:i:s', strtotime($practicalDate . ' + 2 weeks'));
        
        // Check if deadline already exists
        $existing = $this->getDeadlineForPractical($practicalId, $studentId);
        if ($existing) {
            return $existing['id'];
        }
        
        $deadlineData = [
            'id' => bin2hex(random_bytes(16)),
            'practical_id' => $practicalId,
            'student_id' => $studentId,
            'deadline_date' => $deadlineDate,
            'extended' => false,
            'created_by' => 'system'
        ];
        
        if ($this->createDeadline($deadlineData)) {
            return $deadlineData['id'];
        }
        
        return null;
    }
    
    public function extendDeadline(string $deadlineId, string $extendedUntil, string $lecturerId): bool {
        $stmt = $this->db->prepare(
            "UPDATE report_deadlines 
             SET extended = true, extended_until = ?, updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$extendedUntil, $deadlineId]);
    }
    
    public function getStudentDeadlines(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT rd.*, p.title as practical_title, p.scheduled_date as practical_date
             FROM report_deadlines rd
             JOIN practicals p ON rd.practical_id = p.id
             WHERE rd.student_id = ?
             ORDER BY rd.deadline_date ASC"
        );
        $stmt->execute([$studentId]);
        $deadlines = $stmt->fetchAll();
        
        foreach ($deadlines as &$deadline) {
            $deadline['status'] = $this->getDeadlineStatus($deadline);
            $deadline['days_remaining'] = $this->getDaysRemaining($deadline);
            $deadline['is_expired'] = $deadline['status'] === 'expired';
        }
        
        return $deadlines;
    }
    
    public function getLecturerDeadlines(string $lecturerId): array {
        $stmt = $this->db->prepare(
            "SELECT rd.*, p.title as practical_title, p.scheduled_date as practical_date,
                   u.full_name as student_name, u.reg_number
             FROM report_deadlines rd
             JOIN practicals p ON rd.practical_id = p.id
             JOIN users u ON rd.student_id = u.id
             WHERE p.lecturer_id = ?
             ORDER BY rd.deadline_date ASC"
        );
        $stmt->execute([$lecturerId]);
        $deadlines = $stmt->fetchAll();
        
        foreach ($deadlines as &$deadline) {
            $deadline['status'] = $this->getDeadlineStatus($deadline);
            $deadline['days_remaining'] = $this->getDaysRemaining($deadline);
            $deadline['is_expired'] = $deadline['status'] === 'expired';
        }
        
        return $deadlines;
    }
    
    private function getDeadlineStatus(array $deadline): string {
        $now = new DateTime();
        
        if ($deadline['extended'] && $deadline['extended_until']) {
            $deadlineDate = new DateTime($deadline['extended_until']);
        } else {
            $deadlineDate = new DateTime($deadline['deadline_date']);
        }
        
        if ($now > $deadlineDate) {
            return 'expired';
        } elseif ($now->diff($deadlineDate)->days <= 3) {
            return 'urgent';
        } elseif ($now->diff($deadlineDate)->days <= 7) {
            return 'approaching';
        } else {
            return 'normal';
        }
    }
    
    private function getDaysRemaining(array $deadline): int {
        $now = new DateTime();
        
        if ($deadline['extended'] && $deadline['extended_until']) {
            $deadlineDate = new DateTime($deadline['extended_until']);
        } else {
            $deadlineDate = new DateTime($deadline['deadline_date']);
        }
        
        if ($now > $deadlineDate) {
            return -1 * $now->diff($deadlineDate)->days;
        }
        
        return $now->diff($deadlineDate)->days;
    }
}
