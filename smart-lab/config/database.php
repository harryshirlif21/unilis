<?php
// Use the Docker Service Name, not localhost
define('DB_HOST',    'smart-labs-db'); 

// Match the credentials defined in your docker-compose.yml
define('DB_USER',    'lab_admin'); 
define('DB_PASS',    'lab_password'); 
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
            die("Database connection failed. Please check if the 'smart-labs-db' container is running.");
        }
    }
    return $pdo;
}