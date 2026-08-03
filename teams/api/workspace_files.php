<?php
// teams/api/workspace_files.php

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
    // Read-only for now: just expose whatever is already in team_files
    // so the Files tab can show a simple list. We'll refine schema usage later.

    $sql = "
        SELECT 
            tf.id,
            team_id,
            original_name AS file_name,
            filepath      AS file_path,
            version,
            uploader_id,
            tf.mime_type,
            tf.uploaded_at,
            s.name AS uploader_name
        FROM team_files tf
        LEFT JOIN students s ON s.id = tf.uploader_id
        WHERE team_id = ?
        ORDER BY tf.uploaded_at DESC, tf.id DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare workspace_files query: ' . $conn->error);
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'files'   => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

