<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../learn/includes/catalogue.php';
require_once __DIR__ . '/../learn/includes/mpesa.php';

header('Content-Type: application/json');
$payload = json_decode(file_get_contents('php://input'), true);
$callback = $payload['Body']['stkCallback'] ?? null;
if (!is_array($callback)) {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback']);
    exit;
}

$checkout = (string)($callback['CheckoutRequestID'] ?? '');
$resultCode = (int)($callback['ResultCode'] ?? 1);
$resultDescription = (string)($callback['ResultDesc'] ?? '');
if ($checkout === '') {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing checkout request']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM short_course_payments WHERE checkout_request_id = ? LIMIT 1");
$stmt->bind_param('s', $checkout);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$payment) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// Parse the trusted metadata values Daraja echoes back for a successful result.
$metadataValues = [];
foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
    $metadataValues[(string)($item['Name'] ?? '')] = (string)($item['Value'] ?? '');
}
$receipt = (string)($metadataValues['MpesaReceiptNumber'] ?? '');
$callbackAmount = (string)($metadataValues['Amount'] ?? '');

if ($resultCode === 0) {
    $conn->begin_transaction();
    try {
        // Verify the amount the wallet actually charged matches the authoritative
        // course fee we stored. If Daraja echoes a different amount (or none),
        // the callback cannot be trusted to unlock enrolment - mark it for an
        // operator instead of silently enrolling the learner.
        $amountMatches = false;
        if ($callbackAmount !== '') {
            $amountMatches = learn_mpesa_amount_matches((float)$callbackAmount, (float)$payment['amount']);
        }
        if (!$amountMatches) {
            $stmt = $conn->prepare("UPDATE short_course_payments SET status='reconciliation_required', result_code=?, result_description=?, raw_callback=? WHERE id=? AND status='pending'");
            $raw = json_encode($payload, JSON_THROW_ON_ERROR);
            $stmt->bind_param('issi', $resultCode, 'Successful result but the reported amount did not match the course fee - reconciliation required.', $raw, $payment['id']);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            error_log('[mpesa_callback] amount mismatch on checkout ' . $checkout . ' - no enrolment.');
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE short_course_payments SET status='paid', mpesa_receipt=?, result_code=?, result_description=?, raw_callback=?, paid_at=NOW() WHERE id=? AND status='pending'");
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $stmt->bind_param('sissi', $receipt, $resultCode, $resultDescription, $raw, $payment['id']);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();
        if ($changed) learn_enrol($conn, (int)$payment['learner_id'], (int)$payment['course_id']);
        $conn->commit();

        if ($changed) {
            $stmt = $conn->prepare("
                SELECT c.department_id, da.payout_type, da.payout_shortcode
                FROM public_courses c
                LEFT JOIN department_admins da
                    ON da.department_id = c.department_id AND da.is_active = 1
                WHERE c.id = ? LIMIT 1
            ");
            $stmt->bind_param('i', $payment['course_id']);
            $stmt->execute();
            $payout = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ((int)round($payment['department_amount']) <= 0) {
                $stmt = $conn->prepare("UPDATE short_course_payments SET status='payout_paid', payout_reference='NOT_REQUIRED' WHERE id=?");
                $stmt->bind_param('i', $payment['id']);
                $stmt->execute();
                $stmt->close();
            } elseif (!empty($payout['payout_shortcode']) && ($payout['payout_type'] ?? '') !== '') {
                try {
                    $b2b = learn_mpesa_b2b(
                        (string)$payout['payout_shortcode'],
                        (int)round($payment['department_amount']),
                        'SC-PAYOUT-' . (int)$payment['id'],
                        (string)$payout['payout_type']
                    );
                    $payoutReference = (string)($b2b['ConversationID'] ?? $b2b['OriginatorConversationID'] ?? '');
                    $stmt = $conn->prepare("UPDATE short_course_payments SET status='payout_pending', payout_reference=? WHERE id=?");
                    $stmt->bind_param('si', $payoutReference, $payment['id']);
                    $stmt->execute();
                    $stmt->close();
                } catch (Throwable $e) {
                    error_log('[mpesa_b2b] ' . $e->getMessage());
                    $stmt = $conn->prepare("UPDATE short_course_payments SET status='payout_failed', result_description=? WHERE id=?");
                    $message = 'Payment received; department payout failed: ' . $e->getMessage();
                    $stmt->bind_param('si', $message, $payment['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[mpesa_callback] ' . $e->getMessage());
    }
} else {
    $outcome = learn_mpesa_failure_status($resultCode);
    $stmt = $conn->prepare("UPDATE short_course_payments SET status=?, result_code=?, result_description=?, raw_callback=? WHERE id=? AND status='pending'");
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $newDescription = ($resultDescription !== '' ? $resultDescription . ' ' : '') . $outcome[1];
    $stmt->bind_param('sissi', $outcome[0], $resultCode, $newDescription, $raw, $payment['id']);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
