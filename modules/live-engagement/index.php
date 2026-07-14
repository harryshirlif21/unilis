<?php
/**
 * Live Engagement Module - Router
 * 
 * Main entry point that routes to appropriate views based on page parameter.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';
le_require_auth();

$page = le_get('page', 'dashboard');
$sessionId = (int)le_get('id', 0, true);
$code = le_get('code', '');

$role = le_current_user_role();

// Route to appropriate view
switch ($page) {
    case 'dashboard':
        // Lecturer dashboard
        if (!le_has_role(['lecturer', 'admin'])) {
            // Students see join page instead
            include __DIR__ . '/views/join.php';
        } else {
            include __DIR__ . '/views/dashboard.php';
        }
        break;

    case 'presenter':
        // Presenter view for live session control
        if (!le_has_role(['lecturer', 'admin'])) {
            header('Location: ?page=join');
            exit;
        }
        if (!$sessionId) {
            header('Location: ?page=dashboard');
            exit;
        }
        include __DIR__ . '/views/presenter.php';
        break;

    case 'join':
        // Student join page
        include __DIR__ . '/views/join.php';
        break;

    case 'session':
        // Active session view for participants
        if (!$sessionId && empty($code)) {
            header('Location: ?page=join');
            exit;
        }
        include __DIR__ . '/views/session.php';
        break;

    case 'report':
        // Session report view
        if (!$sessionId) {
            header('Location: ?page=dashboard');
            exit;
        }
        include __DIR__ . '/views/report.php';
        break;

    default:
        // 404
        header('HTTP/1.0 404 Not Found');
        echo '<h1>Page not found</h1>';
        break;
}