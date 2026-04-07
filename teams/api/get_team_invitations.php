<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Ensure invitation code helper table exists before joins
    $conn->query("
        CREATE TABLE IF NOT EXISTS team_invitation_codes (
            invitation_id INT NOT NULL PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            code_expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tic_invitation FOREIGN KEY (invitation_id) REFERENCES team_invitations(id) ON DELETE CASCADE
        )
    ");

    $teamId = (int)($_GET['team_id'] ?? 0);
    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    // Leader-only visibility for invite tracking
    $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare role check: ' . $conn->error);
    $stmt->bind_param("ii", $teamId, $userId);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$role || $role['role'] !== 'leader') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only team leader can view invitations']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            ti.id,
            ti.team_id,
            ti.invited_student_id,
            ti.invited_by,
            ti.status,
            ti.invited_at,
            ti.responded_at,
            s.name AS invited_name,
            s.reg_no AS invited_reg_no,
            s.email AS invited_email,
            tic.code_expires_at
        FROM team_invitations ti
        JOIN students s ON s.id = ti.invited_student_id
        LEFT JOIN team_invitation_codes tic ON tic.invitation_id = ti.id
        WHERE ti.team_id = ?
        ORDER BY ti.invited_at DESC
        LIMIT 100
    ");
    if (!$stmt) throw new Exception('Failed to prepare invitations query: ' . $conn->error);
    $stmt->bind_param("i", $teamId);
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

