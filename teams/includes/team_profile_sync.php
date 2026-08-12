<?php

/**
 * Keep team leadership and team metadata aligned after a student profile update.
 *
 * @return array{teams_updated:int,leadership_preserved:int}
 */
function team_sync_after_student_profile_update(
    mysqli $conn,
    int $studentId,
    int $courseId,
    int $yearOfStudy
): array {
    require_once __DIR__ . '/ensure_team_registrations.php';
    ensure_team_registrations_tables($conn);

    $summary = [
        'teams_updated' => 0,
        'leadership_preserved' => 0,
    ];

    if ($studentId <= 0 || $courseId <= 0 || $yearOfStudy <= 0) {
        return $summary;
    }

    $stmt = $conn->prepare('
        SELECT tm.team_id, tm.role, t.created_by
        FROM team_members tm
        JOIN teams t ON t.id = tm.team_id
        WHERE tm.student_id = ?
    ');
    if (!$stmt) {
        throw new RuntimeException('Failed to load team memberships for profile sync');
    }

    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    $leaderTeamIds = [];
    while ($row = $result->fetch_assoc()) {
        $teamId = (int) ($row['team_id'] ?? 0);
        if ($teamId <= 0) {
            continue;
        }

        $role = strtolower(trim((string) ($row['role'] ?? '')));
        $isCreator = (int) ($row['created_by'] ?? 0) === $studentId;
        $isLeader = $role === 'leader' || $isCreator;

        if (!$isLeader) {
            continue;
        }

        $leaderTeamIds[$teamId] = true;

        $preserveStmt = $conn->prepare("
            UPDATE team_members
            SET role = 'leader'
            WHERE team_id = ? AND student_id = ?
            LIMIT 1
        ");
        if ($preserveStmt) {
            $preserveStmt->bind_param('ii', $teamId, $studentId);
            $preserveStmt->execute();
            if ($preserveStmt->affected_rows >= 0) {
                $summary['leadership_preserved']++;
            }
            $preserveStmt->close();
        }

        $updateTeamStmt = $conn->prepare('
            UPDATE teams
            SET course_id = ?, year = ?
            WHERE id = ?
            LIMIT 1
        ');
        if ($updateTeamStmt) {
            $updateTeamStmt->bind_param('iii', $courseId, $yearOfStudy, $teamId);
            $updateTeamStmt->execute();
            if ($updateTeamStmt->affected_rows > 0) {
                $summary['teams_updated']++;
            }
            $updateTeamStmt->close();
        }
    }
    $stmt->close();

    $creatorStmt = $conn->prepare('
        SELECT t.id
        FROM teams t
        LEFT JOIN team_members tm ON tm.team_id = t.id AND tm.student_id = ?
        WHERE t.created_by = ? AND tm.student_id IS NULL
    ');
    if ($creatorStmt) {
        $creatorStmt->bind_param('ii', $studentId, $studentId);
        $creatorStmt->execute();
        $creatorResult = $creatorStmt->get_result();

        while ($row = $creatorResult->fetch_assoc()) {
            $teamId = (int) ($row['id'] ?? 0);
            if ($teamId <= 0 || isset($leaderTeamIds[$teamId])) {
                continue;
            }

            $insertMember = $conn->prepare('
                INSERT INTO team_members (team_id, student_id, role)
                VALUES (?, ?, \'leader\')
            ');
            if ($insertMember) {
                $insertMember->bind_param('ii', $teamId, $studentId);
                $insertMember->execute();
                $insertMember->close();
                $summary['leadership_preserved']++;
            }

            $updateTeamStmt = $conn->prepare('
                UPDATE teams
                SET course_id = ?, year = ?
                WHERE id = ?
                LIMIT 1
            ');
            if ($updateTeamStmt) {
                $updateTeamStmt->bind_param('iii', $courseId, $yearOfStudy, $teamId);
                $updateTeamStmt->execute();
                if ($updateTeamStmt->affected_rows > 0) {
                    $summary['teams_updated']++;
                }
                $updateTeamStmt->close();
            }
        }
        $creatorStmt->close();
    }

    return $summary;
}
