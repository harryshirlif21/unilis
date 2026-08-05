<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_access.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $teamId = (int)($input['team_id'] ?? 0);
    $csrfToken = (string)($input['csrf_token'] ?? '');

    if ($teamId <= 0) {
        throw new Exception('team_id is required');
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }

    $conn->begin_transaction();

    $teamCheck = $conn->prepare('SELECT id FROM teams WHERE id = ? LIMIT 1');
    $teamCheck->bind_param('i', $teamId);
    $teamCheck->execute();
    if ($teamCheck->get_result()->num_rows === 0) {
        $teamCheck->close();
        throw new Exception('Team not found');
    }
    $teamCheck->close();

    $userId = (int)$_SESSION['user_id'];
    $userRole = (string)$_SESSION['user_role'];
    $authCheck = $conn->prepare('SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1');
    $authCheck->bind_param('ii', $teamId, $userId);
    $authCheck->execute();
    $authRow = $authCheck->get_result()->fetch_assoc();
    $authCheck->close();

    $isTeamLeader = $authRow && strtolower((string)$authRow['role']) === 'leader';
    if ($userRole !== 'lecturer' && !$isTeamLeader) {
        throw new Exception('Only lecturers or team leaders can delete a team');
    }

    $deleteFiles = $conn->prepare('DELETE FROM team_files WHERE team_id = ?');
    $deleteFiles->bind_param('i', $teamId);
    $deleteFiles->execute();
    $deleteFiles->close();

    $deleteMembers = $conn->prepare('DELETE FROM team_members WHERE team_id = ?');
    $deleteMembers->bind_param('i', $teamId);
    $deleteMembers->execute();
    $deleteMembers->close();

    // Invitation codes reference invitation rows, so they must go first — deleting
    // the invitations before them leaves the codes orphaned instead of removed.
    //
    // The table is optional, and config/db.php enables MYSQLI_REPORT_STRICT, which
    // makes a query against a missing table throw rather than return false. SHOW
    // TABLES reports zero rows instead, so it is safe to probe with.
    $codesTable = $conn->query("SHOW TABLES LIKE 'team_invitation_codes'");
    if ($codesTable->num_rows > 0) {
        $deleteInvitationCodes = $conn->prepare(
            'DELETE FROM team_invitation_codes
             WHERE invitation_id IN (SELECT id FROM team_invitations WHERE team_id = ?)'
        );
        $deleteInvitationCodes->bind_param('i', $teamId);
        $deleteInvitationCodes->execute();
        $deleteInvitationCodes->close();
    }
    $codesTable->free();

    $deleteInvitations = $conn->prepare('DELETE FROM team_invitations WHERE team_id = ?');
    $deleteInvitations->bind_param('i', $teamId);
    $deleteInvitations->execute();
    $deleteInvitations->close();

    $deleteRequests = $conn->prepare('DELETE FROM team_membership_requests WHERE team_id = ?');
    $deleteRequests->bind_param('i', $teamId);
    $deleteRequests->execute();
    $deleteRequests->close();

    $deleteMarks = $conn->prepare('DELETE FROM team_marks WHERE team_id = ?');
    $deleteMarks->bind_param('i', $teamId);
    $deleteMarks->execute();
    $deleteMarks->close();

    $deleteActivity = $conn->prepare('DELETE FROM team_activity_log WHERE team_id = ?');
    $deleteActivity->bind_param('i', $teamId);
    $deleteActivity->execute();
    $deleteActivity->close();

    $deleteTeam = $conn->prepare('DELETE FROM teams WHERE id = ?');
    $deleteTeam->bind_param('i', $teamId);
    $deleteTeam->execute();
    $deleteTeam->close();

    $conn->commit();

    // Deliberately not written to team_activity_log: those rows are scoped to the
    // team and were just deleted, and if team_id carries a foreign key the insert
    // would fail after the commit — reporting a 500 for a deletion that actually
    // succeeded, with rollback() no longer able to undo anything.
    error_log(sprintf(
        'team_delete: team %d deleted by user %d (%s)',
        $teamId,
        $userId,
        $userRole === 'lecturer' ? 'lecturer' : 'team leader'
    ));

    echo json_encode(['success' => true, 'message' => 'Team deleted successfully']);
} catch (Throwable $e) {
    if ($conn && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
