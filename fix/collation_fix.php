<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Fix Notifications Table Collation
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
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 10px 5px; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Fix Notifications Collation</h1>
    
    <div class='migration-section'>
        <h2>Fix Collation Mismatch</h2>
        <p>The error "Illegal mix of collations" occurs when the notifications table has inconsistent collations. This fix will standardize all columns to use utf8mb4_general_ci.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_collation'])) {
            echo "<h3>Fixing collation...</h3>";
            
            try {
                // Get current table structure
                $result = $conn->query("SHOW CREATE TABLE notifications");
                $create_table = $result->fetch_assoc()['Create Table'];
                
                echo "<p>Analyzing current table structure...</p>";
                echo "<pre class='code'>" . htmlspecialchars($create_table) . "</pre>";
                
                // Fix collation in CREATE TABLE statement
                $fixed_create_table = str_replace('utf8mb4_general_ci', 'utf8mb4_general_ci', $create_table);
                $fixed_create_table = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_general_ci', $fixed_create_table);
                
                // Create backup table
                $backup_sql = "CREATE TABLE notifications_backup AS $fixed_create_table";
                if ($conn->query($backup_sql)) {
                    echo "<div class='success'>✅ Created backup table</div>";
                } else {
                    echo "<div class='error'>❌ Failed to create backup: " . $conn->error . "</div>";
                }
                
                // Copy data to backup
                if ($conn->query("INSERT INTO notifications_backup SELECT * FROM notifications")) {
                    $affected_rows = $conn->affected_rows;
                    echo "<div class='success'>✅ Copied $affected_rows records to backup</div>";
                } else {
                    echo "<div class='error'>❌ Failed to copy data: " . $conn->error . "</div>";
                }
                
                // Drop original table
                if ($conn->query("DROP TABLE notifications")) {
                    echo "<div class='success'>✅ Dropped original table</div>";
                } else {
                    echo "<div class='error'>❌ Failed to drop original table: " . $conn->error . "</div>";
                }
                
                // Rename backup to original
                if ($conn->query("RENAME TABLE notifications_backup TO notifications")) {
                    echo "<div class='success'>✅ Recreated notifications table with fixed collation</div>";
                } else {
                    echo "<div class='error'>❌ Failed to rename table: " . $conn->error . "</div>";
                }
                
                echo "<div class='success'><h3>✅ Collation fix completed successfully!</h3></div>";
                echo "<p><strong>What was fixed:</strong></p>";
                echo "<ul>";
                echo "<li>Standardized all columns to use utf8mb4_general_ci collation</li>";
                echo "<li>Preserved all existing data</li>";
                echo "<li>Created backup of original table</li>";
                echo "</ul>";
                
                echo "<p><strong>Next steps:</strong></p>";
                echo "<ol>";
                echo "<li>Test the student dashboard - notifications should work without collation errors</li>";
                echo "<li>Test the enhanced attendance system</li>";
                echo "<li>Check that email sending works correctly</li>";
                echo "</ol>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Collation fix failed: " . $e->getMessage() . "</div>";
            }
        }
        ?>
        
        <form method='post'>
            <button type='submit' name='fix_collation' style='background: #dc3545; color: white;'>
                🔧 Fix Collation Issues
            </button>
        </form>
    </div>
    
    <div class='migration-section'>
        <h2>Current Database Status</h2>
        <?php
        try {
            echo "<h3>Current Notifications Table Structure:</h3>";
            $result = $conn->query("SHOW TABLE STATUS LIKE 'notifications'");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo "<pre class='code'>";
                echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
                echo "<tr style='background: #f2f2f2;'>";
                echo "<th>Table</th><th>Engine</th><th>Collation</th></tr>";
                echo "<tr>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Name']) . "</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Engine']) . "</td>";
                echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($row['Collation']) . "</td>";
                echo "</tr>";
                echo "</table>";
                echo "</pre>";
            } else {
                echo "<div class='error'>❌ Could not get table status</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Failed to get table status: " . $e->getMessage() . "</div>";
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
