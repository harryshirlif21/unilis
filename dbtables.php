<?php
require_once __DIR__ . '/smart-lab/config/app.php';

$hosts = ['localhost', '127.0.0.1', 'smart-labs-db'];
$pdo   = null;
foreach ($hosts as $host) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=unilis_smartlab;charset=utf8mb4",
            'lab_admin', 'lab_password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $e) { continue; }
}
if (!$pdo) die("Cannot connect to unilis_smartlab");

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>DB Tables</title>
<style>
  body { font-family: Segoe UI, sans-serif; padding: 24px; background: #f5f5f5; }
  h1   { font-size: 20px; margin-bottom: 4px; }
  p    { color: #666; font-size: 13px; margin-bottom: 24px; }
  .tbl { background: #fff; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; overflow: hidden; }
  .hdr { background: #1e3a5f; color: #fff; padding: 10px 16px; font-size: 14px; font-weight: 600; display: flex; justify-content: space-between; }
  .hdr span { font-weight: 400; opacity: .7; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { background: #f0f4f8; padding: 7px 14px; text-align: left; color: #374151; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; }
  td { padding: 6px 14px; border-bottom: 1px solid #f0f0f0; }
  tr:last-child td { border: none; }
  .pk  { color: #b45309; font-weight: 600; }
  .fk  { color: #1d4ed8; }
  .null { color: #9ca3af; font-size: 11px; }
</style>
</head><body>

<h1>unilis_smartlab — database tables</h1>
<p><?= count($tables) ?> tables &nbsp;·&nbsp; <?= date('Y-m-d H:i:s') ?></p>

<?php foreach ($tables as $table):
    $rows = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="tbl">
  <div class="hdr">
    <?= htmlspecialchars($table) ?>
    <span><?= $rows ?> rows</span>
  </div>
  <table>
    <tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>
    <?php foreach ($cols as $c): ?>
    <tr>
      <td class="<?= $c['Key']==='PRI' ? 'pk' : ($c['Key']==='MUL'||$c['Key']==='UNI' ? 'fk' : '') ?>">
        <?= htmlspecialchars($c['Field']) ?>
        <?= $c['Key']==='PRI' ? ' 🔑' : ($c['Key']==='UNI' ? ' ◆' : ($c['Key']==='MUL' ? ' ⌁' : '')) ?>
      </td>
      <td><?= htmlspecialchars($c['Type']) ?></td>
      <td class="null"><?= $c['Null'] ?></td>
      <td><?= $c['Key'] ?></td>
      <td class="null"><?= $c['Default'] ?? '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endforeach; ?>

</body></html>
