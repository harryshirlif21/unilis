<?php
if (($_GET['token'] ?? '') !== 'unilis_audit_2026') { die('403'); }

$dirs = [
    '/var/www/html/uploads',
    '/var/www/html/assets/uploads/short_courses',
    '/var/www/html/assets/uploads/short_courses/sponsors',
    '/var/www/html/uploads/course_images',
    '/var/www/html/uploads/course_pdfs',
    '/var/www/html/uploads/course_videos',
    '/var/www/html/uploads/course_audio',
    '/var/www/html/uploads/course_presentations',
    '/var/www/html/uploads/course_diagrams',
    '/var/www/html/uploads/chat',
    '/var/www/html/uploads/answers',
    '/var/www/html/assets/uploads',
    '/var/www/html/assets/assignments',
    '/var/www/html/assets/meetings',
    '/var/www/html/assets/requested_files',
];

echo "<pre style='font:14px monospace;padding:30px;background:#0f172a;color:#e2e8f0;min-height:100vh'>";
echo "=== DIRECTORY PERMISSION FIXER ===\n";
echo "PHP user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n\n";

foreach ($dirs as $dir) {
    echo "--- $dir ---\n";

    // Create if missing
    if (!is_dir($dir)) {
        if (mkdir($dir, 0775, true)) {
            echo "  Created directory\n";
        } else {
            echo "  FAILED to create directory\n";
            continue;
        }
    } else {
        echo "  Exists: YES\n";
    }

    // Current perms
    $perms = substr(sprintf('%o', fileperms($dir)), -4);
    $writable = is_writable($dir);
    $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($dir))['name'] : '?';
    $group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($dir))['name'] : '?';
    echo "  Owner: $owner | Group: $group | Perms: $perms | Writable: " . ($writable ? 'YES' : 'NO') . "\n";

    // Try to chmod
    if (!$writable) {
        if (chmod($dir, 0775)) {
            echo "  chmod 0775: SUCCESS\n";
            echo "  Writable now: " . (is_writable($dir) ? 'YES' : 'NO') . "\n";
        } else {
            echo "  chmod 0775: FAILED (not owner)\n";
        }
    } else {
        echo "  Already writable - no action needed\n";
    }

    // Write test
    $test = $dir . '/.writetest_' . time();
    $ok = @file_put_contents($test, 'test');
    echo "  Write test: " . ($ok !== false ? 'SUCCESS' : 'FAILED') . "\n";
    if ($ok !== false) @unlink($test);

    echo "\n";
}

echo "=== DONE ===\n";
echo "</pre>";

