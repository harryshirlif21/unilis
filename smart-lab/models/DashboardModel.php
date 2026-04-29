<?php
require_once __DIR__.'/../config/app.php';

class DashboardModel {
    private PDO $db;
    
    public function __construct() { 
        $this->db = getDB(); 
    }

    public function getStats(): array {
        $stats = [];
        $stats['labs']              = $this->db->query("SELECT COUNT(*) FROM labs WHERE is_active=1")->fetchColumn();
        $stats['students']          = $this->db->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn();
        $stats['lecturers']         = $this->db->query("SELECT COUNT(*) FROM users WHERE role='lecturer' AND is_active=1")->fetchColumn();
        $stats['technicians']       = $this->db->query("SELECT COUNT(*) FROM users WHERE role='technician' AND is_active=1")->fetchColumn();
        $stats['practicals']        = $this->db->query("SELECT COUNT(*) FROM practicals WHERE status IN ('published','completed')")->fetchColumn();
        $stats['assets']            = $this->db->query("SELECT COUNT(*) FROM assets WHERE status='available'")->fetchColumn();
        $stats['sessions']          = $this->db->query("SELECT COUNT(*) FROM lab_sessions WHERE status='open'")->fetchColumn();
        $stats['blocks']            = $this->db->query("SELECT COUNT(*) FROM blockchain_blocks")->fetchColumn();
        $stats['notebooks']         = $this->db->query("SELECT COUNT(*) FROM notebooks")->fetchColumn();
        $stats['reports']           = $this->db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        $stats['today_logins']      = $this->db->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE '%login%' AND DATE(created_at) = CURDATE()")->fetchColumn();
        $stats['pending_reports']   = $this->db->query("SELECT COUNT(*) FROM reports WHERE status = 'submitted'")->fetchColumn();
        $stats['pending_notebooks'] = $this->db->query("SELECT COUNT(*) FROM notebooks WHERE status = 'submitted'")->fetchColumn();
        return $stats;
    }

    public function getLabOccupancy(): array {
        return $this->db->query(
            "SELECT name, lab_code, type, current_count, max_capacity, 
                    ROUND((current_count / NULLIF(max_capacity,0)) * 100) as occupancy_percentage
             FROM labs WHERE is_active=1 ORDER BY name"
        )->fetchAll();
    }

    public function getTodaySchedule(): array {
        return $this->db->query(
            "SELECT p.id, p.title, p.status, p.scheduled_date,
                    l.name as lab_name, l.lab_code, u.full_name as lecturer_name
             FROM practicals p 
             JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.scheduled_date = CURDATE()
             ORDER BY p.created_at ASC LIMIT 8"
        )->fetchAll();
    }

    public function getRecentActivity(): array {
        return $this->db->query(
            "SELECT al.action, al.module, al.created_at, u.full_name, u.role
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC LIMIT 10"
        )->fetchAll();
    }

    public function getRecentBlocks(): array {
        return $this->db->query(
            "SELECT block_index, block_data, hash, timestamp
             FROM blockchain_blocks ORDER BY block_index DESC LIMIT 5"
        )->fetchAll();
    }
    
    public function getWeeklyActivity(): array {
        return $this->db->query(
            "SELECT DATE(created_at) as date, COUNT(*) as activity_count
             FROM audit_logs 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        )->fetchAll();
    }
    
    public function getLabUtilizationData(): array {
        return $this->db->query(
            "SELECT l.name, l.type, 
                    COUNT(CASE WHEN ls.status = 'open' THEN 1 END) as active_sessions,
                    COUNT(CASE WHEN p.status = 'published' THEN 1 END) as scheduled_sessions
             FROM labs l
             LEFT JOIN lab_sessions ls ON l.id = ls.lab_id
             LEFT JOIN practicals p ON l.id = p.lab_id 
             AND p.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             WHERE l.is_active = 1
             GROUP BY l.id, l.name, l.type
             ORDER BY l.name"
        )->fetchAll();
    }
    
    public function getAssetStatusBreakdown(): array {
        return $this->db->query(
            "SELECT status, COUNT(*) as count FROM assets GROUP BY status ORDER BY count DESC"
        )->fetchAll();
    }
    
