<?php
// Include DB connection
require_once __DIR__ . "/config/db.php";

// Step 1: Make sure meetings.id is AUTO_INCREMENT
$sql = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT";
if ($conn->query($sql)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 2: Optional - check structure after modification
$result = $conn->query("DESCRIBE meetings");
if ($result) {
    echo "<h2>Updated Meetings Table Structure</h2>";
    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $col) {
            echo "<td>" . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Error fetching structure: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 3: Close connection
$conn->close();
?>
