<?php
try {
    $pdo = new PDO("mysql:host=unilis-db;dbname=unilis_smartlab;charset=utf8mb4", 'root', 'rootpass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Check if column already exists before adding
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'department'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `role`");
        echo "✅ Department column added!";
    } else {
        echo "✅ Department column already exists — nothing to do!";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
