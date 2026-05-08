<?php
// Production Database Configuration with Docker Support

// Try Docker container name first, fallback to localhost for local development
$dockerHost = 'unilis-db';
$localHost  = 'localhost';
$dbName     = 'unilis_smartlab';
$dbUser     = 'lab_admin';
$dbPass     = 'lab_password';
$dbCharset  = 'utf8mb4';

// Auto-detect if running in Docker or local development
function getProductionDB(): PDO {
    global $dockerHost, $localHost, $dbName, $dbUser, $dbPass, $dbCharset;
    
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    
    // Try Docker first
    $hosts = [$dockerHost, $localHost, '127.0.0.1'];
    $lastError = null;
    
    foreach ($hosts as $host) {
        try {
            $dsn = "mysql:host=$host;dbname=$dbName;charset=$dbCharset";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            
            // Connection successful, store the working host
            define('DB_HOST', $host);
            define('DB_NAME', $dbName);
            define('DB_USER', $dbUser);
            define('DB_PASS', $dbPass);
            define('DB_CHARSET', $dbCharset);
            
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
            // Try next host
            continue;
        }
    }
    
    // All connection attempts failed
    $errorMsg = "Could not connect to database.<br>";
    $errorMsg .= "Tried hosts: " . implode(', ', $hosts) . "<br>";
    $errorMsg .= "Last error: " . htmlspecialchars($lastError->getMessage());
    
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 6px; margin: 20px 0; color: #721c24;'>";
    echo "<h3>❌ Database Connection Failed</h3>";
    echo "<p>$errorMsg</p>";
    echo "</div>";
    
    throw $lastError;
}

function getDB(): PDO {
    return getProductionDB();
}

// Define constants for backward compatibility
if (!defined('DB_HOST')) {
    define('DB_HOST', 'unilis-db');
    define('DB_NAME', 'unilis_smartlab');
    define('DB_USER', 'lab_admin');
    define('DB_PASS', 'lab_password');
    define('DB_CHARSET', 'utf8mb4');
}
?>