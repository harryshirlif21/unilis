<?php
/**
 * Phase 1 - Test Suite
 * UNILIS Academic Foundation Expansion
 * 
 * Run this file to verify Phase 1 implementation is working correctly.
 * Tests are non-destructive and do not modify any data.
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_extended.php';

// Only Global Admin can run tests
phase1_guard_role('admin', '../../login.php');

$tests = [];
$passed = 0;
$failed = 0;

// ── Test 1: Check database tables exist ─────────────────────────────────────
$phase1_tables = [
    'department_admins' => 'Department Admin assignments',
    'technicians' => 'Technician accounts',
    'system_versions' => 'System version tracking',
    'system_migrations' => 'Migration history',
    'system_upgrade_logs' => 'Upgrade activity logs',
    'technician_pools' => 'Technician pool groups',
    'pool_technicians' => 'Pool-technician assignments',
];

foreach ($phase1_tables as $table => $description) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $check && $check->num_rows > 0;
    $tests[] = [
        'name' => "Table: $table ($description)",
        'status' => $exists ? 'PASS' : 'FAIL',
        'details' => $exists ? "Table '$table' exists" : "Table '$table' is missing",
    ];
    if ($exists) $passed++; else $failed++;
}

// ── Test 2: Check upgraded columns ──────────────────────────────────────────
$upgrade_checks = [
    ['courses', 'course_type', "courses table has course_type column"],
    ['admins', 'is_super_admin', "admins table has is_super_admin column"],
    ['admins', 'is_verified', "admins table has is_verified column"],
    ['lecturers', 'department_id', "lecturers table has department_id column"],
];

foreach ($upgrade_checks as $check) {
    list($table, $column, $description) = $check;
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    $exists = $result && $result->num_rows > 0;
    $tests[] = [
        'name' => "Upgrade: $description",
        'status' => $exists ? 'PASS' : 'FAIL',
        'details' => $exists ? "Column '$column' exists in '$table'" : "Column '$column' missing from '$table'",
    ];
    if ($exists) $passed++; else $failed++;
}

// ── Test 3: Check super admin exists ────────────────────────────────────────
$result = $conn->query("SELECT id, name, email, is_super_admin FROM admins WHERE is_super_admin = 1");
$super_admin = $result->fetch_assoc();
$exists = $super_admin && $super_admin['is_super_admin'] == 1;
$tests[] = [
    'name' => "Super Admin exists",
    'status' => $exists ? 'PASS' : 'FAIL',
    'details' => $exists ? "Super Admin: {$super_admin['name']} ({$super_admin['email']})" : "No super admin found",
];
if ($exists) $passed++; else $failed++;

// ── Test 4: Check config loads correctly ────────────────────────────────────
$config_loaded = defined('PHASE1_VERSION') && defined('ROLE_DEPARTMENT_ADMIN') && defined('ROLE_TECHNICIAN');
$tests[] = [
    'name' => "Phase 1 configuration loaded",
    'status' => $config_loaded ? 'PASS' : 'FAIL',
    'details' => $config_loaded ? "Version: " . PHASE1_VERSION : "Configuration constants not defined",
];
if ($config_loaded) $passed++; else $failed++;

// ── Test 5: Check auth_extended functions exist ─────────────────────────────
$functions = ['phase1_has_permission', 'phase1_get_user_permissions', 'phase1_get_user_assignments', 'phase1_guard_role', 'phase1_log_upgrade'];
$all_functions_exist = true;
foreach ($functions as $func) {
    if (!function_exists($func)) {
        $all_functions_exist = false;
        break;
    }
}
$tests[] = [
    'name' => "Auth extended functions available",
    'status' => $all_functions_exist ? 'PASS' : 'FAIL',
    'details' => $all_functions_exist ? "All " . count($functions) . " functions available" : "Some functions missing",
];
if ($all_functions_exist) $passed++; else $failed++;

// ── Test 6: Check login handler exists ──────────────────────────────────────
$login_handler_exists = function_exists('phase1_try_login') && function_exists('phase1_get_role_redirect');
$tests[] = [
    'name' => "Login handler functions available",
    'status' => $login_handler_exists ? 'PASS' : 'FAIL',
    'details' => $login_handler_exists ? "phase1_try_login and phase1_get_role_redirect available" : "Login handler functions missing",
];
if ($login_handler_exists) $passed++; else $failed++;

// ── Test 7: Check dashboard files exist ─────────────────────────────────────
$dashboard_files = [
    '../department_admin/dashboard.php' => 'Department Admin Dashboard',
    '../technician/dashboard.php' => 'Technician Dashboard',
    '../admin/department_admins.php' => 'Department Admin Management',
    '../admin/technicians.php' => 'Technician Management',
];

foreach ($dashboard_files as $path => $description) {
    $full_path = __DIR__ . '/' . $path;
    $exists = file_exists($full_path);
    $tests[] = [
        'name' => "File: $description",
        'status' => $exists ? 'PASS' : 'FAIL',
        'details' => $exists ? "$path exists" : "$path is missing",
    ];
    if ($exists) $passed++; else $failed++;
}

// ── Test 8: Check migration file exists ─────────────────────────────────────
$migration_file = __DIR__ . '/../database/migration_001_phase1.php';
$migration_exists = file_exists($migration_file);
$tests[] = [
    'name' => "Migration file exists",
    'status' => $migration_exists ? 'PASS' : 'FAIL',
    'details' => $migration_exists ? "migration_001_phase1.php exists" : "Migration file missing",
];
if ($migration_exists) $passed++; else $failed++;

// ── Test 9: Check actions.php integration ───────────────────────────────────
$actions_content = file_get_contents(__DIR__ . '/../../actions.php');
$has_login_integration = strpos($actions_content, "phase1/includes/login_handler.php") !== false;
$tests[] = [
    'name' => "Login integration in actions.php",
    'status' => $has_login_integration ? 'PASS' : 'FAIL',
    'details' => $has_login_integration ? "actions.php includes Phase 1 login handler" : "Login handler not integrated in actions.php",
];
if ($has_login_integration) $passed++; else $failed++;

// ── Test 10: Check login.php redirects ──────────────────────────────────────
$login_content = file_get_contents(__DIR__ . '/../../login.php');
$has_dept_admin_redirect = strpos($login_content, "department_admin") !== false;
$has_technician_redirect = strpos($login_content, "technician") !== false;
$tests[] = [
    'name' => "Login redirects for new roles",
    'status' => ($has_dept_admin_redirect && $has_technician_redirect) ? 'PASS' : 'FAIL',
    'details' => ($has_dept_admin_redirect && $has_technician_redirect) ? "login.php handles department_admin and technician redirects" : "Missing role redirects in login.php",
];
if ($has_dept_admin_redirect && $has_technician_redirect) $passed++; else $failed++;

// ── Test 11: Check admin dashboard menu integration ─────────────────────────
$admin_dashboard = file_get_contents(__DIR__ . '/../../admin/dashboard.php');
$has_upgrade_manager = strpos($admin_dashboard, "upgrade_manager.php") !== false;
$has_dept_admins_link = strpos($admin_dashboard, "department_admins.php") !== false;
$has_technicians_link = strpos($admin_dashboard, "technicians.php") !== false;
$tests[] = [
    'name' => "Admin dashboard Phase 1 menu items",
    'status' => ($has_upgrade_manager && $has_dept_admins_link && $has_technicians_link) ? 'PASS' : 'FAIL',
    'details' => ($has_upgrade_manager && $has_dept_admins_link && $has_technicians_link) ? "All 3 Phase 1 menu items present" : "Missing Phase 1 menu items in admin dashboard",
];
if ($has_upgrade_manager && $has_dept_admins_link && $has_technicians_link) $passed++; else $failed++;

// ── Render Results ──────────────────────────────────────────────────────────
$total = count($tests);
$percentage = $total > 0 ? round(($passed / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phase 1 Test Suite - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; padding: 24px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 24px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
        .summary { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .summary-card { background: #fff; border-radius: 10px; padding: 20px; text-align: center; flex: 1; min-width: 120px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .summary-card .num { font-size: 28px; font-weight: 700; }
        .summary-card .lbl { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .summary-card.pass { border-top: 3px solid #16a34a; }
        .summary-card.pass .num { color: #16a34a; }
        .summary-card.fail { border-top: 3px solid #dc2626; }
        .summary-card.fail .num { color: #dc2626; }
        .summary-card.total { border-top: 3px solid #2563eb; }
        .summary-card.total .num { color: #2563eb; }
        .test-row { background: #fff; border-radius: 8px; padding: 14px 18px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.06); display: flex; align-items: center; gap: 12px; }
        .test-row .icon { font-size: 18px; width: 24px; text-align: center; }
        .test-row .name { flex: 1; font-size: 14px; }
        .test-row .details { font-size: 12px; color: #6b7280; }
        .test-row.pass { border-left: 3px solid #16a34a; }
        .test-row.fail { border-left: 3px solid #dc2626; }
        .progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; margin-bottom: 20px; overflow: hidden; }
        .progress-bar .fill { height: 100%; border-radius: 3px; transition: width .5s; background: linear-gradient(90deg, #16a34a, #22c55e); }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-vial"></i> Phase 1 Test Suite</h1>
        <p class="subtitle">UNILIS Academic Foundation Expansion — <?= date('Y-m-d H:i:s') ?></p>

        <div class="progress-bar">
            <div class="fill" style="width: <?= $percentage ?>%"></div>
        </div>

        <div class="summary">
            <div class="summary-card total">
                <div class="num"><?= $total ?></div>
                <div class="lbl">Total Tests</div>
            </div>
            <div class="summary-card pass">
                <div class="num"><?= $passed ?></div>
                <div class="lbl">Passed</div>
            </div>
            <div class="summary-card fail">
                <div class="num"><?= $failed ?></div>
                <div class="lbl">Failed</div>
            </div>
            <div class="summary-card total">
                <div class="num"><?= $percentage ?>%</div>
                <div class="lbl">Success Rate</div>
            </div>
        </div>

        <?php foreach ($tests as $test): ?>
            <div class="test-row <?= strtolower($test['status']) ?>">
                <div class="icon">
                    <?php if ($test['status'] === 'PASS'): ?>
                        <i class="fas fa-check-circle" style="color:#16a34a"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle" style="color:#dc2626"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="name"><?= htmlspecialchars($test['name']) ?></div>
                    <div class="details"><?= htmlspecialchars($test['details']) ?></div>
                </div>
                <div style="margin-left:auto">
                    <span style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:4px;<?= $test['status'] === 'PASS' ? 'background:#dcfce7;color:#166534' : 'background:#fee2e2;color:#dc2626' ?>">
                        <?= $test['status'] ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>

        <p style="text-align:center;margin-top:24px;color:#6b7280;font-size:13px;">
            <i class="fas fa-info-circle"></i> 
            Tests are read-only and do not modify any data.
            <?php if ($failed > 0): ?>
                <br>Some tests failed. Run the migration from the System Upgrade Manager first.
            <?php else: ?>
                <br>All tests passed! Phase 1 is fully deployed.
            <?php endif; ?>
        </p>
    </div>
</body>
</html>