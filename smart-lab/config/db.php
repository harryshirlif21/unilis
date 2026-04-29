<?php
/**
 * Smart Lab Database Configuration
 * Correct database connection parameters for the smart-labs-db container
 */

global $smart_lab_conn;
$maxRetries = 5;
$retryDelay = 3;

// Smart Lab Database connection parameters (from docker-compose.yml)
$host = getenv('SMART_LAB_DB_HOST') ?: 'smart-labs-db';
$user = getenv('SMART_LAB_DB_USER') ?: 'lab_admin';
$password = getenv('SMART_LAB_DB_PASSWORD') ?: 'lab_password';
$dbname = getenv('SMART_LAB_DB_NAME') ?: 'unilis_smartlab';

// Connection retry loop with improved error handling
$smart_lab_conn = null;
for ($i = 0; $i < $maxRetries; $i++) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $smart_lab_conn = new mysqli($host, $user, $password, $dbname);
        
        // Successfully connected - set UTF-8 character set
        $smart_lab_conn->set_charset("utf8mb4");
        
        // Set SQL mode for compatibility
        $smart_lab_conn->query("SET SESSION sql_mode = ''");
        
        // Set timezone
        $smart_lab_conn->query("SET time_zone = '+03:00'");
        
        // If we get here, connection was successful
        break;
        
    } catch (mysqli_sql_exception $e) {
        // Log connection attempt failure
        error_log(sprintf(
            "Smart Lab Database connection attempt %d failed - Host: %s, User: %s, Database: %s, Error: %s",
            $i + 1,
            $host,
            $user,
            $dbname,
            $e->getMessage()
        ));
        
        // On last retry, rethrow the exception
        if ($i === $maxRetries - 1) {
            throw new Exception("Failed to connect to Smart Lab database after {$maxRetries} attempts: " . $e->getMessage());
        }
        
        sleep($retryDelay);
    }
}

// Final connection check
if (!$smart_lab_conn) {
    $error_message = "Failed to establish Smart Lab database connection after {$maxRetries} attempts.";
    error_log($error_message);
    die($error_message);
}

// Make the connection available globally
$GLOBALS['smart_lab_conn'] = $smart_lab_conn;

// Function to get the smart lab connection
function getSmartLabConnection() {
    global $smart_lab_conn;
    return $smart_lab_conn;
}

?>
