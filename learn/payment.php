<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/mpesa.php';
require_once __DIR__ . '/includes/layout.php';

learn_require_schema($conn);
$learner = learn_current($conn);
if ($learner === null) {
    header('Location: /learn/login.php');
    exit;
}

$slug = trim((string)($_GET['course'] ?? $_POST['course'] ?? ''));
$course = $slug !== '' ? learn_course_by_slug($conn, $slug) : null;
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$courseId = (int)$course['id'];
if (learn_is_enrolled($conn, (int)$learner['id'], $courseId)) {
    header('Location: /learn/course.php?c=' . urlencode($slug));
    exit;
}

$paymentTable = $conn->query("SHOW TABLES LIKE 'short_course_payments'");
$paymentsReady = $paymentTable && $paymentTable->num_rows > 0;
$sponsored = (int)($course['is_sponsored'] ?? 0) === 1;
$courseAmount = $sponsored ? 0 : (float)($course['price'] ?? 0);
$platformFee = $sponsored ? 250 : 500;
$total = (int)round($courseAmount + $platformFee);
$errors = [];

/**
 * The learner's latest payment attempt for this course, or null.
 */
function learn_course_latest_payment(mysqli $conn, int $learnerId, int $courseId): ?array
{
    $tbl = $conn->query("SHOW TABLES LIKE 'short_course_payments'");
    if (!$tbl || $tbl->num_rows === 0) {
        return null;
    }
    $tbl->free();
    $stmt = $conn->prepare("
        SELECT id, amount, status, mpesa_receipt, checkout_request_id, result_description, created_at, paid_at
        FROM short_course_payments
        WHERE learner_id = ? AND course_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * True when this learner already has a payment still awaiting a result. This is
 * what stops a double-click (or a tab reload) from firing a second STK prompt
 * for the same course.
 */
function learn_course_has_inflight(mysqli $conn, int $learnerId, int $courseId): bool
{
    $stmt = $conn->prepare("
        SELECT id FROM short_course_payments
        WHERE learner_id = ? AND course_id = ?
          AND status IN ('pending', 'reconciliation_required')
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $inflight = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $inflight;
}

$statusLabels = [
    'pending' => 'Payment request sent - awaiting approval',
    'paid' => 'Paid',
    'failed' => 'Payment failed',
    'cancelled' => 'Payment cancelled',
    'timeout' => 'Payment timed out',
    'reconciliation_required' => 'Payment under review',
    'payout_pending' => 'Paid (settlement pending)',
    'payout_paid' => 'Paid',
    'payout_failed' => 'Paid',
];

$latestPayment = learn_course_latest_payment($conn, (int)$learner['id'], $courseId);
$inflight = $latestPayment && in_array($latestPayment['status'], ['pending', 'reconciliation_required'], true);
$paymentId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$paymentsReady) {
        $errors[] = 'Payments are not enabled yet. An administrator must run the M-Pesa migration.';
    } elseif (!learn_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (learn_course_has_inflight($conn, (int)$learner['id'], $courseId)) {
        $errors[] = 'A payment for this course is still being processed. Check its status below.';
        $inflight = true;
    } else {
        try {
            $phone = learn_mpesa_normalise_phone((string)($_POST['phone'] ?? $learner['phone'] ?? ''));
            $stmt = $conn->prepare("
                INSERT INTO short_course_payments
                    (learner_id, course_id, phone, amount, course_amount, platform_fee, department_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $departmentAmount = $courseAmount;
            $stmt->bind_param('iisdddd', $learner['id'], $courseId, $phone, $total, $courseAmount, $platformFee, $departmentAmount);
            $stmt->execute();
            $paymentId = (int)$conn->insert_id;
            $stmt->close();

            $response = learn_mpesa_stk_push($phone, $total, 'SC-' . $paymentId, $course['title']);
            $merchant = (string)($response['MerchantRequestID'] ?? '');
            $checkout = (string)($response['CheckoutRequestID'] ?? '');
            if ($checkout === '') {
                throw new RuntimeException((string)($response['errorMessage'] ?? 'M-Pesa did not create a checkout request.'));
            }
            $stmt = $conn->prepare("UPDATE short_course_payments SET merchant_request_id = ?, checkout_request_id = ? WHERE id = ?");
            $stmt->bind_param('ssi', $merchant, $checkout, $paymentId);
            $stmt->execute();
            $stmt->close();
            $notice = 'A payment prompt has been sent to your phone. Complete it, then return to the course.';
        } catch (Throwable $e) {
            // STK may have rejected the request after the payment row was created.
            // Do not leave a dead 'pending' row that would block a retry - mark it
            // failed so the learner can try again.
            if ($paymentId > 0) {
                $stmt = $conn->prepare("UPDATE short_course_payments SET status='failed', result_description=? WHERE id=? AND status='pending'");
                $stmt->bind_param('si', $e->getMessage(), $paymentId);
                $stmt->execute();
                $stmt->close();
            }
            $errors[] = $e->getMessage();
        }
    }
}

// Re-evaluate after a submitted payment so the status panel reflects it.
$latestPayment = learn_course_latest_payment($conn, (int)$learner['id'], $courseId);
$inflight = $latestPayment && in_array($latestPayment['status'], ['pending', 'reconciliation_required'], true);

learn_head(['title' => 'Pay for course', 'narrow' => true]);
?>
<div class="ln-card">
    <h1>Complete registration</h1>
    <p class="ln-sub"><?= learn_e($course['title']) ?></p>
    <?php if (!empty($notice)): ?><?php learn_notice($notice, 'success'); ?><?php endif; ?>
    <?php learn_errors($errors); ?>
    <?php if (!$paymentsReady): ?>
        <p class="ln-sub">Payment setup is incomplete. Please contact the administrator.</p>
    <?php endif; ?>

    <?php if ($latestPayment): ?>
        <div id="paymentStatusPanel" style="padding:16px;background:#f5f6fa;border-radius:10px;margin:18px 0;">
            <strong>Payment status</strong><br>
            <span id="paymentStatusText"><?= learn_e($statusLabels[$latestPayment['status']] ?? (string)$latestPayment['status']) ?></span>
            <?php if (!empty($latestPayment['result_description']) && $latestPayment['status'] !== 'pending'): ?>
                <br><small><?= learn_e($latestPayment['result_description']) ?></small>
            <?php endif; ?>
            <?php if (!empty($latestPayment['mpesa_receipt'])): ?>
                <br><small>M-Pesa receipt: <?= learn_e($latestPayment['mpesa_receipt']) ?></small>
            <?php endif; ?>
            <button type="button" id="paymentStatusRefresh" class="ln-btn ln-btn-ghost" style="margin-top:10px;">
                <span class="material-symbols-rounded">refresh</span> Check status
            </button>
        </div>
    <?php endif; ?>

    <?php if (!$inflight): ?>
        <div style="padding:16px;background:#f5f6fa;border-radius:10px;margin:18px 0;">
            <strong>Amount to pay: KSh <?= number_format($total, 2) ?></strong><br>
            <small><?= $sponsored ? 'Sponsored registration fee: KSh 250' : 'Course fee: KSh ' . number_format($courseAmount, 2) . ' + service fee: KSh 500' ?></small>
        </div>
        <form method="post" <?= !$paymentsReady ? 'style="display:none"' : '' ?> id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
            <input type="hidden" name="course" value="<?= learn_e($slug) ?>">
            <div class="ln-field">
                <label for="phone">Safaricom phone number</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="0712345678" value="<?= learn_e($learner['phone'] ?? '') ?>">
            </div>
            <button class="ln-btn ln-btn-primary ln-btn-block" type="submit" id="paymentSubmit">
                <span class="material-symbols-rounded">phone_android</span> Pay with M-Pesa
            </button>
        </form>
    <?php else: ?>
        <p class="ln-sub">Complete the payment prompt on your phone, then use <em>Check status</em> above. Please do not submit another payment while one is pending.</p>
    <?php endif; ?>
</div>

<script>
(function () {
    const refreshBtn = document.getElementById('paymentStatusRefresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async function () {
            const original = refreshBtn.innerHTML;
            refreshBtn.disabled = true;
            refreshBtn.textContent = 'Checking…';
            try {
                const res = await fetch('/api/learn_payment_status.php?course=<?= learn_e(urlencode($slug)) ?>', {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                const data = await res.json();
                const text = document.getElementById('paymentStatusText');
                if (res.ok && data.ok && text) {
                    text.textContent = data.status_label || data.status || 'Unknown';
                    if (data.paid === true) {
                        window.location.reload();
                    }
                } else if (text) {
                    text.textContent = data.message || 'Could not check payment status.';
                }
            } catch (err) {
                const text = document.getElementById('paymentStatusText');
                if (text) text.textContent = 'Could not check payment status.';
            } finally {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = original;
            }
        });
    }

    const form = document.getElementById('paymentForm');
    if (form) {
        // Double-click guard: switch it off after the first submit so a second
        // STK prompt cannot be fired for the same course.
        form.addEventListener('submit', function () {
            const btn = document.getElementById('paymentSubmit');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Sending payment prompt…';
            }
        });
    }
})();
</script>
<?php learn_foot(); ?>
