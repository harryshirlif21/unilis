<?php
/**
 * Authenticated payment-status endpoint for short-course learners.
 *
 * Lets a signed-in learner check the status of their latest M-Pesa attempt for
 * a course WITHOUT firing a new STK prompt or a new Daraja call. Never returns
 * credentials, tokens, or the merchant/checkout request identifiers - just the
 * state the learner is allowed to see.
 */
require_once __DIR__ . '/../learn/config.php';
require_once __DIR__ . '/../learn/includes/catalogue.php';

header('Content-Type: application/json; charset=utf-8');

function learn_status_respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    learn_status_respond(405, ['ok' => false, 'message' => 'GET required.']);
}

$learner = learn_current($conn);
if ($learner === null) {
    learn_status_respond(401, ['ok' => false, 'message' => 'You must be signed in to check payment status.']);
}

$slug = trim((string)($_GET['course'] ?? ''));
$course = $slug !== '' ? learn_course_by_slug($conn, $slug) : null;
if (!$course) {
    learn_status_respond(400, ['ok' => false, 'message' => 'Course not found.']);
}

$tbl = $conn->query("SHOW TABLES LIKE 'short_course_payments'");
if (!$tbl || $tbl->num_rows === 0) {
    $tbl->free();
    learn_status_respond(200, ['ok' => true, 'paid' => false, 'status' => null, 'status_label' => 'No payment recorded yet', 'amount' => null]);
}
$tbl->free();

$stmt = $conn->prepare("
    SELECT id, amount, status, mpesa_receipt, created_at, paid_at
    FROM short_course_payments
    WHERE learner_id = ? AND course_id = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param('ii', $learner['id'], (int)$course['id']);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    learn_status_respond(200, ['ok' => true, 'paid' => false, 'status' => null, 'status_label' => 'No payment recorded yet', 'amount' => null]);
}

$statusLabels = [
    'pending' => 'Payment request sent - awaiting approval',
    'paid' => 'Paid',
    'failed' => 'Payment failed',
    'cancelled' => 'Payment cancelled',
    'timeout' => 'Payment timed out',
    'reconciliation_required' => 'Payment under review',
    'payout_pending' => 'Paid',
    'payout_paid' => 'Paid',
    'payout_failed' => 'Paid',
];

$status = (string)$payment['status'];
$paidStatuses = ['paid', 'payout_paid', 'payout_pending', 'payout_failed'];
$paid = in_array($status, $paidStatuses, true);

learn_status_respond(200, [
    'ok' => true,
    'paid' => $paid,
    'status' => $status,
    'status_label' => $statusLabels[$status] ?? $status,
    'amount' => (float)$payment['amount'],
    'created_at' => (string)($payment['created_at'] ?? ''),
    'paid_at' => $payment['paid_at'] ?? null,
    'receipt' => $payment['mpesa_receipt'] ?? null,
]);