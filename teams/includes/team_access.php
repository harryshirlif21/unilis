<?php
/**
 * Shared team-access checks for supervisor and lecturer workflows.
 */

/**
 * Determine whether a user can manage a team.
 *
 * Returns true if the user is one of:
 *   - A system admin
 *   - A Team Leader (role = 'leader' in team_members)
 *   - An approved Class/Group Supervisor (team_supervisors.status = 'approved')
 *   - A Lecturer assigned to the unit that owns the team (lecturer_units)
 *
 * Ordinary team members (role != 'leader') are NOT granted management access.
 *
 * @param mysqli $conn   Active mysqli connection
 * @param int    $teamId Team ID
 * @param int    $userId User ID (student, lecturer, technician, or admin id)
 * @param string $userRole Session role (student|lecturer|admin|technician)
 * @return bool
 */
function canManageTeam($conn, $teamId, $userId, $userRole = '')
{
    if (!$conn || !$teamId || !$userId) {
        return false;
    }

    $teamId = (int) $teamId;
    $userId = (int) $userId;
    $role = strtolower((string) ($userRole ?? ''));

    // Admins can manage any team.
    if ($role === 'admin') {
        return true;
    }

    // 1) Team Leader check (student member with role = 'leader').
    $leaderSql = "
        SELECT 1
        FROM team_members
        WHERE team_id = ? AND student_id = ? AND LOWER(COALESCE(role, '')) = 'leader'
        LIMIT 1
    ";
    $leaderStmt = $conn->prepare($leaderSql);
    if ($leaderStmt) {
        $leaderStmt->bind_param('ii', $teamId, $userId);
        $leaderStmt->execute();
        if ($leaderStmt->get_result()->num_rows > 0) {
            $leaderStmt->close();
            return true;
        }
        $leaderStmt->close();
    }

    // 2) Lecturer assigned to the unit that owns the team (global supervisor).
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

    // 3) Approved Class/Group Supervisor (team_supervisors).
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

/**
 * Backward-compatible wrapper for the legacy team_user_can_access_team().
 * Kept so existing callers continue to work unchanged.
 */
function team_user_can_access_team($conn, $teamId, $userId, $userRole)
{
    return canManageTeam($conn, $teamId, $userId, $userRole);
}