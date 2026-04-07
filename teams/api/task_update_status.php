<?php
// teams/api/task_update_status.php

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

require_once __DIR__ . '/../../config/db.php'; // mysqli $conn
require_once __DIR__ . '/../models/ActivityLog.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $teamId    = isset($input['team_id']) ? (int) $input['team_id'] : 0;
    $taskId    = isset($input['task_id']) ? (int) $input['task_id'] : 0;
    $status    = trim((string)($input['status'] ?? ''));
    $csrfToken = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || $taskId <= 0 || $status === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'team_id, task_id and status are required']);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    // Team membership check
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to prepare membership check: ' . $conn->error);
    }
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    $isMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$isMember) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    $allowedStatuses = ['Backlog', 'In Progress', 'In Review', 'Done'];
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }

    // Fetch current title/status (for log)
    $stmt = $conn->prepare("SELECT title, status FROM team_tasks WHERE id = ? AND team_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to prepare task fetch: ' . $conn->error);
    }
    $stmt->bind_param('ii', $taskId, $teamId);
    $stmt->execute();
    $taskRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$taskRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    $oldStatus = (string)($taskRow['status'] ?? '');
    $title = (string)($taskRow['title'] ?? '');

    $stmt = $conn->prepare("UPDATE team_tasks SET status = ?, updated_at = NOW() WHERE id = ? AND team_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare task update: ' . $conn->error);
    }
    $stmt->bind_param('sii', $status, $taskId, $teamId);
    $stmt->execute();
    $stmt->close();

    $logger = new ActivityLog($conn);
    $logger->log(
        $teamId,
        $userId,
        'task_status',
        sprintf('Task #%d "%s" moved: %s → %s', $taskId, $title, $oldStatus, $status)
    );

    echo json_encode(['success' => true, 'message' => 'Task updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

