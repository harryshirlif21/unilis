<?php
/**
 * Migration: Add notebook creator columns
 * Date: 2026-06-05
 * Description: Add created_by and creator_role required by notebook create/list queries
 */

require_once __DIR__ . '/../config/database_production.php';

try {
    $db = getDB();

    echo "Starting migration: Add notebook creator columns\n";
    echo "------------------------------------------------------------\n";

    $columns = [
        'created_by' => "ALTER TABLE notebooks ADD COLUMN created_by CHAR(36) DEFAULT NULL AFTER updated_at",
        'creator_role' => "ALTER TABLE notebooks ADD COLUMN creator_role VARCHAR(20) DEFAULT NULL AFTER created_by",
    ];

    foreach ($columns as $name => $sql) {
        $check = $db->query("SHOW COLUMNS FROM notebooks LIKE '$name'");
        if ($check->rowCount() > 0) {
            echo "⊘ $name column already exists, skipping\n";
            continue;
        }

        echo "Adding $name column...\n";
        $db->exec($sql);
        echo "✓ $name column added successfully\n";
    }

    // Allow notebooks without a linked lab session (optional session on create form)
    $sessionCol = $db->query("SHOW COLUMNS FROM notebooks LIKE 'session_id'")->fetch();
    if ($sessionCol && strtoupper($sessionCol['Null']) === 'NO') {
        echo "Making session_id nullable...\n";
        $db->exec("ALTER TABLE notebooks MODIFY session_id CHAR(36) NULL");
        echo "✓ session_id is now nullable\n";
    } else {
        echo "⊘ session_id already nullable or missing, skipping\n";
    }

    echo "\n------------------------------------------------------------\n";
    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
