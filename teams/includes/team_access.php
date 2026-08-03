<?php
/**
 * Shared team-access checks for supervisor and lecturer workflows.
 */
function team_user_can_access_team($conn, $teamId, $userId, $userRole)
{
    if (!$conn || !$teamId || !$userId) {
        return false;
    }

    $role = strtolower((string) ($userRole ?? ''));
    if ($role === 'admin') {
        return true;
    }

    $teamId = (int) $teamId;
    $userId = (int) $userId;

    $unitAccessSql = "
        SELECT 1
        FROM teams t
        JOIN units u ON u.id = t.unit_id
        JOIN lecturer_units lu ON lu.unit_id = u.id
        WHERE t.id = ? AND lu.lecturer_id = ?
        LIMIT 1
    ";
    $unitStmt = $conn->prepare($unitAccessSql);
    if ($unitStmt) {
        $unitStmt->bind_param('ii', $teamId, $userId);
        $unitStmt->execute();
        if ($unitStmt->get_result()->num_rows > 0) {
            $unitStmt->close();
            return true;
        }
        $unitStmt->close();
    }

    $supervisorAccessSql = "
        SELECT 1
        FROM team_supervisors
        WHERE team_id = ?
          AND lecturer_id = ?
          AND status = 'approved'
        LIMIT 1
    ";
    $supervisorStmt = $conn->prepare($supervisorAccessSql);
    if ($supervisorStmt) {
        $supervisorStmt->bind_param('ii', $teamId, $userId);
        $supervisorStmt->execute();
        $hasAccess = $supervisorStmt->get_result()->num_rows > 0;
        $supervisorStmt->close();
        return $hasAccess;
    }

    return false;
}
