<?php
try {
    $pdo = new PDO("mysql:host=unilis-db;dbname=unilis_smartlab;charset=utf8mb4", 'root', 'rootpass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `department` varchar(100) DEFAULT NULL AFTER `role`");
    echo "✅ Department column added successfully!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
