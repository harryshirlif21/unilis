<?php
// Production Database Configuration for UNILIS SmartLab
// This file should be used on production server (not Docker)

// Production database connection parameters
define('DB_HOST',    'localhost'); 
define('DB_USER',    'unilisuser'); 
define('DB_PASS',    'unilispass'); 
define('DB_NAME',    'unilis_smartlab'); 
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Logs error and prevents the "Internal Server Error" white screen
            error_log("SmartLab DB Error: " . $e->getMessage());
            die("Database connection failed. Please contact system administrator.");
        }
    }
    return $pdo;
}

?>
