<?php
require_once __DIR__ . '/smart-lab/config/database_production.php';
$pdo = getProductionDB();

// Drop and recreate with explicit charset matching users.id
try {
    // First check if it exists already (partial from failed attempt)
    $exists = $pdo->query("SHOW TABLES LIKE 'rfid_cards'")->rowCount() > 0;
    if ($exists) {
        $pdo->exec("DROP TABLE rfid_cards");
        echo "<p style='color:#92400e'>Dropped existing rfid_cards table</p>";
    }

    // Get exact column definition of users.id to match it
    $col = $pdo->query("SHOW FULL COLUMNS FROM users WHERE Field='id'")->fetch();
    echo "<p>users.id — Type: <strong>{$col['Type']}</strong> | Collation: <strong>{$col['Collation']}</strong></p>";

    $pdo->exec("CREATE TABLE rfid_cards (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        uid        VARCHAR(100) NOT NULL,
        device_id  VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_uid (uid),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    echo "<p style='color:#166534;font-weight:600'>✓ rfid_cards created successfully</p>";
    echo "<p><a href='dbtables.php'>← view all tables</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:#dc2626'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    // Show users table collation info to diagnose
    $info = $pdo->query("SELECT CCSA.character_set_name, CCSA.collation_name
        FROM information_schema.TABLES T
        JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY CCSA
            ON CCSA.collation_name = T.table_collation
        WHERE T.table_schema = DATABASE() AND T.table_name = 'users'")->fetch();
    echo "<p>users table charset: <strong>{$info['character_set_name']}</strong> collation: <strong>{$info['collation_name']}</strong></p>";
}
