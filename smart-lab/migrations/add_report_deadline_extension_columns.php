<?php
/**
 * Migration: Add deadline extension columns to report_deadlines
 * Date: 2026-06-05
 * Description: Add extended_until (and related columns) required by report submission
 */

require_once __DIR__ . '/../config/database_production.php';

try {
    $db = getDB();

    echo "Starting migration: Add deadline extension columns to report_deadlines\n";
    echo "------------------------------------------------------------\n";

    $columns = [
        'extended' => "ALTER TABLE report_deadlines ADD COLUMN extended TINYINT(1) DEFAULT 0 AFTER deadline_date",
        'extended_until' => "ALTER TABLE report_deadlines ADD COLUMN extended_until DATETIME DEFAULT NULL AFTER extended",
        'updated_at' => "ALTER TABLE report_deadlines ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];

    foreach ($columns as $name => $sql) {
        $check = $db->query("SHOW COLUMNS FROM report_deadlines LIKE '$name'");
        if ($check->rowCount() > 0) {
            echo "⊘ $name column already exists, skipping\n";
            continue;
        }

        echo "Adding $name column...\n";
        $db->exec($sql);
        echo "✓ $name column added successfully\n";
    }

    echo "\n------------------------------------------------------------\n";
    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
