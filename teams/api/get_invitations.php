<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT ti.id, ti.team_id, ti.invited_by, ti.status, ti.invited_at,
               t.title AS team_title,
               s.name AS inviter_name
        FROM team_invitations ti
        JOIN teams t ON t.id = ti.team_id
        LEFT JOIN students s ON s.id = ti.invited_by
        WHERE ti.invited_student_id = ? AND ti.status = 'pending'
        ORDER BY ti.invited_at DESC
    ");
    if (!$stmt) throw new Exception('Failed to prepare invitations query: ' . $conn->error);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    echo json_encode(['success' => true, 'invitations' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

