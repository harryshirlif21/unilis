<?php
require_once __DIR__ . "/config/db.php";

// Step 0: Truncate dependent tables first to remove FK conflicts
$tablesToTruncate = ['notifications', 'meeting_attendance', 'meetings'];

foreach ($tablesToTruncate as $table) {
    $sql = "TRUNCATE TABLE $table";
    if ($conn->query($sql)) {
        echo "<p>✅ Table <strong>$table</strong> truncated successfully.</p>";
    } else {
        echo "<p>❌ Could not truncate table <strong>$table</strong>: " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Step 1: Alter meetings.id to AUTO_INCREMENT
$alter = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
if ($conn->query($alter)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 2: Re-add foreign keys for dependent tables
$fkQueries = [
    "ALTER TABLE notifications
        ADD CONSTRAINT fk_notifications_meetings
        FOREIGN KEY (meeting_id) REFERENCES meetings(id)
        ON DELETE SET NULL ON UPDATE CASCADE",
    "ALTER TABLE meeting_attendance
        ADD CONSTRAINT meeting_attendance_ibfk_1
        FOREIGN KEY (meeting_id) REFERENCES meetings(id)
        ON DELETE CASCADE"
];

foreach ($fkQueries as $sql) {
    if ($conn->query($sql)) {
        echo "<p>✅ Foreign key added: " . htmlspecialchars($sql) . "</p>";
    } else {
        echo "<p>❌ Could not add foreign key: " . htmlspecialchars($conn->error) . "</p>";
    }
}

$conn->close();
?>
