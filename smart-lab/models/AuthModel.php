<?php
require_once __DIR__.'/../config/database.php';

class AuthModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
}
