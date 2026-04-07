<?php
// teams/api/ghost_status.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config.php'; // $conn + GHOST_INACTIVE_DAYS

try {
    $teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
        exit;
    }

    $days = (defined('GHOST_INACTIVE_DAYS') && (int)GHOST_INACTIVE_DAYS > 0) ? (int)GHOST_INACTIVE_DAYS : 3;

    $sql = "
        SELECT
            tm.student_id AS user_id,
            s.name AS user_name,
            MAX(l.created_at) AS last_activity_at
        FROM team_members tm
        JOIN students s ON s.id = tm.student_id
        LEFT JOIN team_activity_log l
            ON l.team_id = tm.team_id
           AND l.user_id = tm.student_id
        WHERE tm.team_id = ?
        GROUP BY tm.student_id, s.name
        ORDER BY s.name ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare ghost status query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result();

    $ghosts = [];
    $members = [];
    $now = new DateTimeImmutable('now');

    while ($row = $res->fetch_assoc()) {
        $last = $row['last_activity_at'];
        $inactiveDays = 9999;
        if ($last) {
            $lastDt = new DateTimeImmutable($last);
            $inactiveDays = (int)$lastDt->diff($now)->format('%a');
        }
        $item = [
            'user_id' => (int)$row['user_id'],
            'user_name' => (string)$row['user_name'],
            'last_activity_at' => $last,
            'inactive_days' => $inactiveDays
        ];
        $members[] = $item;
        if ($inactiveDays >= $days) {
            $ghosts[] = $item;
        }
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'threshold_days' => $days,
        'members' => $members,
        'ghosts' => $ghosts
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

