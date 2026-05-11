<?php
/**
 * SmartLab Error Monitor & Activity Tracker
 * Real-time monitoring of errors and database activity
 */

// SECURITY: Only allow in development
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    die('Debug tools are only accessible from localhost');
}

// Token validation
$token = $_GET['token'] ?? '';
if ($token !== 'smartlab_debug_2024') {
    die('<h2>Access Denied</h2><p>Append <code>?token=smartlab_debug_2024</code> to the URL</p>');
}

// Load configuration
require_once __DIR__.'/../config/app.php';

// Get DB connection
$dbConnection = null;
$dbError = null;
try {
    require_once __DIR__.'/../config/database.php';
    $dbConnection = getDB();
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Get monitoring data
$errorLogLines = [];
$auditLogs = [];
$recentPracticals = [];
$stats = [
    'total_practicals' => 0,
    'active_labs' => 0,
    'recent_errors' => 0,
    'draft_practicals' => 0
];

if ($dbConnection) {
    try {
        // Get audit logs
        $auditLogs = $dbConnection->query(
            "SELECT al.id, al.created_at, al.user_id, al.action, al.module, 
                    u.full_name, u.email 
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC 
             LIMIT 10"
        )->fetchAll();
    } catch (Exception $e) {
        // Audit logs may not exist
    }
    
    try {
        // Get recent practicals
        $recentPracticals = $dbConnection->query(
            "SELECT id, title, lab_id, scheduled_date, status, created_at 
             FROM practicals 
             WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) 
             ORDER BY created_at DESC 
             LIMIT 10"
        )->fetchAll();
    } catch (Exception $e) {
        // Practicals may not be recent
    }
    
    try {
        // Get statistics
        $statResult = $dbConnection->query(
            "SELECT 
                (SELECT COUNT(*) FROM practicals) as total_practicals,
                (SELECT COUNT(*) FROM labs WHERE is_active = 1) as active_labs,
                (SELECT COUNT(*) FROM practicals WHERE status = 'draft') as draft_practicals"
        )->fetch();
        $stats = array_merge($stats, $statResult);
    } catch (Exception $e) {
        // Stats query failed
    }
}

// Get PHP error log
$errorLogPaths = [
    'C:\\xampp\\php\\logs\\php_error_log',
    'C:\\xampp\\apache\\logs\\error.log',
    '/var/log/php-error.log',
    '/var/log/apache2/error.log'
];

$foundLogPath = null;
foreach ($errorLogPaths as $path) {
    if (file_exists($path)) {
        $foundLogPath = $path;
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $errorLogLines = array_slice($lines, -50);
        break;
    }
}

// Count recent errors (last 24h)
$recentErrorCount = 0;
foreach ($errorLogLines as $line) {
    if (preg_match('/\d{4}-\d{2}-\d{2}/', $line)) {
        $recentErrorCount++;
    }
}
$stats['recent_errors'] = $recentErrorCount;

