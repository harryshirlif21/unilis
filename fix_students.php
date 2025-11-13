<?php
require_once __DIR__ . "/config/db.php";

// Step 0: Drop foreign keys referencing meetings.id
$dropFKs = [
    'fk_notifications_meetings',        // notifications table
    'meeting_attendance_ibfk_1'         // meeting_attendance table
];

foreach ($dropFKs as $fk) {
    $sql = "ALTER TABLE ";
    if ($fk === 'fk_notifications_meetings') $sql .= "notifications";
    if ($fk === 'meeting_attendance_ibfk_1') $sql .= "meeting_attendance";
    $sql .= " DROP FOREIGN KEY $fk";

    if ($conn->query($sql)) {
        echo "<p>✅ Dropped foreign key $fk temporarily.</p>";
    } else {
        echo "<p>❌ Could not drop foreign key $fk: " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Step 1: Make meetings.id AUTO_INCREMENT
$alter = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT";
if ($conn->query($alter)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 2: Re-add foreign keys
$reAddFKs = [
    'notifications' => "ALTER TABLE notifications
                        ADD CONSTRAINT fk_notifications_meetings
                        FOREIGN KEY (meeting_id) REFERENCES meetings(id)
                        ON DELETE SET NULL ON UPDATE CASCADE",
    'meeting_attendance' => "ALTER TABLE meeting_attendance
                             ADD CONSTRAINT meeting_attendance_ibfk_1
                             FOREIGN KEY (meeting_id) REFERENCES meetings(id)
                             ON DELETE CASCADE"
];

foreach ($reAddFKs as $table => $sql) {
    if ($conn->query($sql)) {
        echo "<p>✅ Re-added foreign key for table $table.</p>";
    } else {
        echo "<p>❌ Could not re-add foreign key for $table: " . htmlspecialchars($conn->error) . "</p>";
    }
}

$conn->close();
?>
