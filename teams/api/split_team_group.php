<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_membership.php';
require_once __DIR__ . '/../includes/ensure_team_registrations.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $registrationId = (int) ($input['registration_id'] ?? 0);
    $memberIds = $input['member_ids'] ?? [];
    $csrfToken = (string) ($input['csrf_token'] ?? '');
    $userId = (int) $_SESSION['user_id'];

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    if ($registrationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Registration is required']);
        exit;
    }

    if (!is_array($memberIds) || count($memberIds) < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Select members for the split group']);
        exit;
    }

    $splitIds = array_values(array_unique(array_map('intval', $memberIds)));
    $result = team_split_registration_group($conn, $registrationId, $splitIds, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Split group created successfully',
        'split' => $result,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
