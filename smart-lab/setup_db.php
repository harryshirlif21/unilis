<?php
// Diagnostic - find available database

$hosts = ['smart-labs-db', 'unilis-db', 'db', 'localhost', '127.0.0.1'];
$user = 'root';
$pass = 'rootpass';

echo "<h2>Database Host Discovery</h2>";
foreach ($hosts as $host) {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4;connect_timeout=3", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "<p>✅ <strong>$host</strong> - CONNECTED! Databases: ";
        $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo implode(', ', $dbs) . "</p>";
    } catch (PDOException $e) {
        echo "<p>❌ <strong>$host</strong> - " . $e->getMessage() . "</p>";
    }
}

echo "<p>Server IP: " . $_SERVER['SERVER_ADDR'] . "</p>";
echo "<p>Host: " . $_SERVER['HTTP_HOST'] . "</p>";
