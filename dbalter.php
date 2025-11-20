<?php
// include your db connection
require_once __DIR__ . "/config/db.php";

// Fix for classnotes table - add missing subtopics_json column
$check_sql = "SHOW COLUMNS FROM classnotes LIKE 'subtopics_json'";
$result = $conn->query($check_sql);

if ($result->num_rows == 0) {
    // Column doesn't exist, so add it
    $alter_sql = "ALTER TABLE classnotes ADD COLUMN subtopics_json LONGTEXT NOT NULL AFTER title";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "✅ SUCCESS: Added 'subtopics_json' column to classnotes table.<br>";
        echo "The notes save functionality should now work properly!";
    } else {
        echo "❌ ERROR: Failed to add subtopics_json column: " . $conn->error;
    }
} else {
    echo "✅ 'subtopics_json' column already exists in classnotes table.<br>";
    echo "The notes save functionality should work properly!";
}

// close connection
$conn->close();
?>