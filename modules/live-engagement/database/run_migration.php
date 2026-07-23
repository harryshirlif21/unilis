<?php
require_once dirname(__DIR__) . '/../../config/db.php';

if (isset($conn)) {
    $result = $conn->query("ALTER TABLE live_presentations ADD COLUMN created_by INT UNSIGNED NULL AFTER presenter_notes");
    if ($result) {
        echo "Column created_by added successfully to live_presentations";
    } else {
        echo "Failed to add column: " . $conn->error;
    }
} else {
    echo "Database connection failed";
}
