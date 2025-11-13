<?php
require_once __DIR__ . "/config/db.php";

// Step 1: Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Step 2: Truncate the meetings table (this deletes all rows and resets AUTO_INCREMENT)
$truncateSql = "TRUNCATE TABLE meetings";
if ($conn->query($truncateSql)) {
    echo "<p>✅ All rows deleted and AUTO_INCREMENT reset.</p>";
} else {
    echo "<p>❌ Could not truncate table: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 3: Ensure id column is AUTO_INCREMENT
$alterSql = "ALTER TABLE meetings MODIFY id INT NOT NULL AUTO_INCREMENT";
if ($conn->query($alterSql)) {
    echo "<p>✅ meetings.id column is now AUTO_INCREMENT.</p>";
} else {
    echo "<p>❌ Error setting AUTO_INCREMENT: " . htmlspecialchars($conn->error) . "</p>";
}

// Step 4: Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

$conn->close();
?>
