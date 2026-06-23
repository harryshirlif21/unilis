<?php
session_start();
define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

// Environment detection
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false) {
    require_once DOCUMENT_ROOT . '/smart-lab/config/app_production.php';
    require_once DOCUMENT_ROOT . '/smart-lab/config/database_production.php';
} else {
    require_once DOCUMENT_ROOT . '/smart-lab/config/app.php';
    require_once DOCUMENT_ROOT . '/smart-lab/config/database.php';
}

try {
    $db = getDB();

    $type       = $_GET['type']       ?? 'datasheet';
    $student_id = $_GET['student_id'] ?? null;
    $report_id  = $_GET['report_id']  ?? null;   // new lab_reports flow
    $practical_id = $_GET['practical_id'] ?? null; // legacy datasheets flow

    // ── NEW FLOW: type=lab_report ──────────────────────────────────────────
    if ($type === 'lab_report' && $report_id && $student_id) {

        $stmt = $db->prepare("
            SELECT r.id, r.status, r.submitted_at, r.report_file, r.report_uploaded_at,
                   r.calculations, r.result, r.conclusion,
                   p.title as practical_title, p.course_code, p.scheduled_date,
                   l.name as lab_name,
                   u.full_name as student_name, u.reg_number
            FROM lab_reports r
            JOIN practicals p ON r.practical_id = p.id
            LEFT JOIN labs l ON p.lab_id = l.id
            JOIN users u ON r.student_id = u.id
            WHERE r.id = ? AND r.student_id = ? AND r.status = 'submitted'
            LIMIT 1
        ");
        $stmt->execute([$report_id, $student_id]);
        $datasheet = $stmt->fetch(PDO::FETCH_ASSOC);

        $verified = ($datasheet !== false);
        $isLabReport = true;

    // ── LEGACY FLOW: datasheets table ─────────────────────────────────────
    } else {
        $isLabReport = false;

        if (!$practical_id || !$student_id) {
            http_response_code(400);
            die('Missing required parameters: practical_id and student_id (or report_id and type=lab_report)');
        }

        $stmt = $db->prepare(
            "SELECT d.*, p.title as practical_title, s.full_name as student_name, s.reg_number
             FROM datasheets d
             LEFT JOIN practicals p ON d.practical_id = p.id
             LEFT JOIN users s ON d.student_id = s.id
             WHERE d.practical_id = ? AND d.student_id = ?
             LIMIT 1"
        );
        $stmt->execute([$practical_id, $student_id]);
        $datasheet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$datasheet) {
            http_response_code(404);
            die('Datasheet not found');
        }

        $verified = ($datasheet['approval_status'] === 'approved');
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Datasheet Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .verification-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .logo {
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 100px;
            height: auto;
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
        .verification-status {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-weight: bold;
            font-size: 18px;
        }
        .verified {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }
        .not-verified {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #333;
        }
        .detail-value {
            color: #666;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .verified .icon {
            color: #28a745;
        }
        .not-verified .icon {
            color: #f5c6cb;
        }
        .footer {
            font-size: 12px;
            color: #999;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
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
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="logo">
            <img src="<?php echo DOCUMENT_ROOT; ?>/smart-lab/jkuatlogo.jpg" alt="JKUAT Logo">
        </div>

        <h1>JKUAT Smart Lab System</h1>
        <p class="subtitle">Lab Datasheet Verification Portal</p>

        <div class="verification-status <?php echo $verified ? 'verified' : 'not-verified'; ?>">
            <div class="icon">
                <?php echo $verified ? '✓' : '✗'; ?>
            </div>
            <div>
                <?php
                if ($verified) {
                    echo '<strong>VERIFICATION SUCCESSFUL</strong><br>';
                    echo 'This datasheet has been verified and approved.';
                } else {
                    echo '<strong>VERIFICATION FAILED</strong><br>';
                    echo 'This datasheet could not be verified or is pending approval.';
                }
                ?>
            </div>
        </div>

        <div class="details">
            <div class="detail-row">
                <span class="detail-label">Student Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($datasheet['student_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Admission Number:</span>
                <span class="detail-value"><?php echo htmlspecialchars($datasheet['reg_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Practical:</span>
                <span class="detail-value"><?php echo htmlspecialchars($datasheet['practical_title'] ?? 'N/A'); ?></span>
            </div>
            <?php if (!empty($datasheet['course_code'])): ?>
            <div class="detail-row">
                <span class="detail-label">Course Code:</span>
                <span class="detail-value"><?php echo htmlspecialchars($datasheet['course_code']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($datasheet['lab_name'])): ?>
            <div class="detail-row">
                <span class="detail-label">Laboratory:</span>
                <span class="detail-value"><?php echo htmlspecialchars($datasheet['lab_name']); ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <strong><?php echo $verified ? 'SUBMITTED' : 'NOT VERIFIED'; ?></strong>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Submitted:</span>
                <span class="detail-value">
                    <?php
                    $ts = $datasheet['submitted_at'] ?? $datasheet['created_at'] ?? null;
                    echo $ts ? date('F j, Y \a\t g:i A', strtotime($ts)) : 'N/A';
                    ?>
                </span>
            </div>
            <?php if (!empty($datasheet['report_uploaded_at'])): ?>
            <div class="detail-row">
                <span class="detail-label">Report Uploaded:</span>
                <span class="detail-value" style="color:#16a34a;font-weight:600;">
                    <?php echo date('F j, Y \a\t g:i A', strtotime($datasheet['report_uploaded_at'])); ?> ✓
                </span>
            </div>
            <?php endif; ?>
            <?php if ($isLabReport && $verified): ?>
            <div class="detail-row">
                <span class="detail-label">Datasheet ID:</span>
                <span class="detail-value" style="font-family:monospace;font-size:12px;"><?php echo substr($datasheet['id'], 0, 16); ?>...</span>
            </div>
            <?php elseif (!$isLabReport && $verified): ?>
            <div class="detail-row">
                <span class="detail-label">Signature Hash:</span>
                <span class="detail-value"><?php echo substr($datasheet['signature_hash'], 0, 16); ?>...</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <button class="btn btn-primary" onclick="window.print()">Print Page</button>
            <button class="btn btn-secondary" onclick="window.history.back()">Go Back</button>
        </div>

        <div class="footer">
            <p>Verification Timestamp: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Page ID: <?php echo substr(md5($datasheet['id']), 0, 8); ?></p>
        </div>
    </div>
</body>
</html>
<?php
} catch (\Exception $e) {
    error_log('Verification Page Error: ' . $e->getMessage());
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Error</title>
        <style>
            body { font-family: sans-serif; padding: 40px; text-align: center; }
            .error { color: #d32f2f; font-size: 18px; }
        </style>
    </head>
    <body>
        <div class="error">An error occurred during verification. Please try again later.</div>
    </body>
    </html>
    <?php
}
?>
