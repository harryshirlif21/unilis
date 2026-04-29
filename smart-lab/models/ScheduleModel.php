<?php
require_once __DIR__.'/../config/database.php';

class ScheduleModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function getTodaySchedule(): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.scheduled_date = CURDATE()
             ORDER BY p.start_time ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getWeekSchedule(string $startDate = null): array {
        $startDate = $startDate ?: date('Y-m-d', strtotime('this week monday'));
        $endDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));
        
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.scheduled_date BETWEEN ? AND ?
             ORDER BY p.scheduled_date, p.start_time ASC"
        );
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
    
    public function getMonthSchedule(string $year = null, string $month = null): array {
        $year = $year ?: date('Y');
        $month = $month ?: date('m');
        
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE YEAR(p.scheduled_date) = ? AND MONTH(p.scheduled_date) = ?
             ORDER BY p.scheduled_date, p.start_time ASC"
        );
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll();
    }
    
    public function getAllSchedule(): array {
        $stmt = $this->db->query(
            "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.scheduled_date >= CURDATE()
             ORDER BY p.scheduled_date, p.start_time ASC"
        );
        return $stmt->fetchAll();
    }
    
    public function getScheduleStats(): array {
        $stmt = $this->db->query(
            "SELECT 
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN scheduled_date = CURDATE() THEN 1 END) as today_sessions,
                COUNT(CASE WHEN scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as this_week,
                COUNT(DISTINCT lab_id) as active_labs
             FROM practicals
             WHERE scheduled_date >= CURDATE()"
        );
        return $stmt->fetch();
    }
    
    public function getLabSchedule(string $labId, string $date = null): array {
        $date = $date ?: date('Y-m-d');
        
        $stmt = $this->db->prepare(
            "SELECT p.*, u.full_name as lecturer_name
             FROM practicals p
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.lab_id = ? AND p.scheduled_date = ?
             ORDER BY p.start_time ASC"
        );
        $stmt->execute([$labId, $date]);
        return $stmt->fetchAll();
    }
    
    public function getLecturerSchedule(string $lecturerId, string $date = null): array {
        $date = $date ?: date('Y-m-d');
        
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             WHERE p.lecturer_id = ? AND p.scheduled_date = ?
             ORDER BY p.start_time ASC"
        );
        $stmt->execute([$lecturerId, $date]);
        return $stmt->fetchAll();
    }
    
    public function checkLabAvailability(string $labId, string $date, string $startTime, string $endTime, string $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as conflicts 
                FROM practicals 
                WHERE lab_id = ? AND scheduled_date = ? 
                AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time < ? AND end_time > ?) OR
                    (start_time >= ? AND end_time <= ?)
                )";
        
        $params = [$labId, $date, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['conflicts'] == 0;
    }
    
    public function getLabs(): array {
        $stmt = $this->db->query("SELECT id, name, lab_code FROM labs WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getUpcomingSessions(string $userId = null, string $userRole = null): array {
        $sql = "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
                FROM practicals p
                LEFT JOIN labs l ON p.lab_id = l.id
                LEFT JOIN users u ON p.lecturer_id = u.id
                WHERE p.scheduled_date >= CURDATE() AND p.status = 'published'";
        
        $params = [];
        
        if ($userRole === 'student' && $userId) {
            $sql .= " AND p.lab_id IN (SELECT lab_id FROM users WHERE id = ?)";
            $params[] = $userId;
        } elseif ($userRole === 'lecturer' && $userId) {
            $sql .= " AND p.lecturer_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY p.scheduled_date, p.start_time ASC LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
