<?php
/**
 * Migration: Add RFID support and submission tracking fields
 * Date: 2026-05-12
 * Description: Create rfid_cards table and add verified/started_at to student_submissions
 */

require_once __DIR__ . '/../config/database_production.php';

try {
    $db = getDB();

    echo "Starting migration: Add RFID support and submission tracking\n";
    echo "------------------------------------------------------\n";

    // Create rfid_cards table
    $checkRfidTable = $db->query("SHOW TABLES LIKE 'rfid_cards'");
    if ($checkRfidTable->rowCount() === 0) {
        echo "Creating rfid_cards table...\n";
        $db->exec(
            "CREATE TABLE rfid_cards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                uid VARCHAR(100) NOT NULL,
                device_id VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_uid (uid),
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        echo "✓ rfid_cards table created successfully\n";
    } else {
        echo "⊘ rfid_cards table already exists, skipping\n";
    }

    // Add verified column to student_submissions
    $checkVerified = $db->query("SHOW COLUMNS FROM student_submissions LIKE 'verified'");
    if ($checkVerified->rowCount() === 0) {
        echo "Adding verified column to student_submissions...\n";
        $db->exec("ALTER TABLE student_submissions ADD COLUMN verified TINYINT(1) DEFAULT 0 COMMENT 'Attendance verified before practical start'");
        echo "✓ verified column added successfully\n";
    } else {
        echo "⊘ verified column already exists, skipping\n";
    }

    // Add started_at column to student_submissions
    $checkStartedAt = $db->query("SHOW COLUMNS FROM student_submissions LIKE 'started_at'");
    if ($checkStartedAt->rowCount() === 0) {
        echo "Adding started_at column to student_submissions...\n";
        $db->exec("ALTER TABLE student_submissions ADD COLUMN started_at TIMESTAMP NULL COMMENT 'When the practical session was started'");
        echo "✓ started_at column added successfully\n";
    } else {
        echo "⊘ started_at column already exists, skipping\n";
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "\nMigration failed with error: " . $e->getMessage() . "\n";
    exit(1);
}
?>