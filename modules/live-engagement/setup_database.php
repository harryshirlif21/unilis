<?php
/**
 * Live Engagement Module - Database Setup
 * 
 * This script sets up the database tables for the Live Engagement module.
 * It includes the migration to add the created_by column to live_presentations.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    define('UNILIS_ACCESS', true);
}

require_once __DIR__ . '/../../config/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die('Database connection not available');
}

$messages = [];
$success = true;

// Function to check if column exists
function columnExists(mysqli $conn, string $table, string $column): bool {
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $result && $result->num_rows > 0;
}

// Add created_by column to live_presentations
$tableName = 'live_presentations';
if (!columnExists($conn, $tableName, 'created_by')) {
    $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `created_by` INT UNSIGNED NULL AFTER `presenter_notes`";
    
    if ($conn->query($sql)) {
        $messages[] = "Added created_by column to {$tableName}";
    } else {
        $messages[] = "Failed to add created_by column to {$tableName}: " . $conn->error;
        $success = false;
    }
} else {
    $messages[] = "Column created_by already exists in {$tableName}";
}

// Run the main installer if it exists
$installerPath = __DIR__ . '/database/install.php';
if (file_exists($installerPath)) {
    define('LIVE_ENGAGEMENT_INSTALL', true);
    require_once $installerPath;
    
    if (function_exists('installLiveEngagementTables')) {
        $result = installLiveEngagementTables($conn);
        $success = $success && $result['success'];
        $messages = array_merge($messages, $result['messages']);
    }
}

// Output results
header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'messages' => $messages
], JSON_PRETTY_PRINT);
