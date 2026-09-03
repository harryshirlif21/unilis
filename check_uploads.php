<?php
/**
 * Upload File Auditor
 * Works locally and on any remote server.
 * Just drop this file in your project root and visit it.
 */

// ── Simple access guard ───────────────────────────────────────────────────────
// Change this to a hard-to-guess token before deploying
define('AUDIT_TOKEN', 'unilis_audit_2026');
if (($_GET['token'] ?? '') !== AUDIT_TOKEN) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;padding:40px;color:#b00020">403 — Pass ?token=unilis_audit_2026 in the URL</h2>');
}

require_once __DIR__ . "/config/db.php";

$baseDir    = __DIR__ . "/uploads/short_courses";
$sponsorDir = $baseDir  . "/sponsors";

// ── DB records ────────────────────────────────────────────────────────────────
$dbCourses  = [];
$dbSponsors = [];

$res = $conn->query("SELECT id, title, cover_image FROM public_courses ORDER BY id");
if ($res) while ($r = $res->fetch_assoc()) $dbCourses[] = $r;

$res = $conn->query("SELECT id, course_id, sponsor_name, sponsor_logo FROM course_sponsors ORDER BY id");
if ($res) while ($r = $res->fetch_assoc()) $dbSponsors[] = $r;

// ── Disk files ────────────────────────────────────────────────────────────────
function scanFiles(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = [];
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (is_file($dir . '/' . $f)) $files[] = $f;
    }
    return $files;
}

$diskMain    = scanFiles($baseDir);
$diskSponsor = scanFiles($sponsorDir);

// ── Helpers ───────────────────────────────────────────────────────────────────
function dbPathToFilename(string $path): string {
    return basename(str_replace('\\', '/', $path));
}

function fileExistsAnywhere(string $fn, array $main, array $sponsor): string {
    if (in_array($fn, $main))    return 'main';
    if (in_array($fn, $sponsor)) return 'sponsors';
    return 'missing';
}

function badge(string $text, string $color): string {
    return "<span style='display:inline-block;padding:2px 10px;border-radius:12px;"
         . "font-size:12px;font-weight:600;background:{$color}20;color:{$color};"
         . "border:1px solid {$color}40'>{$text}</span>";
}

function humanSize(string $path): string {
    if (!file_exists($path)) return '?';
    $b = filesize($path);
    if ($b >= 1048576) return round($b/1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b/1024, 1) . ' KB';
    return $b . ' B';
}

// ── Build referenced sets ─────────────────────────────────────────────────────
$referencedMain    = [];
$referencedSponsor = [];

foreach ($dbCourses as $c) {
    if (!$c['cover_image']) continue;
    $fn = dbPathToFilename($c['cover_image']);
    if (in_array($fn, $diskMain))    $referencedMain[]    = $fn;
    if (in_array($fn, $diskSponsor)) $referencedSponsor[] = $fn;
}
foreach ($dbSponsors as $s) {
    if (!$s['sponsor_logo']) continue;
    $fn = dbPathToFilename($s['sponsor_logo']);
    if (in_array($fn, $diskMain))    $referencedMain[]    = $fn;
    if (in_array($fn, $diskSponsor)) $referencedSponsor[] = $fn;
}

$orphanMain    = array_values(array_diff($diskMain,    $referencedMain));
$orphanSponsor = array_values(array_diff($diskSponsor, $referencedSponsor));
$totalOrphans  = count($orphanMain) + count($orphanSponsor);
$totalDb       = count($dbCourses) + count($dbSponsors);
$totalDisk     = count($diskMain)  + count($diskSponsor);
$totalMatched  = count($referencedMain) + count($referencedSponsor);

