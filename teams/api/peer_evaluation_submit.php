<?php
// teams/api/peer_evaluation_submit.php

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

    $teamId       = isset($input['team_id']) ? (int)$input['team_id'] : 0;
    $evaluateeId  = isset($input['evaluatee_id']) ? (int)$input['evaluatee_id'] : 0;
    $contribution = isset($input['contribution']) ? (int)$input['contribution'] : 0;
    $communication= isset($input['communication']) ? (int)$input['communication'] : 0;
    $quality      = isset($input['quality']) ? (int)$input['quality'] : 0;
    $reliability  = isset($input['reliability']) ? (int)$input['reliability'] : 0;
    $csrfToken    = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || $evaluateeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'team_id and evaluatee_id are required']);
        exit;
    }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $evaluatorId = (int)$_SESSION['user_id'];
    if ($evaluateeId === $evaluatorId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You cannot evaluate yourself']);
        exit;
    }

    $scores = [$contribution, $communication, $quality, $reliability];
    foreach ($scores as $s) {
        if ($s < 1 || $s > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Scores must be between 1 and 5']);
            exit;
        }
    }

    // Membership checks for evaluator and evaluatee
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare membership check: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $evaluatorId);
    $stmt->execute();
    $okEvaluator = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$okEvaluator) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare evaluatee check: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $evaluateeId);
    $stmt->execute();
    $okEvaluatee = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$okEvaluatee) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Evaluatee is not in this team']);
        exit;
    }

    // Upsert-like behavior: replace existing evaluation from same evaluator to same evaluatee
    $stmt = $conn->prepare("
        SELECT id FROM peer_evaluations
        WHERE team_id = ? AND evaluator_id = ? AND evaluatee_id = ?
        LIMIT 1
    ");
    if (!$stmt) throw new Exception('Failed to prepare existing evaluation check: ' . $conn->error);
    $stmt->bind_param('iii', $teamId, $evaluatorId, $evaluateeId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $stmt = $conn->prepare("
            UPDATE peer_evaluations
            SET contribution = ?, communication = ?, quality = ?, reliability = ?, submitted_at = NOW()
            WHERE id = ?
        ");
        if (!$stmt) throw new Exception('Failed to prepare evaluation update: ' . $conn->error);
        $id = (int)$existing['id'];
        $stmt->bind_param('iiiii', $contribution, $communication, $quality, $reliability, $id);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO peer_evaluations
            (team_id, evaluator_id, evaluatee_id, contribution, communication, quality, reliability, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) throw new Exception('Failed to prepare evaluation insert: ' . $conn->error);
        $stmt->bind_param('iiiiiii', $teamId, $evaluatorId, $evaluateeId, $contribution, $communication, $quality, $reliability);
    }
    $stmt->execute();
    $stmt->close();

    $logger = new ActivityLog($conn);
    $logger->log($teamId, $evaluatorId, 'peer_eval_submit', 'Submitted peer evaluation for user #' . $evaluateeId);

    echo json_encode(['success' => true, 'message' => 'Peer evaluation submitted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

