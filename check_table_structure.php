<?php
require_once 'config/db.php';

// Get the actual table structure
$result = $conn->query("DESCRIBE notifications");

echo "<h2>Notifications Table Structure</h2>";
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    echo "\n";
    foreach ($row as $key => $value) {
        echo "$key: $value | ";
    }
}
echo "</pre>";

// Also get the CREATE TABLE statement
$createTableResult = $conn->query("SHOW CREATE TABLE notifications");
$createTable = $createTableResult->fetch_assoc();
echo "<h2>CREATE TABLE Statement</h2>";
echo "<pre>";
echo $createTable['Create Table'];
echo "</pre>";

// List all columns
echo "<h2>Columns</h2>";
echo "<ul>";
$columnsResult = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'notifications' AND TABLE_SCHEMA = 'unilis'");
while ($col = $columnsResult->fetch_assoc()) {
    echo "<li>" . $col['COLUMN_NAME'] . "</li>";
}
echo "</ul>";
?>
