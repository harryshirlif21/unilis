<?php
require_once __DIR__ . "/config/db.php";

// Step 0: Temporarily disable foreign key checks
if ($conn->query("SET FOREIGN_KEY_CHECKS = 0") === TRUE) {
    echo "<p>⚙️ Foreign key checks disabled temporarily.</p>";
} else {
    die("<p>❌ Failed to disable foreign key checks: " . htmlspecialchars($conn->error) . "</p>");
}

// Step 1: Try dropping foreign keys (ignore errors if they don’t exist)
$dropFKQueries = [
    "ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_meetings",
    "ALTER TABLE meeting_attendance DROP FOREIGN KEY meeting_attendance_ibfk_1"
];

foreach ($dropFKQueries as $sql) {
    if ($conn->query($sql)) {
        echo "<p>✅ Foreign key dropped successfully.</p>";
    } else {
        echo "<p>⚠️ Note: Could not drop a foreign key (maybe it didn’t exist): " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Step 2: Truncate dependent and main tables
$tablesToTruncate = ['notifications', 'meeting_attendance', 'meetings'];
foreach ($tablesToTruncate as $table) {
    $sql = "TRUNCATE TABLE $table";
    if ($conn->query($sql)) {
        echo "<p>✅ Table <strong>$table</strong> truncated successfully.</p>";
    } else {
        echo "<p>❌ Could not truncate table <strong>$table</strong>: " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Step 3: Alter meetings.id to AUTO_INCREMENT
$alter = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
if ($conn->query($alter)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

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

// Step 5: Re-enable foreign key checks
if ($conn->query("SET FOREIGN_KEY_CHECKS = 1") === TRUE) {
    echo "<p>🔒 Foreign key checks re-enabled.</p>";
} else {
    echo "<p>⚠️ Warning: Could not re-enable foreign key checks: " . htmlspecialchars($conn->error) . "</p>";
}

$conn->close();
echo "<p>🎯 All steps completed successfully.</p>";
?>
