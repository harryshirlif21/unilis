<?php
require_once __DIR__.'/Block.php';
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';

class Blockchain {
    private array $chain = [];
    private int   $difficulty;
    private PDO   $db;

    public function __construct() {
        $this->difficulty = BLOCKCHAIN_DIFFICULTY;
        $this->db         = getDB();
        $this->chain      = $this->load();
        if (empty($this->chain)) {
            $genesis       = new Block(0, ['event' => 'Genesis', 'system' => 'UNILIS SmartLab'], '0');
            $this->chain[] = $genesis;
            $this->save($genesis);
        }
    }
    public function addBlock(array $data): Block {
        $latest        = $this->chain[count($this->chain) - 1];
        $block         = new Block(count($this->chain), $data, $latest->hash);
        $block->mine($this->difficulty);
        $this->chain[] = $block;
        $this->save($block);
        return $block;
    }
    public function isValid(): bool {
        for ($i = 1; $i < count($this->chain); $i++) {
            $cur  = $this->chain[$i];
            $prev = $this->chain[$i - 1];
            if ($cur->hash !== $cur->calculateHash()) return false;
            if ($cur->previousHash !== $prev->hash)   return false;
        }
        return true;
    }
    public function getAssetHistory(string $assetId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM blockchain_blocks
             WHERE JSON_EXTRACT(block_data, '$.asset_id') = ?
             ORDER BY block_index ASC"
        );
        $stmt->execute([$assetId]);
        return $stmt->fetchAll();
    }
    public function getChain(): array {
        return $this->chain;
    }
    
    public function getLatestBlock(): ?Block {
        return end($this->chain) ?: null;
    }
    
    private function save(Block $b): void {
        $this->db->prepare(
            "INSERT INTO blockchain_blocks
             (block_index,timestamp,block_data,previous_hash,hash,nonce)
             VALUES (?,?,?,?,?,?)"
        )->execute([
            $b->index, $b->timestamp,
            json_encode($b->data),
            $b->previousHash, $b->hash, $b->nonce
        ]);
    }
    private function load(): array {
        $rows = $this->db->query(
            "SELECT * FROM blockchain_blocks ORDER BY block_index ASC"
        )->fetchAll();
        return array_map(function ($r) {
            $b            = new Block($r['block_index'], json_decode($r['block_data'], true), $r['previous_hash']);
            $b->hash      = $r['hash'];
            $b->timestamp = $r['timestamp'];
            $b->nonce     = (int)$r['nonce'];
            return $b;
        }, $rows);
    }
}
