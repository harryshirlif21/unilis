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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$paymentsReady) {
        $errors[] = 'Payments are not enabled yet. An administrator must run the M-Pesa migration.';
    } elseif (!learn_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
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
            $errors[] = $e->getMessage();
        }
    }
}

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
    <div style="padding:16px;background:#f5f6fa;border-radius:10px;margin:18px 0;">
        <strong>Amount to pay: KSh <?= number_format($total, 2) ?></strong><br>
        <small><?= $sponsored ? 'Sponsored registration fee: KSh 250' : 'Course fee: KSh ' . number_format($courseAmount, 2) . ' + service fee: KSh 500' ?></small>
    </div>
    <form method="post" <?= !$paymentsReady ? 'style="display:none"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
        <input type="hidden" name="course" value="<?= learn_e($slug) ?>">
        <div class="ln-field">
            <label for="phone">Safaricom phone number</label>
            <input id="phone" name="phone" type="tel" required placeholder="0712345678" value="<?= learn_e($learner['phone'] ?? '') ?>">
        </div>
        <button class="ln-btn ln-btn-primary ln-btn-block" type="submit">
            <span class="material-symbols-rounded">phone_android</span> Pay with M-Pesa
        </button>
    </form>
</div>
<?php learn_foot(); ?>
