<?php
require_once 'config/db.php'; // assumes $conn is defined

// Disable strict mysqli error throwing (prevents fatal crash)
mysqli_report(MYSQLI_REPORT_OFF);

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

// course_type has been retired — drop it if an older database still carries it
if (columnExists($conn, 'courses', 'course_type')) {
    if ($conn->query("ALTER TABLE courses DROP COLUMN course_type")) {
        echo "Column 'course_type' dropped successfully.<br><br>";
    } else {
        echo "Error dropping course_type: " . $conn->error . "<br><br>";
    }
} else {
    echo "Column 'course_type' already removed.<br><br>";
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