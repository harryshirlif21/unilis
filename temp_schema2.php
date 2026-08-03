<?php
require_once __DIR__ . '/teams/config.php';
if (!isset($conn)) {
    echo "conn missing\n";
    exit(1);
}
$patterns = ['%tech%', '%technician%', '%technicians%', '%admin%', '%lecturer%'];
foreach ($patterns as $pattern) {
    echo "TABLES LIKE $pattern:\n";
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($pattern) . "'");
    if (!$r) {
        echo "ERROR: " . $conn->error . "\n\n";
        continue;
    }
    if ($r->num_rows === 0) {
        echo "NOT FOUND\n\n";
        continue;
    }
    while ($row = $r->fetch_row()) {
        $t = $row[0];
        echo "TABLE: $t\n";
        $columns = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($t) . "`");
        if (!$columns) {
            echo "ERROR COLS: " . $conn->error . "\n\n";
            continue;
        }
        while ($f = $columns->fetch_assoc()) {
            echo $f['Field'] . "\t" . $f['Type'] . "\n";
        }
        echo "\n";
    }
}
