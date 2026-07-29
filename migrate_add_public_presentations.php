<?php
/**
 * Migration: Add public presentations table
 * 
 * This migration adds a table to track which presentations are publicly accessible
 * to non-authenticated users via share links.
 * 
 * @package UNILIS\Migrations
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    define('UNILIS_ACCESS', true);
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    die('Database connection not available');
}

$messages = [];
$success = true;

// Create public_presentations table
$tableName = 'public_presentations';
$checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");

if ($checkTable && $checkTable->num_rows > 0) {
    $messages[] = "Table $tableName already exists";
} else {
    $sql = "CREATE TABLE `$tableName` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        presentation_id INT UNSIGNED NOT NULL,
        share_token VARCHAR(64) NOT NULL UNIQUE,
        created_by INT UNSIGNED NOT NULL,
        access_count INT UNSIGNED DEFAULT 0,
        expires_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_presentation_id (presentation_id),
        INDEX idx_share_token (share_token),
        INDEX idx_expires_at (expires_at),
        FOREIGN KEY (presentation_id) REFERENCES live_presentations(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES lecturers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        $messages[] = "Created table $tableName";
    } else {
        $messages[] = "Failed to create table $tableName: " . $conn->error;
        $success = false;
    }
}

// Add is_public column to live_presentations if it doesn't exist
$checkColumn = $conn->query("SHOW COLUMNS FROM live_presentations LIKE 'is_public'");
if ($checkColumn && $checkColumn->num_rows > 0) {
    $messages[] = "Column is_public already exists in live_presentations";
} else {
    $sql = "ALTER TABLE live_presentations ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER visibility";
    if ($conn->query($sql)) {
        $messages[] = "Added is_public column to live_presentations";
    } else {
        $messages[] = "Failed to add is_public column to live_presentations: " . $conn->error;
        $success = false;
    }
}

// Output results
header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'messages' => $messages
], JSON_PRETTY_PRINT);
