<?php
/**
 * ONE-OFF SCRIPT: fix uploads/ permissions so PHP (www-data) can write banners.
 *
 * Deploy this to phase1/admin/fix_upload_perms.php on production, visit it
 * once while logged in as an admin, confirm it reports success, then DELETE
 * IT (it also tries to delete itself automatically after a successful run).
 *
 * Safe by design:
 *  - Requires an authenticated admin/department_admin session (reuses the
 *    app's own auth), so it cannot be hit anonymously.
 *  - Only touches paths under uploads/ - never anything else.
 *  - Reports exactly what it changed and what still failed, so you know
 *    immediately if this is a permissions problem PHP can fix, or an
 *    ownership problem that needs real server (SSH/cPanel) access.
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
if (file_exists(__DIR__ . '/../includes/auth_extended.php')) {
    require_once __DIR__ . '/../includes/auth_extended.php';
}
if (!function_exists('phase1_guard_role')) {
    function phase1_guard_role($allowed_roles, $redirect_url = '../../login.php') {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) { header("Location: $redirect_url"); exit; }
        if (is_string($allowed_roles)) $allowed_roles = [$allowed_roles];
        if (!in_array($_SESSION['user_role'], $allowed_roles)) { header("Location: $redirect_url"); exit; }
    }
}
phase1_guard_role(['admin', 'department_admin']);

header('Content-Type: text/plain');

$projectRoot = dirname(__DIR__, 2); // phase1/admin -> project root
$targets = [
    $projectRoot . '/assets/uploads/short_courses',
    $projectRoot . '/assets/uploads/short_courses/sponsors',
];

echo "Running as PHP user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n";
echo "----------------------------------------\n";

$allOk = true;

foreach ($targets as $dir) {
    echo "Checking: $dir\n";

    if (!is_dir($dir)) {
        $made = @mkdir($dir, 0775, true);
        echo $made ? "  Created directory.\n" : "  FAILED to create directory (parent may not be writable by this process).\n";
        if (!$made) { $allOk = false; continue; }
    } else {
        echo "  Exists.\n";
    }

    // Report current owner/perms before attempting a change.
    $perms = substr(sprintf('%o', fileperms($dir)), -4);
    $ownerId = function_exists('fileowner') ? fileowner($dir) : null;
    $ownerName = ($ownerId !== null && function_exists('posix_getpwuid')) ? (posix_getpwuid($ownerId)['name'] ?? $ownerId) : $ownerId;
    echo "  Current perms: $perms, owner: $ownerName\n";

    // Try to force group-writable 775. This only succeeds if the PHP process
    // (www-data) already owns the directory, or is running as root (it isn't
    // in Docker here) - so it tells us definitively which case we're in.
    $chmodOk = @chmod($dir, 0775);
    clearstatcache();
    $newPerms = substr(sprintf('%o', fileperms($dir)), -4);
    echo "  chmod(0775) call returned: " . ($chmodOk ? 'true' : 'false') . ", perms now: $newPerms\n";

    // The real test: can PHP actually write a file here right now?
    $testFile = $dir . '/_write_test_' . time() . '.tmp';
    $writeOk = @file_put_contents($testFile, 'test') !== false;
    if ($writeOk) {
        @unlink($testFile);
        echo "  WRITE TEST: PASSED - PHP can write files here.\n";
    } else {
        echo "  WRITE TEST: FAILED - PHP still cannot write here. This directory is owned by a\n";
        echo "  different Linux user and needs a real server fix (SSH/cPanel), e.g.:\n";
        echo "    chown -R www-data:www-data " . $dir . "\n";
        echo "    (or, if www-data's UID inside Docker is different: chown -R 33:33 " . $dir . ")\n";
        $allOk = false;
    }
    echo "----------------------------------------\n";
}

if ($allOk) {
    echo "\nALL CHECKS PASSED. Banner uploads should now work.\n";
    echo "Deleting this script for security...\n";
    if (@unlink(__FILE__)) {
        echo "Deleted. You're done - go test a real banner upload now.\n";
    } else {
        echo "Could not self-delete (no write permission on this file). Please delete\n";
        echo "phase1/admin/fix_upload_perms.php manually via your deploy method.\n";
    }
} else {
    echo "\nSOME CHECKS FAILED. See the WRITE TEST failures above - those directories\n";
    echo "are owned by a Linux user other than the one PHP runs as (www-data), and\n";
    echo "chmod from inside PHP cannot fix ownership. Send the owner/perms output\n";
    echo "above to whoever has SSH/cPanel access to the server and ask them to run\n";
    echo "the chown command(s) shown.\n";
    echo "\nThis script was left in place since it didn't fully succeed - delete\n";
    echo "phase1/admin/fix_upload_perms.php manually once the issue is resolved.\n";
}
