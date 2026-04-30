<?php
require_once __DIR__.'/../config/app.php';

class PracticalRequestModel {
    private PDO $db;
    
    public function __construct() {
        try {
            $this->db = getDB();
        } catch (Exception $e) {
            error_log("PracticalRequestModel::__construct Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function create(array $data): bool {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO practical_requests 
                 (id, student_id, practical_id, reason, preferred_lab, urgency, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            return $stmt->execute([
                $data['id'],
                $data['student_id'],
                $data['practical_id'],
                $data['reason'],
                $data['preferred_lab'],
                $data['urgency'],
                $data['status'],
                $data['created_at']
            ]);
        } catch (Exception $e) {
            error_log("PracticalRequestModel::create Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getById(string $requestId): ?array {
        $stmt = $this->db->prepare(
            "SELECT pr.*, p.title as practical_title, p.description as practical_description,
                   u.full_name as student_name, u.email as student_email,
                   l.name as preferred_lab_name
             FROM practical_requests pr
             JOIN practicals p ON pr.practical_id = p.id
             JOIN users u ON pr.student_id = u.id
             LEFT JOIN labs l ON pr.preferred_lab = l.id
             WHERE pr.id = ?"
        );
        $stmt->execute([$requestId]);
        return $stmt->fetch() ?: null;
    }
    
    public function getStudentRequests(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT pr.*, p.title as practical_title, p.description as practical_description,
                   l.name as preferred_lab_name
             FROM practical_requests pr
             JOIN practicals p ON pr.practical_id = p.id
             LEFT JOIN labs l ON pr.preferred_lab = l.id
             WHERE pr.student_id = ?
             ORDER BY pr.created_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    public function getAllRequests(): array {
        $stmt = $this->db->query(
            "SELECT pr.*, p.title as practical_title, p.description as practical_description,
                   u.full_name as student_name, u.email as student_email, u.reg_number,
                   l.name as preferred_lab_name
             FROM practical_requests pr
             JOIN practicals p ON pr.practical_id = p.id
             JOIN users u ON pr.student_id = u.id
             LEFT JOIN labs l ON pr.preferred_lab = l.id
             ORDER BY pr.created_at DESC"
        );
        return $stmt->fetchAll();
    }
    
    public function updateStatus(string $requestId, string $status): bool {
        $stmt = $this->db->prepare(
            "UPDATE practical_requests 
             SET status = ?, updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$status, $requestId]);
    }
    
    public function getPendingRequests(): array {
        $stmt = $this->db->query(
            "SELECT pr.*, p.title as practical_title, p.description as practical_description,
                   u.full_name as student_name, u.email as student_email, u.reg_number,
                   l.name as preferred_lab_name
             FROM practical_requests pr
             JOIN practicals p ON pr.practical_id = p.id
             JOIN users u ON pr.student_id = u.id
             LEFT JOIN labs l ON pr.preferred_lab = l.id
             WHERE pr.status = 'pending'
             ORDER BY pr.created_at ASC"
        );
        return $stmt->fetchAll();
    }
    
    public function getRequestStats(): array {
        $stmt = $this->db->query(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
             FROM practical_requests"
        );
        return $stmt->fetch();
    }
}
