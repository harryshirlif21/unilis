<?php
/**
 * SmartLab Labs Fixer & Test Tool
 * Repair database issues and test practical creation
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
try {
    require_once __DIR__.'/../config/database.php';
    $dbConnection = getDB();
} catch (Exception $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

require_once __DIR__.'/../utils/helpers.php';
require_once __DIR__.'/../models/PracticalModel.php';

// Simple token for actions
$actionToken = md5('smartlab_action_' . date('Y-m-d'));
$submittedToken = $_POST['action_token'] ?? '';

$actionResult = [];
$logFile = __DIR__ . '/debug_log.txt';

// Helper function to log actions
function logAction($action, $details) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $action | " . json_encode($details) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $submittedToken === $actionToken) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'activate_labs') {
        try {
            $result = $dbConnection->exec("UPDATE labs SET is_active = 1 WHERE is_active IS NULL OR is_active = 0");
            $actionResult = [
                'success' => true,
                'message' => "✓ Updated $result lab(s) to active status",
                'action' => 'activate_labs'
            ];
            logAction('ACTIVATE_LABS', ['rows_updated' => $result]);
        } catch (Exception $e) {
            $actionResult = [
                'success' => false,
                'message' => '✗ Error: ' . $e->getMessage(),
                'action' => 'activate_labs'
            ];
            logAction('ACTIVATE_LABS_ERROR', ['error' => $e->getMessage()]);
        }
    }
    
    elseif ($action === 'test_create') {
        try {
            $model = new PracticalModel();
            $labs = $model->getLabs();
            
            if (empty($labs)) {
                throw new Exception('No active labs found. Run "Activate All Labs" first.');
            }
            
            $testData = [
                'id' => bin2hex(random_bytes(16)),
                'title' => 'Debug Test - ' . date('H:i:s'),
                'description' => 'Automated test practical',
                'lab_id' => $labs[0]['id'],
                'lecturer_id' => 'debug-user-001',
                'course_code' => 'TEST101',
                'scheduled_date' => date('Y-m-d', strtotime('+2 days')),
                'start_time' => '13:00',
                'end_time' => '15:00',
                'max_students' => 25,
                'required_equipment' => 'Test equipment',
                'required_chemicals' => '',
                'safety_notes' => 'Test safety notes',
                'status' => 'draft'
            ];
            
            $created = $model->create($testData);
            
            $actionResult = [
                'success' => $created,
                'message' => $created ? '✓ Test practical created successfully' : '✗ Create returned false',
                'action' => 'test_create',
                'practical_id' => $testData['id'],
                'test_data' => $testData
            ];
            logAction('TEST_CREATE_' . ($created ? 'SUCCESS' : 'FAILED'), $testData);
        } catch (Exception $e) {
            $actionResult = [
                'success' => false,
                'message' => '✗ Error: ' . $e->getMessage(),
                'action' => 'test_create'
            ];
            logAction('TEST_CREATE_ERROR', ['error' => $e->getMessage()]);
        }
    }
    
    elseif ($action === 'clear_drafts') {
        try {
            $result = $dbConnection->exec("DELETE FROM practicals WHERE status = 'draft' AND scheduled_date < CURDATE()");
            $actionResult = [
                'success' => true,
                'message' => "✓ Deleted $result old draft practical(s)",
                'action' => 'clear_drafts'
            ];
            logAction('CLEAR_DRAFTS', ['rows_deleted' => $result]);
        } catch (Exception $e) {
            $actionResult = [
                'success' => false,
                'message' => '✗ Error: ' . $e->getMessage(),
                'action' => 'clear_drafts'
            ];
            logAction('CLEAR_DRAFTS_ERROR', ['error' => $e->getMessage()]);
        }
    }
}

// Get current labs state
$labsState = [];
try {
    $labsState = $dbConnection->query(
        "SELECT id, name, lab_code, is_active FROM labs ORDER BY name"
    )->fetchAll();
} catch (Exception $e) {
    // Labs fetch failed
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SmartLab Labs Fixer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #334155;
        }
        h1 { font-size: 32px; color: #fff; font-weight: bold; }
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .pass { background: #22c55e; color: #000; }
        .fail { background: #ef4444; color: #fff; }
        .warning { background: #eab308; color: #000; }
        button {
            background: #3b82f6;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 0;
        }
        button:hover { background: #2563eb; }
        button.danger {
            background: #ef4444;
        }
        button.danger:hover {
            background: #dc2626;
        }
        .action-result {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            border-left: 4px solid #22c55e;
        }
        .action-result.error {
            background: #7c2d12;
            color: #fca5a5;
            border-left-color: #ef4444;
        }
        .action-result.success {
            background: #166534;
            color: #bbf7d0;
            border-left-color: #22c55e;
        }
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
        .confirmation-needed {
            background: #7c2d12;
            border: 1px solid #ea580c;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .confirmation-needed p {
            color: #fca5a5;
            margin-bottom: 15px;
        }
        .code-block {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 4px;
            padding: 12px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #a1d96c;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔧 SmartLab Labs Fixer</h1>
        <div class="nav">
            <a href="practical_debug.php?token=smartlab_debug_2024">Debug</a>
            <a href="fix_labs.php?token=smartlab_debug_2024">Fix Labs</a>
            <a href="error_monitor.php?token=smartlab_debug_2024">Monitor</a>
        </div>
    </div>

    <div class="warning-banner">
        ⚠️ DEBUG MODE — Remove these files before production deployment
    </div>

    <!-- Current Labs State -->
    <div class="panel">
        <div class="panel-header">📊 Current Labs State</div>
        <?php if (!empty($labsState)): ?>
        <table>
            <tr>
                <th>Lab Name</th>
                <th>Code</th>
                <th>Status</th>
            </tr>
            <?php foreach ($labsState as $lab): ?>
            <tr>
                <td><?= htmlspecialchars($lab['name']) ?></td>
                <td><?= htmlspecialchars($lab['lab_code']) ?></td>
                <td>
                    <?php if ($lab['is_active']): ?>
                        <span class="status-badge pass">ACTIVE</span>
                    <?php else: ?>
                        <span class="status-badge fail">INACTIVE</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <p style="color: #94a3b8;">No labs found in database</p>
        <?php endif; ?>
    </div>

    <!-- Action Results -->
    <?php if (!empty($actionResult)): ?>
    <div class="panel">
        <div class="panel-header">✓ Action Result</div>
        <div class="action-result <?= $actionResult['success'] ? 'success' : 'error' ?>">
            <?= htmlspecialchars($actionResult['message']) ?>
        </div>
        
        <?php if ($actionResult['action'] === 'test_create' && $actionResult['success']): ?>
        <div class="code-block">
            <strong>Practical ID:</strong> <?= htmlspecialchars($actionResult['practical_id']) ?><br>
            <strong>Title:</strong> <?= htmlspecialchars($actionResult['test_data']['title']) ?><br>
            <strong>Lab:</strong> <?= htmlspecialchars($actionResult['test_data']['lab_id']) ?><br>
            <strong>Date:</strong> <?= htmlspecialchars($actionResult['test_data']['scheduled_date']) ?><br>
            <strong>Time:</strong> <?= htmlspecialchars($actionResult['test_data']['start_time']) ?> - <?= htmlspecialchars($actionResult['test_data']['end_time']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Activate All Labs -->
    <div class="panel">
        <div class="panel-header">🟢 Activate All Labs</div>
        <p style="color: #94a3b8; margin-bottom: 15px;">
            This will set is_active = 1 for all labs with NULL or 0 status.
            This fixes the issue where labs don't appear in the create practical form dropdown.
        </p>
        <div class="code-block">
            UPDATE labs SET is_active = 1 WHERE is_active IS NULL OR is_active = 0;
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="activate_labs">
            <input type="hidden" name="action_token" value="<?= htmlspecialchars($actionToken) ?>">
            <div class="confirmation-needed">
                <p>⚠️ This action will modify your database.</p>
                <button type="submit" name="confirm" value="yes">Confirm: Activate All Labs</button>
            </div>
        </form>
    </div>

    <!-- Clear Old Drafts -->
    <div class="panel">
        <div class="panel-header">🗑️ Clear Old Draft Practicals</div>
        <p style="color: #94a3b8; margin-bottom: 15px;">
            This will delete all draft practicals with scheduled_date before today.
            This prevents old test data from blocking lab bookings.
        </p>
        <div class="code-block">
            DELETE FROM practicals WHERE status = 'draft' AND scheduled_date &lt; CURDATE();
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="clear_drafts">
            <input type="hidden" name="action_token" value="<?= htmlspecialchars($actionToken) ?>">
            <div class="confirmation-needed">
                <p>⚠️ This action will delete data from your database.</p>
                <button type="submit" name="confirm" value="yes" class="danger">Confirm: Delete Old Drafts</button>
            </div>
        </form>
    </div>

    <!-- Test Create Practical -->
    <div class="panel">
        <div class="panel-header">🧪 Test Create Practical</div>
        <p style="color: #94a3b8; margin-bottom: 15px;">
            This will create a test practical in the first active lab with tomorrow's date.
            Use this to verify that the create practical functionality is working.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="test_create">
            <input type="hidden" name="action_token" value="<?= htmlspecialchars($actionToken) ?>">
            <div class="confirmation-needed">
                <p>✓ This action will create test data (can be deleted later)</p>
                <button type="submit" name="confirm" value="yes">Create Test Practical</button>
            </div>
        </form>
    </div>

    <!-- Debug Log -->
    <div class="panel">
        <div class="panel-header">📋 Action Log</div>
        <?php
        if (file_exists($logFile)) {
            $logLines = file($logFile, FILE_IGNORE_NEW_LINES);
            $recentLines = array_slice($logLines, -20);
            echo '<div class="code-block" style="max-height: 300px; overflow-y: auto;">';
            foreach (array_reverse($recentLines) as $line) {
                echo htmlspecialchars($line) . '<br>';
            }
            echo '</div>';
            echo '<p style="color: #94a3b8; margin-top: 15px; font-size: 12px;">Log file: ' . htmlspecialchars($logFile) . '</p>';
        } else {
            echo '<p style="color: #94a3b8;">No log file yet. Actions will create one.</p>';
        }
        ?>
    </div>
</div>
</body>
</html>
