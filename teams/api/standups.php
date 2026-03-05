<?php
// teams/api/standups.php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php'; // mysqli $conn

$teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;

if ($teamId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
    exit;
}

try {
    // Simple read-only list of recent stand-up entries for this team.
    $sql = "
        SELECT 
            id,
            team_id,
            user_id,
            did_today    AS yesterday,
            will_do_next AS today,
            blockers,
            created_at
        FROM standup_entries
        WHERE team_id = ?
        ORDER BY created_at DESC
        LIMIT 30
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare standups query: ' . $conn->error);
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success'  => true,
        'standups' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

