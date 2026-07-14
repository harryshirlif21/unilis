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

    case 'presentations':
        // Presentation library
        if (!le_has_role(['lecturer', 'admin'])) {
            header('Location: ?page=join');
            exit;
        }
        include __DIR__ . '/views/presentations.php';
        break;

    case 'report':
        // Session report view
        if (!$sessionId) {
            header('Location: ?page=dashboard');
            exit;
        }
        include __DIR__ . '/views/report.php';
        break;

    case 'reports':
        // Reports overview dashboard
        if (!le_has_role(['lecturer', 'admin'])) {
            header('Location: ?page=join');
            exit;
        }
        include __DIR__ . '/views/reports_overview.php';
        break;

    default:
        // 404 with premium styling
        header('HTTP/1.0 404 Not Found');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 - Page Not Found | UNILIS Live Engagement</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
            <link href="<?= le_asset_url('css/live-engagement.css') ?>" rel="stylesheet">
        </head>
        <body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--le-gray-50);">
            <div style="text-align: center; max-width: 400px; padding: var(--le-space-4);">
                <div style="font-size: 5rem; margin-bottom: var(--le-space-3);">🔍</div>
                <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--le-space-1);">Page Not Found</h1>
                <p style="color: var(--le-gray-500); margin-bottom: var(--le-space-4);">
                    The page you're looking for doesn't exist or has been moved.
                </p>
                <a href="?page=dashboard" class="le-btn le-btn-primary le-btn-lg">
                    <span class="material-symbols-rounded" style="font-size: 20px;">arrow_back</span>
                    Back to Dashboard
                </a>
            </div>
        </body>
        </html>
        <?php
        break;
}