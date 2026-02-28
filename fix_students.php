<?php
require_once 'config/db.php'; // assumes $conn is defined

// Disable strict mysqli error throwing (prevents fatal crash)
mysqli_report(MYSQLI_REPORT_OFF);

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

// Only add course_type if it does NOT exist
if (!columnExists($conn, 'courses', 'course_type')) {
    $sql = "ALTER TABLE courses 
            ADD COLUMN course_type VARCHAR(50) NOT NULL DEFAULT 'Degree' AFTER duration";

    if ($conn->query($sql)) {
        echo "Column 'course_type' added successfully.<br><br>";
    } else {
        echo "Error adding course_type: " . $conn->error . "<br><br>";
    }
} else {
    echo "Column 'course_type' already exists.<br><br>";
}

// Show final table structure
$result = $conn->query("DESCRIBE courses");

if ($result) {
    echo "<h3>Courses Table Structure:</h3>";
    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr>
            <th>Field</th>
            <th>Type</th>
            <th>Null</th>
            <th>Key</th>
            <th>Default</th>
            <th>Extra</th>
          </tr>";

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
    echo "Error fetching structure: " . $conn->error;
}

$conn->close();
?>