$missingCount = 0;
foreach (array_merge($dbCourses, $dbSponsors) as $row) {
    $pathKey = isset($row['cover_image']) ? $row['cover_image'] : ($row['sponsor_logo'] ?? '');
    if ($pathKey) {
        $fn = dbPathToFilename($pathKey);
        if (fileExistsAnywhere($fn, $diskMain, $diskSponsor) === 'missing') $missingCount++;
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Upload File Auditor — UNILIS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f4f6fb;color:#111;padding:28px;min-height:100vh}
.wrap{max-width:1140px;margin:0 auto}
.top{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:6px}
h1{font-size:1.35rem;font-weight:800}
.env{display:flex;gap:8px;flex-wrap:wrap}
.env-chip{background:#1e3a5f;color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px}
.sub{color:#6b7280;font-size:12.5px;margin-bottom:22px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:26px}
.stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 14px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.stat .num{font-size:28px;font-weight:800;letter-spacing:-0.02em}
.stat .lbl{font-size:11px;color:#6b7280;margin-top:3px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:20px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.card-head{padding:13px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px}
.card-head h2{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151}
.dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#f8fafc;padding:9px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;border-bottom:1px solid #f0f0f0}
td{padding:9px 16px;border-bottom:1px solid #f9f9f9;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafbff}
.mono{font-family:Consolas,monospace;font-size:11.5px;color:#374151}
.empty{padding:24px;text-align:center;color:#9ca3af;font-size:13px;font-style:italic}
.thumb{width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb}
.thumb-missing{width:40px;height:40px;border-radius:6px;border:2px dashed #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:18px;color:#d1d5db}
.footer{margin-top:30px;font-size:12px;color:#9ca3af;text-align:center}
</style>
</head>
<body>
<div class="wrap">

<div class="top">
  <h1>Upload File Auditor</h1>
  <div class="env">
    <span class="env-chip">Host: <?= htmlspecialchars(gethostname()) ?></span>
    <span class="env-chip">DB: <?= htmlspecialchars($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? '?') ?></span>
    <span class="env-chip"><?= date('Y-m-d H:i:s') ?></span>
  </div>
</div>
<div class="sub">
  Main: <code><?= htmlspecialchars($baseDir) ?></code>
  &nbsp;·&nbsp;
  Sponsors: <code><?= htmlspecialchars($sponsorDir) ?></code>
  &nbsp;·&nbsp;
  Main dir exists: <strong><?= is_dir($baseDir) ? 'Yes' : 'NO' ?></strong>
  &nbsp;·&nbsp;
  Sponsors dir exists: <strong><?= is_dir($sponsorDir) ? 'Yes' : 'NO' ?></strong>
</div>

<div class="grid">
  <div class="stat"><div class="num" style="color:#1d4ed8"><?= $totalDb ?></div><div class="lbl">DB Records</div></div>
  <div class="stat"><div class="num" style="color:#1d4ed8"><?= count($diskMain) ?></div><div class="lbl">Files in main/</div></div>
  <div class="stat"><div class="num" style="color:#1d4ed8"><?= count($diskSponsor) ?></div><div class="lbl">Files in sponsors/</div></div>
  <div class="stat"><div class="num" style="color:#16a34a"><?= $totalMatched ?></div><div class="lbl">Matched</div></div>
  <div class="stat"><div class="num" style="color:<?= $missingCount > 0 ? '#dc2626' : '#16a34a' ?>"><?= $missingCount ?></div><div class="lbl">Missing from disk</div></div>
  <div class="stat"><div class="num" style="color:<?= $totalOrphans > 0 ? '#d97706' : '#16a34a' ?>"><?= $totalOrphans ?></div><div class="lbl">Orphaned files</div></div>
</div>

<!-- ── Course Banners ── -->
<div class="card">
  <div class="card-head"><div class="dot" style="background:#6366f1"></div><h2>Course Banners (<?= count($dbCourses) ?> courses)</h2></div>
  <?php if (empty($dbCourses)): ?>
    <div class="empty">No courses in database.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Preview</th><th>ID</th><th>Title</th><th>DB Path</th><th>Filename</th><th>Size</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($dbCourses as $c):
      $fn    = $c['cover_image'] ? dbPathToFilename($c['cover_image']) : '';
      $where = $fn ? fileExistsAnywhere($fn, $diskMain, $diskSponsor) : 'none';
      $fullPath = $where === 'main' ? $baseDir.'/'.$fn : ($where === 'sponsors' ? $sponsorDir.'/'.$fn : '');
      if (!$c['cover_image'])       $sb = badge('No image', '#6b7280');
      elseif ($where === 'main')    $sb = badge('✓ Found', '#16a34a');
      elseif ($where === 'sponsors')$sb = badge('⚠ Wrong folder', '#d97706');
      else                          $sb = badge('✗ Missing', '#dc2626');
      $webPath = $c['cover_image'] ? (str_starts_with($c['cover_image'],'/') ? $c['cover_image'] : '/'.$c['cover_image']) : '';
    ?>
    <tr>
      <td><?= $fullPath
            ? "<img src='".htmlspecialchars($webPath)."' class='thumb' onerror=\"this.style.display='none'\">"
            : "<div class='thumb-missing'>?</div>" ?></td>
      <td><?= $c['id'] ?></td>
      <td><?= htmlspecialchars($c['title']) ?></td>
      <td class="mono"><?= htmlspecialchars($c['cover_image'] ?: '—') ?></td>
      <td class="mono"><?= htmlspecialchars($fn ?: '—') ?></td>
      <td><?= $fullPath ? humanSize($fullPath) : '—' ?></td>
      <td><?= $sb ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- ── Sponsor Logos ── -->
<div class="card">
  <div class="card-head"><div class="dot" style="background:#f59e0b"></div><h2>Sponsor Logos (<?= count($dbSponsors) ?> sponsors)</h2></div>
  <?php if (empty($dbSponsors)): ?>
    <div class="empty">No sponsors in database.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Preview</th><th>ID</th><th>Course</th><th>Sponsor</th><th>DB Path</th><th>Filename</th><th>Size</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($dbSponsors as $s):
      $fn    = $s['sponsor_logo'] ? dbPathToFilename($s['sponsor_logo']) : '';
      $where = $fn ? fileExistsAnywhere($fn, $diskMain, $diskSponsor) : 'none';
      $fullPath = $where === 'sponsors' ? $sponsorDir.'/'.$fn : ($where === 'main' ? $baseDir.'/'.$fn : '');
      if (!$s['sponsor_logo'])       $sb = badge('No logo', '#6b7280');
      elseif ($where === 'sponsors') $sb = badge('✓ Found', '#16a34a');
      elseif ($where === 'main')     $sb = badge('⚠ Wrong folder', '#d97706');
      else                           $sb = badge('✗ Missing', '#dc2626');
      $rawPath = $s['sponsor_logo'];
      $webPath = $rawPath ? (str_starts_with($rawPath,'/') ? $rawPath : '/'.$rawPath) : '';
    ?>
    <tr>
      <td><?= $fullPath
            ? "<img src='".htmlspecialchars($webPath)."' class='thumb' onerror=\"this.style.display='none'\">"
            : "<div class='thumb-missing'>?</div>" ?></td>
      <td><?= $s['id'] ?></td>
      <td><?= $s['course_id'] ?></td>
      <td><?= htmlspecialchars($s['sponsor_name']) ?></td>
      <td class="mono"><?= htmlspecialchars($s['sponsor_logo'] ?: '—') ?></td>
      <td class="mono"><?= htmlspecialchars($fn ?: '—') ?></td>
      <td><?= $fullPath ? humanSize($fullPath) : '—' ?></td>
      <td><?= $sb ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- ── Orphaned Files ── -->
<div class="card">
  <div class="card-head"><div class="dot" style="background:#f97316"></div><h2>Orphaned Files — on disk but not in DB (<?= $totalOrphans ?>)</h2></div>
  <?php if ($totalOrphans === 0): ?>
    <div class="empty">No orphaned files — every file on disk is referenced by a DB record.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Preview</th><th>Folder</th><th>Filename</th><th>Size</th><th>Last Modified</th></tr></thead>
    <tbody>
    <?php foreach ($orphanMain as $f):
      $fp = $baseDir.'/'.$f;
      $mtime = file_exists($fp) ? date('Y-m-d H:i', filemtime($fp)) : '?';
    ?>
    <tr>
      <td><img src="/uploads/short_courses/<?= rawurlencode($f) ?>" class="thumb" onerror="this.style.display='none'"></td>
      <td class="mono">short_courses/</td>
      <td class="mono"><?= htmlspecialchars($f) ?></td>
      <td><?= humanSize($fp) ?></td>
      <td><?= $mtime ?></td>
    </tr>
    <?php endforeach; ?>
    <?php foreach ($orphanSponsor as $f):
      $fp = $sponsorDir.'/'.$f;
      $mtime = file_exists($fp) ? date('Y-m-d H:i', filemtime($fp)) : '?';
    ?>
    <tr>
      <td><img src="/uploads/short_courses/sponsors/<?= rawurlencode($f) ?>" class="thumb" onerror="this.style.display='none'"></td>
      <td class="mono">short_courses/sponsors/</td>
      <td class="mono"><?= htmlspecialchars($f) ?></td>
      <td><?= humanSize($fp) ?></td>
      <td><?= $mtime ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="footer">UNILIS Upload Auditor &nbsp;·&nbsp; Remove or protect this file before sharing the URL publicly.</div>
</div>
</body>
</html>
