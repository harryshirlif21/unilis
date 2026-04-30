<?php
require_once __DIR__.'/../config/app.php';

class ScheduleModel {
    private PDO $db;
    
    public function __construct() {
        try {
            $this->db = getDB();
        } catch (Exception $e) {
            error_log("ScheduleModel::__construct Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getTodaySchedule(): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
                 FROM practicals p
                 LEFT JOIN labs l ON p.lab_id = l.id
                 LEFT JOIN users u ON p.lecturer_id = u.id
                 WHERE p.scheduled_date = CURDATE()
                 ORDER BY p.created_at ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getTodaySchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getWeekSchedule(string $startDate = null): array {
        try {
            $startDate = $startDate ?: date('Y-m-d', strtotime('this week monday'));
            $endDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));
            
            $stmt = $this->db->prepare(
                "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
                 FROM practicals p
                 LEFT JOIN labs l ON p.lab_id = l.id
                 LEFT JOIN users u ON p.lecturer_id = u.id
                 WHERE p.scheduled_date BETWEEN ? AND ?
                 ORDER BY p.scheduled_date ASC"
            );
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getWeekSchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getMonthSchedule(string $year = null, string $month = null): array {
        try {
            $year = $year ?: date('Y');
            $month = $month ?: date('m');
            
            $stmt = $this->db->prepare(
                "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
                 FROM practicals p
                 LEFT JOIN labs l ON p.lab_id = l.id
                 LEFT JOIN users u ON p.lecturer_id = u.id
                 WHERE YEAR(p.scheduled_date) = ? AND MONTH(p.scheduled_date) = ?
                 ORDER BY p.scheduled_date ASC"
            );
            $stmt->execute([$year, $month]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getMonthSchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAllSchedule(): array {
        try {
            $stmt = $this->db->query(
                "SELECT p.*, l.name as lab_name, l.lab_code, u.full_name as lecturer_name
                 FROM practicals p
                 LEFT JOIN labs l ON p.lab_id = l.id
                 LEFT JOIN users u ON p.lecturer_id = u.id
                 WHERE p.scheduled_date >= CURDATE()
                 ORDER BY p.scheduled_date ASC"
            );
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getAllSchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getScheduleStats(): array {
        try {
            $stmt = $this->db->query(
                "SELECT 
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN scheduled_date = CURDATE() THEN 1 END) as today_sessions,
                    COUNT(CASE WHEN scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as this_week,
                    COUNT(DISTINCT lab_id) as active_labs
                 FROM practicals
                 WHERE scheduled_date >= CURDATE()"
            );
            return $stmt->fetch() ?: [];
        } catch (Exception $e) {
            error_log("ScheduleModel::getScheduleStats Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLabSchedule(string $labId, string $date = null): array {
        try {
            $date = $date ?: date('Y-m-d');
            
            $stmt = $this->db->prepare(
                "SELECT p.*, u.full_name as lecturer_name
                 FROM practicals p
                 LEFT JOIN users u ON p.lecturer_id = u.id
                 WHERE p.lab_id = ? AND p.scheduled_date = ?
                 ORDER BY p.scheduled_date ASC"
            );
            $stmt->execute([$labId, $date]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getLabSchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLecturerSchedule(string $lecturerId, string $date = null): array {
        try {
            $date = $date ?: date('Y-m-d');
            
            $stmt = $this->db->prepare(
                "SELECT p.*, l.name as lab_name, l.lab_code
                 FROM practicals p
                 LEFT JOIN labs l ON p.lab_id = l.id
                 WHERE p.lecturer_id = ? AND p.scheduled_date = ?
                 ORDER BY p.scheduled_date ASC"
            );
            $stmt->execute([$lecturerId, $date]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getLecturerSchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function checkLabAvailability(string $labId, string $date, string $excludeId = null): bool {
        try {
            $sql = "SELECT COUNT(*) as conflicts 
                    FROM practicals 
                    WHERE lab_id = ? AND scheduled_date = ? AND status IN ('published', 'completed')";
            
            $params = [$labId, $date];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['conflicts'] == 0;
        } catch (Exception $e) {
            error_log("ScheduleModel::checkLabAvailability Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getLabs(): array {
        try {
            $stmt = $this->db->query("SELECT id, name, lab_code FROM labs WHERE is_active = 1 ORDER BY name");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getLabs Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUpcomingSessions(string $userId = null, string $userRole = null): array {
        try {
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
            
            $sql .= " ORDER BY p.scheduled_date ASC LIMIT 10";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("ScheduleModel::getUpcomingSessions Error: " . $e->getMessage());
            return [];
        }
    }
}
