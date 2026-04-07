<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/../models/ActivityLog.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $teamId = (int)($input['team_id'] ?? 0);
    $invitationId = (int)($input['invitation_id'] ?? 0);
    $identifier = trim((string)($input['identifier'] ?? ''));
    $csrfToken = $input['csrf_token'] ?? '';

    if ($teamId <= 0 || ($identifier === '' && $invitationId <= 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'team_id and either identifier or invitation_id are required']);
        exit;
    }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $leaderId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Failed to prepare leader check: ' . $conn->error);
    $stmt->bind_param("ii", $teamId, $leaderId);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$role || $role['role'] !== 'leader') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only team leader can resend codes']);
        exit;
    }

    // Resolve invitation by ID (preferred) or identifier
    $invite = null;
    $student = null;
    $studentId = 0;

    if ($invitationId > 0) {
        $stmt = $conn->prepare("
            SELECT ti.id, s.id AS student_id, s.name, s.email
            FROM team_invitations ti
            JOIN students s ON s.id = ti.invited_student_id
            WHERE ti.id = ? AND ti.team_id = ? AND ti.status = 'pending'
            LIMIT 1
        ");
        if (!$stmt) throw new Exception('Failed to prepare invitation lookup: ' . $conn->error);
        $stmt->bind_param("ii", $invitationId, $teamId);
        $stmt->execute();
        $invite = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($invite) {
            $student = ['id' => $invite['student_id'], 'name' => $invite['name'], 'email' => $invite['email']];
            $studentId = (int)$invite['student_id'];
        }
    } else {
        $stmt = $conn->prepare("SELECT id, name, email FROM students WHERE reg_no = ? OR email = ? LIMIT 1");
        if (!$stmt) throw new Exception('Failed to prepare student lookup: ' . $conn->error);
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($student) {
            $studentId = (int)$student['id'];
            $stmt = $conn->prepare("
                SELECT id FROM team_invitations
                WHERE team_id = ? AND invited_student_id = ? AND status = 'pending'
                LIMIT 1
            ");
            if (!$stmt) throw new Exception('Failed to prepare invitation lookup: ' . $conn->error);
            $stmt->bind_param("ii", $teamId, $studentId);
            $stmt->execute();
            $invite = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if (!$invite) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No pending invitation found']);
        exit;
    }
    $invitationId = (int)$invite['id'];

    // Ensure helper table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS team_invitation_codes (
            invitation_id INT NOT NULL PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            code_expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tic_invitation FOREIGN KEY (invitation_id) REFERENCES team_invitations(id) ON DELETE CASCADE
        )
    ");

    $plainCode = (string)random_int(100000, 999999);
    $codeHash = password_hash($plainCode, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO team_invitation_codes (invitation_id, code_hash, code_expires_at, created_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
        ON DUPLICATE KEY UPDATE
            code_hash = VALUES(code_hash),
            code_expires_at = VALUES(code_expires_at),
            created_at = NOW()
    ");
    if (!$stmt) throw new Exception('Failed to prepare code upsert: ' . $conn->error);
    $stmt->bind_param("is", $invitationId, $codeHash);
    $stmt->execute();
    $stmt->close();

    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($student['email'], $student['name'] ?? ('Student #' . $studentId));
        $mail->isHTML(true);
        $mail->Subject = "Team Invitation Confirmation Code (Resent)";
        $mail->Body = "
            <p>Hello " . htmlspecialchars($student['name'] ?? 'Student') . ",</p>
            <p>Your team invitation code for team <strong>#{$teamId}</strong> has been resent.</p>
            <p style='font-size:24px;font-weight:bold;letter-spacing:2px;'>" . htmlspecialchars($plainCode) . "</p>
            <p>This code expires in 24 hours.</p>
        ";
        $mail->AltBody = "Team #{$teamId} invitation code (resent): {$plainCode}. Expires in 24 hours.";
        $mail->send();
    } catch (Exception $mailEx) {
        // return success false only if email is critical for your flow
        throw new Exception('Invitation code generated, but email failed: ' . $mailEx->getMessage());
    }

    $logger = new ActivityLog($conn);
    $logger->log($teamId, $leaderId, 'member_invite_resend', 'Resent invite code to member #' . $studentId);

    echo json_encode(['success' => true, 'message' => 'Code resent successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

