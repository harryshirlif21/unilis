<?php
if (($_GET['token'] ?? '') !== 'unilis_audit_2026') { die('403'); }
require_once __DIR__ . "/config/db.php";

$sqls = [
    "ALTER TABLE public_courses ADD COLUMN IF NOT EXISTS cover_image_history JSON NULL COMMENT 'Previous banner filenames' AFTER cover_image",
    "ALTER TABLE course_sponsors ADD COLUMN IF NOT EXISTS sponsor_logo_history JSON NULL COMMENT 'Previous logo filenames' AFTER sponsor_logo",
];

echo "<pre style='font:14px monospace;padding:30px;background:#0f172a;color:#e2e8f0;min-height:100vh'>";
echo "=== MIGRATION ===\n\n";
foreach ($sqls as $sql) {
    if ($conn->query($sql)) {
        echo "\e[32m✓ OK\e[0m : $sql\n\n";
    } else {
        echo "\e[31m✗ ERR\e[0m : " . $conn->error . "\n    SQL: $sql\n\n";
    }
}

echo "=== VERIFY public_courses ===\n";
$res = $conn->query("SHOW COLUMNS FROM public_courses LIKE 'cover_image%'");
while ($r = $res->fetch_assoc()) echo "  {$r['Field']} | {$r['Type']} | {$r['Null']}\n";

echo "\n=== VERIFY course_sponsors ===\n";
$res = $conn->query("SHOW COLUMNS FROM course_sponsors LIKE 'sponsor_logo%'");
while ($r = $res->fetch_assoc()) echo "  {$r['Field']} | {$r['Type']} | {$r['Null']}\n";
echo "</pre>";
