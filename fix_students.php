<?php
// include your existing DB connection
require_once __DIR__ . "/config/db.php";

// Drop the two columns from the notifications table if they exist
$columns = ['user_id', 'user_role'];

foreach ($columns as $column) {
    $check = $conn->query("SHOW COLUMNS FROM notifications LIKE '$column'");
    if ($check && $check->num_rows > 0) {
        $sql = "ALTER TABLE notifications DROP COLUMN $column";
        if ($conn->query($sql)) {
            echo "<p>✅ Column <strong>$column</strong> removed successfully.</p>";
        } else {
            echo "<p>❌ Error removing <strong>$column</strong>: " . htmlspecialchars($conn->error) . "</p>";
        }
    } else {
        echo "<p>ℹ️ Column <strong>$column</strong> does not exist or was already removed.</p>";
    }
}

// Confirm final structure
echo "<h2>Updated Notifications Table Structure</h2>";
$result = $conn->query("DESCRIBE notifications");
if ($result) {
    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>
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
    echo "Error fetching structure: " . htmlspecialchars($conn->error);
}

$conn->close();
?>
