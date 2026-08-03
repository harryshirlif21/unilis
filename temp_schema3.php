<?php
require_once __DIR__ . '/teams/config.php';
if (!isset($conn)) {
    echo "conn missing\n";
    exit(1);
}
$r = $conn->query('SHOW TABLES');
if (!$r) {
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
}
while ($row = $r->fetch_row()) {
    echo $row[0] . "\n";
}
