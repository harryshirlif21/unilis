<?php
require_once __DIR__ . "/config/db.php"; // offline DB connection

// Step 1: Delete the row with id = 0
$deleteSql = "DELETE FROM meetings WHERE id = 0";
if ($conn->query($deleteSql)) {
    echo "<p>✅ Row with id=0 deleted successfully.</p>";
} else {
    echo "<p>❌ Error deleting row: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 2: Reset AUTO_INCREMENT on meetings.id
$alterSql = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
if ($conn->query($alterSql)) {
    echo "<p>✅ meetings.id column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 3: Display the updated meetings table
$sql = "SELECT * FROM meetings";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h2>Meetings Table</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>";
    while ($field = $result->fetch_field()) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p>No records found in the meetings table.</p>";
}

$conn->close();
?>
