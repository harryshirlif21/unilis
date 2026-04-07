<?php
// teams/api/task_create.php

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
    $title     = trim((string)($input['title'] ?? ''));
    $desc      = trim((string)($input['description'] ?? ''));
    $dueDate   = trim((string)($input['due_date'] ?? '')); // YYYY-MM-DD or empty
    $priority  = trim((string)($input['priority'] ?? 'Medium')); // Low/Medium/High
    $csrfToken = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'team_id and title are required']);
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

    $allowedPriorities = ['Low', 'Medium', 'High'];
    if (!in_array($priority, $allowedPriorities, true)) {
        $priority = 'Medium';
    }

    // Due date validation: allow empty
    $due = null;
    if ($dueDate !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $dueDate);
        if (!$dt || $dt->format('Y-m-d') !== $dueDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid due_date format (YYYY-MM-DD)']);
            exit;
        }
        $due = $dueDate;
    }

    // Insert task with default status Backlog; assigned_to is optional
    if ($due !== null) {
        $stmt = $conn->prepare("
            INSERT INTO team_tasks (team_id, title, description, due_date, priority, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'Backlog', ?, NOW())
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare task insert: ' . $conn->error);
        }
        $stmt->bind_param('issssi', $teamId, $title, $desc, $due, $priority, $userId);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO team_tasks (team_id, title, description, due_date, priority, status, created_by, created_at)
            VALUES (?, ?, ?, NULL, ?, 'Backlog', ?, NOW())
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare task insert: ' . $conn->error);
        }
        $stmt->bind_param('isssi', $teamId, $title, $desc, $priority, $userId);
    }

    $stmt->execute();
    $taskId = (int) $stmt->insert_id;
    $stmt->close();

    // Activity log (best-effort)
    $logger = new ActivityLog($conn);
    $logger->log($teamId, $userId, 'task_create', sprintf('Task #%d created: %s', $taskId, $title));

    echo json_encode([
        'success' => true,
        'message' => 'Task created',
        'task_id' => $taskId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

