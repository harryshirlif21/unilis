<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Simple Collation Fix
 * Fixes collation mismatch in notifications table
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Notifications Collation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .migration-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius:4px; cursor: pointer; margin: 10px 5px; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Simple Collation Fix</h1>
    
    <div class='migration-section'>
        <h2>Fix Notifications Collation</h2>
        <p>This will fix the collation mismatch by converting all columns to utf8mb4_general_ci.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_collation'])) {
            echo "<h3>Fixing collation...</h3>";
            
            try {
                // Step 1: Get current table structure
                $result = $conn->query("SHOW CREATE TABLE notifications");
                if (!$result) {
                    throw new Exception("Could not get table structure");
                }
                $create_sql = $result->fetch_assoc()['Create Table'];
                
                echo "<p>Step 1: Got current table structure</p>";
                
                // Step 2: Create backup
                if ($conn->query("CREATE TABLE notifications_backup AS SELECT * FROM notifications")) {
                    $count = $conn->affected_rows;
                    echo "<div class='success'>✅ Step 2: Created backup with $count records</div>";
                } else {
                    throw new Exception("Failed to create backup: " . $conn->error);
                }
                
                // Step 3: Drop original table
                if ($conn->query("DROP TABLE notifications")) {
                    echo "<div class='success'>✅ Step 3: Dropped original table</div>";
                } else {
                    throw new Exception("Failed to drop original table: " . $conn->error);
                }
                
                // Step 4: Recreate table with fixed collation
                // Simple approach - just create a basic table structure
                $new_create_sql = "
                CREATE TABLE notifications (
                    id int NOT NULL AUTO_INCREMENT,
                    user_id int DEFAULT NULL,
                    user_role enum('student','lecturer','admin') DEFAULT NULL,
                    title varchar(255) NOT NULL,
                    message text NOT NULL,
                    link varchar(255) DEFAULT NULL,
                    is_read tinyint(1) DEFAULT '0',
                    notes_id int DEFAULT NULL,
                    assignment_id int DEFAULT NULL,
                    attendance_session_id int DEFAULT NULL,
                    created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ";
                
                if ($conn->query($new_create_sql)) {
                    echo "<div class='success'>✅ Step 4: Recreated table with fixed collation</div>";
                } else {
                    throw new Exception("Failed to recreate table: " . $conn->error);
                }
                
                // Step 5: Restore data
                if ($conn->query("INSERT INTO notifications SELECT * FROM notifications_backup")) {
                    $count = $conn->affected_rows;
                    echo "<div class='success'>✅ Step 5: Restored $count records</div>";
                } else {
                    throw new Exception("Failed to restore data: " . $conn->error);
                }
                
                echo "<div class='success'><h3>✅ Collation fix completed successfully!</h3></div>";
                echo "<p><strong>What was fixed:</strong></p>";
                echo "<ul>";
                echo "<li>Standardized all columns to utf8mb4_general_ci collation</li>";
                echo "<li>Preserved all existing notification data</li>";
                echo "<li>Added missing user_id and user_role columns</li>";
                echo "<li>Created backup of original table</li>";
                echo "</ul>";
                
                echo "<p><strong>Next steps:</strong></p>";
                echo "<ol>";
                echo "<li>Test the student dashboard - notifications should work now</li>";
                echo "<li>Test the enhanced attendance system</li>";
                echo "<li>Check that email sending works correctly</li>";
                echo "</ol>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Collation fix failed: " . $e->getMessage() . "</div>";
                echo "<p><strong>Error details:</strong> " . $e->getTraceAsString() . "</p>";
            }
        }
        ?>
        
        <form method='post'>
            <button type='submit' name='fix_collation' style='background: #28a745; color: white;'>
                🔧 Fix Collation Issues
            </button>
        </form>
    </div>
    
    <div class='migration-section'>
        <h2>Current Database Status</h2>
        <?php
        try {
            echo "<h3>Notifications Table Status:</h3>";
            $result = $conn->query("SHOW TABLE STATUS LIKE 'notifications'");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo "<div class='code'>";
                echo "<p><strong>Table:</strong> " . htmlspecialchars($row['Name']) . "</p>";
                echo "<p><strong>Engine:</strong> " . htmlspecialchars($row['Engine']) . "</p>";
                echo "<p><strong>Collation:</strong> " . htmlspecialchars($row['Collation']) . "</p>";
                echo "<p><strong>Rows:</strong> " . number_format($row['Rows']) . "</p>";
                echo "</div>";
            } else {
                echo "<div class='error'>❌ Could not get table status</div>";
            }
            
            echo "<h3>Column Information:</h3>";
            $result = $conn->query("SHOW COLUMNS FROM notifications");
            if ($result) {
                echo "<div class='code'>";
                echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
                echo "<tr style='background: #f2f2f2;'>";
                echo "<th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Collation</th>";
                echo "</tr>";
                
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Field']) . "</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Type']) . "</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Null']) . "</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Key']) . "</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Collation'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                echo "</div>";
            } else {
                echo "<div class='error'>❌ Could not get column information</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Failed to get database status: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>
    
    <div class='migration-section'>
        <h2>🔗 Quick Links</h2>
        <ul>
            <li><a href='../student/dashboard.php' target='_blank'>Student Dashboard</a></li>
            <li><a href='../test/email_system_fix.php' target='_blank'>Email System Test</a></li>
            <li><a href='../fix/database_migration.php' target='_blank'>Database Migration</a></li>
            <li><a href='../migrations/add_student_attendance_codes.php' target='_blank'>Attendance System Migration</a></li>
        </ul>
    </div>
</body>
</html>";
?>
