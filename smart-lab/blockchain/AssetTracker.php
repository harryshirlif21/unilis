<?php
require_once __DIR__.'/Blockchain.php';

class AssetTracker {
    private Blockchain $bc;
    private PDO $db;
    
    public function __construct() { 
        $this->bc = new Blockchain(); 
        $this->db = getDB();
    }

    public function register(array $asset, string $userId, string $labId): Block {
        $block = $this->bc->addBlock([
            'asset_id' => $asset['id'], 
            'asset_type' => $asset['type'],
            'asset_name' => $asset['name'],
            'asset_code' => $asset['asset_code'] ?? '',
            'action'   => 'registered', 
            'user_id'    => $userId,
            'lab_id'   => $labId,       
            'status'      => 'available',
            'quantity' => $asset['quantity'] ?? 1,
            'timestamp'=> date('Y-m-d H:i:s'),
        ]);
        
        // Also record in traditional database for quick queries
        $this->recordTransaction($asset['id'], 'registered', $userId, $labId, [
            'quantity' => $asset['quantity'] ?? 1,
            'notes' => 'Asset registered in blockchain'
        ]);
        
        return $block;
    }
    
    public function issue(string $assetId, string $userId, string $labId, float $quantity = 1, array $metadata = []): Block {
        $block = $this->bc->addBlock([
            'asset_id' => $assetId, 
            'action'  => 'issued',
            'user_id'  => $userId,  
            'lab_id'  => $labId,
            'status'   => 'in_use', 
            'quantity' => $quantity,
            'purpose' => $metadata['purpose'] ?? '',
            'timestamp'=> date('Y-m-d H:i:s'),
        ]);
        
        $this->recordTransaction($assetId, 'issued', $userId, $labId, array_merge([
            'quantity' => $quantity,
            'notes' => 'Asset issued to user'
        ], $metadata));
        
        return $block;
    }
    
    public function returnAsset(string $assetId, string $userId, string $labId, float $quantity = 1, array $metadata = []): Block {
        $block = $this->bc->addBlock([
            'asset_id' => $assetId,     
            'action'   => 'returned',
            'user_id'  => $userId,      
            'lab_id'   => $labId,
            'status'   => 'available',  
            'quantity' => $quantity,
            'condition' => $metadata['condition'] ?? 'good',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        
        $this->recordTransaction($assetId, 'returned', $userId, $labId, array_merge([
            'quantity' => $quantity,
            'notes' => 'Asset returned to inventory'
        ], $metadata));
        
        return $block;
    }
    
    public function transfer(string $assetId, string $fromLab, string $toLab, string $userId, float $quantity = 1, array $metadata = []): Block {
        $block = $this->bc->addBlock([
            'asset_id' => $assetId,       
            'action'   => 'transferred',
            'from_lab' => $fromLab,        
            'to_lab'   => $toLab,
            'user_id'  => $userId,         
            'status'   => 'in_transit',
            'quantity' => $quantity,
            'reason' => $metadata['reason'] ?? 'Lab transfer',
            'timestamp'=> date('Y-m-d H:i:s'),
        ]);
        
        $this->recordTransaction($assetId, 'transferred', $userId, $fromLab, array_merge([
            'quantity' => $quantity,
            'target_lab_id' => $toLab,
            'notes' => 'Asset transferred between labs'
        ], $metadata));
        
        return $block;
    }
    
    public function logUsage(string $assetId, string $userId, string $labId, array $usageData): Block {
        $block = $this->bc->addBlock([
            'asset_id' => $assetId,
            'action' => 'usage_logged',
            'user_id' => $userId,
            'lab_id' => $labId,
            'status' => 'in_use',
            'usage_type' => $usageData['type'] ?? 'general',
            'duration_minutes' => $usageData['duration'] ?? 0,
            'consumed_amount' => $usageData['consumed'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        
        $this->recordTransaction($assetId, 'usage_logged', $userId, $labId, [
            'quantity' => $usageData['consumed'] ?? 0,
            'notes' => 'Asset usage logged: ' . ($usageData['type'] ?? 'general')
        ]);
        
        return $block;
    }
    
    public function dispose(string $assetId, string $userId, string $labId, array $disposalData): Block {
        $block = $this->bc->addBlock([
            'asset_id' => $assetId,
            'action' => 'disposed',
            'user_id' => $userId,
            'lab_id' => $labId,
            'status' => 'disposed',
            'disposal_reason' => $disposalData['reason'] ?? 'end_of_life',
            'disposal_method' => $disposalData['method'] ?? 'standard',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        
        $this->recordTransaction($assetId, 'disposed', $userId, $labId, [
            'quantity' => 0,
            'notes' => 'Asset disposed: ' . ($disposalData['reason'] ?? 'end_of_life')
        ]);
        
        return $block;
    }
    
    public function history(string $assetId): array { 
        return $this->bc->getAssetHistory($assetId); 
    }
    
    public function validate(): bool { 
        return $this->bc->isValid(); 
    }
    
    public function getChainStats(): array {
        $chain = $this->bc->getChain();
        $totalBlocks = count($chain);
        $latestBlock = $this->bc->getLatestBlock();
        
        return [
            'total_blocks' => $totalBlocks,
            'latest_block_index' => $latestBlock->index,
            'latest_block_hash' => $latestBlock->hash,
            'is_valid' => $this->validate(),
            'difficulty' => BLOCKCHAIN_DIFFICULTY
        ];
    }
    
    public function getAssetTransactions(string $assetId, int $limit = 50): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM asset_transactions 
             WHERE asset_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?"
        );
        $stmt->execute([$assetId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getLabAssets(string $labId): array {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT a.* FROM assets a
             JOIN asset_transactions at ON a.id = at.asset_id
             WHERE a.lab_id = ? OR at.target_lab_id = ?
             ORDER BY a.name"
        );
        $stmt->execute([$labId, $labId]);
        return $stmt->fetchAll();
    }
    
    private function recordTransaction(string $assetId, string $action, string $userId, string $labId, array $metadata): void {
        $stmt = $this->db->prepare(
            "INSERT INTO asset_transactions 
             (asset_id, action, user_id, lab_id, target_lab_id, quantity, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $stmt->execute([
            $assetId,
            $action,
            $userId,
            $labId,
            $metadata['target_lab_id'] ?? null,
            $metadata['quantity'] ?? 1,
            $metadata['notes'] ?? ''
        ]);
    }
}
