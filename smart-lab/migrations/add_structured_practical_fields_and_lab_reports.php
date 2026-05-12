<?php
/**
 * Migration: Add Structured Practical Fields and Lab Reports Table
 * Date: 2026-05-12
 * Description: Add structured content fields to practicals table and create lab_reports table for student submissions
 */

require_once __DIR__ . '/../config/database_production.php';

try {
    $db = getDB();

    echo "Starting migration: Add Structured Practical Fields and Lab Reports Table\n";
    echo "------------------------------------------------------------------------\n";

    // Part 1: Add structured content fields to practicals table
    echo "\nPart 1: Adding structured content fields to practicals table\n";
    echo "-----------------------------------------------------------\n";

    // Check and add objective column
    $checkObjective = $db->query("SHOW COLUMNS FROM practicals LIKE 'objective'");
    $hasObjective = $checkObjective->rowCount() > 0;

    if (!$hasObjective) {
        echo "Adding objective column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN objective LONGTEXT NULL COMMENT 'Learning objectives for the practical'");
        echo "✓ objective column added successfully\n";
    } else {
        echo "⊘ objective column already exists, skipping\n";
    }

    // Check and add procedure_json column
    $checkProcedure = $db->query("SHOW COLUMNS FROM practicals LIKE 'procedure_json'");
    $hasProcedure = $checkProcedure->rowCount() > 0;

    if (!$hasProcedure) {
        echo "Adding procedure_json column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN procedure_json LONGTEXT NULL COMMENT 'Procedure steps as JSON array'");
        echo "✓ procedure_json column added successfully\n";
    } else {
        echo "⊘ procedure_json column already exists, skipping\n";
    }

    // Check and add observations_table_structure column
    $checkObservations = $db->query("SHOW COLUMNS FROM practicals LIKE 'observations_table_structure'");
    $hasObservations = $checkObservations->rowCount() > 0;

    if (!$hasObservations) {
        echo "Adding observations_table_structure column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN observations_table_structure LONGTEXT NULL COMMENT 'Observations table structure as JSON'");
        echo "✓ observations_table_structure column added successfully\n";
    } else {
        echo "⊘ observations_table_structure column already exists, skipping\n";
    }

    // Check and add theory column
    $checkTheory = $db->query("SHOW COLUMNS FROM practicals LIKE 'theory'");
    $hasTheory = $checkTheory->rowCount() > 0;

    if (!$hasTheory) {
        echo "Adding theory column...\n";
        $db->exec("ALTER TABLE practicals ADD COLUMN theory LONGTEXT NULL COMMENT 'Theoretical background for the practical'");
        echo "✓ theory column added successfully\n";
    } else {
        echo "⊘ theory column already exists, skipping\n";
    }

    // Part 2: Create lab_reports table
    echo "\nPart 2: Creating lab_reports table\n";
    echo "----------------------------------\n";

    // Check if table already exists
    $checkTable = $db->query("SHOW TABLES LIKE 'lab_reports'");
    $hasTable = $checkTable->rowCount() > 0;

    if (!$hasTable) {
        echo "Creating lab_reports table...\n";
        $createTableSQL = "
            CREATE TABLE lab_reports (
                id VARCHAR(32) PRIMARY KEY,
                practical_id VARCHAR(32) NOT NULL,
                student_id VARCHAR(32) NOT NULL,
                status ENUM('in_progress', 'submitted') DEFAULT 'in_progress',
                observations_json LONGTEXT,
                calculations LONGTEXT,
                result LONGTEXT,
                conclusion LONGTEXT,
                submitted_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (practical_id) REFERENCES practicals(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_practical_student (practical_id, student_id),
                INDEX idx_student (student_id),
                INDEX idx_status (status),
                INDEX idx_submitted (submitted_at)
            )
        ";
        $db->exec($createTableSQL);
        echo "✓ lab_reports table created successfully\n";
    } else {
        echo "⊘ lab_reports table already exists, skipping\n";
    }

    echo "\nMigration completed successfully!\n";
    echo "==================================\n";

} catch (Exception $e) {
    echo "\nMigration failed with error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}
?>