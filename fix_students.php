<?php
// include your existing DB connection
require_once __DIR__ . "/config/db.php";

// === Step 1: Rename note_id → notes_id ===
$check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'note_id'");
if ($check && $check->num_rows > 0) {
    // Fetch the column type to preserve it
    $colData = $check->fetch_assoc();
    // Rename while preserving type
    $sql = "ALTER TABLE notifications CHANGE note_id notes_id INT";
    if ($conn->query($sql)) {
        echo "<p>✅ Column <strong>note_id</strong> successfully renamed to <strong>notes_id</strong>.</p>";
    } else {
        echo "<p>❌ Error renaming column: " . htmlspecialchars($conn->error) . "</p>";
    }
} else {
    echo "<p>ℹ️ Column <strong>note_id</strong> does not exist or was already renamed.</p>";
}

// === Step 2: Confirm updated table structure ===
echo "<h2>Updated Notifications Table Structure</h2>";
$result = $conn->query("DESCRIBE notifications");

if ($result) {
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
    echo "Error fetching structure: " . htmlspecialchars($conn->error);
}

$conn->close();
?>
