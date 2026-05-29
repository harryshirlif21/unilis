<?php
require_once __DIR__ . '/smart-lab/config/database_production.php';
$pdo = getProductionDB();
$log = [];

function run(PDO $pdo, string $label, string $sql, array &$log): void {
    try { $pdo->exec($sql); $log[] = ['label'=>$label,'status'=>'ok','msg'=>'done']; }
    catch (PDOException $e) { $log[] = ['label'=>$label,'status'=>'err','msg'=>$e->getMessage()]; }
}
function skip(PDO $pdo, string $check, string $label, array &$log): bool {
    $exists = $pdo->query($check)->rowCount() > 0;
    if ($exists) $log[] = ['label'=>$label,'status'=>'skip','msg'=>'already exists'];
    return $exists;
}

// 1. attendance table
if (!skip($pdo, "SHOW TABLES LIKE 'attendance'", 'attendance table', $log)) {
    run($pdo, 'attendance table',
        "CREATE TABLE attendance (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            student_id          VARCHAR(36) NOT NULL,
            practical_id        VARCHAR(36) NOT NULL,
            verification_method ENUM('qr','rfid','fingerprint','admin_code') DEFAULT 'qr',
            marked_at           DATETIME DEFAULT NOW(),
            INDEX idx_student   (student_id),
            INDEX idx_practical (practical_id),
            UNIQUE KEY unique_attendance (student_id, practical_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $log);
}

// 2. Verify lab_reports has the status values we need
if (!skip($pdo, "SHOW COLUMNS FROM lab_reports LIKE 'status'", 'lab_reports.status check', $log)) {
    $log[] = ['label'=>'lab_reports.status','status'=>'warn','msg'=>'column not found'];
}

// Show current enum values for lab_reports.status
try {
    $col = $pdo->query("SHOW COLUMNS FROM lab_reports LIKE 'status'")->fetch();
    $log[] = ['label'=>'lab_reports.status type','status'=>'info','msg'=>$col['Type'] ?? 'unknown'];
} catch(Exception $e) {}

?><!doctype html><html><head><meta charset="utf-8"><title>Migration</title>
<style>body{font-family:Segoe UI,sans-serif;padding:24px;max-width:700px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#1e3a5f;color:#fff;padding:8px 14px;text-align:left}
td{padding:7px 14px;border-bottom:1px solid #f0f0f0}
.ok{color:#166534;font-weight:600}.skip{color:#92400e}.err{color:#dc2626;font-weight:600}.info{color:#1d4ed8}
</style></head><body>
<h2>Attendance Migration</h2>
<p>host: <?= defined('DB_HOST')?DB_HOST:'unknown' ?> · <?= date('Y-m-d H:i:s') ?></p>
<table><tr><th>Item</th><th>Status</th><th>Message</th></tr>
<?php foreach($log as $r): ?>
<tr><td><?=htmlspecialchars($r['label'])?></td>
    <td class="<?=$r['status']?>"><?=$r['status']?></td>
    <td><?=htmlspecialchars($r['msg'])?></td></tr>
<?php endforeach; ?>
</table>
<br><a href="dbtables.php">← view all tables</a>
</body></html>
