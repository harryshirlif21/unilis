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
    // Ensure helper table exists to avoid runtime SQL errors
    $conn->query("
        CREATE TABLE IF NOT EXISTS team_invitation_codes (
            invitation_id INT NOT NULL PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            code_expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tic_invitation FOREIGN KEY (invitation_id) REFERENCES team_invitations(id) ON DELETE CASCADE
        )
    ");

    // Mark pending invitations as cancelled if code expired
    $stmt = $conn->prepare("
        UPDATE team_invitations ti
        JOIN team_invitation_codes tic ON tic.invitation_id = ti.id
        SET ti.status = 'cancelled', ti.responded_at = NOW()
        WHERE ti.status = 'pending'
          AND tic.code_expires_at < NOW()
    ");
    if (!$stmt) throw new Exception('Failed to prepare cleanup query: ' . $conn->error);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => true, 'cleaned' => $affected]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

