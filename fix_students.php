<?php
// include your existing DB connection
require_once __DIR__ . "/config/db.php";

// get table structure
echo "<h2>Notifications Table Structure</h2>";
$result = $conn->query("DESCRIBE notifications");
if ($result) {
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $col) {
            echo "<td>" . htmlspecialchars($col) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error fetching structure: " . $conn->error;
}

// get sample data
echo "<h2>Sample Data from Notifications</h2>";
$data = $conn->query("SELECT * FROM notifications LIMIT 10");
if ($data && $data->num_rows > 0) {
    echo "<table border='1' cellpadding='5'><tr>";
    // print column headers
    while ($field = $data->fetch_field()) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";
    // print rows
    $data->data_seek(0);
    while ($row = $data->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No data found or error: " . $conn->error;
}

$conn->close();
?>
