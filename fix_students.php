<?php
// Include database connection
require_once 'config/db.php'; // assumes $conn is defined

// SQL to alter table
$sql = "
ALTER TABLE courses
ADD COLUMN duration INT NOT NULL DEFAULT 1 AFTER department_id,
ADD COLUMN course_type VARCHAR(50) NOT NULL DEFAULT 'Degree' AFTER duration
";

// Execute query
if ($conn->query($sql) === TRUE) {
    echo "Table altered successfully. Columns 'duration' and 'course_type' added.<br>";
} else {
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "One or both columns already exist.<br>";
    } else {
        echo "Error altering table: " . $conn->error . "<br>";
    }
}

// Close connection
$conn->close();
?>