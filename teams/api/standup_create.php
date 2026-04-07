<?php
// teams/api/standup_create.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/ActivityLog.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $teamId      = isset($input['team_id']) ? (int) $input['team_id'] : 0;
    $didToday    = trim((string)($input['did_today'] ?? ''));
    $willDoNext  = trim((string)($input['will_do_next'] ?? ''));
    $blockers    = trim((string)($input['blockers'] ?? ''));
    $csrfToken   = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || $didToday === '' || $willDoNext === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'team_id, did_today and will_do_next are required']);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to prepare membership check: ' . $conn->error);
    }
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    $okMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$okMember) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO standup_entries (team_id, user_id, did_today, will_do_next, blockers, entry_date, created_at)
        VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare standup insert: ' . $conn->error);
    }
    $stmt->bind_param('iisss', $teamId, $userId, $didToday, $willDoNext, $blockers);
    $stmt->execute();
    $stmt->close();

    $logger = new ActivityLog($conn);
    $logger->log($teamId, $userId, 'standup_submit', 'Submitted daily stand-up entry');

    echo json_encode(['success' => true, 'message' => 'Stand-up submitted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

