<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Database Migration for Notifications System
 * Adds missing user_id, user_role, and foreign key columns to notifications table
 */

echo "<h2>Notifications System Migration</h2>";

try {
    // Check if user_id column exists
    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_id'");
    if ($result->num_rows === 0) {
        $sql = "ALTER TABLE notifications ADD COLUMN user_id INT DEFAULT NULL COMMENT 'User ID for filtering'";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added user_id column to notifications table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add user_id column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ user_id column already exists in notifications table</p>";
    }
    
    // Check if user_role column exists
    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_role'");
    if ($result->num_rows === 0) {
        $sql = "ALTER TABLE notifications ADD COLUMN user_role ENUM('student','lecturer','admin') DEFAULT NULL COMMENT 'User role for filtering'";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added user_role column to notifications table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add user_role column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ user_role column already exists in notifications table</p>";
    }
    
    // Check if notes_id column exists
    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'notes_id'");
    if ($result->num_rows === 0) {
        $sql = "ALTER TABLE notifications ADD COLUMN notes_id INT DEFAULT NULL COMMENT 'Foreign key to notes table'";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added notes_id column to notifications table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add notes_id column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ notes_id column already exists in notifications table</p>";
    }
    
    // Check if assignment_id column exists
    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'assignment_id'");
    if ($result->num_rows === 0) {
        $sql = "ALTER TABLE notifications ADD COLUMN assignment_id INT DEFAULT NULL COMMENT 'Foreign key to assignments table'";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added assignment_id column to notifications table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add assignment_id column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ assignment_id column already exists in notifications table</p>";
    }
    
    // Check if attendance_session_id column exists
    $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'attendance_session_id'");
    if ($result->num_rows === 0) {
        $sql = "ALTER TABLE notifications ADD COLUMN attendance_session_id INT DEFAULT NULL COMMENT 'Foreign key to attendance_sessions table'";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Added attendance_session_id column to notifications table</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add attendance_session_id column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ attendance_session_id column already exists in notifications table</p>";
    }
    
    // Update existing notifications to have user_id and user_role based on context
    echo "<h3>Updating existing notifications...</h3>";
    
    // Update student notifications by matching content patterns
    $update_student_sql = "
        UPDATE notifications n
        JOIN students s ON (
            n.title LIKE '%Notes%' OR 
            n.title LIKE '%Assignment%' OR 
            n.title LIKE '%Attendance%'
        ) AND n.message LIKE CONCAT('%', s.name, '%')
        SET n.user_id = s.id,
            n.user_role = 'student'
        WHERE n.user_id IS NULL
        LIMIT 1000
    ";
    
    if ($conn->query($update_student_sql)) {
        $affected_student = $conn->affected_rows;
        echo "<p style='color: green;'>✅ Updated $affected_student student notifications with user_id and user_role</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to update student notifications: " . $conn->error . "</p>";
    }
    
    // Update lecturer notifications by matching content patterns
    $update_lecturer_sql = "
        UPDATE notifications n
        JOIN lecturers l ON (
            n.title LIKE '%Submission%' AND 
            n.message LIKE CONCAT('%submitted%', l.name, '%')
        ) OR (
            n.title LIKE '%Attendance%' AND
            n.message LIKE CONCAT('%started%', l.name, '%')
        )
        SET n.user_id = l.id,
            n.user_role = 'lecturer'
        WHERE n.user_id IS NULL
        LIMIT 1000
    ";
    
    if ($conn->query($update_lecturer_sql)) {
        $affected_lecturer = $conn->affected_rows;
        echo "<p style='color: green;'>✅ Updated $affected_lecturer lecturer notifications with user_id and user_role</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to update lecturer notifications: " . $conn->error . "</p>";
    }
    
    echo "<h3>Migration Complete!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Test notification filtering in student dashboard</li>";
    echo "<li>Verify notification display works correctly</li>";
    echo "<li>Check that user-specific notifications are shown</li>";
    echo "<li>Test attendance notifications work properly</li>";
    echo "</ol>";
    
    echo "<h3>Database Schema Summary:</h3>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>notifications table columns:</h4>";
    echo "<ul>";
    echo "<li><strong>id:</strong> Primary key</li>";
    echo "<li><strong>user_id:</strong> Links to students/lecturers/admins</li>";
    echo "<li><strong>user_role:</strong> ENUM('student','lecturer','admin')</li>";
    echo "<li><strong>title:</strong> Notification title</li>";
    echo "<li><strong>message:</strong> Notification message</li>";
    echo "<li><strong>link:</strong> Action link</li>";
    echo "<li><strong>is_read:</strong> Read status</li>";
    echo "<li><strong>notes_id:</strong> Foreign key to notes</li>";
    echo "<li><strong>assignment_id:</strong> Foreign key to assignments</li>";
    echo "<li><strong>attendance_session_id:</strong> Foreign key to attendance_sessions</li>";
    echo "<li><strong>created_at:</strong> Timestamp</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Migration error: " . $e->getMessage() . "</p>";
}
?>