    public function getTopUsers(): array {
        return $this->db->query(
            "SELECT u.full_name, u.role, COUNT(al.id) as activity_count
             FROM users u
             LEFT JOIN audit_logs al ON u.id = al.user_id 
             AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             WHERE u.is_active = 1
             GROUP BY u.id, u.full_name, u.role
             ORDER BY activity_count DESC LIMIT 10"
        )->fetchAll();
    }
    
    public function getPracticalCompletionRates(): array {
        return $this->db->query(
            "SELECT p.title, p.scheduled_date, p.status
             FROM practicals p
             WHERE p.scheduled_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY p.scheduled_date DESC LIMIT 10"
        )->fetchAll();
    }
    
    public function getSystemHealth(): array {
        $health = [];
        try {
            $this->db->query("SELECT 1");
            $health['database'] = 'healthy';
        } catch (Exception $e) {
            $health['database'] = 'error';
        }
        $blockCount     = $this->db->query("SELECT COUNT(*) FROM blockchain_blocks")->fetchColumn();
        $health['blockchain'] = $blockCount > 0 ? 'healthy' : 'warning';
        $activeSessions = $this->db->query("SELECT COUNT(*) FROM lab_sessions WHERE status='open'")->fetchColumn();
        $health['sessions'] = $activeSessions > 0 ? 'active' : 'idle';
        $recentActivity = $this->db->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
        $health['activity'] = $recentActivity > 0 ? 'active' : 'low';
        return $health;
    }
    
    public function getUserDashboard(string $userId, string $userRole): array {
        $data = [];
        if ($userRole === 'student') {
            $data['upcoming_practicals'] = $this->getStudentUpcomingPracticals($userId);
            $data['my_reports']          = $this->getStudentReports($userId);
            $data['my_notebooks']        = $this->getStudentNotebooks($userId);
        } elseif ($userRole === 'lecturer') {
            $data['my_practicals']   = $this->getLecturerPracticals($userId);
            $data['pending_grading'] = $this->getPendingGradingCount($userId);
        } elseif ($userRole === 'technician') {
            $data['lab_assets']        = $this->getLabAssets($userId);
            $data['pending_approvals'] = $this->getPendingApprovalsCount($userId);
        }
        return $data;
    }
    
    private function getStudentUpcomingPracticals(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             WHERE p.scheduled_date >= CURDATE() AND p.status = 'published'
             ORDER BY p.scheduled_date ASC LIMIT 5"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function getStudentReports(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, p.title as practical_title
             FROM reports r
             LEFT JOIN practicals p ON r.practical_id = p.id
             WHERE r.student_id = ?
             ORDER BY r.created_at DESC LIMIT 5"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    private function getStudentNotebooks(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT n.*, p.title as practical_title
             FROM notebooks n
             LEFT JOIN lab_sessions ls ON n.session_id = ls.id
             LEFT JOIN practicals p ON ls.practical_id = p.id
             WHERE n.student_id = ?
             ORDER BY n.created_at DESC LIMIT 5"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    private function getLecturerPracticals(string $lecturerId): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             WHERE p.lecturer_id = ?
             ORDER BY p.scheduled_date DESC LIMIT 5"
        );
        $stmt->execute([$lecturerId]);
        return $stmt->fetchAll();
    }
    
    private function getPendingGradingCount(string $lecturerId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM reports r
             LEFT JOIN practicals p ON r.practical_id = p.id
             WHERE p.lecturer_id = ? AND r.status = 'submitted'"
        );
        $stmt->execute([$lecturerId]);
        return (int)$stmt->fetchColumn();
    }
    
    private function getLabAssets(string $technicianId): array {
        return $this->db->query(
            "SELECT a.*, l.name as lab_name FROM assets a
             LEFT JOIN labs l ON a.lab_id = l.id
             ORDER BY a.name LIMIT 10"
        )->fetchAll();
    }
    
    private function getPendingApprovalsCount(string $technicianId): int {
        return (int)$this->db->query(
            "SELECT COUNT(*) FROM notebooks WHERE status = 'submitted'"
        )->fetchColumn();
    }
}
