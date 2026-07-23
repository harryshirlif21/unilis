<?php
/**
 * Migration: Add created_by column to live_presentations table
 * 
 * This migration adds the created_by column to track which user created
 * a presentation. This is needed for UNILIS SSO integration.
 * 
 * @package UNILIS\LiveEngagement\Database
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS') && !defined('LIVE_ENGAGEMENT_INSTALL')) {
    die('Direct access not permitted');
}

/**
 * Add created_by column to live_presentations table
 * 
 * @param mysqli $conn Database connection
 * @return array{success: bool, message: string}
 */
function addCreatedByToPresentations(mysqli $conn): array
{
    $tableName = 'live_presentations';
    
    // Check if column already exists
    $result = $conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'created_by'");
    if ($result && $result->num_rows > 0) {
        return ['success' => true, 'message' => 'Column created_by already exists in live_presentations'];
    }
    
    // Add the column
    $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `created_by` INT UNSIGNED NULL AFTER `presenter_notes`";
    
    if ($conn->query($sql)) {
        return ['success' => true, 'message' => 'Added created_by column to live_presentations'];
    } else {
        return ['success' => false, 'message' => 'Failed to add created_by column: ' . $conn->error];
    }
}

// Run migration if this file is accessed directly
if (php_sapi_name() === 'cli' || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    require_once __DIR__ . '/../../config/db.php';
    
    if (isset($conn) && $conn instanceof mysqli) {
        $result = addCreatedByToPresentations($conn);
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database connection not available'], JSON_PRETTY_PRINT);
    }
}
