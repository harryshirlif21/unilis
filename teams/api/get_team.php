<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_display_helpers.php';
require_once __DIR__ . '/../includes/ensure_team_registrations.php';

$response = [];
$team_id = null;

try {
    $team_id = $_GET['team_id'] ?? null;
    if (!$team_id) {
        throw new Exception('Team ID missing');
    }

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    $stmt = $conn->prepare("
        SELECT
            t.*,
            u.name AS unit_name,
            u.code AS unit_code,
            c.name AS course_name
        FROM teams t
        JOIN units u ON u.id = t.unit_id
        LEFT JOIN courses c ON c.id = t.course_id
        WHERE t.id = ?
    ");
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$team) {
        throw new Exception('Team not found');
    }

    $team = team_enrich_row($team, $conn);

    $stmt = $conn->prepare("
        SELECT s.id AS student_id, s.name, s.reg_no, s.email, tm.role
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
    ");
    if (!$stmt) {
        throw new Exception('Members query preparation failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $team_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($members as &$member) {
        $member['display_role'] = team_role_label((string) ($member['role'] ?? 'member'));
    }
    unset($member);

    usort($members, function ($a, $b) {
        if ($a['role'] === 'leader') {
            return -1;
        }
        if ($b['role'] === 'leader') {
            return 1;
        }
        return 0;
    });

    $team['creator_id'] = $team['created_by'] ?? null;
    $team['member_count'] = count($members);
    $team['registrations'] = team_get_registrations($conn, (int) $team_id);

    foreach ($team['registrations'] as &$registration) {
        $registration['member_ids'] = team_registration_member_ids(
            $conn,
            (int) $registration['id'],
            (int) $team_id
        );
        $registration['member_count'] = count($registration['member_ids']);
    }
    unset($registration);

    $response = [
        'success' => true,
        'team' => $team,
        'members' => $members,
        'registrations' => $team['registrations'],
    ];
} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'team_id' => $team_id,
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'session_user_role' => $_SESSION['user_role'] ?? null,
        ],
    ];
}

echo json_encode($response);
