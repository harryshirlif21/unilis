<?php
// STRICT JSON OUTPUT MODE
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

session_start();
ob_start(); // buffer output

$logFile = __DIR__ . '/add_member_debug.log';
function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

debugLog("=== REQUEST START ===");
debugLog("Session ID: " . session_id());
debugLog("Session contents: " . print_r($_SESSION, true));

// AUTH CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    debugLog("Unauthorized access attempt.");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
debugLog("Authenticated user_id: $userId, role: $userRole");

if ($userRole !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

// READ INPUT
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$teamId     = $input['team_id'] ?? null;
$identifier = trim($input['identifier'] ?? '');
$csrfToken  = $input['csrf_token'] ?? '';

debugLog("Received input: " . json_encode($input));

// VALIDATION
if (!$teamId || !$identifier) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// CSRF CHECK
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    debugLog("CSRF validation failed.");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

debugLog("CSRF validation passed.");

// DATABASE & MODELS
require_once __DIR__ . '/../../config/db.php'; // $conn is mysqli
require_once __DIR__ . '/../controllers/MemberController.php';
require_once __DIR__ . '/../models/ActivityLog.php';
require_once __DIR__ . '/../../config/email.php';

$controller = new MemberController($conn);
$activityLog = new ActivityLog($conn);

try {
    // 1. Find student by reg_no or email
    $stmt = $conn->prepare("SELECT id, name, reg_no, email FROM students WHERE reg_no = ? OR email = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();

    if (!$member) {
        throw new Exception("Member not found");
    }

    $studentId = (int)$member['id']; // <-- FIX: use 'id', not 'student_id'

    // 2. Only team leader can invite members
    $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    $stmt->bind_param("ii", $teamId, $userId);
    $stmt->execute();
    $actorRoleRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$actorRoleRow || $actorRoleRow['role'] !== 'leader') {
        throw new Exception("Only team leader can invite members");
    }

    // 3. Check if already in team
    $stmt = $conn->prepare("SELECT * FROM team_members WHERE team_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $teamId, $studentId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Member already in team");
    }

    // 4. Ensure invitation helper table exists (code storage)
    $conn->query("
        CREATE TABLE IF NOT EXISTS team_invitation_codes (
            invitation_id INT NOT NULL PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            code_expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tic_invitation FOREIGN KEY (invitation_id) REFERENCES team_invitations(id) ON DELETE CASCADE
        )
    ");

    // 5. Create or reuse pending invitation
    $stmt = $conn->prepare("SELECT id, status FROM team_invitations WHERE team_id = ? AND invited_student_id = ? LIMIT 1");
    $stmt->bind_param("ii", $teamId, $studentId);
    $stmt->execute();
    $existingInvite = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existingInvite) {
        $inviteId = (int)$existingInvite['id'];
        $stmt = $conn->prepare("UPDATE team_invitations SET invited_by = ?, status = 'pending', invited_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $userId, $inviteId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO team_invitations (team_id, invited_student_id, invited_by, status, invited_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("iii", $teamId, $studentId, $userId);
        $stmt->execute();
        $inviteId = (int)$stmt->insert_id;
        $stmt->close();
    }

    // 6. Generate confirmation code and store hash
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
    $stmt->bind_param("is", $inviteId, $codeHash);
    $stmt->execute();
    $stmt->close();

    // 7. Send invitation email (best effort)
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($member['email'], $member['name'] ?? ('Student #' . $studentId));
        $mail->isHTML(true);
        $mail->Subject = "Team Invitation Confirmation Code";
        $mail->Body = "
            <p>Hello " . htmlspecialchars($member['name'] ?? 'Student') . ",</p>
            <p>You have been invited to join team <strong>#{$teamId}</strong> on UniLIS.</p>
            <p>Your confirmation code is:</p>
            <p style='font-size:24px;font-weight:bold;letter-spacing:2px;'>" . htmlspecialchars($plainCode) . "</p>
            <p>This code expires in 24 hours.</p>
            <p>You can accept from your dashboard, or share this code with your team leader for manual confirmation.</p>
        ";
        $mail->AltBody = "You were invited to team #{$teamId}. Confirmation code: {$plainCode}. Expires in 24 hours.";
        $mail->send();
    } catch (Exception $mailEx) {
        debugLog("Email send failed: " . $mailEx->getMessage());
    }

    debugLog("Invitation created: invite_id $inviteId for student $studentId team $teamId");

    // 8. Log activity (best-effort; failure should not break main flow)
    $detail = sprintf(
        'Invitation sent to member %d (%s, %s) for team %d by leader %d',
        $studentId,
        $member['reg_no'] ?? '',
        $member['email'] ?? '',
        $teamId,
        $userId
    );
    $logged = $activityLog->log(
        (int)$teamId,
        (int)$userId,
        'member_invite',
        $detail
    );
    debugLog('Activity log write: ' . ($logged ? 'ok' : 'failed'));

    echo json_encode([
        'success' => true,
        'message' => 'Invitation sent. Confirmation code has been emailed to the member.',
        'invitation_id' => $inviteId
    ]);

} catch (Exception $e) {
    debugLog("Exception: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

debugLog("=== REQUEST END ===\n");