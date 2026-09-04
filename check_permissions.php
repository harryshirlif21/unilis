<?php
if (($_GET['token'] ?? '') !== 'unilis_audit_2026') { http_response_code(403); die('403'); }
require_once __DIR__ . "/config/db.php";

$baseDir    = __DIR__ . "/assets/uploads/short_courses";
$sponsorDir = $baseDir . "/sponsors";

$checks = [
    'Main dir exists'        => is_dir($baseDir),
    'Main dir writable'      => is_writable($baseDir),
    'Sponsors dir exists'    => is_dir($sponsorDir),
    'Sponsors dir writable'  => is_writable($sponsorDir),
    'Main dir permissions'   => substr(sprintf('%o', fileperms($baseDir)), -4),
    'Sponsors permissions'   => is_dir($sponsorDir) ? substr(sprintf('%o', fileperms($sponsorDir)), -4) : 'N/A',
    'PHP running as user'    => get_current_user(),
    'Process user (posix)'   => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'posix not available',
    'Main dir owner'         => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($baseDir))['name'] : 'N/A',
];

// Try actually writing a test file
$testFile = $baseDir . '/write_test_' . time() . '.tmp';
$writeOk  = @file_put_contents($testFile, 'test') !== false;
if ($writeOk) @unlink($testFile);
$checks['Can actually write a file'] = $writeOk ? 'YES' : 'NO — this is the problem';

echo '<pre style="font-family:monospace;padding:30px;background:#0f172a;color:#e2e8f0;min-height:100vh;font-size:14px">';
echo "=== UPLOAD DIRECTORY PERMISSION CHECK ===\n";
echo "Server: " . gethostname() . "\n";
echo "Time:   " . date('Y-m-d H:i:s') . "\n\n";
foreach ($checks as $label => $value) {
    $ok = ($value === true || $value === 'YES');
    $flag = is_bool($value) ? ($value ? '✓' : '✗') : ' ';
    $color = ($value === false || $value === 'NO — this is the problem') ? "\e[31m" : ($value === true || $value === 'YES' ? "\e[32m" : "\e[33m");
    echo $color . str_pad($flag . ' ' . $label, 35) . "\e[0m : " . ($value === true ? 'true' : ($value === false ? 'false' : $value)) . "\n";
}

// List current files with owner info
echo "\n=== FILES IN main/ ===\n";
if (is_dir($baseDir)) {
    foreach (scandir($baseDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $fp = $baseDir . '/' . $f;
        if (is_file($fp)) {
            $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($fp))['name'] : '?';
            $perms = substr(sprintf('%o', fileperms($fp)), -4);
            echo "  $perms $owner  $f\n";
        }
    }
}

echo "\n=== FILES IN sponsors/ ===\n";
if (is_dir($sponsorDir)) {
    foreach (scandir($sponsorDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $fp = $sponsorDir . '/' . $f;
        if (is_file($fp)) {
            $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($fp))['name'] : '?';
            $perms = substr(sprintf('%o', fileperms($fp)), -4);
            echo "  $perms $owner  $f\n";
        }
    }
}
echo '</pre>';
