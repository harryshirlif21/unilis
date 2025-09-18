<?php
// Database configuration
global $conn;

$maxRetries = 5;
$retryDelay = 3; // seconds

// Get environment variables with fallbacks for Docker
$host = getenv('DB_HOST');
if (!$host) {
    // Check if running in Docker
    if (file_exists('/.dockerenv')) {
        $host = 'db';  // Docker service name
    } else {
        $host = 'localhost'; // Local development
    }
}

$user = getenv('MYSQL_USER') ?: 'unilisuser';
$password = getenv('MYSQL_PASSWORD') ?: 'unilispass';
$dbname = getenv('MYSQL_DATABASE') ?: 'unilis';

// Connection retry loop with improved error handling
$conn = null;
for ($i = 0; $i < $maxRetries; $i++) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli($host, $user, $password, $dbname);
        
        // Successfully connected - set UTF-8 character set
        $conn->set_charset("utf8mb4");
        
        // Set SQL mode for compatibility
        $conn->query("SET SESSION sql_mode = ''");
        
        // Set timezone
        $conn->query("SET time_zone = '+03:00'");
        
        // If we get here, connection was successful
        break;
        
    } catch (mysqli_sql_exception $e) {
        // Log connection attempt failure
        error_log(sprintf(
            "Database connection attempt %d failed - Host: %s, User: %s, Database: %s, Error: %s",
            $i + 1,
            $host,
            $user,
            $dbname,
            $e->getMessage()
        ));
        
        // On last retry, rethrow the exception
        if ($i === $maxRetries - 1) {
            throw new Exception("Failed to connect to database after {$maxRetries} attempts: " . $e->getMessage());
        }
        
        sleep($retryDelay);
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
?>
