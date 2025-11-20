<?php
// include your db connection
require_once __DIR__ . "/config/db.php";

echo "Fixing classnotes table structure...<br>";

// Check and add missing columns
$missing_columns = [];

// Check subtopics_json column
$check_sql = "SHOW COLUMNS FROM classnotes LIKE 'subtopics_json'";
$result = $conn->query($check_sql);
if ($result->num_rows == 0) {
    $missing_columns[] = "ADD COLUMN subtopics_json LONGTEXT NOT NULL AFTER title";
}

// Check uploaded_by column
$check_sql = "SHOW COLUMNS FROM classnotes LIKE 'uploaded_by'";
$result = $conn->query($check_sql);
if ($result->num_rows == 0) {
    $missing_columns[] = "ADD COLUMN uploaded_by INT NOT NULL AFTER subtopics_json";
}

// Check uploaded_at column
$check_sql = "SHOW COLUMNS FROM classnotes LIKE 'uploaded_at'";
$result = $conn->query($check_sql);
if ($result->num_rows == 0) {
    $missing_columns[] = "ADD COLUMN uploaded_at DATETIME NOT NULL AFTER uploaded_by";
}

// Add all missing columns at once
if (!empty($missing_columns)) {
    $alter_sql = "ALTER TABLE classnotes " . implode(", ", $missing_columns);
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "✅ SUCCESS: Added missing columns to classnotes table:<br>";
        foreach ($missing_columns as $column) {
            echo "&nbsp;&nbsp;• " . str_replace("ADD COLUMN ", "", $column) . "<br>";
        }
    } else {
        echo "❌ ERROR: Failed to add columns: " . $conn->error . "<br>";
    }
} else {
    echo "✅ All required columns already exist in classnotes table.<br>";
}

echo "The notes save functionality should now work properly!";

// close connection
$conn->close();
?>