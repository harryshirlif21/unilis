<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../learn/includes/mpesa.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$adminId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT email FROM admins WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || strtolower(trim((string)$admin['email'])) !== 'admin@unilis.com') {
    http_response_code(403);
    exit('Forbidden: this page is restricted to the designated super administrator.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stkConfig = learn_mpesa_config('stk');
$requiredStkConfig = [
    'MPESA_CONSUMER_KEY' => $stkConfig['consumer_key'],
    'MPESA_CONSUMER_SECRET' => $stkConfig['consumer_secret'],
    'MPESA_SHORTCODE' => $stkConfig['shortcode'],
    'MPESA_PASSKEY' => $stkConfig['passkey'],
    'MPESA_STK_RESULT_URL (or MPESA_RESULT_URL)' => $stkConfig['result_url'],
];
$missingStkConfig = array_keys(array_filter($requiredStkConfig, static function ($value): bool {
    return trim((string)$value) === '';
}));

$paymentCount = 0;
$recentPayments = [];
$paymentTable = $conn->query("SHOW TABLES LIKE 'short_course_payments'");
if ($paymentTable && $paymentTable->num_rows > 0) {
    $paymentCount = (int)$conn->query('SELECT COUNT(*) AS total FROM short_course_payments')->fetch_assoc()['total'];
    $recent = $conn->query("
        SELECT p.id, p.amount, p.status, p.created_at, c.title, l.email
        FROM short_course_payments p
        JOIN public_courses c ON c.id = p.course_id
        JOIN external_learners l ON l.id = p.learner_id
        ORDER BY p.id DESC LIMIT 8
    ");
    if ($recent) {
        while ($row = $recent->fetch_assoc()) {
            $recentPayments[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Integration - UNILIS Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .mpesa-page { width: min(1000px, calc(100% - 36px)); margin: 28px auto; }
        .mpesa-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 22px; margin-bottom: 18px; box-shadow: 0 4px 15px rgba(0,0,0,.06); }
        .mpesa-status { display: inline-flex; gap: 6px; align-items: center; padding: 8px 12px; border-radius: 999px; font-size: 14px; }
        .mpesa-ok { background: #dcfce7; color: #166534; }
        .mpesa-error { background: #fee2e2; color: #991b1b; }
        .mpesa-info { background: #e0f2fe; color: #075985; }
        .mpesa-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .mpesa-test-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
        .mpesa-test-form label { display: block; margin-bottom: 6px; font-weight: 600; }
        .mpesa-test-form input { box-sizing: border-box; width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 7px; }
        .mpesa-test-result { display: none; margin-top: 14px; padding: 12px; border-radius: 8px; white-space: pre-wrap; }
        .mpesa-table-wrap { overflow-x: auto; }
        .mpesa-table { width: 100%; border-collapse: collapse; background: #fff; }
        .mpesa-table th, .mpesa-table td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body data-theme="light">
<header class="header">
    <h1>M-Pesa Integration</h1>
    <div class="admin-info">Welcome, <?= htmlspecialchars((string)($_SESSION['user_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></div>
</header>

<main class="mpesa-page">
    <div class="mpesa-card">
        <h2><i class="fas fa-mobile-alt" style="color:#16a34a;"></i> Sandbox STK Push</h2>
        <p>This test sends a KSh 1 STK request through Safaricom's sandbox. It does not create a course payment or enrolment record.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <span class="mpesa-status mpesa-info">Test endpoint: <strong>Sandbox</strong></span>
            <span class="mpesa-status <?= $missingStkConfig ? 'mpesa-error' : 'mpesa-ok' ?>">
                STK settings: <strong><?= $missingStkConfig ? 'Incomplete' : 'Present' ?></strong>
            </span>
            <span class="mpesa-status mpesa-info">Payments recorded: <strong><?= $paymentCount ?></strong></span>
        </div>
        <?php if ($missingStkConfig): ?>
            <p class="mpesa-error" style="padding:12px;border-radius:8px;margin:16px 0 0;">
                STK settings missing from PHP: <?= htmlspecialchars(implode(', ', $missingStkConfig), ENT_QUOTES, 'UTF-8') ?>.
                Credential values are not displayed. Ensure the values already configured on the server are passed through to the app.
            </p>
        <?php endif; ?>
        <div class="mpesa-actions">
            <a class="btn btn-primary" href="../learn/" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Open course catalogue</a>
            <a class="btn btn-success" href="../migrations/short_course_mpesa.php"><i class="fas fa-database"></i> Run M-Pesa migration</a>
            <a class="btn btn-secondary" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
        </div>
    </div>

    <div class="mpesa-card">
        <h2>Test phone number</h2>
        <form id="mpesaStkTestForm" class="mpesa-test-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div style="flex:1;min-width:220px;">
                <label for="mpesaTestPhone">Sandbox Safaricom number</label>
                <input id="mpesaTestPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="0712345678">
            </div>
            <button id="mpesaStkTestButton" type="submit" class="btn btn-success"><i class="fas fa-mobile-alt"></i> Send KSh 1 STK Test</button>
        </form>
        <div id="mpesaStkTestResult" class="mpesa-test-result" role="status" aria-live="polite"></div>
        <p style="margin-bottom:0;">Callback URL: <code>/api/mpesa_callback.php</code></p>
    </div>

    <div class="mpesa-card">
        <h2>Recent course payments</h2>
        <?php if ($recentPayments): ?>
            <div class="mpesa-table-wrap">
                <table class="mpesa-table">
                    <thead><tr><th>Course</th><th>Learner</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPayments as $payment): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$payment['title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$payment['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>KSh <?= number_format((float)$payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars((string)$payment['status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$payment['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="margin-bottom:0;">No short-course payment attempts have been recorded.</p>
        <?php endif; ?>
    </div>
</main>

<script>
document.getElementById('mpesaStkTestForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    const button = document.getElementById('mpesaStkTestButton');
    const result = document.getElementById('mpesaStkTestResult');
    const originalLabel = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    result.style.display = 'block';
    result.style.background = '#e0f2fe';
    result.style.color = '#075985';
    result.textContent = 'Sending a KSh 1 sandbox STK request...';

    try {
        const response = await fetch('mpesa_stk_test.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: new URLSearchParams(new FormData(this)),
            cache: 'no-store'
        });
        const data = await response.json();
        const succeeded = response.ok && data.success;
        result.style.background = succeeded ? '#dcfce7' : '#fee2e2';
        result.style.color = succeeded ? '#166534' : '#991b1b';
        result.textContent = (data.message || 'No response message was provided.')
            + (data.checkout_request_id ? '\nCheckout request ID: ' + data.checkout_request_id : '');
    } catch (error) {
        result.style.background = '#fee2e2';
        result.style.color = '#991b1b';
        result.textContent = 'Unable to send the STK test: ' + error.message;
    } finally {
        button.disabled = false;
        button.innerHTML = originalLabel;
    }
});
</script>
</body>
</html>
