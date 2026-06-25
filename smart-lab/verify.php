<?php
declare(strict_types=1);

session_start();

$appRoot = __DIR__;
$isProduction = strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false;

try {
    if ($isProduction) {
        require_once $appRoot . '/config/app_production.php';
        require_once $appRoot . '/config/database_production.php';
    } else {
        require_once $appRoot . '/config/app.php';
        require_once $appRoot . '/config/database.php';
    }
} catch (Throwable $e) {
    error_log('Verification config error: ' . $e->getMessage());
    http_response_code(500);
    $verificationError = 'Verification is temporarily unavailable.';
}

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_date(?string $value): string {
    if (!$value) {
        return 'N/A';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('F j, Y \a\t g:i A', $timestamp) : $value;
}

$verified = false;
$isLabReport = false;
$datasheet = [];
$message = $verificationError ?? '';

if ($message === '') {
    try {
        $db = getDB();

        $type = $_GET['type'] ?? 'datasheet';
        $studentId = $_GET['student_id'] ?? null;
        $reportId = $_GET['report_id'] ?? null;
        $practicalId = $_GET['practical_id'] ?? null;
        $token = $_GET['token'] ?? '';

        if ($type === 'lab_report') {
            $isLabReport = true;

            if (!$reportId || !$studentId) {
                http_response_code(400);
                $message = 'Missing report or student identifier.';
            } else {
                $stmt = $db->prepare("
                    SELECT r.id, r.status, r.submitted_at, r.updated_at,
                           r.practical_id, r.student_id,
                           p.title AS practical_title, p.course_code, p.scheduled_date,
                           l.name AS lab_name,
                           u.full_name AS student_name, u.reg_number
                    FROM lab_reports r
                    JOIN practicals p ON r.practical_id = p.id
                    LEFT JOIN labs l ON p.lab_id = l.id
                    JOIN users u ON r.student_id = u.id
                    WHERE r.id = ? AND r.student_id = ? AND r.status = 'submitted'
                    LIMIT 1
                ");
                $stmt->execute([$reportId, $studentId]);
                $datasheet = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                if ($datasheet) {
                    $expectedToken = hash_hmac(
                        'sha256',
                        $datasheet['id'] . '|' . $datasheet['student_id'] . '|' . ($datasheet['submitted_at'] ?? ''),
                        defined('QR_SECRET_KEY') ? QR_SECRET_KEY : 'smart-lab-verification'
                    );
                    $verified = ($token === '' || hash_equals($expectedToken, $token));
                    $message = $verified
                        ? 'Practical done. This student completed and submitted this practical.'
                        : 'Verification token does not match this student practical record.';
                } else {
                    http_response_code(404);
                    $message = 'No submitted practical record was found for this student.';
                }
            }
        } else {
            if (!$practicalId || !$studentId) {
                http_response_code(400);
                $message = 'Missing practical or student identifier.';
            } else {
                $stmt = $db->prepare("
                    SELECT d.*, p.title AS practical_title, p.course_code,
                           s.full_name AS student_name, s.reg_number
                    FROM datasheets d
                    LEFT JOIN practicals p ON d.practical_id = p.id
                    LEFT JOIN users s ON d.student_id = s.id
                    WHERE d.practical_id = ? AND d.student_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$practicalId, $studentId]);
                $datasheet = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $verified = !empty($datasheet) && (($datasheet['approval_status'] ?? '') === 'approved');
                $message = $verified
                    ? 'Practical done. This student completed this practical.'
                    : 'This practical record could not be verified.';

                if (!$datasheet) {
                    http_response_code(404);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Verification Page Error: ' . get_class($e) . ': ' . $e->getMessage());
        http_response_code(500);
        $message = 'An error occurred during verification. Please try again later.';
    }
}

$pageIdSource = (string)($datasheet['id'] ?? $datasheet['practical_id'] ?? $message);
$appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
$logoUrl = ($appUrl !== '' ? $appUrl : '/smart-lab') . '/jkuatlogo.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical Verification</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
            background: #eef2f7;
        }
        .modal {
            width: min(94vw, 520px);
            background: #fff;
            border: 1px solid #d8dee8;
            border-radius: 8px;
            box-shadow: 0 18px 50px rgba(18, 28, 45, 0.18);
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            gap: 14px;
            align-items: center;
            padding: 22px 24px;
            border-bottom: 1px solid #e5e9f0;
        }
        .modal-header img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }
        .modal-title h1 {
            margin: 0;
            font-size: 21px;
            line-height: 1.2;
        }
        .modal-title p {
            margin: 4px 0 0;
            color: #667085;
            font-size: 13px;
        }
        .status {
            padding: 22px 24px 12px;
            text-align: center;
        }
        .status-mark {
            display: inline-grid;
            place-items: center;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .status-ok .status-mark {
            color: #136c43;
            background: #dcfce7;
            border: 1px solid #86efac;
        }
        .status-fail .status-mark {
            color: #a32020;
            background: #fee2e2;
            border: 1px solid #fca5a5;
        }
        .status h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }
        .status p {
            margin: 0;
            color: #475467;
            line-height: 1.45;
        }
        .details {
            margin: 14px 24px 24px;
            border: 1px solid #e5e9f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid #e5e9f0;
            font-size: 14px;
        }
        .row:last-child { border-bottom: 0; }
        .label {
            color: #667085;
            font-weight: 700;
        }
        .value {
            color: #101828;
            overflow-wrap: anywhere;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 24px;
            color: #667085;
            background: #f8fafc;
            border-top: 1px solid #e5e9f0;
            font-size: 12px;
        }
        @media (max-width: 520px) {
            .row { grid-template-columns: 1fr; gap: 4px; }
            .modal-header { align-items: flex-start; }
            .footer { flex-direction: column; }
        }
    </style>
</head>
<body>
    <main class="modal" role="dialog" aria-labelledby="verification-title" aria-modal="true">
        <div class="modal-header">
            <img src="<?php echo h($logoUrl); ?>" alt="JKUAT Logo">
            <div class="modal-title">
                <h1 id="verification-title">Smart Lab Verification</h1>
                <p>Scanned practical completion record</p>
            </div>
        </div>

        <section class="status <?php echo $verified ? 'status-ok' : 'status-fail'; ?>">
            <div class="status-mark"><?php echo $verified ? '&#10003;' : '!'; ?></div>
            <h2><?php echo $verified ? 'Practical Done' : 'Not Verified'; ?></h2>
            <p><?php echo h($message); ?></p>
        </section>

        <section class="details" aria-label="Verification details">
            <div class="row">
                <div class="label">Student</div>
                <div class="value"><?php echo h($datasheet['student_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="row">
                <div class="label">Admission No.</div>
                <div class="value"><?php echo h($datasheet['reg_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="row">
                <div class="label">Practical</div>
                <div class="value"><?php echo h($datasheet['practical_title'] ?? 'N/A'); ?></div>
            </div>
            <?php if (!empty($datasheet['course_code'])): ?>
            <div class="row">
                <div class="label">Course Code</div>
                <div class="value"><?php echo h($datasheet['course_code']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($datasheet['lab_name'])): ?>
            <div class="row">
                <div class="label">Laboratory</div>
                <div class="value"><?php echo h($datasheet['lab_name']); ?></div>
            </div>
            <?php endif; ?>
            <div class="row">
                <div class="label">Submitted</div>
                <div class="value"><?php echo h(format_date($datasheet['submitted_at'] ?? $datasheet['created_at'] ?? null)); ?></div>
            </div>
            <div class="row">
                <div class="label">Record Type</div>
                <div class="value"><?php echo $isLabReport ? 'Submitted lab report' : 'Approved datasheet'; ?></div>
            </div>
        </section>

        <footer class="footer">
            <span>Checked: <?php echo h(date('Y-m-d H:i:s')); ?></span>
            <span>Page ID: <?php echo h(substr(hash('sha256', $pageIdSource), 0, 10)); ?></span>
        </footer>
    </main>
</body>
</html>
