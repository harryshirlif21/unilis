<?php
namespace SmartLab\Models;

use PDO;

class DatasheetModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function create(array $data): string {
        $id = $data['id'] ?? bin2hex(random_bytes(18));

        $stmt = $this->db->prepare(
            "INSERT INTO datasheets 
             (id, student_id, practical_id, report_id, pdf_filename, pdf_path, 
              signature_hash, qr_code_data, qr_code_path, authentication_method, 
              approval_status, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $id,
            $data['student_id'],
            $data['practical_id'],
            $data['report_id'] ?? null,
            $data['pdf_filename'],
            $data['pdf_path'],
            $data['signature_hash'],
            $data['qr_code_data'],
            $data['qr_code_path'] ?? null,
            $data['authentication_method'] ?? 'password',
            $data['approval_status'] ?? 'pending',
            $data['status'] ?? 'generated'
        ]);

        return $id;
    }

    public function getByStudentAndPractical(string $studentId, string $practicalId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM datasheets 
             WHERE student_id = ? AND practical_id = ?
             LIMIT 1"
        );
        $stmt->execute([$studentId, $practicalId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getById(string $datasheetId): ?array {
        $stmt = $this->db->prepare(
            "SELECT d.*, s.full_name as student_name, s.reg_number,
                    p.title as practical_title, p.description
             FROM datasheets d
             LEFT JOIN users s ON d.student_id = s.id
             LEFT JOIN practicals p ON d.practical_id = p.id
             WHERE d.id = ?
             LIMIT 1"
        );
        $stmt->execute([$datasheetId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByStudent(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT d.*, p.title as practical_title, p.scheduled_date
             FROM datasheets d
             LEFT JOIN practicals p ON d.practical_id = p.id
             WHERE d.student_id = ?
             ORDER BY d.created_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(string $datasheetId, string $status): bool {
        $stmt = $this->db->prepare(
            "UPDATE datasheets SET status = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$status, $datasheetId]);
    }

    public function updateApprovalStatus(
        string $datasheetId,
        string $status,
        ?string $approvedBy = null
    ): bool {
        $stmt = $this->db->prepare(
            "UPDATE datasheets 
             SET approval_status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$status, $approvedBy, $datasheetId]);
    }

    public function verify(string $datasheetId, string $signatureHash): bool {
        $stmt = $this->db->prepare(
            "SELECT signature_hash FROM datasheets WHERE id = ?"
        );
        $stmt->execute([$datasheetId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        return hash_equals($result['signature_hash'], $signatureHash);
    }

    public function addReadings(string $datasheetId, array $readings): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO datasheet_readings 
             (datasheet_id, trial_number, measurement, units, observation)
             VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($readings as $reading) {
            $stmt->execute([
                $datasheetId,
                $reading['trial'] ?? 0,
                $reading['measurement'] ?? null,
                $reading['units'] ?? null,
                $reading['observation'] ?? null
            ]);
        }

        return true;
    }

    public function getReadings(string $datasheetId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM datasheet_readings WHERE datasheet_id = ? ORDER BY trial_number"
        );
        $stmt->execute([$datasheetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingDatasheets(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT d.*, p.title as practical_title, p.scheduled_date
             FROM datasheets d
             LEFT JOIN practicals p ON d.practical_id = p.id
             WHERE d.student_id = ? AND d.approval_status = 'pending'
             ORDER BY d.created_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getApprovedDatasheets(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT d.*, p.title as practical_title, p.scheduled_date,
                    u.full_name as approved_by_name
             FROM datasheets d
             LEFT JOIN practicals p ON d.practical_id = p.id
             LEFT JOIN users u ON d.approved_by = u.id
             WHERE d.student_id = ? AND d.approval_status = 'approved'
             ORDER BY d.approved_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete(string $datasheetId): bool {
        $stmt = $this->db->prepare("DELETE FROM datasheets WHERE id = ?");
        return $stmt->execute([$datasheetId]);
    }

    public function search(array $filters): array {
        $sql = "SELECT d.*, p.title as practical_title, s.full_name as student_name
                FROM datasheets d
                LEFT JOIN practicals p ON d.practical_id = p.id
                LEFT JOIN users s ON d.student_id = s.id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['student_id'])) {
            $sql .= " AND d.student_id = ?";
            $params[] = $filters['student_id'];
        }

        if (!empty($filters['practical_id'])) {
            $sql .= " AND d.practical_id = ?";
            $params[] = $filters['practical_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['approval_status'])) {
            $sql .= " AND d.approval_status = ?";
            $params[] = $filters['approval_status'];
        }

        $sql .= " ORDER BY d.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
