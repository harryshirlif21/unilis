<?php
/**
 * Live Engagement Module - Database Setup
 * 
 * This script sets up the database tables for the Live Engagement module.
 * It includes the migration to add the created_by column to live_presentations.
 * Displays the schema of all tables after installation.
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
$tableSchemas = [];

// Function to check if column exists
function columnExists(mysqli $conn, string $table, string $column): bool {
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $result && $result->num_rows > 0;
}

// Function to get table schema
function getTableSchema(mysqli $conn, string $table): array {
    $columns = [];
    $result = $conn->query("DESCRIBE `{$table}`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = [
                'field' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
    }
    return $columns;
}

// Function to get table indexes
function getTableIndexes(mysqli $conn, string $table): array {
    $indexes = [];
    $result = $conn->query("SHOW INDEX FROM `{$table}`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $indexes[] = [
                'key_name' => $row['Key_name'],
                'column_name' => $row['Column_name'],
                'unique' => $row['Non_unique'] == 0,
                'index_type' => $row['Index_type']
            ];
        }
    }
    return $indexes;
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

// Get schema for all Live Engagement tables
$liveEngagementTables = [
    'live_sessions',
    'live_presentations',
    'presentation_slides',
    'live_participants',
    'live_polls',
    'live_poll_options',
    'live_poll_responses',
    'live_quizzes',
    'quiz_questions',
    'quiz_answers',
    'live_wordcloud',
    'live_open_responses',
    'live_notes',
    'live_whiteboards',
    'whiteboard_objects',
    'live_reports',
    'live_statistics',
    'wordcloud_submissions',
    'open_response_submissions',
    'quiz_attempts',
    'quiz_attempt_answers',
    'live_reactions',
    'le_guest_users'
];

foreach ($liveEngagementTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    if ($result && $result->num_rows > 0) {
        $tableSchemas[$table] = [
            'columns' => getTableSchema($conn, $table),
            'indexes' => getTableIndexes($conn, $table)
        ];
    }
}

// Output results
header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'messages' => $messages,
    'table_schemas' => $tableSchemas
], JSON_PRETTY_PRINT);
