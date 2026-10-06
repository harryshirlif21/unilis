<?php
/**
 * Server-side M-Pesa reconciliation for the super administrator.
 *
 * GET  -> summary of payment statuses + the pending/in-flight transactions an
 *         operator may need to look at.
 * POST action=reconcile -> idempotently flag stale 'pending' payments that have
 *         had no callback within a cutoff window. This NEVER marks anything as
 *         paid: an unknown outcome is flagged for a human, never auto-approved.
 * POST action=resolve   -> lets the operator record the outcome of a
 *         reconciliation_required payment (paid | failed | cancelled | timeout).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

function mpesa_reconcile_respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../learn/includes/catalogue.php';

if (($_SESSION['user_role'] ?? '') !== 'admin' || empty($_SESSION['user_id'])) {
    mpesa_reconcile_respond(403, ['ok' => false, 'message' => 'Forbidden.']);
}

// Only the designated super administrator may reconcile payments.
$adminId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT email FROM admins WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$admin || strtolower(trim((string)$admin['email'])) !== 'admin@unilis.com') {
    mpesa_reconcile_respond(403, ['ok' => false, 'message' => 'Forbidden.']);
}

$tbl = $conn->query("SHOW TABLES LIKE 'short_course_payments'");
if (!$tbl || $tbl->num_rows === 0) {
    $tbl->free();
    mpesa_reconcile_respond(200, ['ok' => true, 'enabled' => false]);
}
$tbl->free();

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $counts = [];
    $stmt = $conn->query("
        SELECT status, COUNT(*) AS total
        FROM short_course_payments
        GROUP BY status
    ");
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $counts[(string)$row['status']] = (int)$row['total'];
        }
        $stmt->free();
    }

    $pending = [];
    $stmt = $conn->prepare("
        SELECT p.id, p.amount, p.status, p.created_at,
               c.title AS course, l.email AS learner
        FROM short_course_payments p
        JOIN public_courses c ON c.id = p.course_id
        JOIN external_learners l ON l.id = p.learner_id
        WHERE p.status IN ('pending', 'reconciliation_required')
        ORDER BY p.created_at ASC
        LIMIT 200
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $row) {
        $pending[] = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'status' => (string)$row['status'],
            'course' => (string)$row['course'],
            'learner' => (string)$row['learner'],
            'created_at' => (string)$row['created_at'],
        ];
    }

    mpesa_reconcile_respond(200, [
        'ok' => true,
        'enabled' => true,
        'counts' => $counts,
        'pending' => $pending,
    ]);
}

if ($method !== 'POST') {
    mpesa_reconcile_respond(405, ['ok' => false, 'message' => 'GET or POST required.']);
}

if (
    empty($_SESSION['csrf_token'])
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    mpesa_reconcile_respond(403, ['ok' => false, 'message' => 'Invalid or expired request token. Reload the dashboard and try again.']);
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'resolve') {
    $paymentId = (int)($_POST['id'] ?? 0);
    $resolution = (string)($_POST['resolution'] ?? '');
    if ($paymentId <= 0 || !in_array($resolution, ['paid', 'failed', 'cancelled', 'timeout'], true)) {
        mpesa_reconcile_respond(400, ['ok' => false, 'message' => 'A valid id and resolution (paid|failed|cancelled|timeout) are required.']);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            SELECT id, learner_id, course_id, status
            FROM short_course_payments
            WHERE id = ? AND status = 'reconciliation_required'
            LIMIT 1
        ");
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$payment) {
            $conn->rollback();
            mpesa_reconcile_respond(404, ['ok' => false, 'message' => 'No reconciliation-required payment with that id.']);
        }

        if ($resolution === 'paid') {
            $stmt = $conn->prepare("UPDATE short_course_payments SET status='paid', paid_at=COALESCE(paid_at, NOW()) WHERE id=? AND status='reconciliation_required'");
            $stmt->bind_param('i', $paymentId);
            $stmt->execute();
            $changed = $stmt->affected_rows > 0;
            $stmt->close();
            if ($changed) {
                learn_enrol($conn, (int)$payment['learner_id'], (int)$payment['course_id']);
            }
        } else {
            $stmt = $conn->prepare("UPDATE short_course_payments SET status=? WHERE id=? AND status='reconciliation_required'");
            $stmt->bind_param('si', $resolution, $paymentId);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
        mpesa_reconcile_respond(200, ['ok' => true, 'message' => 'Payment ' . $paymentId . ' resolved as ' . $resolution . '.']);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[mpesa_reconcile] ' . $e->getMessage());
        mpesa_reconcile_respond(500, ['ok' => false, 'message' => 'Could not resolve the payment.']);
    }
}

mpesa_reconcile_respond(400, ['ok' => false, 'message' => 'Unknown action.']);