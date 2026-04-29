<?php
try {
    $pdo = new PDO("mysql:host=unilis-db;dbname=unilis_smartlab;charset=utf8mb4", 'root', 'rootpass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'device_fingerprint'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN `device_fingerprint` varchar(255) DEFAULT NULL AFTER `biometric_hash`");
        echo "✅ device_fingerprint column added!";
    } else {
        echo "✅ Already exists!";
    }

    // qr_sessions table for pending QR logins
    $pdo->exec("CREATE TABLE IF NOT EXISTS `qr_sessions` (
        `id` varchar(36) NOT NULL,
        `token` varchar(255) NOT NULL,
        `status` enum('pending','claimed','expired') DEFAULT 'pending',
        `user_id` varchar(36) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `expires_at` timestamp NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<br>✅ qr_sessions table ready!";

} catch (PDOException $e) {
    echo "❌ " . $e->getMessage();
}
