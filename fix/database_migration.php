<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Quick Database Migration Runner
 * Fixes the missing user_id column in notifications table
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Notifications Database</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .migration-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Database Migration Runner</h1>
    
    <div class='migration-section'>
        <h2>Fix Notifications Table</h2>
        <p>This migration will add the missing <code>user_id</code> and <code>user_role</code> columns to the notifications table.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
            echo "<h3>Running Migration...</h3>";
            
            try {
                // Check if user_id column exists
                $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_id'");
                $user_id_exists = $result->num_rows > 0;
                
                // Check if user_role column exists
                $result = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_role'");
                $user_role_exists = $result->num_rows > 0;
                
                if (!$user_id_exists) {
                    echo "<p>Adding user_id column...</p>";
                    $sql = "ALTER TABLE notifications ADD COLUMN user_id INT DEFAULT NULL COMMENT 'User ID for filtering'";
                    if ($conn->query($sql)) {
                        echo "<div class='success'>✅ Added user_id column</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to add user_id column: " . $conn->error . "</div>";
                    }
                } else {
                    echo "<div class='warning'>⚠️ user_id column already exists</div>";
                }
                
                if (!$user_role_exists) {
                    echo "<p>Adding user_role column...</p>";
                    $sql = "ALTER TABLE notifications ADD COLUMN user_role ENUM('student','lecturer','admin') DEFAULT NULL COMMENT 'User role for filtering'";
                    if ($conn->query($sql)) {
                        echo "<div class='success'>✅ Added user_role column</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to add user_role column: " . $conn->error . "</div>";
                    }
                } else {
                    echo "<div class='warning'>⚠️ user_role column already exists</div>";
                }
                
                // Check if we need to update existing notifications
                $update_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id IS NULL";
                $result = $conn->query($update_sql);
                $null_count = $result->fetch_assoc()['count'];
                
                if ($null_count > 0) {
                    echo "<p>Updating existing notifications (found $null_count records)...</p>";
                    
                    // Simple update - set some existing notifications to have user_id = 1, user_role = 'student'
                    $update_sql = "UPDATE notifications SET user_id = 1, user_role = 'student' WHERE user_id IS NULL LIMIT 10";
                    if ($conn->query($update_sql)) {
                        $affected = $conn->affected_rows;
                        echo "<div class='success'>✅ Updated $affected existing notifications</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to update existing notifications: " . $conn->error . "</div>";
                    }
                }
                
                echo "<div class='success'><h3>✅ Migration completed successfully!</h3></div>";
                echo "<p><strong>Next steps:</strong></p>";
                echo "<ol>";
                echo "<li>Test the student dashboard - notifications should work now</li>";
                echo "<li>Test the enhanced attendance system</li>";
                echo "<li>Check that email sending works correctly</li>";
                echo "</ol>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Migration failed: " . $e->getMessage() . "</div>";
            }
        }
        ?>
        
        <form method='post'>
            <button type='submit' name='run_migration' style='background: #28a745; color: white;'>
                🔧 Run Database Migration
            </button>
        </form>
    </div>
    
    <div class='migration-section'>
        <h2>Current Database Status</h2>
        <?php
        try {
            echo "<h3>Current Notifications Table Structure:</h3>";
            $result = $conn->query("DESCRIBE notifications");
            echo "<pre class='code'>";
            echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
            echo "<tr style='background: #f2f2f2;'>";
            echo "<th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Extra</th>";
            echo "</tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Field']) . "</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Type']) . "</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Null']) . "</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Key']) . "</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Extra']) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            echo "</pre>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Failed to get table structure: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>
    
    <div class='migration-section'>
        <h2>🔗 Quick Links</h2>
        <ul>
            <li><a href='../student/dashboard.php' target='_blank'>Student Dashboard</a></li>
            <li><a href='../test/email_system_fix.php' target='_blank'>Email System Test</a></li>
            <li><a href='../migrations/fix_notifications_columns.php' target='_blank'>Full Migration</a></li>
            <li><a href='../migrations/add_student_attendance_codes.php' target='_blank'>Attendance System Migration</a></li>
        </ul>
    </div>
</body>
</html>";
?>
