<?php
if (($_GET['token'] ?? '') !== 'unilis_audit_2026') { die('403'); }
require_once __DIR__ . "/config/db.php";

echo "<pre style='font:14px monospace;padding:30px;background:#0f172a;color:#e2e8f0;min-height:100vh'>";
echo "=== MIGRATION ===\n\n";
echo "MySQL version: " . $conn->server_info . "\n\n";

// Check and add cover_image_history to public_courses
$check = $conn->query("SHOW COLUMNS FROM public_courses LIKE 'cover_image_history'");
if ($check->num_rows === 0) {
    if ($conn->query("ALTER TABLE public_courses ADD COLUMN cover_image_history JSON NULL COMMENT 'Previous banner filenames' AFTER cover_image")) {
        echo "\e[32m✓ Added cover_image_history to public_courses\e[0m\n";
    } else {
        echo "\e[31m✗ Failed: " . $conn->error . "\e[0m\n";
    }
} else {
    echo "\e[33m~ cover_image_history already exists in public_courses\e[0m\n";
}

// Check and add sponsor_logo_history to course_sponsors
$check = $conn->query("SHOW COLUMNS FROM course_sponsors LIKE 'sponsor_logo_history'");
if ($check->num_rows === 0) {
    if ($conn->query("ALTER TABLE course_sponsors ADD COLUMN sponsor_logo_history JSON NULL COMMENT 'Previous logo filenames' AFTER sponsor_logo")) {
        echo "\e[32m✓ Added sponsor_logo_history to course_sponsors\e[0m\n";
    } else {
        echo "\e[31m✗ Failed: " . $conn->error . "\e[0m\n";
    }
} else {
    echo "\e[33m~ sponsor_logo_history already exists in course_sponsors\e[0m\n";
}

echo "\n=== VERIFY public_courses ===\n";
$res = $conn->query("SHOW COLUMNS FROM public_courses LIKE 'cover_image%'");
while ($r = $res->fetch_assoc()) {
    echo "  {$r['Field']} | {$r['Type']} | Null:{$r['Null']}\n";
}

echo "\n=== VERIFY course_sponsors ===\n";
$res = $conn->query("SHOW COLUMNS FROM course_sponsors LIKE 'sponsor_logo%'");
while ($r = $res->fetch_assoc()) {
    echo "  {$r['Field']} | {$r['Type']} | Null:{$r['Null']}\n";
}

echo "\n=== DONE ===\n";
echo "</pre>";
