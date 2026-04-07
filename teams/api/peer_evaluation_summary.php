<?php
// teams/api/peer_evaluation_summary.php

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

try {
    $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare membership check: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    $okMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if (!$okMember) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    // Members list (for dropdown + row completeness)
    $stmt = $conn->prepare("
        SELECT s.id AS student_id, s.name
        FROM team_members tm
        JOIN students s ON s.id = tm.student_id
        WHERE tm.team_id = ?
        ORDER BY s.name ASC
    ");
    if (!$stmt) throw new Exception('Failed to prepare members query: ' . $conn->error);
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result();
    $members = [];
    while ($row = $res->fetch_assoc()) {
        $members[] = $row;
    }
    $stmt->close();

    // Aggregated evaluation summary per evaluatee
    $stmt = $conn->prepare("
        SELECT
            p.evaluatee_id,
            s.name AS evaluatee_name,
            COUNT(*) AS responses,
            AVG(p.contribution) AS avg_contribution,
            AVG(p.communication) AS avg_communication,
            AVG(p.quality) AS avg_quality,
            AVG(p.reliability) AS avg_reliability,
            AVG((p.contribution + p.communication + p.quality + p.reliability) / 4.0) AS avg_overall
        FROM peer_evaluations p
        JOIN students s ON s.id = p.evaluatee_id
        WHERE p.team_id = ?
        GROUP BY p.evaluatee_id, s.name
        ORDER BY s.name ASC
    ");
    if (!$stmt) throw new Exception('Failed to prepare summary query: ' . $conn->error);
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result();
    $summary = [];
    while ($row = $res->fetch_assoc()) {
        $summary[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'members' => $members,
        'summary' => $summary
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

