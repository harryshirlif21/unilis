<?php
// teams/api/ghost_nudge.php

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

require_once __DIR__ . '/../config.php'; // $conn + NUDGE_COOLDOWN_HOURS
require_once __DIR__ . '/../models/ActivityLog.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $teamId    = isset($input['team_id']) ? (int)$input['team_id'] : 0;
    $targetId  = isset($input['target_user_id']) ? (int)$input['target_user_id'] : 0;
    $csrfToken = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || $targetId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'team_id and target_user_id are required']);
        exit;
    }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $actorId = (int)$_SESSION['user_id'];
    $cooldown = (defined('NUDGE_COOLDOWN_HOURS') && (int)NUDGE_COOLDOWN_HOURS > 0) ? (int)NUDGE_COOLDOWN_HOURS : 24;

    // Actor must be team member
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare actor membership check: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $actorId);
    $stmt->execute();
    $okActor = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$okActor) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    // Target must be team member
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare target membership check: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $targetId);
    $stmt->execute();
    $okTarget = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$okTarget) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Target user not in team']);
        exit;
    }

    // Cooldown check on last nudge
    $stmt = $conn->prepare("
        SELECT nudge_sent_at
        FROM ghost_flags
        WHERE team_id = ? AND user_id = ?
        ORDER BY nudge_sent_at DESC
        LIMIT 1
    ");
    if (!$stmt) throw new Exception('Failed to prepare cooldown query: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $targetId);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($last && !empty($last['nudge_sent_at'])) {
        $diffHours = (time() - strtotime($last['nudge_sent_at'])) / 3600;
        if ($diffHours < $cooldown) {
            echo json_encode([
                'success' => false,
                'error' => 'Nudge cooldown active',
                'cooldown_hours' => $cooldown
            ]);
            exit;
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO ghost_flags (team_id, user_id, flagged_at, nudge_sent_at)
        VALUES (?, ?, NOW(), NOW())
    ");
    if (!$stmt) throw new Exception('Failed to prepare nudge insert: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $targetId);
    $stmt->execute();
    $stmt->close();

    $logger = new ActivityLog($conn);
    $logger->log($teamId, $actorId, 'ghost_nudge', 'Nudged inactive member #' . $targetId);

    echo json_encode(['success' => true, 'message' => 'Nudge sent']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

