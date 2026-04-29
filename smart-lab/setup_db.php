<?php
// One-time database setup script
// Access via: https://unilis.jhubafrica.com/smart-lab/setup_db.php
// DELETE THIS FILE after running!

$host = 'smart-labs-db';
$user = 'root';
$pass = 'rootpass';

try {
    // Connect as root to create DB and user
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS unilis_smartlab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE unilis_smartlab");

    // Create user and grant privileges
    $pdo->exec("CREATE USER IF NOT EXISTS 'lab_admin'@'%' IDENTIFIED BY 'lab_password'");
    $pdo->exec("GRANT ALL PRIVILEGES ON unilis_smartlab.* TO 'lab_admin'@'%'");
    $pdo->exec("FLUSH PRIVILEGES");

    // Run schema SQL
    $sql = file_get_contents(__DIR__ . '/unilis_smartlab.sql');
    
    // Fix uuid() syntax for MySQL 8.0
    $sql = str_replace('DEFAULT uuid()', 'DEFAULT (uuid())', $sql);
    
    // Split and execute statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $success = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        try {
            $pdo->exec($statement);
            $success++;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }

    echo "<h2>✅ Database Setup Complete</h2>";
    echo "<p>Statements executed: $success</p>";
    
    if ($errors) {
        echo "<h3>⚠️ Errors (may be safe to ignore if tables already exist):</h3><ul>";
        foreach ($errors as $e) echo "<li>$e</li>";
        echo "</ul>";
    }

    // Show tables created
    $tables = $pdo->query("SHOW TABLES FROM unilis_smartlab")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables created (" . count($tables) . "):</h3><ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";
    
    echo "<p><strong>⚠️ DELETE setup_db.php from your server immediately!</strong></p>";

} catch (PDOException $e) {
    echo "<h2>❌ Setup Failed</h2><p>" . $e->getMessage() . "</p>";
}
