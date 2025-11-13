<?php
require_once __DIR__ . "/config/db.php";

// Step 0: Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Step 1: Recreate foreign keys related to 'meetings'

// notifications.meeting_id → meetings.id
$fk1 = "
ALTER TABLE notifications
ADD CONSTRAINT fk_notifications_meetings
FOREIGN KEY (meeting_id) REFERENCES meetings(id)
ON DELETE SET NULL
ON UPDATE CASCADE
";

// meeting_attendance.meeting_id → meetings.id
$fk2 = "
ALTER TABLE meeting_attendance
ADD CONSTRAINT meeting_attendance_ibfk_1
FOREIGN KEY (meeting_id) REFERENCES meetings(id)
ON DELETE CASCADE
ON UPDATE CASCADE
";

// meeting_signals.meeting_id → meetings.id
$fk3 = "
ALTER TABLE meeting_signals
ADD CONSTRAINT meeting_signals_ibfk_1
FOREIGN KEY (meeting_id) REFERENCES meetings(id)
ON DELETE CASCADE
ON UPDATE CASCADE
";

// Execute all queries and report
$fkQueries = [$fk1, $fk2, $fk3];

foreach ($fkQueries as $sql) {
    if ($conn->query($sql)) {
        echo "<p>✅ Foreign key added successfully.</p>";
    } else {
        echo "<p>❌ Could not add foreign key: " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Step 2: Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

$conn->close();
echo "<p>🎯 All foreign keys restored successfully.</p>";
?>
