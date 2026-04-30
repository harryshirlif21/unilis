<?php
require_once __DIR__.'/../config/app.php';

class AuthModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
}
