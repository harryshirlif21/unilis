<?php
/**
 * Lab Datasheet Workflow - Advanced Migration Runner
 * 
 * Usage: 
 *   CLI: php migrate.php
 *   WEB: http://localhost/smart-lab/migrate.php
 * 
 * Supports both command-line and web-based execution
 */

define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
$isWebRequest = !empty($_SERVER['HTTP_HOST']);

if ($isWebRequest) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Migration - Lab Datasheet System</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 700px;
                width: 100%;
                padding: 40px;
            }
            h1 {
                color: #003366;
                margin-bottom: 10px;
                font-size: 28px;
            }
            .subtitle {
                color: #666;
                margin-bottom: 30px;
                font-size: 14px;
            }
            .output {
                background: #f5f5f5;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                max-height: 400px;
                overflow-y: auto;
                line-height: 1.6;
                color: #333;
            }
            .log-line { margin: 5px 0; }
            .log-success { color: #28a745; }
            .log-error { color: #dc3545; }
            .log-info { color: #17a2b8; }
            .log-warn { color: #ffc107; }
            .button-group {
                display: flex;
                gap: 10px;
                justify-content: center;
            }
            .btn {
                padding: 12px 30px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            .btn-primary {
                background-color: #667eea;
                color: white;
            }
            .btn-primary:hover {
                background-color: #5a67d8;
            }
            .btn-secondary {
                background-color: #e9ecef;
                color: #333;
            }
            .btn-secondary:hover {
                background-color: #dee2e6;
            }
            .status {
                padding: 15px;
                border-radius: 8px;
                margin-top: 20px;
                font-weight: 600;
            }
            .status-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .status-error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .next-steps {
                background: #e7f3ff;
                border-left: 4px solid #667eea;
                padding: 20px;
                margin-top: 20px;
                border-radius: 4px;
                font-size: 13px;
                line-height: 1.8;
            }
            .next-steps h3 {
                color: #003366;
                margin-bottom: 10px;
                font-size: 14px;
            }
            .next-steps ol {
                margin-left: 20px;
            }
            .next-steps li {
                margin: 8px 0;
                color: #333;
            }
            code {
                background: #f0f0f0;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🗄️ Database Migration</h1>
            <p class="subtitle">Lab Datasheet Workflow System</p>
            
            <div class="output" id="output"></div>
            
            <div class="button-group">
                <button class="btn btn-primary" onclick="runMigration()">▶️ Run Migration</button>
                <button class="btn btn-secondary" onclick="clearOutput()">🗑️ Clear</button>
            </div>
            
            <div id="status"></div>
            <div id="nextSteps"></div>
        </div>

        <script>
            const output = document.getElementById('output');
            const statusDiv = document.getElementById('status');
            const nextStepsDiv = document.getElementById('nextSteps');

            function log(message, type = 'info') {
                const line = document.createElement('div');
                line.className = `log-line log-${type}`;
                line.textContent = message;
                output.appendChild(line);
                output.scrollTop = output.scrollHeight;
            }

            function clearOutput() {
                output.innerHTML = '';
                statusDiv.innerHTML = '';
                nextStepsDiv.innerHTML = '';
            }

            function runMigration() {
                clearOutput();
                log('Starting migration...', 'info');
                
                fetch('?action=migrate', { method: 'GET' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.logs) {
                            data.logs.forEach(msg => {
                                log(msg.message, msg.type);
                            });
                        }

                        if (data.success) {
                            statusDiv.innerHTML = '<div class="status status-success">✅ ' + (data.message || 'Migration completed successfully!') + '</div>';
                            showNextSteps();
                        } else {
                            statusDiv.innerHTML = '<div class="status status-error">❌ ' + (data.message || 'Migration failed!') + '</div>';
                        }
                    })
                    .catch(error => {
                        log('Error: ' + error.message, 'error');
                        statusDiv.innerHTML = '<div class="status status-error">❌ Error occurred</div>';
                    });
            }

            function showNextSteps() {
                nextStepsDiv.innerHTML = `
                    <div class="next-steps">
                        <h3>📋 Next Steps</h3>
                        <ol>
                            <li>Setup chemistry practicals: <code>php setup_chemistry_practicals.php</code></li>
                            <li>Install dependencies: <code>composer require teknickcom/tcpdf phpqrcode/phpqrcode</code></li>
                            <li>Create directories: <code>mkdir -p assets/datasheets assets/qrcodes</code></li>
                            <li>Test dashboard: <a href="/smart-lab/views/datasheets.php" target="_blank">http://localhost/smart-lab/views/datasheets.php</a></li>
                        </ol>
                    </div>
                `;
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// CLI/API Handler
if ($isWebRequest && ($_GET['action'] ?? null) !== 'migrate') {
    http_response_code(404);
    die;
}

$logs = [];

function addLog($message, $type = 'info') {
    global $logs;
    $logs[] = ['message' => $message, 'type' => $type];
    if (!empty($_GET['action'])) {
        echo $message . "\n";
    }
}

try {
    addLog("=== Lab Datasheet Workflow Migration Runner ===\n", 'info');

    if (!file_exists(dirname(__DIR__) . '/config/db.php')) {
        throw new Exception("Database config not found");
    }

    require_once dirname(__DIR__) . '/config/db.php';

    $migrationFile = __DIR__ . '/migrations/datasheet_workflow_migration.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    addLog("📁 Migration file: " . basename($migrationFile), 'info');
    addLog("📊 Database: unilis_smartlab", 'info');

    $mysqli = new mysqli('localhost', 'root', '', 'unilis_smartlab');

    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }

    addLog("✓ Database connection established\n", 'success');

    $sqlContent = file_get_contents($migrationFile);
    $queries = explode(';', $sqlContent);

    $executed = 0;
    $skipped = 0;
    $errors = [];

    foreach ($queries as $query) {
        $query = trim($query);

        if (empty($query) || strpos($query, '--') === 0) {
            continue;
        }

        if (preg_match('/^\/\*[\s\S]*?\*\/$/', $query)) {
            continue;
        }

        $queryPreview = substr(preg_replace('/\s+/', ' ', $query), 0, 80);

        if ($mysqli->multi_query($query)) {
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());

            addLog("✓ " . $queryPreview . "...", 'success');
            $executed++;
        } else {
            $error = $mysqli->error;

            if (strpos($error, 'already exists') !== false || 
                strpos($error, 'Duplicate') !== false ||
                strpos($error, 'already') !== false) {
                addLog("ⓘ " . $queryPreview . "... (already exists)", 'warn');
                $skipped++;
            } else {
                addLog("✗ " . $queryPreview . "... ERROR: $error", 'error');
                $errors[] = [
                    'query' => $queryPreview,
                    'error' => $error
                ];
            }
        }
    }

    $mysqli->close();

    addLog("\n=== Migration Summary ===", 'info');
    addLog("✓ Executed: $executed", 'success');
    addLog("ⓘ Skipped: $skipped", 'info');
    addLog("✗ Errors: " . count($errors), $errors ? 'error' : 'success');

    if (!empty($errors)) {
        addLog("\nError Details:", 'error');
        foreach ($errors as $err) {
            addLog("  Query: " . $err['query'], 'error');
            addLog("  Error: " . $err['error'], 'error');
        }
        throw new Exception("Migration completed with " . count($errors) . " error(s)");
    }

    addLog("\n✅ Migration completed successfully!", 'success');
    addLog("\n📋 Next steps:", 'info');
    addLog("1. Setup practicals: php setup_chemistry_practicals.php", 'info');
    addLog("2. Install dependencies: composer require teknickcom/tcpdf phpqrcode/phpqrcode", 'info');
    addLog("3. Create directories: mkdir -p assets/datasheets assets/qrcodes", 'info');
    addLog("4. Test dashboard: http://localhost/smart-lab/views/datasheets.php", 'info');

    if ($isWebRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Migration completed successfully',
            'executed' => $executed,
            'skipped' => $skipped,
            'errors' => count($errors),
            'logs' => $logs
        ]);
    }

} catch (Exception $e) {
    addLog("\n❌ Migration failed!", 'error');
    addLog("Error: " . $e->getMessage(), 'error');

    if ($isWebRequest) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'logs' => $logs
        ]);
    } else {
        exit(1);
    }
}
?>
