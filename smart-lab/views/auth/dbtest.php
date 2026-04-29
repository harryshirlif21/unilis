<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DB Test — UNILIS SmartLab</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
<style>
  body { padding: 40px; max-width: 800px; margin: 0 auto; }
  .check { display:flex;align-items:center;gap:12px;padding:12px 16px;
    border-radius:8px;margin-bottom:10px;font-size:13px; }
  .check.ok   { background:rgba(46,204,113,.1);border:1px solid rgba(46,204,113,.3); }
  .check.fail { background:rgba(231,76,60,.1); border:1px solid rgba(231,76,60,.3); }
  .check-icon { font-size:18px;flex-shrink:0; }
  .check-label { font-weight:600; flex:1; }
  .check-val   { font-family:'DM Mono',monospace;font-size:12px;color:var(--text2); }
  h2 { font-size:13px;font-weight:600;color:var(--text3);
    letter-spacing:1px;text-transform:uppercase;margin:28px 0 12px; }
</style>
</head>
<body>

<div style="display:flex;align-items:center;gap:14px;margin-bottom:32px;">
  <div class="auth-logo-icon" style="width:44px;height:44px;border-radius:10px;
    background:var(--teal);display:flex;align-items:center;justify-content:center;
    font-size:18px;font-weight:800;color:var(--navy);">SL</div>
  <div>
    <div style="font-size:18px;font-weight:700;">UNILIS SmartLab</div>
    <div style="font-size:11px;color:var(--text2);letter-spacing:1px;">DATABASE CONNECTION TEST</div>
  </div>
</div>

<h2>1 — Database Connection</h2>
<?php
$dbOk = false;
try {
    $pdo = getDB();
    $dbOk = true;
    echo '<div class="check ok"><span class="check-icon">✓</span>
          <span class="check-label">Connected to MySQL</span>
          <span class="check-val">'.DB_HOST.' / '.DB_NAME.'</span></div>';
} catch (Exception $e) {
    echo '<div class="check fail"><span class="check-icon">✗</span>
          <span class="check-label">Connection failed</span>
          <span class="check-val">'.htmlspecialchars($e->getMessage()).'</span></div>';
}
?>

<?php if ($dbOk): ?>

<h2>2 — Tables</h2>
<?php
$tables = ['roles','users','labs','practicals','lab_sessions',
           'student_sessions','notebooks','notebook_versions',
           'assets','asset_transactions','blockchain_blocks',
           'reports','approvals','lab_requests','audit_logs'];

foreach ($tables as $tbl) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        echo "<div class='check ok'>
                <span class='check-icon'>✓</span>
                <span class='check-label'>$tbl</span>
                <span class='check-val'>$count row(s)</span>
              </div>";
    } catch (Exception $e) {
        echo "<div class='check fail'>
                <span class='check-icon'>✗</span>
                <span class='check-label'>$tbl</span>
                <span class='check-val'>MISSING — run 001_schema.sql</span>
              </div>";
    }
}
?>

<h2>3 — Seed Data</h2>
<?php
$checks = [
    'Labs'   => "SELECT COUNT(*) FROM labs",
    'Users'  => "SELECT COUNT(*) FROM users",
    'Roles'  => "SELECT COUNT(*) FROM roles",
];
foreach ($checks as $label => $sql) {
    $n = $pdo->query($sql)->fetchColumn();
    $ok = $n > 0;
    $icon = $ok ? '✓' : '✗';
    $cls  = $ok ? 'ok' : 'fail';
    $note = $ok ? "$n record(s) found" : "Empty — run 001_seed.sql";
    echo "<div class='check $cls'>
            <span class='check-icon'>$icon</span>
            <span class='check-label'>$label</span>
            <span class='check-val'>$note</span>
          </div>";
}
?>

<h2>4 — Lab List</h2>
<?php
$labs = $pdo->query("SELECT name, lab_code, type, max_capacity FROM labs ORDER BY name")->fetchAll();
if ($labs) {
    echo "<div style='overflow-x:auto;'><table><thead><tr>
            <th>Name</th><th>Code</th><th>Type</th><th>Capacity</th>
          </tr></thead><tbody>";
    foreach ($labs as $l) {
        echo "<tr>
          <td class='td-main'>".htmlspecialchars($l['name'])."</td>
          <td><span class='badge badge-teal'>".htmlspecialchars($l['lab_code'])."</span></td>
          <td>".htmlspecialchars($l['type'])."</td>
          <td>".$l['max_capacity']."</td>
        </tr>";
    }
    echo "</tbody></table></div>";
} else {
    echo "<div class='check fail'><span class='check-icon'>✗</span>
          <span class='check-label'>No labs found — import seed data</span></div>";
}
?>

<h2>5 — Users</h2>
<?php
$users = $pdo->query(
    "SELECT reg_number, full_name, role, email, is_active FROM users ORDER BY role"
)->fetchAll();
if ($users) {
    $roleColors = ['admin'=>'badge-red','lecturer'=>'badge-amber',
                   'technician'=>'badge-blue','student'=>'badge-teal'];
    echo "<div style='overflow-x:auto;'><table><thead><tr>
            <th>Reg No</th><th>Name</th><th>Role</th><th>Email</th><th>Status</th>
          </tr></thead><tbody>";
    foreach ($users as $u) {
        $bc = $roleColors[$u['role']] ?? 'badge-gray';
        $st = $u['is_active'] ? '<span class="badge badge-green badge-dot">Active</span>'
                               : '<span class="badge badge-red">Inactive</span>';
        echo "<tr>
          <td class='td-main' style='font-family:DM Mono,monospace;font-size:12px;'>
            ".htmlspecialchars($u['reg_number'])."</td>
          <td>".htmlspecialchars($u['full_name'])."</td>
          <td><span class='badge $bc'>".htmlspecialchars($u['role'])."</span></td>
          <td style='font-size:12px;'>".htmlspecialchars($u['email'])."</td>
          <td>$st</td>
        </tr>";
    }
    echo "</tbody></table></div>";
} else {
    echo "<div class='check fail'><span class='check-icon'>✗</span>
          <span class='check-label'>No users — import seed data</span></div>";
}
?>

<?php endif; ?>

<div style="margin-top:32px;display:flex;gap:12px;flex-wrap:wrap;">
  <a href="<?= APP_URL ?>/auth/login"    class="btn btn-primary">Go to Login</a>
  <a href="<?= APP_URL ?>/auth/register" class="btn btn-outline">Register</a>
  <a href="<?= APP_URL ?>/dashboard"     class="btn btn-outline">Dashboard</a>
  <a href="<?= APP_URL ?>/auth/dbtest"   class="btn btn-ghost btn-sm"
     onclick="location.reload();return false;">↺ Refresh test</a>
</div>

</body>
</html>
