<?php
if (($_GET['token'] ?? '') !== 'unilis_audit_2026') { die('403'); }

echo "<pre style='font:14px monospace;padding:30px;background:#0f172a;color:#e2e8f0;min-height:100vh'>";
echo "=== COMPARING VOLUME MOUNTS ===\n\n";

$paths = [
    'uploads/short_courses'        => __DIR__ . '/uploads/short_courses',
    'assets/uploads'               => __DIR__ . '/assets/uploads',
    'assets/uploads/assignments'   => __DIR__ . '/assets/uploads/assignments',
    'assets/assignments'           => __DIR__ . '/assets/assignments',
    'assets/meetings'              => __DIR__ . '/assets/meetings',
];

foreach ($paths as $label => $path) {
    $exists   = is_dir($path);
    $writable = $exists && is_writable($path);
    $owner    = $exists && function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] : '?';
    $group    = $exists && function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($path))['name'] : '?';
    $perms    = $exists ? substr(sprintf('%o', fileperms($path)), -4) : '----';
    $realpath = $exists ? realpath($path) : 'N/A';

    // Count files
    $fileCount = 0;
    if ($exists) {
        foreach (scandir($path) as $f) {
            if ($f !== '.' && $f !== '..' && $f !== '.gitkeep' && is_file($path.'/'.$f)) $fileCount++;
        }
    }

    // Write test
    $writeTest = 'N/A';
    if ($exists) {
        $t = $path . '/.wtest_' . time();
        $ok = @file_put_contents($t, 'x');
        $writeTest = $ok !== false ? 'SUCCESS' : 'FAILED';
        if ($ok !== false) @unlink($t);
    }

    $color = ($owner === 'root') ? "\e[33m" : "\e[32m";
    echo $color . "[$label]\e[0m\n";
    echo "  Path:      $path\n";
    echo "  Realpath:  $realpath\n";
    echo "  Exists:    " . ($exists ? 'YES' : 'NO') . "\n";
    echo "  Owner:     $owner:$group | Perms: $perms\n";
    echo "  Writable:  " . ($writable ? 'YES' : 'NO') . "\n";
    echo "  Files:     $fileCount\n";
    echo "  WriteTest: $writeTest\n\n";
}

echo "=== KEY INSIGHT ===\n";
echo "Paths owned by 'root' are likely on persistent Docker volumes.\n";
echo "Paths owned by 'www-data' may be reset on container recreate.\n";
echo "</pre>";
