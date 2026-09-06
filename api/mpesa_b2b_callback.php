<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
$payload = json_decode(file_get_contents('php://input'), true);
$result = $payload['Result'] ?? [];
$conversationId = (string)($result['ConversationID'] ?? $result['OriginatorConversationID'] ?? '');
$reference = (string)($result['TransactionID'] ?? '');
$code = (int)($result['ResultCode'] ?? 1);
$description = (string)($result['ResultDesc'] ?? '');

if ($conversationId !== '') {
    $status = $code === 0 ? 'payout_paid' : 'payout_failed';
    $stmt = $conn->prepare("
        UPDATE short_course_payments
        SET status = ?, payout_reference = COALESCE(NULLIF(?, ''), payout_reference),
            result_code = ?, result_description = ?
        WHERE payout_reference = ? OR payout_reference = ?
    ");
    $stmt->bind_param('ssisss', $status, $reference, $code, $description, $conversationId, $conversationId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
