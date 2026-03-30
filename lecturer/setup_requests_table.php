<?php
// Script to create lecturer_file_requests table
require_once '../config/db.php';

echo "<h2>Creating lecturer_file_requests table...</h2>";

try {
    // Read the SQL file
    $sqlFile = '../database_setup/create_lecturer_file_requests_table.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL
    if ($conn->multi_query($sql)) {
        echo "<p style='color: green; font-weight: bold;'>✅ Table 'lecturer_file_requests' created successfully!</p>";
        
        // Clear any remaining results
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
        
        // Verify table was created
        $checkTable = $conn->query("DESCRIBE lecturer_file_requests");
        if ($checkTable) {
            echo "<p style='color: blue;'>✅ Table verified - structure:</p>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
            while ($row = $checkTable->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
echo "<p><a href='request_files.php'>→ Go to Request Files Page</a></p>";
?>
