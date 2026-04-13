<?php
/**
 * API: Get Pending Membership Requests
 * GET /teams/api/get_pending_membership_requests.php?team_id=X
 * 
 * Returns pending membership requests for a team.
 * Requires team_id parameter.
 */

header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
session_start();

require_once __DIR__ . '/../../config/db.php';

$response = [];

try {
    if (empty($_GET['team_id'])) {
        throw new Exception("team_id is required");
    }

    $team_id = intval($_GET['team_id']);

    // Get team and unit info
    $stmt = $conn->prepare("SELECT t.id, t.title, t.unit_id FROM teams t WHERE t.id = ?");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$team) {
        throw new Exception("Team not found");
    }

    // Get all pending requests for this team
    $stmt = $conn->prepare(
        "SELECT 
            tmr.id,
            tmr.team_id,
            tmr.student_id,
            tmr.requested_by,
            tmr.request_type,
            tmr.reason,
            tmr.status,
            tmr.approved_by_lecturer,
            tmr.approved_by_team_lead,
            tmr.lecturer_approval_at,
            tmr.team_lead_approval_at,
            tmr.rejection_reason,
            tmr.created_at,
            s.name as student_name,
            s.email as student_email,
            s.reg_no as student_reg,
            r.name as requested_by_name,
            r.email as requested_by_email
        FROM team_membership_requests tmr
        JOIN students s ON tmr.student_id = s.id
        JOIN students r ON tmr.requested_by = r.id
        WHERE tmr.team_id = ? AND tmr.status IN ('pending', 'approved')
        ORDER BY tmr.created_at DESC"
    );
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response = [
        'success' => true,
        'team' => $team,
        'requests' => $requests
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
