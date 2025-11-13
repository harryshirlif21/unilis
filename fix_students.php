<?php
require_once __DIR__ . "/config/db.php";

// Step 0: Temporarily disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
echo "<p>⚙️ Foreign key checks disabled temporarily.</p>";

// Step 1: Truncate dependent and main tables
$tablesToTruncate = ['notifications', 'meeting_attendance', 'meetings'];

foreach ($tablesToTruncate as $table) {
    $sql = "TRUNCATE TABLE $table";
    if ($conn->query($sql)) {
        echo "<p>✅ Table <strong>$table</strong> truncated successfully.</p>";
    } else {
        echo "<p>❌ Could not truncate table <strong>$table</strong>: " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Step 2: Alter meetings.id to AUTO_INCREMENT
$alter = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
if ($conn->query($alter)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 3: Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
echo "<p>🔒 Foreign key checks re-enabled.</p>";

// Step 4: Recreate foreign keys
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
        echo "<p>✅ Foreign key added successfully.</p>";
    } else {
        echo "<p>❌ Could not add foreign key: " . htmlspecialchars($conn->error) . "</p>";
    }
}

$conn->close();
echo "<p>🎯 All steps completed successfully.</p>";
?>
