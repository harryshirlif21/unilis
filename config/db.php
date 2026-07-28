<?php
// Database configuration
global $conn;
define('TOKEN_EXPIRY_MINUTES', 60); // <-- set to your desired expiry
$maxRetries = 5;
$retryDelay = 3; // seconds

$dbTarget = strtolower((string)(getenv('DB_TARGET') ?: getenv('DB_CONNECTION') ?: ''));
$appEnv = strtolower((string)(getenv('APP_ENV') ?: ''));
$useProductionDb = ($dbTarget === 'db-production') || ($appEnv === 'production');

// Get environment variables with fallbacks for Docker
$host = $useProductionDb
    ? (getenv('DB_PRODUCTION_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1')
    : (getenv('DB_HOST') ?: '127.0.0.1');
if (!$host) {
    // Check if running in Docker
    if (file_exists('/.dockerenv')) {
        $host = 'db';  // Docker service name
    } else {
        $host = 'localhost'; // Local development
    }
}

$user = $useProductionDb
    ? (getenv('DB_PRODUCTION_USER') ?: getenv('MYSQL_USER'))
    : getenv('MYSQL_USER');
if ($user === false || $user === '') {
    $user = 'unilisuser';
}

$productionPassword = getenv('DB_PRODUCTION_PASSWORD');
$defaultPassword = getenv('MYSQL_PASSWORD');
$password = $useProductionDb
    ? ($productionPassword !== false ? $productionPassword : $defaultPassword)
    : $defaultPassword;
if ($password === false) {
    $password = 'unilispass';
}

$dbname = $useProductionDb
    ? (getenv('DB_PRODUCTION_NAME') ?: getenv('MYSQL_DATABASE'))
    : getenv('MYSQL_DATABASE');
if ($dbname === false || $dbname === '') {
    $dbname = 'unilis';
}

// Put PHP in the same timezone the database session is set to below (+03:00).
//
// Without this the two disagree: MySQL writes and reads NOW() in +03:00, while
// PHP defaults to UTC on the container, so every
// strtotime($row['scheduled_time']) comparison is three hours out. That is what
// decides whether a meeting shows as live, whether the Start Meeting button
// appears, and whether a verification token has expired - all of which were
// silently wrong by three hours.
//
// Named zone rather than a fixed offset so daylight saving, if the deployment
// ever moves somewhere that has it, is handled rather than ignored.
date_default_timezone_set('Africa/Nairobi');

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

