<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/ActivityLog.php';
require_once __DIR__ . '/../includes/team_limits.php';

try {
    ensure_team_max_members_column($conn);

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $invitationId = (int)($input['invitation_id'] ?? 0);
    $code = trim((string)($input['code'] ?? ''));
    $csrfToken = $input['csrf_token'] ?? '';

    if ($invitationId <= 0 || $code === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'invitation_id and code are required']);
        exit;
    }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT id, team_id, invited_student_id, invited_by, status
        FROM team_invitations
        WHERE id = ? LIMIT 1
    ");
    if (!$stmt) throw new Exception('Failed to prepare invitation query: ' . $conn->error);
    $stmt->bind_param("i", $invitationId);
    $stmt->execute();
    $invite = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$invite || (int)$invite['invited_student_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invitation not found for current user']);
        exit;
    }
    if ($invite['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invitation is not pending']);
        exit;
    }

    $teamId = (int)$invite['team_id'];

    // Verify code using helper table
    $stmt = $conn->prepare("SELECT code_hash, code_expires_at FROM team_invitation_codes WHERE invitation_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare code query: ' . $conn->error);
    $stmt->bind_param("i", $invitationId);
    $stmt->execute();
    $codeRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$codeRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No confirmation code found']);
        exit;
    }
    if (strtotime($codeRow['code_expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Confirmation code expired']);
        exit;
    }
    if (!password_verify($code, $codeRow['code_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid confirmation code']);
        exit;
    }

    // Add member if not already present
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    $stmt->bind_param("ii", $teamId, $userId);
    $stmt->execute();
    $alreadyMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$alreadyMember) {
        // Enforce: Student can only be in one team per unit
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM team_members tm 
            JOIN teams t ON tm.team_id = t.id 
            WHERE t.unit_id = (SELECT unit_id FROM teams WHERE id = ?) 
            AND tm.student_id = ? 
            AND t.id != ?"
        );
        $stmt->bind_param("iii", $teamId, $userId, $teamId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Student is already in another team for this unit. A student can only be in one team per unit.']);
            exit;
        }

        assert_team_has_capacity($conn, $teamId);

        $role = strtolower(trim((string)($input['role'] ?? 'member')));
        $allowedRoles = [
            'leader',
            'member',
            'frontend_developer',
            'backend_developer',
            'machine_learning',
            'ui_ux_designer',
            'data_analyst',
            'tester',
            'researcher',
            'presenter',
            'other'
        ];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'member';
        }
        $stmt = $conn->prepare("INSERT INTO team_members (team_id, student_id, role, joined_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) throw new Exception('Failed to prepare member insert: ' . $conn->error);
        $stmt->bind_param("iis", $teamId, $userId, $role);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE team_invitations SET status = 'accepted', responded_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $invitationId);
    $stmt->execute();
    $stmt->close();

    $logger = new ActivityLog($conn);
    $logger->log($teamId, $userId, 'member_accept', 'Invitation accepted via dashboard');

    echo json_encode(['success' => true, 'message' => 'Invitation accepted. You are now a team member.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

