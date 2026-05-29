<?php
require_once __DIR__ . '/smart-lab/config/database_production.php';

$pdo = getProductionDB();
$log = [];

function run(PDO $pdo, string $label, string $sql, array &$log): void {
    try {
        $pdo->exec($sql);
        $log[] = ['label' => $label, 'status' => 'ok', 'msg' => 'created / altered'];
    } catch (PDOException $e) {
        $log[] = ['label' => $label, 'status' => 'err', 'msg' => $e->getMessage()];
    }
}

function already(PDO $pdo, string $check, string $label, array &$log): bool {
    $exists = $pdo->query($check)->rowCount() > 0;
    if ($exists) $log[] = ['label' => $label, 'status' => 'skip', 'msg' => 'already exists'];
    return $exists;
}

// 1. rfid_cards
if (!already($pdo, "SHOW TABLES LIKE 'rfid_cards'", 'rfid_cards table', $log)) {
    run($pdo, 'rfid_cards table',
        "CREATE TABLE rfid_cards (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(36) NOT NULL,
            uid        VARCHAR(100) NOT NULL,
            device_id  VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_uid (uid),
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);
}

// 2. rfid_access_log
if (!already($pdo, "SHOW TABLES LIKE 'rfid_access_log'", 'rfid_access_log table', $log)) {
    run($pdo, 'rfid_access_log table',
        "CREATE TABLE rfid_access_log (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            uid        VARCHAR(100) NOT NULL,
            student_id VARCHAR(36) DEFAULT NULL,
            full_name  VARCHAR(150) DEFAULT NULL,
            status     ENUM('granted','denied') NOT NULL,
            scanned_at DATETIME DEFAULT NOW(),
            INDEX idx_uid      (uid),
            INDEX idx_scanned  (scanned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);
}

// 3. verified column on student_practicals
if (!already($pdo, "SHOW COLUMNS FROM student_practicals LIKE 'verified'", 'student_practicals.verified', $log)) {
    run($pdo, 'student_practicals.verified',
        "ALTER TABLE student_practicals ADD COLUMN verified TINYINT(1) DEFAULT 0 COMMENT 'RFID attendance verified'", $log);
}

// 4. started_at column on student_practicals
if (!already($pdo, "SHOW COLUMNS FROM student_practicals LIKE 'started_at'", 'student_practicals.started_at', $log)) {
    run($pdo, 'student_practicals.started_at',
        "ALTER TABLE student_practicals ADD COLUMN started_at TIMESTAMP NULL COMMENT 'When practical session started'", $log);
}

// ── Output ────────────────────────────────────────────────────────────────────
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>RFID Migration</title>
<style>
  body { font-family: Segoe UI, sans-serif; padding: 24px; background: #f5f5f5; max-width: 700px; }
  h1   { font-size: 20px; margin-bottom: 4px; }
  p    { color: #666; font-size: 13px; margin-bottom: 24px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; }
  th { background: #1e3a5f; color: #fff; padding: 8px 14px; text-align: left; }
  td { padding: 8px 14px; border-bottom: 1px solid #f0f0f0; }
  tr:last-child td { border: none; }
  .ok   { color: #166534; font-weight: 600; }
  .skip { color: #92400e; }
  .err  { color: #dc2626; font-weight: 600; }
</style>
</head><body>
<h1>RFID Migration</h1>
<p>host: <?= defined('DB_HOST') ? DB_HOST : 'unknown' ?> &nbsp;·&nbsp; <?= date('Y-m-d H:i:s') ?></p>
<table>
  <tr><th>Item</th><th>Status</th><th>Message</th></tr>
  <?php foreach ($log as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['label']) ?></td>
    <td class="<?= $r['status'] ?>"><?= $r['status'] ?></td>
    <td><?= htmlspecialchars($r['msg']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<br>
<a href="dbtables.php">← view all tables</a>
</body></html>
