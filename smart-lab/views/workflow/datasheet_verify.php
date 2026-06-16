<?php
/**
 * Datasheet QR Verification Page
 * Displays datasheet verification result when QR code is scanned
 */
$result = $result ?? [];
$token = $token ?? '';
$isValid = $result['valid'] ?? false;
$status = $result['status'] ?? 'INVALID';
$data = $result['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasheet Verification - UNILIS SmartLabs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 600px; width: 100%; margin: 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .header { padding: 30px; text-align: center; }
        .header.valid { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; }
        .header.invalid { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
        .header.warning { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { font-size: 14px; opacity: 0.9; }
        .status-icon { font-size: 48px; margin-bottom: 15px; }
        .content { padding: 30px; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-row:last-child { border-bottom: none; }
        .label { font-weight: 600; min-width: 180px; color: #555; font-size: 14px; }
        .value { flex: 1; color: #333; font-size: 14px; }
        .blockchain-hash { font-family: monospace; font-size: 11px; word-break: break-all; background: #f9f9f9; padding: 8px; border-radius: 4px; margin-top: 5px; }
        .footer { text-align: center; padding: 20px; background: #f8f9fa; font-size: 12px; color: #999; }
        .footer a { color: #3498db; text-decoration: none; }
        .btn { display: inline-block; padding: 10px 25px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; font-size: 14px; }
        .btn:hover { background: #2980b9; }
        .tamper-warning { background: #fde8e8; border: 1px solid #e74c3c; color: #c0392b; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .verified-badge { background: #d5f5e3; border: 1px solid #27ae60; color: #1e8449; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header <?php echo $status === 'VALID' ? 'valid' : ($status === 'TAMPERED' ? 'warning' : 'invalid'); ?>">
                <div class="status-icon">
                    <?php if ($status === 'VALID'): ?>✓
                    <?php elseif ($status === 'TAMPERED'): ?>⚠
                    <?php else: ?>✗
                    <?php endif; ?>
                </div>
                <h1>Datasheet Verification</h1>
                <p>Status: <strong><?php echo htmlspecialchars($status); ?></strong></p>
            </div>
            
            <div class="content">
                <?php if ($status === 'VALID'): ?>
                    <div class="verified-badge">
                        <strong>✓ This datasheet is authentic and verified</strong><br>
                        <small>Blockchain record confirmed - no tampering detected</small>
                    </div>
                <?php elseif ($status === 'TAMPERED'): ?>
                    <div class="tamper-warning">
                        <strong>⚠ TAMPER DETECTED!</strong><br>
                        <small>This datasheet appears to have been modified after submission</small>
                    </div>
                <?php else: ?>
                    <div class="tamper-warning">
                        <strong>✗ Verification Failed</strong><br>
                        <small><?php echo htmlspecialchars($result['message'] ?? 'Invalid or expired QR code'); ?></small>
                    </div>
                <?php endif; ?>
                
                <h3 style="margin: 20px 0 15px; color: #333; font-size: 16px;">Verification Details</h3>
                
                <div class="info-row">
                    <span class="label">Student Name</span>
                    <span class="value"><?php echo htmlspecialchars($data['student_name'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Registration Number</span>
                    <span class="value"><?php echo htmlspecialchars($data['reg_number'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Practical</span>
                    <span class="value"><?php echo htmlspecialchars($data['practical_name'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Attendance Status</span>
                    <span class="value"><?php echo htmlspecialchars($data['attendance_status'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Verification Method</span>
                    <span class="value"><strong><?php echo htmlspecialchars($data['verification_method'] ?? '—'); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Verification Time</span>
                    <span class="value"><?php echo htmlspecialchars($data['verification_time'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Practical Start</span>
                    <span class="value"><?php echo htmlspecialchars($data['practical_start_time'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Practical End</span>
                    <span class="value"><?php echo htmlspecialchars($data['practical_end_time'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Datasheet Submitted</span>
                    <span class="value"><?php echo htmlspecialchars($data['datasheet_submission_time'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Blockchain Hash</span>
                    <span class="value">
                        <div class="blockchain-hash"><?php echo htmlspecialchars($data['blockchain_hash'] ?? '—'); ?></div>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Verification Status</span>
                    <span class="value" style="color: <?php echo $status === 'VALID' ? '#27ae60' : '#e74c3c'; ?>; font-weight: bold;">
                        <?php echo htmlspecialchars($result['verification_status'] ?? $status); ?>
                    </span>
                </div>
            </div>
            
            <div class="footer">
                <p>Verified by UNILIS SmartLabs Blockchain Audit System</p>
                <p>This verification confirms academic integrity of the practical session</p>
                <br>
                <p><a href="<?php echo APP_URL; ?>">&larr; Return to UNILIS SmartLabs</a></p>
            </div>
        </div>
    </div>
</body>
</html>