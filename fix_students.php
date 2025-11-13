<?php
// Include DB connection
require_once __DIR__ . "/config/db.php";

// Ensure meetings.id is AUTO_INCREMENT
$sql = "ALTER TABLE meetings 
        MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
if ($conn->query($sql)) {
    echo "<p>✅ Meetings table 'id' column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>ℹ️ Meetings table 'id' already set or error: " . htmlspecialchars($conn->error) . "</p>";
}

$conn->close();
?>

