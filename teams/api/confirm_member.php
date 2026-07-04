<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
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
    $teamId = (int)($input['team_id'] ?? 0);
    $identifier = trim((string)($input['identifier'] ?? ''));
    $code = trim((string)($input['code'] ?? ''));
    $role = strtolower(trim((string)($input['role'] ?? 'member')));
    $csrfToken = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || $identifier === '' || $code === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'team_id, identifier and code are required']);
        exit;
    }

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
        $role = 'other';
    }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $leaderId = (int)$_SESSION['user_id'];

    // Must be leader
    $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare leader check: ' . $conn->error);
    $stmt->bind_param("ii", $teamId, $leaderId);
    $stmt->execute();
    $roleRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$roleRow || $roleRow['role'] !== 'leader') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only team leader can confirm members']);
        exit;
    }

    // Find student by reg_no or email
    $stmt = $conn->prepare("SELECT id, name, reg_no, email FROM students WHERE reg_no = ? OR email = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare student lookup: ' . $conn->error);
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit;
    }
    $studentId = (int)$student['id'];

    // Already member?
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    $stmt->bind_param("ii", $teamId, $studentId);
    $stmt->execute();
    $alreadyMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($alreadyMember) {
        echo json_encode(['success' => true, 'message' => 'Student is already a team member']);
        exit;
    }

    // Pending invitation
    $stmt = $conn->prepare("
        SELECT id, status
        FROM team_invitations
        WHERE team_id = ? AND invited_student_id = ? AND status = 'pending'
        LIMIT 1
    ");
    if (!$stmt) throw new Exception('Failed to prepare invitation lookup: ' . $conn->error);
    $stmt->bind_param("ii", $teamId, $studentId);
    $stmt->execute();
    $invite = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$invite) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No pending invitation found for this student']);
        exit;
    }
    $invitationId = (int)$invite['id'];

    $stmt = $conn->prepare("SELECT code_hash, code_expires_at FROM team_invitation_codes WHERE invitation_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare code lookup: ' . $conn->error);
    $stmt->bind_param("i", $invitationId);
    $stmt->execute();
    $codeRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$codeRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No confirmation code found for invitation']);
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

    // Enforce: Student can only be in one team per unit
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM team_members tm 
        JOIN teams t ON tm.team_id = t.id 
        WHERE t.unit_id = (SELECT unit_id FROM teams WHERE id = ?) 
        AND tm.student_id = ? 
        AND t.id != ?"
    );
    $stmt->bind_param("iii", $teamId, $studentId, $teamId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Student is already in another team for this unit. A student can only be in one team per unit.']);
        exit;
    }

    assert_team_has_capacity($conn, $teamId);

    // Add member
    $stmt = $conn->prepare("INSERT INTO team_members (team_id, student_id, role, joined_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) throw new Exception('Failed to prepare member insert: ' . $conn->error);
    $stmt->bind_param("iis", $teamId, $studentId, $role);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE team_invitations SET status = 'accepted', responded_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $invitationId);
    $stmt->execute();
    $stmt->close();

    $logger = new ActivityLog($conn);
    $logger->log($teamId, $leaderId, 'member_confirm', 'Leader confirmed invited member #' . $studentId . ' using code');

    echo json_encode(['success' => true, 'message' => 'Member confirmed and added to team']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

