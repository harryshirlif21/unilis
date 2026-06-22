<?php
session_start();
define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

try {
    require_once DOCUMENT_ROOT . '/smart-lab/config/app.php';
    require_once DOCUMENT_ROOT . '/smart-lab/config/database.php';
    require_once DOCUMENT_ROOT . '/smart-lab/includes/autoloader.php';

    $studentId = $_SESSION['user_id'] ?? null;

    if (!$studentId) {
        header('Location: /smart-lab/auth/login.php');
        exit;
    }

    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    $controller = new \SmartLab\Controllers\DatasheetController($db);
    $datasheets = $controller->getStudentDatasheets($studentId);

    $stmt = $db->prepare(
        "SELECT p.* FROM practicals p 
         WHERE p.scheduled_date >= DATE(NOW()) 
         ORDER BY p.scheduled_date ASC LIMIT 10"
    );
    $stmt->execute();
    $upcomingPracticals = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Datasheets - JKUAT Smart Lab</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        header h1 {
            color: #003366;
            margin-bottom: 10px;
            font-size: 28px;
        }
        header p {
            color: #666;
            font-size: 14px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 15px 20px;
            background: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab:hover {
            color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #003366;
            flex: 1;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .card-content {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .card-meta {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
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
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            flex: 0 1 auto;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            margin-bottom: 10px;
            color: #666;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #003366;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Lab Datasheet Management</h1>
            <p>Welcome <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?> - Download and print lab datasheets</p>
        </header>

        <div class="info-box">
            📋 Lab datasheets include experiment descriptions, data tables, and QR codes for verification.
            Download, print, and fill them during practical sessions.
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('datasheets')">My Datasheets</button>
            <button class="tab" onclick="switchTab('available')">Available Practicals</button>
        </div>

        <div id="datasheets" class="tab-content active">
            <?php if (!empty($datasheets)): ?>
                <div class="card-grid">
                    <?php foreach ($datasheets as $ds): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><?php echo htmlspecialchars($ds['practical_title'] ?? 'Datasheet'); ?></div>
                            <span class="status-badge status-<?php echo strtolower($ds['approval_status']); ?>">
                                <?php echo ucfirst($ds['approval_status']); ?>
                            </span>
                        </div>
                        <div class="card-content">
                            <div><strong>Status:</strong> <?php echo ucfirst($ds['status']); ?></div>
                            <div><strong>Generated:</strong> <?php echo date('M d, Y', strtotime($ds['created_at'])); ?></div>
                            <?php if ($ds['approval_status'] === 'approved'): ?>
                            <div style="color: #155724; margin-top: 10px;">✓ Ready for download</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-meta">
                            <span>ID: <?php echo substr($ds['id'], 0, 8); ?>...</span>
                        </div>
                        <div class="card-actions">
                            <?php if ($ds['approval_status'] === 'approved'): ?>
                            <a href="/smart-lab/api/download_datasheet.php?action=download&datasheet_id=<?php echo urlencode($ds['id']); ?>" 
                               class="btn btn-primary" title="Download PDF">
                                📥 Download
                            </a>
                            <button class="btn btn-secondary btn-small" onclick="printDatasheet('<?php echo htmlspecialchars($ds['id']); ?>')">
                                🖨️ Print
                            </button>
                            <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                ⏳ Pending Approval
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📄</div>
                    <h3>No datasheets yet</h3>
                    <p>You haven't generated any datasheets. Create one from an available practical below.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="available" class="tab-content">
            <?php if (!empty($upcomingPracticals)): ?>
                <div class="card-grid">
                    <?php foreach ($upcomingPracticals as $practical): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><?php echo htmlspecialchars($practical['title']); ?></div>
                        </div>
                        <div class="card-content">
                            <div><strong>Date:</strong> <?php echo date('M d, Y', strtotime($practical['scheduled_date'])); ?></div>
                            <?php if (!empty($practical['start_time'])): ?>
                            <div><strong>Time:</strong> <?php echo substr($practical['start_time'], 0, 5); ?></div>
                            <?php endif; ?>
                            <div style="margin-top: 10px; color: #666;">
                                <?php echo htmlspecialchars(substr($practical['description'] ?? '', 0, 100)); ?>...
                            </div>
                        </div>
                        <div class="card-actions">
                            <button class="btn btn-primary" onclick="generateDatasheet('<?php echo htmlspecialchars($practical['id']); ?>')">
                                ✏️ Generate Datasheet
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h3>No upcoming practicals</h3>
                    <p>There are no scheduled practicals available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function generateDatasheet(practicalId) {
            if (!confirm('Generate datasheet for this practical?')) return;

            fetch('/smart-lab/api/datasheet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate',
                    practical_id: practicalId,
                    authentication_method: 'password'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Datasheet generated successfully!\nYou can now download it from "My Datasheets" tab.');
                    switchTab('datasheets');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error occurred'));
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }

        function printDatasheet(datasheetId) {
            alert('Print functionality would open print dialog for datasheet: ' + datasheetId);
        }
    </script>
</body>
</html>
<?php

} catch (\Exception $e) {
    error_log('Datasheets Page Error: ' . $e->getMessage());
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Error</title></head>
    <body>
        <h1>Error</h1>
        <p>An error occurred: <?php echo htmlspecialchars($e->getMessage()); ?></p>
    </body>
    </html>
    <?php
}
?>
