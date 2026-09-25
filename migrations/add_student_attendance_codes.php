<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Database Migration for Enhanced Student Attendance System
 * Adds student_attendance_codes table for individual student codes
 */

echo "<h2>Enhanced Student Attendance System Migration</h2>";

try {
    // Create attendance_sessions table (parent for the other attendance tables).
    // Keep this first: student_attendance_codes and attendance_records both have
    // foreign keys referencing attendance_sessions(id).
    $result = $conn->query("SHOW TABLES LIKE 'attendance_sessions'");
    if ($result->num_rows === 0) {
        $sql = "CREATE TABLE attendance_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id INT NOT NULL,
            lecturer_id INT NOT NULL,
            session_code VARCHAR(10) NOT NULL,
            duration_minutes INT NOT NULL DEFAULT 10,
            deadline DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
            FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
            INDEX idx_unit_deadline (unit_id, deadline),
            INDEX idx_deadline (deadline)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Created attendance_sessions table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create attendance_sessions table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ attendance_sessions table already exists</p>";
    }
    
    // Create student_attendance_codes table
    $result = $conn->query("SHOW TABLES LIKE 'student_attendance_codes'");
    if ($result->num_rows === 0) {
        $sql = "CREATE TABLE student_attendance_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            code VARCHAR(6) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            INDEX idx_session_student (session_id, student_id),
            INDEX idx_code_expires (code, expires_at),
            INDEX idx_student_session (student_id, session_id, expires_at),
            UNIQUE KEY unique_session_student_code (session_id, student_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Created student_attendance_codes table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create student_attendance_codes table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ student_attendance_codes table already exists</p>";
        
        // Check if all required columns exist
        $columns = ['session_id', 'student_id', 'code', 'expires_at', 'used_at', 'created_at'];
        $existing_columns = [];
        
        $result = $conn->query("SHOW COLUMNS FROM student_attendance_codes");
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
        
        foreach ($columns as $column) {
            if (!in_array($column, $existing_columns)) {
                echo "<p style='color: orange;'>⚠️ Missing column: $column</p>";
            }
        }
    }
    
    // Check if attendance_records table exists for tracking
    $result = $conn->query("SHOW TABLES LIKE 'attendance_records'");
    if ($result->num_rows === 0) {
        $sql = "CREATE TABLE attendance_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            attended TINYINT(1) DEFAULT 0,
            attended_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            INDEX idx_session_student (session_id, student_id),
            INDEX idx_attended (attended),
            UNIQUE KEY unique_session_student (session_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Created attendance_records table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create attendance_records table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ attendance_records table already exists</p>";
    }
    
    // Create notifications table if missing. The enhanced attendance flow writes a
    // per-student notification before sending emails, so this table must exist.
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result->num_rows === 0) {
        $sql = "CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_role ENUM('student','lecturer','admin') NOT NULL DEFAULT 'student',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            notes_id INT DEFAULT NULL,
            assignment_id INT DEFAULT NULL,
            attendance_session_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id, user_role),
            INDEX idx_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Created notifications table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create notifications table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ notifications table already exists</p>";
    }
    
    echo "<h3>Migration Complete!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Update lecturer attendance system to use new enhanced functions</li>";
    echo "<li>Add attendance modal to student dashboard</li>";
    echo "<li>Test the enhanced attendance flow</li>";
    echo "</ol>";
    
    echo "<h3>Database Schema Summary:</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>student_attendance_codes:</h4>";
    echo "<ul>";
    echo "<li><strong>session_id:</strong> Links to attendance_sessions</li>";
    echo "<li><strong>student_id:</strong> Links to students table</li>";
    echo "<li><strong>code:</strong> Unique 6-digit code for student</li>";
    echo "<li><strong>expires_at:</strong> 2-minute expiry timestamp</li>";
    echo "<li><strong>used_at:</strong> When code was used (NULL if unused)</li>";
    echo "</ul>";
    
    echo "<h4>attendance_records:</h4>";
    echo "<ul>";
    echo "<li><strong>session_id:</strong> Links to attendance_sessions</li>";
    echo "<li><strong>student_id:</strong> Links to students table</li>";
    echo "<li><strong>attended:</strong> Boolean (0=not attended, 1=attended)</li>";
    echo "<li><strong>attended_at:</strong> Timestamp when attendance was marked</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Migration error: " . $e->getMessage() . "</p>";
}
?>
