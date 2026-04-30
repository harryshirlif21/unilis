<?php
require_once __DIR__.'/../config/app.php';

class AssetModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function getAll(?string $labId = null): array {
        $sql = "SELECT a.*, l.name as lab_name FROM assets a 
                LEFT JOIN labs l ON a.lab_id = l.id";
        
        if ($labId) {
            $sql .= " WHERE a.lab_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$labId]);
        } else {
            $stmt = $this->db->query($sql);
        }
        
        return $stmt->fetchAll();
    }
    
    public function getById(string $assetId): ?array {
        $stmt = $this->db->prepare(
            "SELECT a.*, l.name as lab_name FROM assets a 
             LEFT JOIN labs l ON a.lab_id = l.id 
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$assetId]);
        return $stmt->fetch() ?: null;
    }
    
    public function create(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO assets 
             (id, asset_code, name, type, lab_id, quantity, unit, serial_number, purchase_date, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        return $stmt->execute([
            $data['id'],
            $data['asset_code'],
            $data['name'],
            $data['type'],
            $data['lab_id'],
            $data['quantity'],
            $data['unit'],
            $data['serial_number'],
            $data['purchase_date'],
            $data['notes']
        ]);
    }
    
    public function updateQuantity(string $assetId, float $newQuantity): bool {
        $stmt = $this->db->prepare(
            "UPDATE assets SET quantity = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$newQuantity, $assetId]);
    }
    
    public function transferAsset(string $assetId, string $targetLabId, float $quantity): bool {
        $this->db->beginTransaction();
        
        try {
            // Get current asset
            $asset = $this->getById($assetId);
            if (!$asset) {
                throw new Exception('Asset not found');
            }
            
            // Update quantity
            $newQuantity = $asset['quantity'] - $quantity;
            if ($newQuantity < 0) {
                throw new Exception('Insufficient quantity');
            }
            
            $this->updateQuantity($assetId, $newQuantity);
            
            // If quantity becomes 0, update lab
            if ($newQuantity == 0) {
                $stmt = $this->db->prepare(
                    "UPDATE assets SET lab_id = ?, updated_at = NOW() WHERE id = ?"
                );
                $stmt->execute([$targetLabId, $assetId]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getLabs(): array {
        $stmt = $this->db->query(
            "SELECT id, name, lab_code FROM labs WHERE is_active = 1 ORDER BY name"
        );
        return $stmt->fetchAll();
    }
    
    public function getLabUsers(string $labId): array {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, reg_number, role FROM users 
             WHERE lab_id = ? AND is_active = 1 
             ORDER BY role, full_name"
        );
        $stmt->execute([$labId]);
        return $stmt->fetchAll();
    }
    
    public function getAssetTransactions(string $assetId, int $limit = 20): array {
        $stmt = $this->db->prepare(
            "SELECT at.*, u.full_name as user_name, l.name as lab_name 
             FROM asset_transactions at 
             LEFT JOIN users u ON at.user_id = u.id 
             LEFT JOIN labs l ON at.lab_id = l.id 
             WHERE at.asset_id = ? 
             ORDER BY at.created_at DESC 
             LIMIT ?"
        );
        $stmt->execute([$assetId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function search(string $query, ?string $labId = null): array {
        $sql = "SELECT a.*, l.name as lab_name FROM assets a 
                LEFT JOIN labs l ON a.lab_id = l.id 
                WHERE (a.name LIKE ? OR a.asset_code LIKE ? OR a.serial_number LIKE ?)";
        
        $params = ["%$query%", "%$query%", "%$query%"];
        
        if ($labId) {
            $sql .= " AND a.lab_id = ?";
            $params[] = $labId;
        }
        
        $sql .= " ORDER BY a.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getAssetsByType(string $type, ?string $labId = null): array {
        $sql = "SELECT a.*, l.name as lab_name FROM assets a 
                LEFT JOIN labs l ON a.lab_id = l.id 
                WHERE a.type = ?";
        
        if ($labId) {
            $sql .= " AND a.lab_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$type, $labId]);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$type]);
        }
        
        return $stmt->fetchAll();
    }
    
    public function updateStatus(string $assetId, string $status): bool {
        $stmt = $this->db->prepare(
            "UPDATE assets SET status = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$status, $assetId]);
    }
}
