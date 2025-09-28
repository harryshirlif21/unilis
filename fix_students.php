<?php
require_once 'config/db.php'; // adjust path if needed

$sql = "ALTER TABLE students
        MODIFY id INT(11) NOT NULL AUTO_INCREMENT";

if ($conn->query($sql) === TRUE) {
    echo "Students table fixed successfully!";
} else {
    echo "Error updating table: " . $conn->error;
}
?>
