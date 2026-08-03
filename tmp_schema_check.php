<?php
require 'config/db.php';
$res = $conn->query("SHOW TABLES LIKE 'technicians'");
echo 'tables: ' . ($res ? $res->num_rows : 0) . PHP_EOL;
if ($res && $res->num_rows) {
    while ($row = $res->fetch_row()) {
        echo $row[0] . PHP_EOL;
    }
    $cols = $conn->query('SHOW COLUMNS FROM technicians');
    while ($col = $cols->fetch_assoc()) {
        echo $col['Field'] . ' ' . $col['Type'] . PHP_EOL;
    }
}
