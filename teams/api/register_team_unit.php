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
    $teamId = (int) ($input['team_id'] ?? 0);
    $unitId = (int) ($input['unit_id'] ?? 0);
    $assessmentType = trim((string) ($input['assessment_type'] ?? ''));
    $memberIds = $input['member_ids'] ?? [];
    $csrfToken = (string) ($input['csrf_token'] ?? '');
    $userId = (int) $_SESSION['user_id'];

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    if ($teamId <= 0 || $unitId <= 0 || $assessmentType === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Team, unit, and assessment type are required']);
        exit;
    }

    $roleStmt = $conn->prepare('SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1');
    $roleStmt->bind_param('ii', $teamId, $userId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow || strtolower((string) ($roleRow['role'] ?? '')) !== 'leader') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only team leaders can register the team for another unit']);
        exit;
    }

    if (!team_student_is_member_of_team($conn, $teamId, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not a member of this team']);
        exit;
    }

    $normalizedMemberIds = [];
    if (is_array($memberIds)) {
        foreach ($memberIds as $memberId) {
            $normalizedMemberIds[] = (int) $memberId;
        }
    }

    $registrationId = team_add_registration(
        $conn,
        $teamId,
        $unitId,
        $assessmentType,
        $userId,
        $normalizedMemberIds
    );

    $registration = team_get_registration($conn, $registrationId);

    echo json_encode([
        'success' => true,
        'message' => 'Team registered for the selected unit and assessment',
        'registration_id' => $registrationId,
        'registration' => $registration,
        'registrations' => team_get_registrations($conn, $teamId),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
