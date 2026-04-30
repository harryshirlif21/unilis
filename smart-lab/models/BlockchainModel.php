<?php
require_once __DIR__.'/../config/app.php';

class BlockchainModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
}
