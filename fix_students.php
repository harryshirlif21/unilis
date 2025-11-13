<?php
require_once __DIR__ . "/config/db.php"; // for your offline DB connection

// Query to fetch all meetings
$sql = "SELECT * FROM meetings";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h2>Meetings Table</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>";
    // Display column headers
    while ($field = $result->fetch_field()) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";

    // Display rows
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
