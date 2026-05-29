<?php
require_once __DIR__ . '/smart-lab/config/database_production.php';
$pdo = getProductionDB();

// Show all users so you can pick the right ID
$users = $pdo->query("SELECT id, full_name, reg_number, role FROM users ORDER BY role")->fetchAll();
echo "<h3>Users in DB</h3><table border=1 cellpadding=6 style='font-size:13px;border-collapse:collapse'>";
echo "<tr><th>id</th><th>name</th><th>reg_number</th><th>role</th><th>action</th></tr>";
foreach ($users as $u) {
    echo "<tr><td style='font-size:11px'>{$u['id']}</td><td>{$u['full_name']}</td><td>{$u['reg_number']}</td><td>{$u['role']}</td>";
    echo "<td><a href='?assign={$u['id']}'>Assign 59:14:4D:E8 to this user</a></td></tr>";
}
echo "</table>";

if (!empty($_GET['assign'])) {
    $sid = $_GET['assign'];
    $uid = '59:14:4D:E8';
    try {
        $pdo->prepare("INSERT INTO rfid_cards (student_id, uid) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE student_id = VALUES(student_id)")
            ->execute([$sid, $uid]);
        echo "<p style='color:#166534;font-weight:700'>✓ Card $uid assigned successfully</p>";
    } catch (PDOException $e) {
        echo "<p style='color:#dc2626'>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
