<?php
require_once __DIR__.'/../config/app.php';

class NotebookModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function create(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO notebooks 
             (id, session_id, student_id, group_id, title, content, version, status, created_by, creator_role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, 'draft', ?, ?, NOW())"
        );
        
        return $stmt->execute([
            $data['id'],
            $data['session_id'],
            $data['student_id'],
            $data['group_id'] ?? null,
            $data['title'],
            $data['content'] ?? '',
            $data['created_by'] ?? null,
            $data['creator_role'] ?? 'student'
        ]);
    }
    
    public function getUserNotebooks(string $userId): array {
        $stmt = $this->db->prepare(
            "SELECT n.*, 
                   p.title as practical_title,
                   p.description as practical_description,
                   l.name as lab_name,
                   l.lab_code,
                   ls.started_at as session_date,
                   u.full_name as creator_name,
                   u.role as creator_role
             FROM notebooks n
             JOIN lab_sessions ls ON n.session_id = ls.id
             JOIN practicals p ON ls.practical_id = p.id
             JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON n.created_by = u.id
             WHERE n.student_id = ? OR n.created_by = ?
             ORDER BY n.created_at DESC"
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }
    
    public function update(string $notebookId, string $title, string $content): bool {
        $stmt = $this->db->prepare(
            "UPDATE notebooks 
             SET title = ?, content = ?, updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$title, $content, $notebookId]);
    }
    
    public function updateStatus(string $notebookId, string $status): bool {
        $stmt = $this->db->prepare(
            "UPDATE notebooks 
             SET status = ?, updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$status, $notebookId]);
    }
    
    public function getById(string $notebookId): ?array {
        $stmt = $this->db->prepare(
            "SELECT n.*, s.full_name as student_name, s.reg_number,
                   ls.practical_id, p.title as practical_title,
                   l.name as lab_name
             FROM notebooks n
             JOIN users s ON n.student_id = s.id
             JOIN lab_sessions ls ON n.session_id = ls.id
             JOIN practicals p ON ls.practical_id = p.id
             JOIN labs l ON ls.lab_id = l.id
             WHERE n.id = ? LIMIT 1"
        );
        $stmt->execute([$notebookId]);
        return $stmt->fetch() ?: null;
    }
    
    public function getByStudent(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT n.*, p.title as practical_title, l.name as lab_name,
                   ls.started_at as session_date
             FROM notebooks n
             JOIN lab_sessions ls ON n.session_id = ls.id
             JOIN practicals p ON ls.practical_id = p.id
             JOIN labs l ON ls.lab_id = l.id
             WHERE n.student_id = ?
             ORDER BY n.created_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    public function getBySession(string $sessionId): array {
        $stmt = $this->db->prepare(
            "SELECT n.*, s.full_name as student_name, s.reg_number
             FROM notebooks n
             JOIN users s ON n.student_id = s.id
             WHERE n.session_id = ?
             ORDER BY s.full_name"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll();
    }
    
    public function getAll(): array {
        $stmt = $this->db->query(
            "SELECT n.*, s.full_name as student_name, s.reg_number,
                   p.title as practical_title, l.name as lab_name, l.lab_code,
                   ls.started_at as session_date
             FROM notebooks n
             JOIN users s ON n.student_id = s.id
             JOIN lab_sessions ls ON n.session_id = ls.id
             JOIN practicals p ON ls.practical_id = p.id
             JOIN labs l ON ls.lab_id = l.id
             ORDER BY n.created_at DESC"
        );
        return $stmt->fetchAll();
    }
    
    public function updateContent(string $notebookId, string $content, bool $createVersion = true): bool {
        $this->db->beginTransaction();
        
        try {
            // Get current notebook
            $notebook = $this->getById($notebookId);
            if (!$notebook) {
                throw new Exception('Notebook not found');
            }
            
            // Create version if content changed
            if ($createVersion && $content !== $notebook['content']) {
                $newVersion = $notebook['version'] + 1;
                
                // Save version
                $versionStmt = $this->db->prepare(
                    "INSERT INTO notebook_versions 
                     (id, notebook_id, version, content, saved_at)
                     VALUES (?, ?, ?, ?, NOW())"
                );
                $versionStmt->execute([
                    bin2hex(random_bytes(16)),
                    $notebookId,
                    $newVersion,
                    $content
                ]);
                
                // Update notebook
                $updateStmt = $this->db->prepare(
                    "UPDATE notebooks 
                     SET content = ?, version = ?, updated_at = NOW() 
                     WHERE id = ?"
                );
                $updateStmt->execute([$content, $newVersion, $notebookId]);
            } else {
                // Just update content without version
                $updateStmt = $this->db->prepare(
                    "UPDATE notebooks 
                     SET content = ?, updated_at = NOW() 
                     WHERE id = ?"
                );
                $updateStmt->execute([$content, $notebookId]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getVersions(string $notebookId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM notebook_versions 
             WHERE notebook_id = ? 
             ORDER BY version DESC"
        );
        $stmt->execute([$notebookId]);
        return $stmt->fetchAll();
    }
    
    public function getVersion(string $notebookId, int $version): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM notebook_versions 
             WHERE notebook_id = ? AND version = ? LIMIT 1"
        );
        $stmt->execute([$notebookId, $version]);
        return $stmt->fetch() ?: null;
    }
    
    public function submitForApproval(string $notebookId): bool {
        $stmt = $this->db->prepare(
            "UPDATE notebooks 
             SET status = 'submitted', updated_at = NOW() 
             WHERE id = ?"
        );
        return $stmt->execute([$notebookId]);
    }
    
    public function approve(string $notebookId, string $technicianId, string $signature): bool {
        $this->db->beginTransaction();
        
        try {
            // Update notebook
            $stmt = $this->db->prepare(
                "UPDATE notebooks 
                 SET status = 'approved', approved_by = ?, 
                 tech_signature = ?, approved_at = NOW(), 
                 updated_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([$technicianId, $signature, $notebookId]);
            
            // Create approval record
            $approvalStmt = $this->db->prepare(
                "INSERT INTO approvals 
                 (id, document_type, document_id, reviewer_id, action, signature_hash, reviewed_at)
                 VALUES (?, ?, ?, ?, 'approved', ?, NOW())"
            );
            $approvalStmt->execute([
                bin2hex(random_bytes(16)),
                'notebook',
                $notebookId,
                $technicianId,
                $signature
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function reject(string $notebookId, string $technicianId, string $comments): bool {
        $this->db->beginTransaction();
        
        try {
            // Update notebook
            $stmt = $this->db->prepare(
                "UPDATE notebooks 
                 SET status = 'rejected', updated_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([$notebookId]);
            
            // Create approval record
            $approvalStmt = $this->db->prepare(
                "INSERT INTO approvals 
                 (id, document_type, document_id, reviewer_id, action, comments, reviewed_at)
                 VALUES (?, ?, ?, ?, 'rejected', ?, NOW())"
            );
            $approvalStmt->execute([
                bin2hex(random_bytes(16)),
                'notebook',
                $notebookId,
                $technicianId,
                $comments
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getPendingApprovals(string $technicianLabId): array {
        $stmt = $this->db->prepare(
            "SELECT n.*, s.full_name as student_name, s.reg_number,
                   p.title as practical_title, l.name as lab_name
             FROM notebooks n
             JOIN users s ON n.student_id = s.id
             JOIN lab_sessions ls ON n.session_id = ls.id
             JOIN practicals p ON ls.practical_id = p.id
             JOIN labs l ON ls.lab_id = l.id
             WHERE n.status = 'submitted' AND ls.lab_id = ?
             ORDER BY n.created_at ASC"
        );
        $stmt->execute([$technicianLabId]);
        return $stmt->fetchAll();
    }
    
    public function createGroupNotebook(array $studentIds, string $sessionId, string $title): string {
        $this->db->beginTransaction();
        
        try {
            $groupId = bin2hex(random_bytes(16));
            $notebookId = bin2hex(random_bytes(16));
            
            // Create main notebook
            $this->create([
                'id' => $notebookId,
                'session_id' => $sessionId,
                'student_id' => $studentIds[0], // First student as primary
                'group_id' => $groupId,
                'title' => $title
            ]);
            
            // Create individual copies for each student
            foreach ($studentIds as $studentId) {
                if ($studentId !== $studentIds[0]) {
                    $individualId = bin2hex(random_bytes(16));
                    $this->create([
                        'id' => $individualId,
                        'session_id' => $sessionId,
                        'student_id' => $studentId,
                        'group_id' => $groupId,
                        'title' => $title
                    ]);
                }
            }
            
            $this->db->commit();
            return $notebookId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return '';
        }
    }
    
    public function getGroupNotebooks(string $groupId): array {
        $stmt = $this->db->prepare(
            "SELECT n.*, s.full_name as student_name, s.reg_number
             FROM notebooks n
             JOIN users s ON n.student_id = s.id
             WHERE n.group_id = ?
             ORDER BY s.full_name"
        );
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }
    
    public function syncGroupContent(string $groupId, string $content): bool {
        $stmt = $this->db->prepare(
            "UPDATE notebooks 
             SET content = ?, updated_at = NOW() 
             WHERE group_id = ?"
        );
        return $stmt->execute([$content, $groupId]);
    }
}
