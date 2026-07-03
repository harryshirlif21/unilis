<?php

const TEAM_MEMBERS_ABSOLUTE_CAP = 15;

/**
 * Ensure teams.max_members exists so leaders can configure team capacity.
 */
function ensure_team_max_members_column(mysqli $conn): void
{
    $hasColumn = false;
    $check = $conn->query("SHOW COLUMNS FROM teams LIKE 'max_members'");
    if ($check instanceof mysqli_result) {
        $hasColumn = $check->num_rows > 0;
        $check->free();
    }

    if ($hasColumn) {
        return;
    }

    $sql = "ALTER TABLE teams ADD COLUMN max_members INT NOT NULL DEFAULT 15 AFTER submission_mode";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed to add teams.max_members column: ' . $conn->error);
    }
}

/**
 * Returns team member limit clamped to the absolute cap.
 */
function get_team_member_limit(mysqli $conn, int $teamId): int
{
    $stmt = $conn->prepare('SELECT COALESCE(max_members, 15) AS max_members FROM teams WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare team limit lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Team not found');
    }

    $limit = (int)($row['max_members'] ?? TEAM_MEMBERS_ABSOLUTE_CAP);
    if ($limit < 1) {
        $limit = TEAM_MEMBERS_ABSOLUTE_CAP;
    }

    return min($limit, TEAM_MEMBERS_ABSOLUTE_CAP);
}

/**
 * Returns current number of confirmed team members.
 */
function get_team_member_count(mysqli $conn, int $teamId): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM team_members WHERE team_id = ?');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare team size lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['cnt'] ?? 0);
}

/**
 * Throw if adding another member would exceed the configured team limit.
 */
function assert_team_has_capacity(mysqli $conn, int $teamId): void
{
    $limit = get_team_member_limit($conn, $teamId);
    $count = get_team_member_count($conn, $teamId);

    if ($count >= $limit) {
        throw new RuntimeException("Team is full ({$count}/{$limit}). No more members can be added.");
    }
}
