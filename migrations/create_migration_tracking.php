<?php
/**
 * Migration: Create migration tracking table
 * 
 * This migration creates a table to track which migrations have been run
 * and when they were executed.
 */

require_once __DIR__ . '/../config/db.php';

echo "Starting migration: Create migration tracking table...\n";

try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            description TEXT,
            INDEX idx_migration_name (migration_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ migrations table created\n";

    echo "\n✓ Migration completed successfully!\n";

} catch (mysqli_sql_exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
