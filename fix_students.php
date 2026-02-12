<?php
// Include database connection from config
require_once 'config/db.php'; // assumes $conn is defined inside db.php

// SQL to alter table
$sql = "
ALTER TABLE interactive_answers
ADD COLUMN marks_awarded INT NOT NULL DEFAULT 0
AFTER answer_text
";

// Execute query
if ($conn->query($sql) === TRUE) {
    echo "Table altered successfully. Column 'marks_awarded' added.<br>";
} else {
    // If column already exists, show message
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "Column 'marks_awarded' already exists.<br>";
    } else {
        echo "Error altering table: " . $conn->error . "<br>";
    }
}

// Optional: Show final table structure
$result = $conn->query("DESCRIBE interactive_answers");
if ($result) {
    echo "<h3>Final Table Structure:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['Field']}</td>
                <td>{$row['Type']}</td>
                <td>{$row['Null']}</td>
                <td>{$row['Key']}</td>
                <td>{$row['Default']}</td>
                <td>{$row['Extra']}</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "Error fetching table structure: " . $conn->error;
}

// Close connection
$conn->close();
?>
