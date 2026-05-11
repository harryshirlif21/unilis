<?php
/**
 * Migration: Add Missing Time Columns to Practicals Table
 * Date: 2026-05-11
 * Description: Add start_time, end_time, and course_code columns to practicals table
 */

require_once __DIR__ . '/../config/database_production.php';

try {
    $db = getDB();
    
    echo "Starting migration: Add Missing Time Columns to Practicals Table\n";
    echo "------------------------------------------------------------\n";
    
    // Check if start_time column exists
    $checkStartTime = $db->query("SHOW COLUMNS FROM practicals LIKE 'start_time'");
    $hasStartTime = $checkStartTime->rowCount() > 0;
    
    // Check if end_time column exists
    $checkEndTime = $db->query("SHOW COLUMNS FROM practicals LIKE 'end_time'");
    $hasEndTime = $checkEndTime->rowCount() > 0;
    
    // Check if course_code column exists
    $checkCourseCode = $db->query("SHOW COLUMNS FROM practicals LIKE 'course_code'");
    $hasCourseCode = $checkCourseCode->rowCount() > 0;
    
    // Add start_time column if it doesn't exist
    if (!$hasStartTime) {
        echo "Adding start_time column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN start_time TIME NULL COMMENT 'Start time of the practical session'");
        echo "✓ start_time column added successfully\n";
    } else {
        echo "⊘ start_time column already exists, skipping\n";
    }
    
    // Add end_time column if it doesn't exist
    if (!$hasEndTime) {
        echo "Adding end_time column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN end_time TIME NULL COMMENT 'End time of the practical session'");
        echo "✓ end_time column added successfully\n";
    } else {
        echo "⊘ end_time column already exists, skipping\n";
    }
    
    // Add course_code column if it doesn't exist
    if (!$hasCourseCode) {
        echo "Adding course_code column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN course_code VARCHAR(30) NULL COMMENT 'Course code for the practical'");
        echo "✓ course_code column added successfully\n";
    } else {
        echo "⊘ course_code column already exists, skipping\n";
    }
    
    // Check if required_equipment column exists
    $checkRequiredEquipment = $db->query("SHOW COLUMNS FROM practicals LIKE 'required_equipment'");
    $hasRequiredEquipment = $checkRequiredEquipment->rowCount() > 0;
    
    // Add required_equipment column if it doesn't exist
    if (!$hasRequiredEquipment) {
        echo "Adding required_equipment column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN required_equipment TEXT NULL COMMENT 'Required equipment for the practical'");
        echo "✓ required_equipment column added successfully\n";
    } else {
        echo "⊘ required_equipment column already exists, skipping\n";
    }
    
    // Check if required_chemicals column exists
    $checkRequiredChemicals = $db->query("SHOW COLUMNS FROM practicals LIKE 'required_chemicals'");
    $hasRequiredChemicals = $checkRequiredChemicals->rowCount() > 0;
    
    // Add required_chemicals column if it doesn't exist
    if (!$hasRequiredChemicals) {
        echo "Adding required_chemicals column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN required_chemicals TEXT NULL COMMENT 'Required chemicals for the practical'");
        echo "✓ required_chemicals column added successfully\n";
    } else {
        echo "⊘ required_chemicals column already exists, skipping\n";
    }
    
    // Check if safety_notes column exists
    $checkSafetyNotes = $db->query("SHOW COLUMNS FROM practicals LIKE 'safety_notes'");
    $hasSafetyNotes = $checkSafetyNotes->rowCount() > 0;
    
    // Add safety_notes column if it doesn't exist
    if (!$hasSafetyNotes) {
        echo "Adding safety_notes column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN safety_notes TEXT NULL COMMENT 'Safety notes for the practical'");
        echo "✓ safety_notes column added successfully\n";
    } else {
        echo "⊘ safety_notes column already exists, skipping\n";
    }
    
    echo "\n------------------------------------------------------------\n";
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
