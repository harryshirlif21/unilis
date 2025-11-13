<?php
require_once __DIR__ . "/config/db.php";

// --- Step 0: Fix any problematic IDs (avoid 0 or duplicates) ---
$conn->query("UPDATE meetings SET id = NULL WHERE id = 0");

// --- Step 1: Temporarily drop foreign key from notifications ---
$dropFK = "ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_meetings";
if ($conn->query($dropFK)) {
    echo "<p>✅ Dropped foreign key fk_notifications_meetings temporarily.</p>";
} else {
    echo "<p>ℹ️ Could not drop foreign key (might not exist): " . htmlspecialchars($conn->error) . "</p>";
}

// --- Step 2: Make meetings.id AUTO_INCREMENT ---
$alter = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT";
if ($conn->query($alter)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// --- Step 3: Re-add the foreign key to notifications ---
$addFK = "ALTER TABLE notifications
          ADD CONSTRAINT fk_notifications_meetings
          FOREIGN KEY (meeting_id) REFERENCES meetings(id)
          ON DELETE SET NULL
          ON UPDATE CASCADE";
if ($conn->query($addFK)) {
    echo "<p>✅ Re-added foreign key fk_notifications_meetings.</p>";
} else {
    echo "<p>❌ Could not re-add foreign key: " . htmlspecialchars($conn->error) . "</p>";
}

// --- Step 4: Show updated meetings table structure ---
$result = $conn->query("DESCRIBE meetings");
if ($result) {
    echo "<h2>Updated Meetings Table Structure</h2>";
    echo "<table border='1' cellpadding='5'>
            <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $col) {
            echo "<td>" . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Error fetching meetings table structure: " . htmlspecialchars($conn->error) . "</p>";
}

// --- Step 5: Close connection ---
$conn->close();
?>
