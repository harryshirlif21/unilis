<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function mpesa_test_respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    mpesa_test_respond(405, ['success' => false, 'message' => 'POST required.']);
}

if (($_SESSION['user_role'] ?? '') !== 'admin' || empty($_SESSION['user_id'])) {
    mpesa_test_respond(403, ['success' => false, 'message' => 'Forbidden.']);
}

if (
    empty($_SESSION['csrf_token'])
    || !isset($_POST['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    mpesa_test_respond(403, ['success' => false, 'message' => 'Invalid or expired request token. Reload the dashboard and try again.']);
}

try {
    require_once __DIR__ . '/../config/db.php';
    $adminId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT email FROM admins WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$admin || strtolower(trim((string)$admin['email'])) !== 'admin@unilis.com') {
        mpesa_test_respond(403, ['success' => false, 'message' => 'Forbidden.']);
    }

    require_once __DIR__ . '/../learn/includes/mpesa.php';
    $phone = learn_mpesa_normalise_phone((string)($_POST['phone'] ?? ''));
    $response = learn_mpesa_stk_push($phone, 1, 'ADMIN-TEST', 'UNILIS sandbox STK test', 'sandbox');

    $checkoutRequestId = (string)($response['CheckoutRequestID'] ?? '');
    $responseCode = (string)($response['ResponseCode'] ?? '');
    if ($checkoutRequestId === '' || $responseCode !== '0') {
        mpesa_test_respond(502, [
            'success' => false,
            'message' => (string)($response['errorMessage'] ?? $response['ResponseDescription'] ?? 'M-Pesa did not accept the STK request.'),
        ]);
    }

    mpesa_test_respond(200, [
        'success' => true,
        'message' => (string)($response['CustomerMessage'] ?? $response['ResponseDescription'] ?? 'STK request accepted by M-Pesa.'),
        'checkout_request_id' => $checkoutRequestId,
    ]);
} catch (InvalidArgumentException $e) {
    mpesa_test_respond(400, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[admin_mpesa_stk_test] ' . $e->getMessage());
    mpesa_test_respond(502, ['success' => false, 'message' => $e->getMessage()]);
}
