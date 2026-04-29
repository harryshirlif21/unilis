<?php
$host = 'unilis-db';
$user = 'root';
$pass = 'rootpass';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS unilis_smartlab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER IF NOT EXISTS 'lab_admin'@'%' IDENTIFIED BY 'lab_password'");
    $pdo->exec("GRANT ALL PRIVILEGES ON unilis_smartlab.* TO 'lab_admin'@'%'");
    $pdo->exec("FLUSH PRIVILEGES");
    $pdo->exec("USE unilis_smartlab");

    $sql = file_get_contents(__DIR__ . '/unilis_smartlab.sql');
    $sql = str_replace('DEFAULT uuid()', 'DEFAULT (uuid())', $sql);

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (empty($stmt) || strpos(ltrim($stmt), '--') === 0) continue;
        try { $pdo->exec($stmt); } catch (PDOException $e) { $errors[] = $e->getMessage(); }
    }

    $tables = $pdo->query("SHOW TABLES FROM unilis_smartlab")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h2>✅ Done! " . count($tables) . " tables created in unilis_smartlab</h2>";
    echo "<ul>"; foreach ($tables as $t) echo "<li>$t</li>"; echo "</ul>";
    if (!empty($errors)) { echo "<h3>⚠️ Errors:</h3><ul>"; foreach ($errors as $e) echo "<li>$e</li>"; echo "</ul>"; }
    echo "<p><strong>⚠️ DELETE this file now!</strong></p>";

} catch (PDOException $e) {
    echo "<h2>❌ Failed: " . $e->getMessage() . "</h2>";
}
