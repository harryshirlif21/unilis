<?php
// Database configuration
global $conn;
define('TOKEN_EXPIRY_MINUTES', 60); // <-- set to your desired expiry
$maxRetries = 5;
$retryDelay = 3; // seconds

// Get environment variables with fallbacks for Docker
$host = getenv('DB_HOST') ?: '127.0.0.1';
if (!$host) {
    // Check if running in Docker
    if (file_exists('/.dockerenv')) {
        $host = 'smart-labs-db';  // Docker service name for SmartLab
    } else {
        $host = 'localhost'; // Local development
    }
}

// Support both UNILIS and SmartLab databases
$user = getenv('MYSQL_USER') ?: 'lab_admin';
$password = getenv('MYSQL_PASSWORD') ?: 'lab_password';
$dbname = getenv('MYSQL_DATABASE') ?: 'unilis_smartlab';

// Fallback to old UNILIS config if SmartLab fails
$unilis_user = 'unilisuser';
$unilis_password = 'unilispass';
$unilis_dbname = 'unilis';

// Connection retry loop with improved error handling
$conn = null;
$databases = [
    ['name' => $dbname, 'user' => $user, 'password' => $password, 'type' => 'SmartLab'],
    ['name' => $unilis_dbname, 'user' => $unilis_user, 'password' => $unilis_password, 'type' => 'UNILIS']
];

foreach ($databases as $db_config) {
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli($host, $db_config['user'], $db_config['password'], $db_config['name']);
            
            // Successfully connected - set UTF-8 character set
            $conn->set_charset("utf8mb4");
            
            // Set SQL mode for compatibility
            $conn->query("SET SESSION sql_mode = ''");
            
            // Set timezone
            $conn->query("SET time_zone = '+03:00'");
            
            // Store which database we connected to
            $conn->connected_db = $db_config['type'];
            
            // If we get here, connection was successful
            error_log("Successfully connected to {$db_config['type']} database: {$db_config['name']}");
            break 2; // Break both loops
            
        } catch (mysqli_sql_exception $e) {
            // Log connection attempt failure
            error_log(sprintf(
                "Database connection attempt %d failed - Type: %s, Host: %s, User: %s, Database: %s, Error: %s",
                $i + 1,
                $db_config['type'],
                $host,
                $db_config['user'],
                $db_config['name'],
                $e->getMessage()
            ));
            
            // On last retry for this database, try next database
            if ($i === $maxRetries - 1) {
                continue 2; // Continue to next database
            }
            
            sleep($retryDelay);
        }
    }
}

// Final connection check
if (!$conn) {
    $error_message = "Failed to establish database connection after {$maxRetries} attempts.";
    error_log($error_message);
    die($error_message);
}

// Make the connection available globally
$GLOBALS['conn'] = $conn;

