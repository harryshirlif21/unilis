<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

session_start();

// Make sure path to db.php is correct
require_once __DIR__ . '/../../config/db.php';

function team_role_label(string $role): string
{
    $role = strtolower(trim($role));
    $labels = [
        'leader' => 'Team Lead',
        'member' => 'Member',
        'frontend_developer' => 'Frontend Developer',
        'backend_developer' => 'Backend Developer',
        'machine_learning' => 'Machine Learning',
        'ui_ux_designer' => 'UI/UX Designer',
        'data_analyst' => 'Data Analyst',
        'tester' => 'Tester',
        'researcher' => 'Researcher',
        'presenter' => 'Presenter',
        'other' => 'Other',
    ];

    return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

$response = [];

try {
    $team_id = $_GET['team_id'] ?? null;
    if (!$team_id) throw new Exception("Team ID missing");

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Get team details
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();

    if (!$team) throw new Exception("Team not found");

    // Get team members including role
    $stmt = $conn->prepare("
        SELECT s.id AS student_id, s.name, s.reg_no, s.email, tm.role
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Members query preparation failed: " . $conn->error);
    }
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($members as &$member) {
        $member['display_role'] = team_role_label((string)($member['role'] ?? 'member'));
    }
    unset($member);

    // Sort members: leader first
    usort($members, function($a, $b) {
        if ($a['role'] === 'leader') return -1;
        if ($b['role'] === 'leader') return 1;
        return 0;
    });

    $team['creator_id'] = $team['created_by'] ?? null;

    $response = [
        'success' => true,
        'team' => $team,
        'members' => $members
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'team_id' => $team_id,
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'session_user_role' => $_SESSION['user_role'] ?? null
        ]
    ];
}

echo json_encode($response);