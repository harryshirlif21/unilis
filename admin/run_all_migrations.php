<?php
// admin/run_all_migrations.php
session_start();
header('Content-Type: text/html; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

if (($_SESSION['user_role'] ?? '') !== 'admin' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden.');
}

if (
    empty($_SESSION['csrf_token'])
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid or expired request token.');
}

try {
    require_once __DIR__ . '/../config/db.php';
    $adminCheck = $conn->prepare('SELECT email FROM admins WHERE id = ? LIMIT 1');
    $adminId = (int)$_SESSION['user_id'];
    $adminCheck->bind_param('i', $adminId);
    $adminCheck->execute();
    $admin = $adminCheck->get_result()->fetch_assoc();
    $adminCheck->close();

    if (!$admin || strtolower(trim((string)$admin['email'])) !== 'admin@unilis.com') {
        http_response_code(403);
        exit('Forbidden.');
    }

    require_once __DIR__ . '/../migrations/consolidated_migrations.php';
    echo run_all_migrations();
} catch (Throwable $e) {
    // Surface the reason instead of returning a blank 500 to the dashboard's fetch().
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo '<h1>Migration run failed</h1>';
    echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
    echo '<p>' . htmlspecialchars(basename($e->getFile())) . ' line ' . (int)$e->getLine() . '</p>';
}
