<?php
require_once '../config/db.php';

$check = $conn->query("SHOW COLUMNS FROM notes LIKE 'status'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE notes ADD COLUMN status ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active'");
    echo "✅ Column 'status' added to notes table successfully.<br>";
    
    // Set all existing notes to active
    $conn->query("UPDATE notes SET status = 'active' WHERE status IS NULL OR status = ''");
    echo "✅ All existing notes set to 'active'.<br>";
} else {
    echo "ℹ️ Column 'status' already exists in notes table.<br>";
}

echo "<br><a href='../lecturer/dashboard.php'>← Back to Dashboard</a>";
?>