<?php
require_once __DIR__ . '/smart-lab/config/database_production.php';
$pdo = getProductionDB();

// Disable FK checks, force drop, re-enable, then create clean
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DROP TABLE IF EXISTS rfid_cards");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "<p>Cleared rfid_cards</p>";

try {
    $pdo->exec("CREATE TABLE rfid_cards (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(36) NOT NULL,
        uid        VARCHAR(100) NOT NULL,
        device_id  VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_uid (uid),
        INDEX      idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<p style='color:#166534;font-weight:700'>✓ rfid_cards created successfully</p>";

    $cols = $pdo->query("DESCRIBE rfid_cards")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border=1 cellpadding=6 style='font-size:13px;border-collapse:collapse'>";
    echo "<tr><th>Column</th><th>Type</th><th>Key</th></tr>";
    foreach ($cols as $c)
        echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Key']}</td></tr>";
    echo "</table><br><a href='dbtables.php'>← view all tables</a>";

} catch (PDOException $e) {
    echo "<p style='color:#dc2626'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
