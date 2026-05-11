<?php
/**
 * Migration: Add Rich Content Fields to Practicals Table
 * Date: 2026-05-08
 * Description: Add fields for results template and calculations template to support TinyMCE rich text editing
 */

require_once __DIR__ . '/../config/database_production.php';

try {
    $db = getDB();
    
    echo "Starting migration: Add Rich Content Fields to Practicals Table\n";
    echo "------------------------------------------------------------\n";
    
    // Check if columns already exist
    $checkResults = $db->query("SHOW COLUMNS FROM practicals LIKE 'results_template'");
    $hasResultsTemplate = $checkResults->rowCount() > 0;
    
    $checkCalculations = $db->query("SHOW COLUMNS FROM practicals LIKE 'calculations_template'");
    $hasCalculationsTemplate = $checkCalculations->rowCount() > 0;
    
    // Add results_template column if it doesn't exist
    if (!$hasResultsTemplate) {
        echo "Adding results_template column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN results_template LONGTEXT NULL COMMENT 'HTML template for student results tables'");
        echo "✓ results_template column added successfully\n";
    } else {
        echo "⊘ results_template column already exists, skipping\n";
    }
    
    // Add calculations_template column if it doesn't exist
    if (!$hasCalculationsTemplate) {
        echo "Adding calculations_template column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN calculations_template LONGTEXT NULL COMMENT 'HTML template for student calculations and observations'");
        echo "✓ calculations_template column added successfully\n";
    } else {
        echo "⊘ calculations_template column already exists, skipping\n";
    }
    
    // Check if duration_hours column exists
    $checkDuration = $db->query("SHOW COLUMNS FROM practicals LIKE 'duration_hours'");
    $hasDurationHours = $checkDuration->rowCount() > 0;
    
    // Add duration_hours column if it doesn't exist
    if (!$hasDurationHours) {
        echo "Adding duration_hours column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN duration_hours INT DEFAULT 2 COMMENT 'Duration of practical in hours'");
        echo "✓ duration_hours column added successfully\n";
    } else {
        echo "⊘ duration_hours column already exists, skipping\n";
    }
    
    echo "\n------------------------------------------------------------\n";
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
