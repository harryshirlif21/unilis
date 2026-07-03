<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_limits.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $teamId = (int)($input['team_id'] ?? 0);
    $maxMembers = (int)($input['max_members'] ?? 0);
    $csrfToken = (string)($input['csrf_token'] ?? '');
    $userId = (int)$_SESSION['user_id'];

    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid team_id is required']);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    if ($maxMembers < 2 || $maxMembers > TEAM_MEMBERS_ABSOLUTE_CAP) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Max members must be between 2 and 15']);
        exit;
    }

    ensure_team_max_members_column($conn);

    $roleStmt = $conn->prepare('SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1');
    if (!$roleStmt) {
        throw new RuntimeException('Failed to prepare leader check: ' . $conn->error);
    }

    $roleStmt->bind_param('ii', $teamId, $userId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow || $roleRow['role'] !== 'leader') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only team leaders can update team settings']);
        exit;
    }

    $currentCount = get_team_member_count($conn, $teamId);
    if ($maxMembers < $currentCount) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Cannot set max members below current team size ({$currentCount})"
        ]);
        exit;
    }

    $updateStmt = $conn->prepare('UPDATE teams SET max_members = ? WHERE id = ? LIMIT 1');
    if (!$updateStmt) {
        throw new RuntimeException('Failed to prepare team settings update: ' . $conn->error);
    }

    $updateStmt->bind_param('ii', $maxMembers, $teamId);
    $updateStmt->execute();
    $updateStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Team member limit updated successfully',
        'max_members' => $maxMembers,
        'member_count' => $currentCount
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
