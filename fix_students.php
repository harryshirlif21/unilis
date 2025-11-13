<?php
require_once __DIR__ . "/config/db.php";

// Step 0: Drop foreign key from notifications table
$dropFK = "ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_meetings";
if ($conn->query($dropFK)) {
    echo "<p>✅ Dropped foreign key fk_notifications_meetings temporarily.</p>";
} else {
    echo "<p>❌ Could not drop foreign key: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 1: Make meetings.id AUTO_INCREMENT
$alter = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT";
if ($conn->query($alter)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 2: Re-add the foreign key
$addFK = "ALTER TABLE notifications
          ADD CONSTRAINT fk_notifications_meetings
          FOREIGN KEY (meeting_id) REFERENCES meetings(id)
          ON DELETE SET NULL ON UPDATE CASCADE";
if ($conn->query($addFK)) {
    echo "<p>✅ Re-added foreign key fk_notifications_meetings.</p>";
} else {
    echo "<p>❌ Could not re-add foreign key: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 3: Close connection
$conn->close();
?>
