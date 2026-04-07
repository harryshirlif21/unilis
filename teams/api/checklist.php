<?php
// teams/api/checklist.php

// Strict JSON: hide PHP notices/warnings from output
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

$teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;

if ($teamId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
    exit;
}

try {
    // Read-only: return checklist items tied to this team.
    // To remain schema-tolerant, we select all columns and let the
    // frontend pick appropriate keys (item_label/label/item_text/...).

    $sql = "SELECT * FROM submission_checklist WHERE team_id = ? ORDER BY id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare checklist query: ' . $conn->error);
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    // Also include current sign-offs (optional helper for the UI)
    $stmt = $conn->prepare("
        SELECT ss.id, ss.team_id, ss.user_id, ss.signed_at, s.name AS user_name
        FROM submission_signoffs ss
        LEFT JOIN students s ON ss.user_id = s.id
        WHERE ss.team_id = ?
        ORDER BY ss.signed_at DESC
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare signoffs query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $signoffsRes = $stmt->get_result();
    $signoffs = [];
    while ($row = $signoffsRes->fetch_assoc()) {
        $signoffs[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success'   => true,
        'checklist' => $items,
        'signoffs'  => $signoffs
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

