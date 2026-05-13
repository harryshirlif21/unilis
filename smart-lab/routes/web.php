<?php
$url        = trim($_GET['url'] ?? '', '/');
$segments   = explode('/', $url);
$controller = $segments[0] ?? 'dashboard';
$method     = $segments[1] ?? 'index';
$param      = $segments[2] ?? null;

if ($url === '' || $url === '/') {
    header('Location: '.APP_URL.'/auth/login'); exit;
}

$map = [
    'auth'               => 'AuthController',
    'dashboard'          => 'DashboardController',
    'smartlab'           => 'SmartLabController',
    'practicals'         => 'PracticalController',
    'practical-requests' => 'PracticalRequestController',
    'admin'              => 'AdminPracticalRequestController',
    'notebooks'          => 'NotebookController',
    'reports'            => 'ReportController',
    'report-submission'  => 'ReportSubmissionController',
    'assets'             => 'AssetController',
    'schedule'           => 'ScheduleController',
    'inventory'          => 'InventoryController',
    'audit'              => 'AuditController',
    'blockchain'         => 'BlockchainController',
    'dbtest'             => 'DbtestController',
    'qr'                 => 'QrAuthController',
    'attendance-qr'      => 'QrAuthController',
    'experiments'        => 'ExperimentController',
    'schedules'          => 'ScheduleController',
    'student'            => 'StudentPracticalController',
    'start-practical'    => 'StudentPracticalController',
    'users'              => 'UsersController',
];

if (isset($map[$controller])) {
    $class = $map[$controller];
    require_once __DIR__.'/../controllers/'.$class.'.php';
    $c = new $class();
    if (method_exists($c, $method)) {
        $c->$method($param);
    } else {
        http_response_code(404); echo '404 — Method not found';
    }
} else {
    http_response_code(404); echo '404 — Page not found';
}
