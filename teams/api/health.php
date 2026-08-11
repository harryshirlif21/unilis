<?php
// teams/api/health.php

// Keep output strictly JSON (no PHP warnings/notices)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'technician', 'student'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Load DB connection and health weights
require_once __DIR__ . '/../config.php'; // loads $conn and HEALTH_SCORE_WEIGHTS
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

    // 1) Tasks done: count of tasks with status = 'Done'
    $sqlDone = "SELECT COUNT(*) AS c FROM team_tasks WHERE team_id = ? AND status = 'Done'";
    $stmt = $conn->prepare($sqlDone);
    if (!$stmt) {
        throw new Exception('Failed to prepare tasks query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $tasksDone = (int) ($res['c'] ?? 0);
    $stmt->close();

    // 2) Recent activity: count of activity rows in last 7 days
    $sqlAct = "
        SELECT COUNT(*) AS c
        FROM team_activity_log
        WHERE team_id = ?
          AND created_at >= (NOW() - INTERVAL 7 DAY)
    ";
    $stmt = $conn->prepare($sqlAct);
    if (!$stmt) {
        throw new Exception('Failed to prepare activity query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $activityCount = (int) ($res['c'] ?? 0);
    $stmt->close();

    // 2b) Activity heatmap: counts by day for last 14 days (including today)
    $sqlHeat = "
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM team_activity_log
        WHERE team_id = ?
          AND created_at >= (CURDATE() - INTERVAL 13 DAY)
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ";
    $stmt = $conn->prepare($sqlHeat);
    if (!$stmt) {
        throw new Exception('Failed to prepare heatmap query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    $heatMap = [];
    // Initialize days with 0 so frontend can draw a consistent grid
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $heatMap[$day] = 0;
    }
    while ($row = $result->fetch_assoc()) {
        $dayKey = $row['d'];
        if ($dayKey) {
            $heatMap[$dayKey] = (int) $row['c'];
        }
    }
    $stmt->close();

    // 3) Deadline proximity: basic stub (0.5 by default).
    // Later we can read real deadlines from an assessments table.
    $deadlineFactor = 0.5;

    // Normalize metrics into 0–1 ranges (simple caps)
    $tasksScore    = min($tasksDone / 10.0, 1.0);     // 10+ done tasks → full score
    $activityScore = min($activityCount / 20.0, 1.0); // 20+ events in 7 days → full score

    // Fallback to sane defaults if constant is not defined for any reason
    $defaultWeights = [
        'tasks_done' => 40,
        'activity'   => 30,
        'deadline'   => 30,
    ];
    $weights = (defined('HEALTH_SCORE_WEIGHTS') && is_array(HEALTH_SCORE_WEIGHTS))
        ? HEALTH_SCORE_WEIGHTS
        : $defaultWeights;
    $totalWeight = ($weights['tasks_done'] ?? 0) + ($weights['activity'] ?? 0) + ($weights['deadline'] ?? 0);
    if ($totalWeight <= 0) {
        $totalWeight = 100;
    }

    $score =
        ($tasksScore    * ($weights['tasks_done'] ?? 0)) +
        ($activityScore * ($weights['activity'] ?? 0)) +
        ($deadlineFactor * ($weights['deadline'] ?? 0));

    // Scale to 0–100
    $score = ($score / $totalWeight) * 100;

    echo json_encode([
        'success' => true,
        'score'   => round($score),
        'heatmap' => $heatMap,
        'components' => [
            'tasks_done' => [
                'raw'   => $tasksDone,
                'score' => round($tasksScore * 100)
            ],
            'activity' => [
                'raw'   => $activityCount,
                'score' => round($activityScore * 100)
            ],
            'deadline' => [
                'raw'   => $deadlineFactor,
                'score' => round($deadlineFactor * 100)
            ]
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

