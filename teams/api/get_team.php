<?php
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
session_start();

// Make sure path to db.php is correct
require_once __DIR__ . '/../../config/db.php';

$response = [];

try {
    $team_id = $_GET['team_id'] ?? null;
    if (!$team_id) throw new Exception("Team ID missing");

    // Get team details
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();

    if (!$team) throw new Exception("Team not found");

    // Get team members including role
    $stmt = $conn->prepare("
        SELECT s.id AS student_id, s.name, s.reg_no, s.email, tm.role
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
    ");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Sort members: leader first
    usort($members, function($a, $b) {
        if ($a['role'] === 'leader') return -1;
        if ($b['role'] === 'leader') return 1;
        return 0;
    });

    $team['creator_id'] = $team['created_by'] ?? null;

    $response = [
        'success' => true,
        'team' => $team,
        'members' => $members
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response); 