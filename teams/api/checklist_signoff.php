<?php
// teams/api/checklist_signoff.php

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

    $teamId    = isset($input['team_id']) ? (int) $input['team_id'] : 0;
    $csrfToken = $input['csrf_token'] ?? '';

    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    // Must be a member of the team to sign off
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

    // Prevent duplicate sign-offs by same user
    $stmt = $conn->prepare("SELECT id FROM submission_signoffs WHERE team_id = ? AND user_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to prepare signoff check: ' . $conn->error);
    }
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        echo json_encode([
            'success' => true,
            'message' => 'Already signed off'
        ]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO submission_signoffs (team_id, user_id, signed_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Failed to prepare signoff insert: ' . $conn->error);
    }
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    $stmt->close();

    // Best-effort activity logging
    $logger = new ActivityLog($conn);
    $logger->log($teamId, $userId, 'checklist_signoff', 'Checklist signed off');

    echo json_encode([
        'success' => true,
        'message' => 'Signed off successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

