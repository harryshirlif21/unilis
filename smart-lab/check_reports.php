<?php
require_once __DIR__ . '/smart-lab/config/database_production.php';
$pdo = getProductionDB();

$cols = $pdo->query("DESCRIBE lab_reports")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>lab_reports columns</h3><table border=1 cellpadding=6 style='font-size:13px;border-collapse:collapse'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
foreach ($cols as $c)
    echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>{$c['Default']}</td></tr>";
echo "</table>";

$cols = $pdo->query("DESCRIBE attendance")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>attendance columns</h3><table border=1 cellpadding=6 style='font-size:13px;border-collapse:collapse'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
foreach ($cols as $c)
    echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>{$c['Default']}</td></tr>";
echo "</table>";

$status = $pdo->query("SHOW COLUMNS FROM lab_reports LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
echo "<h3>lab_reports.status enum</h3><pre>" . htmlspecialchars(print_r($status, true)) . "</pre>";
echo "<p style='color:#666;font-size:12px'>__FILE__: " . __FILE__ . "</p>";
