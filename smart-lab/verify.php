<?php
session_start();
define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);
require_once DOCUMENT_ROOT . '/smart-lab/config/app.php';

try {
    $practical_id = $_GET['practical_id'] ?? null;
    $student_id = $_GET['student_id'] ?? null;
    $status = $_GET['status'] ?? 'pending';
    $timestamp = $_GET['timestamp'] ?? date('Y-m-d H:i:s');

    if (!$practical_id || !$student_id) {
        http_response_code(400);
        die('Missing required parameters: practical_id, student_id');
    }

    $db = getDB();

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

    $signature = new \SmartLab\DigitalSignature();
    $isValid = $signature->verifySignature(
        $datasheet['signature_hash'],
        $student_id,
        $practical_id,
        $timestamp
    );

    if ($datasheet['approval_status'] !== 'approved') {
        $isValid = false;
        $status = 'pending';
    }

    $verified = ($isValid && $status === 'approved');

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
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <strong><?php echo htmlspecialchars(strtoupper($datasheet['approval_status'])); ?></strong>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Generated:</span>
                <span class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($datasheet['created_at'])); ?></span>
            </div>
            <?php if ($verified): ?>
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
