<?php

/**
 * Returns true when the student is already a member of another team in the same unit.
 * This allows the same team to be reused in other units/years, but keeps membership
 * limited to one team per unit for each student.
 */
function team_membership_has_other_team_for_unit(mysqli $conn, int $teamId, int $studentId, ?int $unitId = null): bool
{
    if ($unitId === null || $unitId <= 0) {
        $teamStmt = $conn->prepare("SELECT unit_id FROM teams WHERE id = ? LIMIT 1");
        if (!$teamStmt) {
            throw new Exception('Failed to resolve team unit: ' . $conn->error);
        }

        $teamStmt->bind_param('i', $teamId);
        $teamStmt->execute();
        $teamResult = $teamStmt->get_result();
        $teamRow = $teamResult->fetch_assoc();
        $teamStmt->close();

        $unitId = (int)($teamRow['unit_id'] ?? 0);
    }

    if ($unitId <= 0) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM team_members tm
         JOIN teams t ON tm.team_id = t.id
         WHERE tm.student_id = ?
           AND t.unit_id = ?
           AND tm.team_id != ?
         LIMIT 1"
    );
    if (!$stmt) {
        throw new Exception('Failed to check team membership: ' . $conn->error);
    }

    $stmt->bind_param('iii', $studentId, $unitId, $teamId);
    $stmt->execute();
    $hasRow = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $hasRow;
}

/**
 * Throw if the student is already a member of another team in the same unit.
 */
function ensure_student_not_in_other_team_for_unit(mysqli $conn, int $teamId, int $studentId, ?int $unitId = null): void
{
    if (team_membership_has_other_team_for_unit($conn, $teamId, $studentId, $unitId)) {
        throw new Exception('This student is already a member of another team in this unit. Each student can only be a member of one team per unit.');
    }
}
