<?php
/**
 * API: Approve/Reject Membership Request
 * POST /teams/api/approve_membership_request.php
 * 
 * Allows lecturer or team lead to approve a membership request.
 * Requires request_id, action (approve/reject), and optional rejection_reason.
 */

header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
session_start();

// TEMPORARY FOR DEVELOPMENT
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Lecturer ID
    $_SESSION['role'] = 'lecturer';
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

    if (empty($input['request_id']) || empty($input['action'])) {
        throw new Exception("request_id and action are required");
    }

    $request_id = intval($input['request_id']);
    $action = $input['action']; // 'approve' or 'reject'
    $rejection_reason = $input['rejection_reason'] ?? NULL;
    $approver_id = intval($_SESSION['user_id']);
    $approver_role = $_SESSION['role'] ?? 'student';

    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception("Invalid action");
    }

    // Get the request
    $stmt = $conn->prepare(
        "SELECT tmr.*, t.unit_id FROM team_membership_requests tmr 
        JOIN teams t ON tmr.team_id = t.id 
        WHERE tmr.id = ?"
    );
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        throw new Exception("Request not found");
    }

    if ($request['status'] !== 'pending') {
        throw new Exception("This request has already been processed");
    }

    // Determine who is approving
    $is_lecturer = false;
    $is_team_lead = false;

    if ($approver_role === 'lecturer') {
        $is_lecturer = true;
    } else if ($approver_role === 'student') {
        // Check if student is team lead of this team
        $stmt = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $request['team_id'], $approver_id);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($member && $member['role'] === 'leader') {
            $is_team_lead = true;
        }
    }

    if ($action === 'approve') {
        // Approve the request
        if ($is_lecturer) {
            $stmt = $conn->prepare(
                "UPDATE team_membership_requests 
                SET approved_by_lecturer = ?, lecturer_approval_at = NOW() 
                WHERE id = ?"
            );
            $stmt->bind_param("ii", $approver_id, $request_id);
            $stmt->execute();
            $stmt->close();
        } else if ($is_team_lead) {
            $stmt = $conn->prepare(
                "UPDATE team_membership_requests 
                SET approved_by_team_lead = ?, team_lead_approval_at = NOW() 
                WHERE id = ?"
            );
            $stmt->bind_param("ii", $approver_id, $request_id);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("You don't have permission to approve this request");
        }

        // Check if both approvals are done
        $stmt = $conn->prepare("SELECT * FROM team_membership_requests WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $updated_request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($updated_request['approved_by_lecturer'] && $updated_request['approved_by_team_lead']) {
            // Both approved - complete the removal
            $student_id = $updated_request['student_id'];
            $team_id = $updated_request['team_id'];

            // Remove from team
            $stmt = $conn->prepare("DELETE FROM team_members WHERE team_id = ? AND student_id = ?");
            $stmt->bind_param("ii", $team_id, $student_id);
            $stmt->execute();
            $stmt->close();

            // Update request status
            $stmt = $conn->prepare("UPDATE team_membership_requests SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();

            // Log activity
            if (isset($conn)) {
                $logger = new ActivityLog($conn);
                $action_desc = $updated_request['request_type'] === 'leave' 
                    ? "Student removed after approval (member left)"
                    : "Student removed by team lead (approved)";
                $logger->log($team_id, $approver_id, 'membership_removal_approved', $action_desc);
            }

            $response = [
                'success' => true,
                'message' => 'Request approved. Member has been removed from the team.',
                'status' => 'approved'
            ];
        } else {
            $response = [
                'success' => true,
                'message' => 'Request approved by ' . ($is_lecturer ? 'lecturer' : 'team lead') . '. Awaiting other approvals.',
                'status' => 'partial'
            ];
        }

    } else {
        // Reject the request
        $stmt = $conn->prepare(
            "UPDATE team_membership_requests 
            SET status = 'rejected', rejection_reason = ? 
            WHERE id = ?"
        );
        $stmt->bind_param("si", $rejection_reason, $request_id);
        $stmt->execute();
        $stmt->close();

        // Log activity
        if (isset($conn)) {
            $logger = new ActivityLog($conn);
            $logger->log($request['team_id'], $approver_id, 'membership_removal_rejected', 
                "Removal request rejected. Reason: $rejection_reason");
        }

        $response = [
            'success' => true,
            'message' => 'Request rejected.',
            'status' => 'rejected'
        ];
    }

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
$conn->close();
?>
