<?php
// Check database structure script
require_once 'config/app.php';
require_once 'config/database.php';

try {
    $db = getDB();
    
    echo "<h2>SmartLab Database Structure</h2>";
    
    // Get all tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Existing Tables:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong></li>";
        
        // Show structure for key tables
        if (in_array($table, ['otp_codes', 'qr_sessions', 'lab_attendance', 'practicals', 'users'])) {
            echo "<ul>";
            $columns = $db->query("DESCRIBE $table")->fetchAll();
            foreach ($columns as $column) {
                echo "<li>{$column['Field']} ({$column['Type']})</li>";
            }
            echo "</ul>";
        }
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
