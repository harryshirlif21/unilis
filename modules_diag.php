<?php
/**
 * Modules Directory Check
 * ------------------------
 * Confirms from inside the live container whether modules/live-engagement
 * actually exists on disk, to rule out a .dockerignore or build-context issue.
 *
 * USAGE: place at repo root (same level as actions.php), deploy, then visit:
 *   https://unilis.jhubafrica.com/modules_diag.php
 *
 * Delete this file once you're done diagnosing.
 */

header('Content-Type: text/plain');

echo "=== Modules Directory Check ===\n";
echo "Server time: " . date('Y-m-d H:i:s') . "\n";
echo "Script location: " . __DIR__ . "\n\n";

$pathsToCheck = [
    __DIR__ . '/modules',
    __DIR__ . '/modules/live-engagement',
    __DIR__ . '/modules/live-engagement/views',
    __DIR__ . '/modules/live-engagement/views/home.php',
    __DIR__ . '/modules/live-engagement/setup_database.php',
    __DIR__ . '/modules/live-engagement/api',
    __DIR__ . '/modules/live-engagement/api/guest_auth.php',
];

foreach ($pathsToCheck as $path) {
    if (file_exists($path)) {
        $type = is_dir($path) ? 'DIR ' : 'FILE';
        $readable = is_readable($path) ? 'readable' : 'NOT readable';
        echo "[FOUND] $type $path ($readable)\n";
    } else {
        echo "[MISSING] $path\n";
    }
}

echo "\n--- Listing modules/ contents (if it exists) ---\n";
$modulesDir = __DIR__ . '/modules';
if (is_dir($modulesDir)) {
    $items = scandir($modulesDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        echo "  $item\n";
    }
} else {
    echo "  modules/ directory does not exist in this container at all.\n";
}

echo "\n=== End of report ===\n";