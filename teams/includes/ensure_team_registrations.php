<?php

require_once __DIR__ . '/team_display_helpers.php';

/**
 * Ensures team_registrations tables exist and backfills legacy teams.
 */

function ensure_team_registrations_tables(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS `team_registrations` (
            `id` int NOT NULL AUTO_INCREMENT,
            `team_id` int NOT NULL,
            `unit_id` int NOT NULL,
            `assessment_type` varchar(100) NOT NULL,
            `status` enum('active','archived') NOT NULL DEFAULT 'active',
            `is_split` tinyint(1) NOT NULL DEFAULT 0,
            `parent_registration_id` int DEFAULT NULL,
            `child_team_id` int DEFAULT NULL,
            `registered_by` int NOT NULL DEFAULT 0,
            `registered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_team_unit_assessment` (`team_id`, `unit_id`, `assessment_type`),
            KEY `idx_team_registrations_team` (`team_id`),
            KEY `idx_team_registrations_unit` (`unit_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS `team_registration_members` (
            `registration_id` int NOT NULL,
            `student_id` int NOT NULL,
            PRIMARY KEY (`registration_id`, `student_id`),
            KEY `idx_trm_student` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!columnExistsForTeamRegistrations($conn, 'teams', 'parent_team_id')) {
        $conn->query("ALTER TABLE `teams` ADD COLUMN `parent_team_id` int DEFAULT NULL AFTER `created_by`");
        $conn->query("ALTER TABLE `teams` ADD KEY `idx_teams_parent` (`parent_team_id`)");
    }

    team_backfill_registrations_from_teams($conn);

    $ensured = true;
}

function columnExistsForTeamRegistrations(mysqli $conn, string $table, string $column): bool
{
    $escapedTable = $conn->real_escape_string($table);
    $escapedColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");

    return $result && $result->num_rows > 0;
}

function team_backfill_registrations_from_teams(mysqli $conn): void
{
    $sql = "
        INSERT IGNORE INTO team_registrations (team_id, unit_id, assessment_type, registered_by, registered_at)
        SELECT
            t.id,
            t.unit_id,
            COALESCE(NULLIF(TRIM(t.assessment_type), ''), 'project'),
            t.created_by,
            t.created_at
        FROM teams t
        WHERE t.unit_id > 0
    ";
    $conn->query($sql);
}

function team_get_registrations(mysqli $conn, int $teamId, bool $activeOnly = true): array
{
    ensure_team_registrations_tables($conn);

    $sql = "
        SELECT
            tr.id,
            tr.team_id,
            tr.unit_id,
            tr.assessment_type,
            tr.status,
            tr.is_split,
            tr.parent_registration_id,
            tr.child_team_id,
            tr.registered_by,
            tr.registered_at,
            u.name AS unit_name,
            u.code AS unit_code,
            (
                SELECT COUNT(*)
                FROM team_registration_members trm
                WHERE trm.registration_id = tr.id
            ) AS subset_member_count
        FROM team_registrations tr
        JOIN units u ON u.id = tr.unit_id
        WHERE tr.team_id = ?
    ";

    if ($activeOnly) {
        $sql .= " AND tr.status = 'active'";
    }

    $sql .= ' ORDER BY tr.registered_at ASC, tr.id ASC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['assessment_title'] = team_assessment_label($row['assessment_type'] ?? null);
        $row['unit_display'] = team_format_unit_display($row['unit_code'] ?? null, $row['unit_name'] ?? null);
    }
    unset($row);

    return $rows;
}

function team_get_registration(mysqli $conn, int $registrationId): ?array
{
    ensure_team_registrations_tables($conn);

    $stmt = $conn->prepare("
        SELECT tr.*, u.name AS unit_name, u.code AS unit_code, t.title AS team_title
        FROM team_registrations tr
        JOIN units u ON u.id = tr.unit_id
        JOIN teams t ON t.id = tr.team_id
        WHERE tr.id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $registrationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function team_registration_member_ids(mysqli $conn, int $registrationId, int $teamId): array
{
    $stmt = $conn->prepare('SELECT student_id FROM team_registration_members WHERE registration_id = ?');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $registrationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];

    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['student_id'];
    }
    $stmt->close();

    if ($ids !== []) {
        return $ids;
    }

    $stmt = $conn->prepare('SELECT student_id FROM team_members WHERE team_id = ?');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['student_id'];
    }
    $stmt->close();

    return $ids;
}

function team_add_registration(
    mysqli $conn,
    int $teamId,
    int $unitId,
    string $assessmentType,
    int $registeredBy,
    array $memberIds = []
): int {
    require_once __DIR__ . '/team_membership.php';
    ensure_team_registrations_tables($conn);

    $assessmentType = strtolower(trim($assessmentType));
    $allowed = ['assignment', 'cat', 'project', 'practical'];
    if (!in_array($assessmentType, $allowed, true)) {
        throw new Exception('Invalid assessment type selected');
    }

    if ($unitId <= 0 || $teamId <= 0) {
        throw new Exception('Team and unit are required');
    }

    team_validate_unit_for_student($conn, $registeredBy, $unitId);
    ensure_team_members_can_use_unit($conn, $teamId, $unitId);

    $stmt = $conn->prepare("
        INSERT INTO team_registrations (team_id, unit_id, assessment_type, registered_by)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception('Failed to create team registration');
    }

    $stmt->bind_param('iisi', $teamId, $unitId, $assessmentType, $registeredBy);
    $stmt->execute();
    $registrationId = (int) $stmt->insert_id;
    $stmt->close();

    if ($memberIds !== []) {
        team_set_registration_members($conn, $registrationId, $memberIds, $teamId);
    }

    return $registrationId;
}

function team_set_registration_members(mysqli $conn, int $registrationId, array $memberIds, int $teamId): void
{
    $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
    if ($memberIds === []) {
        throw new Exception('Select at least one team member for this registration');
    }

    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $types = str_repeat('i', count($memberIds) + 1);
    $sql = "SELECT student_id FROM team_members WHERE team_id = ? AND student_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to validate registration members');
    }

    $params = array_merge([$teamId], $memberIds);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $validIds = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $validIds[] = (int) $row['student_id'];
    }
    $stmt->close();

    if (count($validIds) !== count($memberIds)) {
        throw new Exception('All selected members must belong to the team');
    }

    $conn->query('DELETE FROM team_registration_members WHERE registration_id = ' . (int) $registrationId);

    $insert = $conn->prepare('INSERT INTO team_registration_members (registration_id, student_id) VALUES (?, ?)');
    if (!$insert) {
        throw new Exception('Failed to save registration members');
    }

    foreach ($validIds as $studentId) {
        $insert->bind_param('ii', $registrationId, $studentId);
        $insert->execute();
    }
    $insert->close();
}

function team_split_registration_group(
    mysqli $conn,
    int $registrationId,
    array $memberIds,
    int $leaderId
): array {
    require_once __DIR__ . '/team_membership.php';
    ensure_team_registrations_tables($conn);

    $registration = team_get_registration($conn, $registrationId);
    if (!$registration || ($registration['status'] ?? '') !== 'active') {
        throw new Exception('Registration not found');
    }

    $parentTeamId = (int) $registration['team_id'];
    $roleStmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    $roleStmt->bind_param('ii', $parentTeamId, $leaderId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow || strtolower((string) ($roleRow['role'] ?? '')) !== 'leader') {
        throw new Exception('Only team leaders can split a group');
    }

    $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
    if (count($memberIds) < 1) {
        throw new Exception('Select at least one member for the split group');
    }

    $allMemberIds = team_registration_member_ids($conn, $registrationId, $parentTeamId);
    if (count($memberIds) >= count($allMemberIds)) {
        throw new Exception('Split group must leave at least one member on the original team registration');
    }

    foreach ($memberIds as $memberId) {
        if (!in_array($memberId, $allMemberIds, true)) {
            throw new Exception('All split members must belong to this registration');
        }
    }

    $remainingIds = array_values(array_diff($allMemberIds, $memberIds));
    $profile = team_get_student_course_year($conn, $leaderId);

    $conn->begin_transaction();

    try {
        $childTitle = trim((string) ($registration['team_title'] ?? 'Team')) . ' - '
            . team_format_unit_display($registration['unit_code'] ?? null, $registration['unit_name'] ?? null)
            . ' Split';

        $teamStmt = $conn->prepare('
            SELECT max_members, course_id, year, description, submission_mode
            FROM teams WHERE id = ? LIMIT 1
        ');
        $teamStmt->bind_param('i', $parentTeamId);
        $teamStmt->execute();
        $parentTeam = $teamStmt->get_result()->fetch_assoc();
        $teamStmt->close();

        if (!$parentTeam) {
            throw new Exception('Parent team not found');
        }

        $maxMembers = max(count($memberIds), (int) ($parentTeam['max_members'] ?? 15));
        $unitId = (int) $registration['unit_id'];
        $assessmentType = (string) $registration['assessment_type'];
        $courseId = (int) ($parentTeam['course_id'] ?? $profile['course_id']);
        $year = (int) ($parentTeam['year'] ?? $profile['year_of_study']);

        $insertTeam = $conn->prepare("
            INSERT INTO teams (
                title, unit_id, course_id, assessment_type, created_by, parent_team_id, year, max_members, description, submission_mode
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$insertTeam) {
            throw new Exception('Failed to create split team');
        }

        $description = $parentTeam['description'] ?? null;
        $submissionMode = $parentTeam['submission_mode'] ?? 'standard';
        $insertTeam->bind_param(
            'siisiiiiss',
            $childTitle,
            $unitId,
            $courseId,
            $assessmentType,
            $leaderId,
            $parentTeamId,
            $year,
            $maxMembers,
            $description,
            $submissionMode
        );
        $insertTeam->execute();
        $childTeamId = (int) $insertTeam->insert_id;
        $insertTeam->close();

        $insertReg = $conn->prepare("
            INSERT INTO team_registrations (
                team_id, unit_id, assessment_type, registered_by, is_split, parent_registration_id
            ) VALUES (?, ?, ?, ?, 1, ?)
        ");
        $insertReg->bind_param('iisii', $childTeamId, $unitId, $assessmentType, $leaderId, $registrationId);
        $insertReg->execute();
        $childRegistrationId = (int) $insertReg->insert_id;
        $insertReg->close();

        $updateParentReg = $conn->prepare('
            UPDATE team_registrations
            SET child_team_id = ?, is_split = 1
            WHERE id = ?
            LIMIT 1
        ');
        $updateParentReg->bind_param('ii', $childTeamId, $registrationId);
        $updateParentReg->execute();
        $updateParentReg->close();

        $memberStmt = $conn->prepare('
            SELECT tm.student_id, tm.role
            FROM team_members tm
            WHERE tm.team_id = ? AND tm.student_id = ?
            LIMIT 1
        ');
        $addMemberStmt = $conn->prepare('
            INSERT INTO team_members (team_id, student_id, role)
            VALUES (?, ?, ?)
        ');
        $removeMemberStmt = $conn->prepare('DELETE FROM team_members WHERE team_id = ? AND student_id = ?');

        $hasLeaderInSplit = in_array($leaderId, $memberIds, true);
        foreach ($memberIds as $index => $memberId) {
            $memberStmt->bind_param('ii', $parentTeamId, $memberId);
            $memberStmt->execute();
            $memberRow = $memberStmt->get_result()->fetch_assoc();
            if (!$memberRow) {
                throw new Exception('Split member not found on parent team');
            }

            $role = (string) ($memberRow['role'] ?? 'member');
            if (!$hasLeaderInSplit && $index === 0) {
                $role = 'leader';
            } elseif ($memberId === $leaderId) {
                $role = 'leader';
            } elseif ($role === 'leader' && $memberId !== $leaderId) {
                $role = 'member';
            }

            $addMemberStmt->bind_param('iis', $childTeamId, $memberId, $role);
            $addMemberStmt->execute();

            $removeMemberStmt->bind_param('ii', $parentTeamId, $memberId);
            $removeMemberStmt->execute();
        }
        $memberStmt->close();
        $addMemberStmt->close();
        $removeMemberStmt->close();

        team_set_registration_members($conn, $childRegistrationId, $memberIds, $childTeamId);
        team_set_registration_members($conn, $registrationId, $remainingIds, $parentTeamId);

        $leaderCheck = $conn->prepare("
            SELECT 1 FROM team_members
            WHERE team_id = ? AND LOWER(COALESCE(role, '')) = 'leader'
            LIMIT 1
        ");
        $leaderCheck->bind_param('i', $parentTeamId);
        $leaderCheck->execute();
        $hasLeader = $leaderCheck->get_result()->num_rows > 0;
        $leaderCheck->close();

        if (!$hasLeader && $remainingIds !== []) {
            $promoteId = (int) $remainingIds[0];
            $promoteStmt = $conn->prepare("UPDATE team_members SET role = 'leader' WHERE team_id = ? AND student_id = ?");
            $promoteStmt->bind_param('ii', $parentTeamId, $promoteId);
            $promoteStmt->execute();
            $promoteStmt->close();
        }

        $conn->commit();

        return [
            'parent_team_id' => $parentTeamId,
            'child_team_id' => $childTeamId,
            'parent_registration_id' => $registrationId,
            'child_registration_id' => $childRegistrationId,
            'child_team_title' => $childTitle,
            'split_member_ids' => $memberIds,
            'remaining_member_ids' => $remainingIds,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function team_teams_for_unit_sql_fragment(string $teamAlias = 't', string $registrationAlias = 'tr'): string
{
    return "
        EXISTS (
            SELECT 1
            FROM team_registrations {$registrationAlias}
            WHERE {$registrationAlias}.team_id = {$teamAlias}.id
              AND {$registrationAlias}.status = 'active'
              AND {$registrationAlias}.unit_id = ?
        )
    ";
}
