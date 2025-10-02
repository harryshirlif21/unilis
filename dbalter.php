<?php
// include your db connection
require_once __DIR__ . "/config/db.php";

// SQL to alter the table
$sql = "
ALTER TABLE notifications
ADD COLUMN note_id INT NULL,
ADD COLUMN assignment_id INT NULL,
ADD COLUMN interactive_assignment_id INT NULL,
ADD COLUMN meeting_id INT NULL
";

// execute query
if ($conn->query($sql) === TRUE) {
    echo "✅ Notifications table updated successfully!";
} else {
    echo "❌ Error updating table: " . $conn->error;
}

// close connection
$conn->close();
?>
