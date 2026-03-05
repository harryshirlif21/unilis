<?php
// teams/api/get_user_teams.php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
session_start();

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$userId = $_SESSION['user_id'];

try {
    // Get teams created or joined by the user
    $stmt = $conn->prepare("
        SELECT DISTINCT t.*
        FROM teams t
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE t.created_by = ? OR tm.student_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $teamsResult = $stmt->get_result();
    $teams = [];

    while ($team = $teamsResult->fetch_assoc()) {
        $team_id = $team['id'];

        // Get members of this team
        $stmtMembers = $conn->prepare("
            SELECT s.id AS student_id, s.name, s.reg_no, s.email, tm.role
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
            WHERE tm.team_id = ?
        ");
        $stmtMembers->bind_param("i", $team_id);
        $stmtMembers->execute();
        $members = $stmtMembers->get_result()->fetch_all(MYSQLI_ASSOC);

        // Assign creator as leader if not already in members
        $creatorFound = false;
        foreach ($members as &$m) {
            if ($m['student_id'] == $team['created_by']) {
                $m['role'] = 'leader';
                $creatorFound = true;
            }
        }
        if (!$creatorFound) {
            // Optionally fetch creator name/email from students table
            $stmtCreator = $conn->prepare("SELECT id, name, reg_no, email FROM students WHERE id = ?");
            $stmtCreator->bind_param("i", $team['created_by']);
            $stmtCreator->execute();
            $creator = $stmtCreator->get_result()->fetch_assoc();
            if ($creator) {
                $creator['role'] = 'leader';
                $members = array_merge([$creator], $members);
            }
        }

        $team['members'] = $members;
        $teams[] = $team;
    }

    echo json_encode(['success' => true, 'teams' => $teams]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}