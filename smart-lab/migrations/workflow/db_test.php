<?php
$basePath = __DIR__ . '/../../';
require_once $basePath . 'config/database_production.php';
try {
    $db = getDB();
    echo "DB OK\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}