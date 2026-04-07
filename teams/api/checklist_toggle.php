<?php
// teams/api/checklist_toggle.php

// Strict JSON output (avoid HTML notices breaking fetch())
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

    $teamId      = isset($input['team_id']) ? (int) $input['team_id'] : 0;
    $checklistId = isset($input['checklist_id']) ? (int) $input['checklist_id'] : 0;
    $isChecked   = isset($input['is_checked']) ? (int) $input['is_checked'] : -1;
    $csrfToken   = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || $checklistId <= 0 || ($isChecked !== 0 && $isChecked !== 1)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid fields']);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    // Must be a member of the team to modify checklist
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

    // Update checklist row (and ensure it belongs to this team)
    if ($isChecked === 1) {
        $stmt = $conn->prepare("
            UPDATE submission_checklist
            SET is_checked = 1, checked_by = ?, checked_at = NOW()
            WHERE id = ? AND team_id = ?
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare checklist update: ' . $conn->error);
        }
        $stmt->bind_param('iii', $userId, $checklistId, $teamId);
    } else {
        $stmt = $conn->prepare("
            UPDATE submission_checklist
            SET is_checked = 0, checked_by = NULL, checked_at = NULL
            WHERE id = ? AND team_id = ?
        ");
        if (!$stmt) {
            throw new Exception('Failed to prepare checklist update: ' . $conn->error);
        }
        $stmt->bind_param('ii', $checklistId, $teamId);
    }

    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Checklist item not found']);
        exit;
    }

    // Best-effort activity logging
    $logger = new ActivityLog($conn);
    $detail = sprintf(
        'Checklist item %d set to %s by user %d',
        $checklistId,
        $isChecked ? 'checked' : 'unchecked',
        $userId
    );
    $logger->log($teamId, $userId, 'checklist_toggle', $detail);

    echo json_encode([
        'success' => true,
        'message' => 'Checklist updated'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