?>
<!DOCTYPE html>
<html>
<head>
    <title>SmartLab Error Monitor</title>
    <meta http-equiv="refresh" content="5">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #334155;
        }
        h1 { font-size: 32px; color: #fff; font-weight: bold; }
        .refresh-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #94a3b8;
        }
        .refresh-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .nav { display: flex; gap: 15px; }
        .nav a {
            padding: 8px 16px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 4px;
            color: #3b82f6;
            text-decoration: none;
        }
        .warning-banner {
            background: #7c2d12;
            border: 2px solid #ea580c;
            color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .panel {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .panel-header {
            color: #3b82f6;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #334155;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .stat-label {
            color: #94a3b8;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #3b82f6;
        }
        .stat-card.success .stat-value { color: #22c55e; }
        .stat-card.warning .stat-value { color: #eab308; }
        .stat-card.error .stat-value { color: #ef4444; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #334155;
            font-size: 13px;
        }
        table th { background: #0f172a; font-weight: bold; color: #3b82f6; }
        table tr:nth-child(odd) { background: #1e293b; }
        table tr:nth-child(even) { background: #0f172a; }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-success { background: #22c55e; color: #000; }
        .badge-error { background: #ef4444; color: #fff; }
        .badge-warning { background: #eab308; color: #000; }
        .badge-info { background: #3b82f6; color: #fff; }
        .code-block {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 4px;
            padding: 12px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
            color: #a1d96c;
            line-height: 1.5;
        }
        .error-line { color: #fca5a5; background: #7c2d12; display: block; }
        .timestamp { color: #94a3b8; font-size: 11px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>📊 SmartLab Error Monitor</h1>
            <div class="refresh-info">
                <span class="refresh-dot"></span>
                Auto-refreshing every 5 seconds
            </div>
        </div>
        <div class="nav">
            <a href="practical_debug.php?token=smartlab_debug_2024">Debug</a>
            <a href="fix_labs.php?token=smartlab_debug_2024">Fix Labs</a>
            <a href="error_monitor.php?token=smartlab_debug_2024">Monitor</a>
        </div>
    </div>

    <div class="warning-banner">
        ⚠️ DEBUG MODE — Remove these files before production deployment
    </div>

    <!-- Live Statistics -->
    <div class="panel">
        <div class="panel-header">📈 Live Statistics</div>
        <div class="stat-grid">
            <div class="stat-card success">
                <div class="stat-label">Total Practicals</div>
                <div class="stat-value"><?= $stats['total_practicals'] ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Active Labs</div>
                <div class="stat-value"><?= $stats['active_labs'] ?></div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Draft Practicals</div>
                <div class="stat-value"><?= $stats['draft_practicals'] ?></div>
            </div>
            <div class="stat-card <?= $stats['recent_errors'] > 0 ? 'error' : 'success' ?>">
                <div class="stat-label">Recent Errors (24h)</div>
                <div class="stat-value"><?= $stats['recent_errors'] ?></div>
            </div>
        </div>
    </div>

    <!-- Recent Practicals -->
    <div class="panel">
        <div class="panel-header">🧪 Practicals Created (Last 24h)</div>
        <?php if (!empty($recentPracticals)): ?>
        <table>
            <tr>
                <th>Title</th>
                <th>Lab</th>
                <th>Date</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
            <?php foreach ($recentPracticals as $prac): ?>
            <tr>
                <td><?= htmlspecialchars(substr($prac['title'], 0, 30)) ?></td>
                <td><?= htmlspecialchars($prac['lab_id']) ?></td>
                <td><?= htmlspecialchars($prac['scheduled_date']) ?></td>
                <td><span class="status-badge badge-<?= strtolower($prac['status']) === 'draft' ? 'warning' : 'info' ?>"><?= htmlspecialchars($prac['status']) ?></span></td>
                <td><span class="timestamp"><?= htmlspecialchars($prac['created_at']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p style="color: #94a3b8;">No practicals created in the last 24 hours</p>
        <?php endif; ?>
    </div>

    <!-- Audit Activity Log -->
    <div class="panel">
        <div class="panel-header">📋 Recent Activity Log</div>
        <?php if (!empty($auditLogs)): ?>
        <table>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Module</th>
            </tr>
            <?php foreach ($auditLogs as $log): ?>
            <tr>
                <td><span class="timestamp"><?= htmlspecialchars($log['created_at']) ?></span></td>
                <td>
                    <?php if ($log['full_name']): ?>
                        <?= htmlspecialchars($log['full_name']) ?><br>
                        <span class="timestamp"><?= htmlspecialchars($log['email'] ?? '') ?></span>
                    <?php else: ?>
                        <span class="timestamp"><?= htmlspecialchars($log['user_id']) ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="status-badge badge-info"><?= htmlspecialchars($log['action']) ?></span></td>
                <td><?= htmlspecialchars($log['module']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p style="color: #94a3b8;">No activity logs found</p>
        <?php endif; ?>
    </div>

    <!-- PHP Error Log -->
    <div class="panel">
        <div class="panel-header">📄 PHP Error Log (Last 50 Lines)</div>
        <?php if ($foundLogPath): ?>
        <p style="color: #94a3b8; margin-bottom: 10px;">
            <strong>Log file:</strong> <?= htmlspecialchars($foundLogPath) ?>
        </p>
        <div class="code-block">
            <?php foreach (array_reverse($errorLogLines) as $line): ?>
                <?php 
                if (preg_match('/(PracticalModel|practical|lab|PDO|SQLSTATE|Error|Exception)/i', $line)) {
                    echo '<span class="error-line">';
                } 
                echo htmlspecialchars($line) . "\n";
                if (preg_match('/(PracticalModel|practical|lab|PDO|SQLSTATE|Error|Exception)/i', $line)) {
                    echo '</span>';
                }
                ?>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: #ef4444;">
            <strong>Error log not found.</strong><br>
            Checked paths:
        </p>
        <div class="code-block" style="color: #ef4444;">
            <?php foreach ($errorLogPaths as $path): ?>
                ✗ <?= htmlspecialchars($path) ?><br>
            <?php endforeach; ?>
        </div>
        <p style="color: #94a3b8; margin-top: 15px;">
            Enable PHP error logging in php.ini or check Apache error logs.
        </p>
        <?php endif; ?>
    </div>

    <!-- Status Summary -->
    <div class="panel">
        <div class="panel-header">✓ System Status</div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <strong style="color: #3b82f6;">Database Connection</strong><br>
                <?php if ($dbConnection): ?>
                    <span class="status-badge badge-success">✓ Connected</span>
                <?php else: ?>
                    <span class="status-badge badge-error">✗ Failed: <?= htmlspecialchars($dbError) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <strong style="color: #3b82f6;">Error Log Access</strong><br>
                <?php if ($foundLogPath): ?>
                    <span class="status-badge badge-success">✓ Found</span>
                <?php else: ?>
                    <span class="status-badge badge-warning">⚠ Not Found</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 12px;">
        <p>SmartLab Debug Console — Last refreshed: <?= date('Y-m-d H:i:s') ?></p>
        <p>This page auto-refreshes every 5 seconds. Press F5 to force refresh.</p>
    </div>
</div>
</body>
</html>
