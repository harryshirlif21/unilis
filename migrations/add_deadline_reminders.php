<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Database Migration for Deadline Reminder System
 * Adds reminder tracking columns to assignments table
 */

echo "<h2>Deadline Reminder System Migration</h2>";

try {
    // Add reminder_24h_sent column
    $result = $conn->query("SHOW COLUMNS FROM assignments LIKE 'reminder_24h_sent'");
    if ($result->num_rows === 0) {
        $sql = "ALTER TABLE assignments ADD COLUMN reminder_24h_sent TINYINT(1) DEFAULT 0 COMMENT '24-hour reminder sent flag'";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added reminder_24h_sent column</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add reminder_24h_sent column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ reminder_24h_sent column already exists</p>";
    }
    
    // Add reminder_12h_sent column
    $result = $conn->query("SHOW COLUMNS FROM assignments LIKE 'reminder_12h_sent'");
    if ($result->num_rows === 0) {
        $sql = "ALTER TABLE assignments ADD COLUMN reminder_12h_sent TINYINT(1) DEFAULT 0 COMMENT '12-hour reminder sent flag'";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added reminder_12h_sent column</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add reminder_12h_sent column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ reminder_12h_sent column already exists</p>";
    }
    
    // Create deadline_reminders_log table for tracking
    $result = $conn->query("SHOW TABLES LIKE 'deadline_reminders_log'");
    if ($result->num_rows === 0) {
        $sql = "CREATE TABLE deadline_reminders_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            assignment_id INT NOT NULL,
            reminder_type ENUM('24h', '12h') NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            recipients_count INT NOT NULL,
            success_count INT NOT NULL,
            errors TEXT,
            FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
            INDEX idx_assignment_reminder (assignment_id, reminder_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Created deadline_reminders_log table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create deadline_reminders_log table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ deadline_reminders_log table already exists</p>";
    }
    
    echo "<h3>Migration Complete!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Set up a cron job to run the deadline reminder system hourly</li>";
    echo "<li>Test the reminder system by running: <code>php includes/deadline_reminders.php</code></li>";
    echo "<li>Monitor the deadline_reminders_log table for tracking</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Migration error: " . $e->getMessage() . "</p>";
}
?>
