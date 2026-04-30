<?php
require_once __DIR__.'/../config/app.php';

class AuditModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
}
