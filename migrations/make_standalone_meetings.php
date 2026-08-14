<?php
/** Run once by a database administrator before using meeting_portal.php. */
require_once __DIR__ . '/../config/db.php';

$column = $conn->query("SHOW COLUMNS FROM meetings LIKE 'unit_id'");
if (!$column || $column->num_rows === 0) {
    exit("The meetings.unit_id column was not found.\n");
}
$definition = $column->fetch_assoc();
if (strtoupper((string)$definition['Null']) !== 'YES') {
    if (!$conn->query('ALTER TABLE meetings MODIFY unit_id INT NULL')) {
        exit('Could not make meetings.unit_id nullable: ' . $conn->error . "\n");
    }
    echo "meetings.unit_id is now nullable.\n";
} else {
    echo "meetings.unit_id already accepts standalone meetings.\n";
}
