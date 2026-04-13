<?php
/**
 * API: Request to Leave Team
 * POST /teams/api/request_membership_leave.php
 * 
 * Allows a student to request to leave a team.
 * Requires approval from both lecturer and team lead.
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

    if (empty($input['team_id'])) {
        throw new Exception("team_id is required");
    }

    $team_id = intval($input['team_id']);
    $student_id = intval($_SESSION['user_id']);
    $reason = $input['reason'] ?? NULL;

    // Verify student is a member of the team
    $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $team_id, $student_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) {
        throw new Exception("You are not a member of this team");
    }

    // Check if there's already a pending request
    $stmt = $conn->prepare("SELECT id FROM team_membership_requests WHERE team_id = ? AND student_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $team_id, $student_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        throw new Exception("You already have a pending leave request for this team");
    }

    // Create the request
    $stmt = $conn->prepare(
        "INSERT INTO team_membership_requests 
        (team_id, student_id, requested_by, request_type, reason, status) 
        VALUES (?, ?, ?, 'leave', ?, 'pending')"
    );
    $stmt->bind_param("iis", $team_id, $student_id, $student_id, $reason);
    $stmt->execute();
    $request_id = $stmt->insert_id;
    $stmt->close();

    // Log the activity
    if (isset($conn)) {
        $logger = new ActivityLog($conn);
        $logger->log($team_id, $student_id, 'membership_leave_request', 
            "Student $student_id requested to leave team $team_id");
    }

    $response = [
        'success' => true,
        'message' => 'Leave request submitted successfully. Awaiting approval from lecturer and team lead.',
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
