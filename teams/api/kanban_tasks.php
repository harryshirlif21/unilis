<?php
// teams/api/kanban_tasks.php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'technician', 'student'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php'; // mysqli $conn
require_once __DIR__ . '/../includes/team_access.php';

$teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;

if ($teamId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
    exit;
}

try {
    if (!team_user_can_access_team($conn, $teamId, (int) $_SESSION['user_id'], $_SESSION['user_role'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied for this team']);
        exit;
    }

    // Read-only v1: return tasks grouped by simple status column.
    // We'll extend this later with create/update endpoints and activity logging.

    $sql = "
        SELECT 
            id,
            team_id,
            title,
            description,
            status AS original_status,
            -- Normalize status into simple buckets for the frontend Kanban
            CASE 
                WHEN status = 'Backlog'      THEN 'todo'
                WHEN status = 'In Progress'  THEN 'in_progress'
                WHEN status = 'In Review'    THEN 'in_progress'
                WHEN status = 'Done'         THEN 'done'
                ELSE 'todo'
            END AS status,
            assigned_to AS assignee_id,
            due_date,
            priority,
            created_at,
            updated_at
        FROM team_tasks
        WHERE team_id = ?
        ORDER BY FIELD(status, 'Backlog', 'In Progress', 'In Review', 'Done'), due_date IS NULL, due_date, id
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare kanban_tasks query: ' . $conn->error);
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'tasks'   => $tasks
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

