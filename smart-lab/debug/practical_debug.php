<?php
/**
 * SmartLab Practical Creation Debug Console
 * Intercepts and diagnoses all errors during practical creation
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

// Increase error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load configuration
require_once __DIR__.'/../config/app.php';

// Attempt to get DB connection with error handling
$dbConnection = null;
$dbError = null;
try {
    require_once __DIR__.'/../config/database.php';
    $dbConnection = getDB();
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Load model and controller for testing
if ($dbConnection) {
    require_once __DIR__.'/../models/PracticalModel.php';
    require_once __DIR__.'/../utils/helpers.php';
}

$postData = [];
$postResults = [];
$issuesFound = [];

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_submit'])) {
    $postData = [
        'id' => bin2hex(random_bytes(16)),
        'title' => sanitize($_POST['title'] ?? 'Debug Test Practical'),
        'description' => $_POST['description'] ?? '',
        'lab_id' => sanitize($_POST['lab_id'] ?? ''),
        'lecturer_id' => 'debug-user-001',
        'course_code' => sanitize($_POST['course_code'] ?? ''),
        'scheduled_date' => $_POST['scheduled_date'] ?? date('Y-m-d', strtotime('+1 day')),
        'start_time' => $_POST['start_time'] ?? '09:00',
        'end_time' => $_POST['end_time'] ?? '11:00',
        'max_students' => intval($_POST['max_students'] ?? 20),
        'required_equipment' => $_POST['required_equipment'] ?? '',
        'required_chemicals' => $_POST['required_chemicals'] ?? '',
        'safety_notes' => $_POST['safety_notes'] ?? '',
        'status' => 'draft'
    ];

    if ($dbConnection) {
        $model = new PracticalModel();
        
        // Step 1: Required fields
        $postResults['step1_required'] = [
            'label' => 'Required Fields Check',
            'pass' => !(empty($postData['title']) || empty($postData['lab_id']) || 
                        empty($postData['scheduled_date']) || empty($postData['start_time']) || 
                        empty($postData['end_time'])),
            'data' => [
                'title' => $postData['title'],
                'lab_id' => $postData['lab_id'],
                'scheduled_date' => $postData['scheduled_date'],
                'start_time' => $postData['start_time'],
                'end_time' => $postData['end_time']
            ]
        ];

        // Step 2: DateTime validation
        $dateErrors = $model->validateDateTime(
            $postData['scheduled_date'],
            $postData['start_time'],
            $postData['end_time']
        );
        $postResults['step2_datetime'] = [
            'label' => 'DateTime Validation',
            'pass' => count($dateErrors) === 0,
            'errors' => $dateErrors
        ];

        // Step 3: Lab availability check
        if ($postResults['step1_required']['pass'] && $postResults['step2_datetime']['pass']) {
            $isAvailable = $model->checkLabAvailability(
                $postData['lab_id'],
                $postData['scheduled_date'],
                $postData['start_time'],
                $postData['end_time']
            );
            $postResults['step3_availability'] = [
                'label' => 'Lab Availability Check',
                'pass' => $isAvailable,
                'lab_id' => $postData['lab_id'],
                'date' => $postData['scheduled_date'],
                'time' => $postData['start_time'] . ' - ' . $postData['end_time'],
                'available' => $isAvailable ? 'YES' : 'NO'
            ];
        }

        // Step 4: Create practical
        if ($postResults['step2_datetime']['pass'] && ($postResults['step3_availability']['pass'] ?? true)) {
            try {
                $result = $model->create($postData);
                $postResults['step4_create'] = [
                    'label' => 'Create Practical',
                    'pass' => $result,
                    'message' => $result ? 'Practical created successfully' : 'PDO statement returned false'
                ];
            } catch (Exception $e) {
                $postResults['step4_create'] = [
                    'label' => 'Create Practical',
                    'pass' => false,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
                $issuesFound[] = [
                    'severity' => 'CRITICAL',
                    'issue' => 'PDO Exception during create',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SmartLab Debug Console</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            padding: 20px;
            line-height: 1.6;
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
        .nav {
            display: flex;
            gap: 15px;
        }
        .nav a {
            padding: 8px 16px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 4px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
        }
        .nav a:hover { background: #334155; }
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }
        .pass { background: #22c55e; color: #000; }
        .fail { background: #ef4444; color: #fff; }
        .warning { background: #eab308; color: #000; }
        .info { background: #3b82f6; color: #fff; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        table th { background: #0f172a; font-weight: bold; color: #3b82f6; }
        table tr:nth-child(odd) { background: #1e293b; }
        table tr:nth-child(even) { background: #0f172a; }
        .code-block {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 4px;
            padding: 12px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            color: #a1d96c;
        }
        .form-group {
            margin: 15px 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #3b82f6;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            border-radius: 4px;
            font-family: inherit;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        button {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-right: 10px;
        }
        button:hover { background: #2563eb; }
        .error-message {
            background: #7c2d12;
            color: #fca5a5;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #ef4444;
        }
        .success-message {
            background: #166534;
            color: #bbf7d0;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #22c55e;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #334155;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #94a3b8; font-weight: bold; }
        .detail-value { color: #e2e8f0; font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔧 SmartLab Debug Console</h1>
        <div class="nav">
            <a href="practical_debug.php?token=smartlab_debug_2024">Debug</a>
            <a href="fix_labs.php?token=smartlab_debug_2024">Fix Labs</a>
            <a href="error_monitor.php?token=smartlab_debug_2024">Monitor</a>
        </div>
    </div>

    <div class="warning-banner">
        ⚠️ DEBUG MODE — Remove these files before production deployment
    </div>

    <?php if (!$dbConnection): ?>
    <div class="panel">
        <div class="panel-header">❌ Database Connection Failed</div>
        <div class="error-message">
            <strong>Error:</strong> <?= htmlspecialchars($dbError) ?>
        </div>
        <p style="margin-top: 15px; color: #94a3b8;">
            The debug console cannot function without a database connection. 
            Please check your database configuration.
        </p>
    </div>
    <?php else: ?>

    <!-- SECTION A: Database Connection Panel -->
    <div class="panel">
        <div class="panel-header">✓ Database Connection</div>
        <div class="success-message">Connected to MySQL Database</div>
        <?php
        try {
            $versionResult = $dbConnection->query("SELECT VERSION() as version, DATABASE() as dbname")->fetch();
            $tablesResult = $dbConnection->query("SHOW TABLES")->fetchAll();
            $tableNames = array_column($tablesResult, key($tablesResult[0] ?? []));
        } catch (Exception $e) {
            $versionResult = null;
            $tablesResult = [];
        }
        ?>
        <div class="detail-row">
            <span class="detail-label">MySQL Version:</span>
            <span class="detail-value"><?= htmlspecialchars($versionResult['version'] ?? 'Unknown') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Database Name:</span>
            <span class="detail-value"><?= htmlspecialchars($versionResult['dbname'] ?? 'Unknown') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Tables Found:</span>
            <span class="detail-value"><?= count($tableNames) ?></span>
        </div>
        
        <h3 style="color: #3b82f6; margin-top: 20px; margin-bottom: 10px;">Table Structures</h3>
        
        <?php foreach (['labs', 'practicals', 'users'] as $table): ?>
            <?php 
            try {
                $describeResult = $dbConnection->query("DESCRIBE $table")->fetchAll();
            } catch (Exception $e) {
                $describeResult = [];
            }
            ?>
            <div style="margin-bottom: 20px;">
                <h4 style="color: #94a3b8; margin-bottom: 10px;">📋 <?= htmlspecialchars($table) ?> Table</h4>
                <table>
                    <tr>
                        <th>Column</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                    </tr>
                    <?php foreach ($describeResult as $col): ?>
                    <tr>
                        <td><?= htmlspecialchars($col['Field']) ?></td>
                        <td style="font-family: 'Courier New', monospace; font-size: 12px;"><?= htmlspecialchars($col['Type']) ?></td>
                        <td><?= $col['Null'] === 'YES' ? '✓' : '✗' ?></td>
                        <td><?= htmlspecialchars($col['Key'] ?? '') ?></td>
                        <td><?= htmlspecialchars($col['Default'] ?? '(none)') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- SECTION B: Labs Table Inspector -->
    <div class="panel">
        <div class="panel-header">🏫 Labs Table Inspector</div>
        <?php
        try {
            $labsResult = $dbConnection->query(
                "SELECT id, name, lab_code, is_active, max_capacity, 
                        COALESCE(current_count, 0) as current_count 
                 FROM labs ORDER BY name"
            )->fetchAll();
            $activeLabs = array_filter($labsResult, fn($l) => $l['is_active']);
        } catch (Exception $e) {
            $labsResult = [];
            $activeLabs = [];
            echo '<div class="error-message">Error fetching labs: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        <div class="detail-row">
            <span class="detail-label">Active Labs:</span>
            <span class="detail-value"><span class="status-badge pass"><?= count($activeLabs) ?> of <?= count($labsResult) ?> active</span></span>
        </div>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Code</th>
                <th>Status</th>
                <th>Capacity</th>
                <th>Current</th>
            </tr>
            <?php foreach ($labsResult as $lab): ?>
            <tr>
                <td><?= htmlspecialchars($lab['id']) ?></td>
                <td><?= htmlspecialchars($lab['name']) ?></td>
                <td><?= htmlspecialchars($lab['lab_code']) ?></td>
                <td>
                    <?php if ($lab['is_active']): ?>
                        <span class="status-badge pass">ACTIVE</span>
                    <?php else: ?>
                        <span class="status-badge fail">INACTIVE</span>
                    <?php endif; ?>
                </td>
                <td><?= $lab['max_capacity'] ?></td>
                <td><?= $lab['current_count'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- SECTION C: Practical Creation Simulation -->
    <div class="panel">
        <div class="panel-header">🧪 Practical Creation Simulation</div>
        <p style="color: #94a3b8; margin-bottom: 15px;">Simulating automatic test data creation...</p>
        
        <?php
        if ($dbConnection && !empty($labsResult)) {
            $testLab = reset($labsResult);
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            
            $testData = [
                'id' => bin2hex(random_bytes(16)),
                'title' => 'Debug Simulation Test',
                'lab_id' => $testLab['id'],
                'scheduled_date' => $tomorrow,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'max_students' => 20,
                'status' => 'draft'
            ];
            
            try {
                $model = new PracticalModel();
                
                // Validate datetime
                $dateErrors = $model->validateDateTime(
                    $testData['scheduled_date'],
                    $testData['start_time'],
                    $testData['end_time']
                );
                
                echo '<div class="detail-row">';
                echo '<span class="detail-label">DateTime Validation:</span>';
                if (count($dateErrors) === 0) {
                    echo '<span class="status-badge pass">PASS</span>';
                } else {
                    echo '<span class="status-badge fail">FAIL</span>';
                }
                echo '</div>';
                
                if (count($dateErrors) > 0) {
                    echo '<div class="error-message">Errors: ' . implode(', ', $dateErrors) . '</div>';
                }
                
                // Check availability
                $isAvailable = $model->checkLabAvailability(
                    $testData['lab_id'],
                    $testData['scheduled_date'],
                    $testData['start_time'],
                    $testData['end_time']
                );
                
                echo '<div class="detail-row">';
                echo '<span class="detail-label">Lab Availability:</span>';
                echo '<span class="status-badge ' . ($isAvailable ? 'pass' : 'fail') . '">' . ($isAvailable ? 'AVAILABLE' : 'BLOCKED') . '</span>';
                echo '</div>';
                
                echo '<div class="code-block">';
                echo 'Lab: ' . htmlspecialchars($testLab['name']) . ' (' . htmlspecialchars($testData['lab_id']) . ')<br>';
                echo 'Date: ' . htmlspecialchars($testData['scheduled_date']) . '<br>';
                echo 'Time: ' . htmlspecialchars($testData['start_time']) . ' - ' . htmlspecialchars($testData['end_time']);
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="error-message">Simulation Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
    </div>

    <!-- SECTION D: Conflict Checker -->
    <div class="panel">
        <div class="panel-header">⚠️ Conflict Checker</div>
        <?php
        try {
            $conflictsResult = $dbConnection->query(
                "SELECT id, title, lab_id, scheduled_date, start_time, end_time, status 
                 FROM practicals 
                 WHERE status IN ('published', 'ongoing') 
                 ORDER BY scheduled_date DESC, start_time ASC"
            )->fetchAll();
        } catch (Exception $e) {
            $conflictsResult = [];
            echo '<div class="error-message">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        if (empty($conflictsResult)) {
            echo '<div class="success-message">✓ No conflicts — lab schedule is clear</div>';
        } else {
            echo '<p style="color: #94a3b8; margin-bottom: 10px;">These practicals are currently BLOCKING new bookings:</p>';
            echo '<table>';
            echo '<tr><th>Title</th><th>Lab</th><th>Date</th><th>Time</th><th>Status</th></tr>';
            foreach ($conflictsResult as $conf) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($conf['title']) . '</td>';
                echo '<td>' . htmlspecialchars($conf['lab_id']) . '</td>';
                echo '<td>' . htmlspecialchars($conf['scheduled_date']) . '</td>';
                echo '<td>' . htmlspecialchars($conf['start_time']) . ' - ' . htmlspecialchars($conf['end_time']) . '</td>';
                echo '<td><span class="status-badge warning">' . htmlspecialchars($conf['status']) . '</span></td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div>

    <!-- SECTION E & H: POST Form Error Interceptor & Full Create Form Test -->
    <div class="panel">
        <div class="panel-header">📝 Create Practical Test Form</div>
        
        <form method="POST" style="margin-bottom: 30px;">
            <input type="hidden" name="test_submit" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($postData['title'] ?? 'Debug Test Practical') ?>" required>
                </div>
                <div class="form-group">
                    <label>Course Code</label>
                    <input type="text" name="course_code" value="<?= htmlspecialchars($postData['course_code'] ?? 'PHY101') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Laboratory</label>
                <select name="lab_id" required>
                    <option value="">Select Laboratory</option>
                    <?php foreach ($labsResult as $lab): ?>
                    <option value="<?= htmlspecialchars($lab['id']) ?>" <?= (isset($postData['lab_id']) && $postData['lab_id'] === $lab['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Scheduled Date</label>
                    <input type="date" name="scheduled_date" value="<?= htmlspecialchars($postData['scheduled_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Max Students</label>
                    <input type="number" name="max_students" value="<?= htmlspecialchars($postData['max_students'] ?? 20) ?>" min="1" max="100">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" value="<?= htmlspecialchars($postData['start_time'] ?? '09:00') ?>" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" value="<?= htmlspecialchars($postData['end_time'] ?? '11:00') ?>" required>
                </div>
            </div>
            
            <button type="submit">🚀 Test Submit</button>
        </form>
        
        <!-- POST Results -->
        <?php if (!empty($postResults)): ?>
        <div style="border-top: 2px solid #334155; padding-top: 20px;">
            <h3 style="color: #3b82f6; margin-bottom: 15px;">📊 Submission Trace Results</h3>
            
            <?php foreach ($postResults as $stepKey => $step): ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #0f172a; border-left: 4px solid <?= ($step['pass'] ?? false) ? '#22c55e' : '#ef4444' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong><?= htmlspecialchars($step['label']) ?></strong>
                    <span class="status-badge <?= ($step['pass'] ?? false) ? 'pass' : 'fail' ?>">
                        <?= ($step['pass'] ?? false) ? '✓ PASS' : '✗ FAIL' ?>
                    </span>
                </div>
                
                <?php if (!empty($step['errors'])): ?>
                    <div class="error-message">
                        <?php foreach ($step['errors'] as $err): ?>
                            • <?= htmlspecialchars($err) ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($step['error'])): ?>
                    <div class="error-message">
                        <strong>Exception:</strong> <?= htmlspecialchars($step['error']) ?><br>
                        <?php if (!empty($step['code'])): ?>
                            <strong>Code:</strong> <?= htmlspecialchars($step['code']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($step['file'])): ?>
                            <strong>File:</strong> <?= htmlspecialchars($step['file']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($step['line'])): ?>
                            <strong>Line:</strong> <?= htmlspecialchars($step['line']) ?><br>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($step['data'])): ?>
                    <div class="code-block">
                        <?php foreach ($step['data'] as $key => $value): ?>
                            <?= htmlspecialchars($key) ?>: <?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($step['message'])): ?>
                    <div class="success-message"><?= htmlspecialchars($step['message']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- SECTION F: PHP Error Log Viewer -->
    <div class="panel">
        <div class="panel-header">📄 PHP Error Log Viewer</div>
        <?php
        $errorLogPaths = [
            'C:\\xampp\\php\\logs\\php_error_log',
            'C:\\xampp\\apache\\logs\\error.log',
            '/var/log/php-error.log',
            '/var/log/apache2/error.log'
        ];
        
        $logContent = null;
        $foundPath = null;
        
        foreach ($errorLogPaths as $path) {
            if (file_exists($path)) {
                $foundPath = $path;
                $lines = file($path, FILE_IGNORE_NEW_LINES);
                $logContent = array_slice($lines, -100);
                break;
            }
        }
        
        if ($logContent) {
            echo '<p style="color: #94a3b8; margin-bottom: 10px;">Last 100 lines from: ' . htmlspecialchars($foundPath) . '</p>';
            echo '<div class="code-block" style="max-height: 400px; overflow-y: auto;">';
            foreach ($logContent as $line) {
                if (preg_match('/(PracticalModel|practical|lab|PDO|SQLSTATE)/i', $line)) {
                    echo '<span style="background: #7c2d12; color: #fca5a5;">' . htmlspecialchars($line) . '</span><br>';
                } else {
                    echo htmlspecialchars($line) . '<br>';
                }
            }
            echo '</div>';
        } else {
            echo '<div class="error-message">PHP error log not found. Checked paths:<br>';
            foreach ($errorLogPaths as $path) {
                echo '• ' . htmlspecialchars($path) . '<br>';
            }
            echo '</div>';
        }
        ?>
    </div>

    <!-- SECTION I: Fix Suggestions Panel -->
    <?php if (!empty($issuesFound)): ?>
    <div class="panel">
        <div class="panel-header">🔧 Fix Suggestions</div>
        <?php foreach ($issuesFound as $issue): ?>
        <div style="margin-bottom: 20px; padding: 15px; background: #0f172a; border-left: 4px solid <?= $issue['severity'] === 'CRITICAL' ? '#ef4444' : '#eab308' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <strong><?= htmlspecialchars($issue['issue']) ?></strong>
                <span class="status-badge <?= $issue['severity'] === 'CRITICAL' ? 'fail' : 'warning' ?>">
                    <?= htmlspecialchars($issue['severity']) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Message:</span>
                <span class="detail-value"><?= htmlspecialchars($issue['message']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">File:</span>
                <span class="detail-value"><?= htmlspecialchars($issue['file']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Line:</span>
                <span class="detail-value"><?= htmlspecialchars($issue['line']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Console error capture -->
<script>
console.errorStack = [];
const originalError = console.error;
console.error = function(...args) {
    console.errorStack.push(args.join(' '));
    originalError.apply(console, args);
};
</script>
</body>
</html>
