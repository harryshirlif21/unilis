<?php
/**
 * Debug script to diagnose PHP errors
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>PHP Debug Information</h2>";

// Check PHP version
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";

// Check required files
$required_files = [
    'config/db.php',
    'actions.php',
    'login.php'
];

echo "<h3>File Check:</h3>";
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>$file - EXISTS</p>";
    } else {
        echo "<p style='color: red;'>$file - MISSING</p>";
    }
}

// Check database connection
echo "<h3>Database Connection:</h3>";
try {
    require_once 'config/db.php';
    if (isset($conn) && $conn->ping()) {
        echo "<p style='color: green;'>Database connection: SUCCESS</p>";
    } else {
        echo "<p style='color: red;'>Database connection: FAILED</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

// Check syntax of actions.php
echo "<h3>Actions.php Syntax Check:</h3>";
$output = [];
$return_code = 0;
exec('php -l actions.php 2>&1', $output, $return_code);

if ($return_code === 0) {
    echo "<p style='color: green;'>Syntax check: PASSED</p>";
} else {
    echo "<p style='color: red;'>Syntax check: FAILED</p>";
    foreach ($output as $line) {
        echo "<p style='color: red;'>$line</p>";
    }
}

// Check POST data
echo "<h3>POST Data:</h3>";
if ($_POST) {
    echo "<pre>" . print_r($_POST, true) . "</pre>";
} else {
    echo "<p>No POST data</p>";
}

// Check session
echo "<h3>Session Status:</h3>";
echo "<p>Session status: " . session_status() . "</p>";
if (session_status() === PHP_SESSION_NONE) {
    echo "<p>Session not started</p>";
} else {
    echo "<p>Session active</p>";
}

echo "<h3>Server Info:</h3>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Check error log
echo "<h3>Last Error Log Entries:</h3>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $lines = file($log_file);
    $last_lines = array_slice($lines, -10);
    echo "<pre>" . implode('', $last_lines) . "</pre>";
} else {
    echo "<p>No error log file found</p>";
}

?>
