<?php
// teams/api/get_activity_log.php

header('Content-Type: application/json');
session_start();

// Basic auth: both students and lecturers can view activity; adjust as needed later.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php'; // provides $conn (mysqli)

$teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;

if ($teamId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
    exit;
}

try {
    // For now we keep access rules simple: any logged-in user can query.
    // Later we can enforce "must be member of this team or lecturer of unit".

    $sql = "
        SELECT 
            l.id,
            l.team_id,
            l.user_id,
            l.action_type,
            l.action_detail AS detail,
            l.created_at,
            s.name AS user_name,
            s.reg_no AS user_reg_no
        FROM team_activity_log l
        LEFT JOIN students s ON l.user_id = s.id
        WHERE l.team_id = ?
        ORDER BY l.created_at DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare activity query: ' . $conn->error);
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
        'success'    => true,
        'activities' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

?>

