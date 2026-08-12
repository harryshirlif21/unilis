<?php
// teams/api/get_user_teams.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_display_helpers.php';
require_once __DIR__ . '/../includes/ensure_team_registrations.php';

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            t.*,
            u.name AS unit_name,
            u.code AS unit_code,
            c.name AS course_name,
            (
                SELECT COUNT(*)
                FROM team_members tm_count
                WHERE tm_count.team_id = t.id
            ) AS member_count,
            (
                SELECT MAX(tal.created_at)
                FROM team_activity_log tal
                WHERE tal.team_id = t.id
            ) AS latest_activity_at
        FROM teams t
        JOIN units u ON u.id = t.unit_id
        LEFT JOIN courses c ON c.id = t.course_id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE t.created_by = ? OR tm.student_id = ?
        ORDER BY COALESCE(latest_activity_at, t.created_at) DESC, t.created_at DESC
    ");
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $teamsResult = $stmt->get_result();
    $teams = [];

    while ($team = $teamsResult->fetch_assoc()) {
        $team_id = (int) $team['id'];

        $stmtMembers = $conn->prepare("
            SELECT s.id AS student_id, s.name, s.reg_no, s.email, tm.role
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
            WHERE tm.team_id = ?
        ");
        $stmtMembers->bind_param('i', $team_id);
        $stmtMembers->execute();
        $members = $stmtMembers->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMembers->close();

        $creatorFound = false;
        foreach ($members as &$m) {
            if ((int) $m['student_id'] === (int) $team['created_by']) {
                $m['role'] = 'leader';
                $creatorFound = true;
            }
        }
        unset($m);

        if (!$creatorFound) {
            $stmtCreator = $conn->prepare('SELECT id, name, reg_no, email FROM students WHERE id = ?');
            $stmtCreator->bind_param('i', $team['created_by']);
            $stmtCreator->execute();
            $creator = $stmtCreator->get_result()->fetch_assoc();
            $stmtCreator->close();
            if ($creator) {
                $creator['role'] = 'leader';
                $members = array_merge([$creator], $members);
            }
        }

        $team['members'] = $members;
        $team['member_count'] = count($members);
        $team = team_enrich_row($team, $conn);
        $team['registrations'] = team_get_registrations($conn, $team_id);
        $teams[] = $team;
    }

    $stmt->close();

    echo json_encode(['success' => true, 'teams' => $teams]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
