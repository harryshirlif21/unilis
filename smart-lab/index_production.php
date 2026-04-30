<?php
session_start();

// Detect environment and load appropriate configuration
$is_production = (strpos($_SERVER['HTTP_HOST'], 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/config/app_production.php';
    require_once __DIR__.'/config/database_production.php';
} else {
    require_once __DIR__.'/config/app.php';
    require_once __DIR__.'/config/database.php';
}

require_once __DIR__.'/config/roles.php';
require_once __DIR__.'/utils/helpers.php';

// Get the requested URL
$url = trim($_GET['url'] ?? '', '/');
$segments = explode('/', $url);
$controller = $segments[0] ?? 'dashboard';

// Allow access to auth pages and QR endpoints without login
if (!isset($_SESSION['user_id']) && $controller !== 'auth' && $controller !== 'qr') {
    if ($is_production) {
        header('Location: https://unilis.jhubafrica.com/smart-lab/landing.html');
    } else {
        header('Location: landing.html');
    }
    exit;
}

require_once __DIR__.'/routes/web.php';

?>
