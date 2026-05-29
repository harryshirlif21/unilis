<?php
/**
 * SmartLab RFID Card Check Endpoint
 * POST: { "uid": "59:14:4D:E8", "key": "..." }
 * Returns JSON: { status, name, reg_number, role, message }
 */
header("Content-Type: application/json");
require_once __DIR__ . '/../../config/database_production.php';

define('RFID_SECRET', 'smartlab_rfid_2025');

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'POST only'], 405);
}

$body = json_decode(file_get_contents("php://input"), true);

if (empty($body['uid']) || empty($body['key'])) {
    respond(['status' => 'error', 'message' => 'Missing uid or key'], 400);
}

if ($body['key'] !== RFID_SECRET) {
    respond(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

$uid = strtoupper(trim($body['uid']));
$pdo = getProductionDB();

// Look up card → student
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.reg_number, u.role, u.is_active
    FROM rfid_cards c
    JOIN users u ON c.student_id = u.id
    WHERE c.uid = :uid
    LIMIT 1
");
$stmt->execute([':uid' => $uid]);
$user = $stmt->fetch();

$granted = $user && (int)$user['is_active'] === 1;

// Log every scan
$pdo->prepare("
    INSERT INTO rfid_access_log (uid, student_id, full_name, status, scanned_at)
    VALUES (:uid, :sid, :name, :status, NOW())
")->execute([
    ':uid'    => $uid,
    ':sid'    => $user['id']        ?? null,
    ':name'   => $user['full_name'] ?? null,
    ':status' => $granted ? 'granted' : 'denied',
]);

if (!$user) {
    respond(['status' => 'denied', 'message' => 'Card not registered', 'uid' => $uid]);
}

if (!(int)$user['is_active']) {
    respond(['status' => 'denied', 'name' => $user['full_name'], 'message' => 'Account deactivated']);
}

respond([
    'status'     => 'granted',
    'name'       => $user['full_name'],
    'reg_number' => $user['reg_number'],
    'role'       => $user['role'],
    'message'    => 'Access granted',
]);
