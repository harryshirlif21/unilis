<?php
require_once __DIR__.'/../config/app.php';

class InventoryModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function getInventory(): array {
        $stmt = $this->db->query(
            "SELECT a.*, l.name as lab_name, l.lab_code
             FROM assets a
             LEFT JOIN labs l ON a.lab_id = l.id
             ORDER BY a.name ASC"
        );
        return $stmt->fetchAll();
    }
    
    public function getInventoryStats(): array {
        $stmt = $this->db->query(
            "SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN quantity <= min_quantity THEN 1 END) as low_stock,
                SUM(quantity * COALESCE(unit_price, 0)) as total_value,
                COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock
             FROM assets"
        );
        $stats = $stmt->fetch();
        
        // Add expiring soon count (placeholder - would need expiry_date field)
        $stats['expiring_soon'] = 0;
        
        return $stats;
    }
    
    public function getLabs(): array {
        $stmt = $this->db->query("SELECT id, name, lab_code FROM labs WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function updateStock(string $assetId, int $quantity, string $reason, string $notes = ''): bool {
        $this->db->beginTransaction();
        
        try {
            // Update asset quantity
            $stmt = $this->db->prepare(
                "UPDATE assets SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$quantity, $assetId]);
            
            // Log the transaction (would integrate with blockchain)
            $logStmt = $this->db->prepare(
                "INSERT INTO audit_logs (user_id, action, module, created_at) 
                 VALUES (?, 'restock', 'assets', NOW())"
            );
            // This would need the actual user ID from session
            $logStmt->execute(['system']);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getLowStockItems(): array {
        $stmt = $this->db->query(
            "SELECT a.*, l.name as lab_name
             FROM assets a
             LEFT JOIN labs l ON a.lab_id = l.id
             WHERE a.quantity <= a.min_quantity
             ORDER BY a.quantity ASC"
        );
        return $stmt->fetchAll();
    }
    
    public function getOutOfStockItems(): array {
        $stmt = $this->db->query(
            "SELECT a.*, l.name as lab_name
             FROM assets a
             LEFT JOIN labs l ON a.lab_id = l.id
             WHERE a.quantity = 0
             ORDER BY a.name ASC"
        );
        return $stmt->fetchAll();
    }
    
    public function getInventoryByCategory(): array {
        $stmt = $this->db->query(
            "SELECT type, COUNT(*) as count, SUM(quantity) as total_quantity,
                   SUM(quantity * COALESCE(unit_price, 0)) as total_value
             FROM assets
             GROUP BY type
             ORDER BY count DESC"
        );
        return $stmt->fetchAll();
    }
    
    public function getInventoryByLab(): array {
        $stmt = $this->db->query(
            "SELECT l.id, l.name, l.lab_code,
                   COUNT(a.id) as item_count,
                   SUM(a.quantity) as total_quantity,
                   SUM(a.quantity * COALESCE(a.unit_price, 0)) as total_value
             FROM labs l
             LEFT JOIN assets a ON l.id = a.lab_id
             WHERE l.is_active = 1
             GROUP BY l.id, l.name, l.lab_code
             ORDER BY l.name"
        );
        return $stmt->fetchAll();
    }
}
