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

$lecturerColumn = $conn->query("SHOW COLUMNS FROM meetings LIKE 'lecturer_id'");
$lecturerDefinition = $lecturerColumn ? $lecturerColumn->fetch_assoc() : null;
if ($lecturerDefinition && strtoupper((string)$lecturerDefinition['Null']) !== 'YES') {
    if (!$conn->query('ALTER TABLE meetings MODIFY lecturer_id INT NULL')) {
        exit('Could not make meetings.lecturer_id nullable: ' . $conn->error . "\n");
    }
    echo "meetings.lecturer_id is now nullable.\n";
}

$columns = [
    'host_user_id' => 'ADD COLUMN host_user_id INT NULL AFTER lecturer_id',
    'host_role' => "ADD COLUMN host_role VARCHAR(32) NOT NULL DEFAULT 'lecturer' AFTER host_user_id",
    'host_name' => 'ADD COLUMN host_name VARCHAR(120) NULL AFTER host_role',
    'host_token' => 'ADD COLUMN host_token VARCHAR(64) NULL UNIQUE AFTER host_name',
];
foreach ($columns as $name => $sql) {
    $exists = $conn->query("SHOW COLUMNS FROM meetings LIKE '" . $conn->real_escape_string($name) . "'");
    if (!$exists || $exists->num_rows === 0) {
        if (!$conn->query('ALTER TABLE meetings ' . $sql)) exit('Could not add meetings.' . $name . ': ' . $conn->error . "\n");
        echo "meetings.$name added.\n";
    }
}

$guestColumns = [
    'guest_access' => 'ADD COLUMN guest_access TINYINT(1) NOT NULL DEFAULT 0',
    'guest_listed' => 'ADD COLUMN guest_listed TINYINT(1) NOT NULL DEFAULT 0',
    'guest_token' => 'ADD COLUMN guest_token VARCHAR(64) NULL',
    'guest_passcode' => 'ADD COLUMN guest_passcode VARCHAR(32) NULL',
];
foreach ($guestColumns as $name => $sql) {
    $exists = $conn->query("SHOW COLUMNS FROM meetings LIKE '" . $conn->real_escape_string($name) . "'");
    if (!$exists || $exists->num_rows === 0) {
        if (!$conn->query('ALTER TABLE meetings ' . $sql)) exit('Could not add meetings.' . $name . ': ' . $conn->error . "\n");
        echo "meetings.$name added.\n";
    }
}

$guestTable = $conn->query("SHOW TABLES LIKE 'meeting_guests'");
if (!$guestTable || $guestTable->num_rows === 0) {
    $sql = 'CREATE TABLE meeting_guests (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, meeting_id INT NOT NULL, learner_id INT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(190) NULL, session_key VARCHAR(64) UNIQUE, ip_address VARCHAR(45) NULL, joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_seen_at DATETIME NULL, INDEX idx_meeting_guest (meeting_id), CONSTRAINT fk_mg_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE) ENGINE=InnoDB';
    if (!$conn->query($sql)) exit('Could not create meeting_guests: ' . $conn->error . "\n");
    echo "meeting_guests created.\n";
}
