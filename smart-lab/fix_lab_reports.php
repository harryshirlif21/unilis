<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/database_production.php';
$pdo = getProductionDB();
$log = [];

function runSQL(PDO $pdo, string $label, string $sql, array &$log): void {
    try {
        $pdo->exec($sql);
        $log[] = ['label' => $label, 'status' => 'ok', 'msg' => 'done'];
    } catch (PDOException $e) {
        $log[] = ['label' => $label, 'status' => 'err', 'msg' => $e->getMessage()];
    }
}

// 1. Add status column to lab_reports
$col = $pdo->query("SHOW COLUMNS FROM lab_reports LIKE 'status'")->fetch();
if (!$col) {
    runSQL($pdo, 'lab_reports: add status column',
        "ALTER TABLE lab_reports
         ADD COLUMN status ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress'
         AFTER student_id", $log);
} else {
    $log[] = ['label' => 'lab_reports.status', 'status' => 'skip', 'msg' => 'already exists: ' . $col['Type']];
}

// 2. Fix lab_reports.id to varchar(36) to match users.id
$idCol = $pdo->query("SHOW COLUMNS FROM lab_reports LIKE 'id'")->fetch();
if ($idCol && $idCol['Type'] === 'varchar(32)') {
    runSQL($pdo, 'lab_reports: widen id to varchar(36)',
        "ALTER TABLE lab_reports MODIFY COLUMN id VARCHAR(36) NOT NULL", $log);
} else {
    $log[] = ['label' => 'lab_reports.id', 'status' => 'skip', 'msg' => 'already ' . ($idCol['Type'] ?? 'unknown')];
}

// 3. Fix practical_id and student_id to varchar(36) too
foreach (['practical_id', 'student_id'] as $col) {
    $c = $pdo->query("SHOW COLUMNS FROM lab_reports LIKE '$col'")->fetch();
    if ($c && $c['Type'] === 'varchar(32)') {
        runSQL($pdo, "lab_reports: widen $col to varchar(36)",
            "ALTER TABLE lab_reports MODIFY COLUMN $col VARCHAR(36) NOT NULL", $log);
    } else {
        $log[] = ['label' => "lab_reports.$col", 'status' => 'skip', 'msg' => 'already ' . ($c['Type'] ?? 'unknown')];
    }
}

// 4. Verify final structure
$cols = $pdo->query("DESCRIBE lab_reports")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset="utf-8"><title>lab_reports fix</title>
<style>
body{font-family:Segoe UI,sans-serif;padding:24px;max-width:700px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#1e3a5f;color:#fff;padding:7px 12px;text-align:left}
td{padding:6px 12px;border-bottom:1px solid #f0f0f0}
.ok{color:#166534;font-weight:600}.skip{color:#92400e}.err{color:#dc2626;font-weight:600}
</style></head><body>
<h2>lab_reports migration</h2>
<p>host: <?= defined('DB_HOST') ? DB_HOST : 'unknown' ?> &nbsp;·&nbsp; <?= date('Y-m-d H:i:s') ?></p>

<table><tr><th>Item</th><th>Status</th><th>Message</th></tr>
<?php foreach ($log as $r): ?>
<tr><td><?= htmlspecialchars($r['label']) ?></td>
    <td class="<?= $r['status'] ?>"><?= $r['status'] ?></td>
    <td><?= htmlspecialchars($r['msg']) ?></td></tr>
<?php endforeach; ?>
</table>

<h3 style="margin-top:24px">lab_reports — final structure</h3>
<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>
<?php foreach ($cols as $c): ?>
<tr><td><?= $c['Field'] ?></td><td><?= $c['Type'] ?></td>
    <td><?= $c['Null'] ?></td><td><?= htmlspecialchars($c['Default'] ?? '') ?></td></tr>
<?php endforeach; ?>
</table>
<br><a href="check_reports.php">← recheck</a>
</body></html>
