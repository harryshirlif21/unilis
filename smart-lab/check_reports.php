<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "<p>Step 1: PHP running</p>";

$config = __DIR__ . '/config/database_production.php';
echo "<p>Step 2: Looking for config at: $config</p>";
echo "<p>Exists: " . (file_exists($config) ? 'YES' : 'NO') . "</p>";

require_once $config;
echo "<p>Step 3: Config loaded</p>";

$pdo = getProductionDB();
echo "<p>Step 4: DB connected — host: " . (defined('DB_HOST') ? DB_HOST : 'unknown') . "</p>";

$cols = $pdo->query("DESCRIBE lab_reports")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>lab_reports</h3><table border=1 cellpadding=5 style='font-size:13px;border-collapse:collapse'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
foreach ($cols as $c)
    echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>" . htmlspecialchars($c['Default'] ?? '') . "</td></tr>";
echo "</table>";

$cols2 = $pdo->query("DESCRIBE attendance")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>attendance</h3><table border=1 cellpadding=5 style='font-size:13px;border-collapse:collapse'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
foreach ($cols2 as $c)
    echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>" . htmlspecialchars($c['Default'] ?? '') . "</td></tr>";
echo "</table>";

$status = $pdo->query("SHOW COLUMNS FROM lab_reports LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
echo "<h3>lab_reports.status</h3><pre>" . htmlspecialchars(print_r($status, true)) . "</pre>";
echo "<p style='color:#888;font-size:11px'>__FILE__: " . __FILE__ . "</p>";
