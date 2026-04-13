<?php
/**
 * API: Team Lead Request Member Removal
 * POST /teams/api/request_member_removal.php
 * 
 * Allows team lead to request removal of a member.
 * Requires approval from lecturer only (team lead already approved).
 */

header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
session_start();

// TEMPORARY FOR DEVELOPMENT
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 3;
    $_SESSION['role'] = 'student';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/ActivityLog.php';

$response = [];

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $csrfToken = $input['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid CSRF token");
    }

    if (empty($input['team_id']) || empty($input['student_id'])) {
        throw new Exception("team_id and student_id are required");
    }

    $team_id = intval($input['team_id']);
    $student_id = intval($input['student_id']);
    $team_lead_id = intval($_SESSION['user_id']);
    $reason = $input['reason'] ?? NULL;

    // Verify requester is team lead
    $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $team_id, $team_lead_id);
    $stmt->execute();
    $leader = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$leader || $leader['role'] !== 'leader') {
        throw new Exception("Only team leads can request member removal");
    }

    // Verify target is a member of the team
    $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $team_id, $student_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) {
        throw new Exception("Target member is not in this team");
    }

    // Cannot remove fellow leaders
    if ($member['role'] === 'leader') {
        throw new Exception("Cannot remove team leaders");
    }

    // Check for existing pending removal request
    $stmt = $conn->prepare(
        "SELECT id FROM team_membership_requests 
        WHERE team_id = ? AND student_id = ? AND request_type = 'remove' AND status = 'pending'"
    );
    $stmt->bind_param("ii", $team_id, $student_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        throw new Exception("There is already a pending removal request for this member");
    }

    // Create the removal request
    $stmt = $conn->prepare(
        "INSERT INTO team_membership_requests 
        (team_id, student_id, requested_by, request_type, reason, status, team_lead_approval_at, approved_by_team_lead) 
        VALUES (?, ?, ?, 'remove', ?, 'pending', NOW(), ?)"
    );
    $stmt->bind_param("iissi", $team_id, $student_id, $team_lead_id, $reason, $team_lead_id);
    $stmt->execute();
    $request_id = $stmt->insert_id;
    $stmt->close();

    // Log the activity
    if (isset($conn)) {
        $logger = new ActivityLog($conn);
        $logger->log($team_id, $team_lead_id, 'membership_removal_request', 
            "Team lead $team_lead_id requested removal of student $student_id from team $team_id. Reason: $reason");
    }

    $response = [
        'success' => true,
        'message' => 'Removal request submitted. Awaiting lecturer approval.',
        'request_id' => $request_id
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
$conn->close();
?>
