<?php
require_once __DIR__.'/../config/database.php';

class BlockchainModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
}
