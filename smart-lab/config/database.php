<?php
// Use Docker service hostname when available, but fall back to localhost for local development.
$defaultDbHost = getenv('DB_HOST') ?: 'smart-labs-db';
$databaseHosts = [$defaultDbHost, 'localhost', '127.0.0.1'];

// Match the credentials defined in your docker-compose.yml
define('DB_USER',    'lab_admin'); 
define('DB_PASS',    'lab_password'); 
define('DB_NAME',    'unilis_smartlab'); 
define('DB_CHARSET', 'utf8mb4');

define('DB_HOST', $defaultDbHost);

function getDB(): PDO {
    static $pdo = null;
    global $databaseHosts;

    if ($pdo === null) {
        $lastError = null;
        foreach ($databaseHosts as $host) {
            try {
                $dsn = 'mysql:host='.$host.';dbname='.DB_NAME.';charset='.DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                if (!defined('DB_HOST')) {
                    define('DB_HOST', $host);
                }
                return $pdo;
            } catch (PDOException $e) {
                $lastError = $e;
                continue;
            }
        }

        error_log("SmartLab DB Error: " . $lastError->getMessage());
        die("Database connection failed. Please check your database container or local MySQL server.");
    }
    return $pdo;
}