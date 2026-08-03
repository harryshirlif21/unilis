<?php
/**
 * GET /teams/api/get_available_supervisors.php
 * 
 * Returns lecturers available to supervise a team (same department, under 5 teams)
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$team_id = (int)($_GET['team_id'] ?? 0);

if ($team_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid team ID']);
    exit;
}

try {
    // Get team unit to check department
    $teamStmt = $conn->prepare("SELECT unit_id FROM teams WHERE id = ?");
    $teamStmt->bind_param("i", $team_id);
    $teamStmt->execute();
    $team = $teamStmt->get_result()->fetch_assoc();
    $teamStmt->close();

    if (!$team) {
        echo json_encode(['success' => false, 'message' => 'Team not found']);
        exit;
    }

    // Get unit department through its course
    $unitStmt = $conn->prepare("SELECT c.department_id
        FROM units u
        LEFT JOIN courses c ON u.course_id = c.id
        WHERE u.id = ?");
    $unitStmt->bind_param("i", $team['unit_id']);
    $unitStmt->execute();
    $unit = $unitStmt->get_result()->fetch_assoc();
    $unitStmt->close();

    $department_id = $unit['department_id'] ?? 0;

    if ($department_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unit has no department']);
        exit;
    }

    // Get already assigned supervisors for this team
    $assignedStmt = $conn->prepare("SELECT lecturer_id FROM team_supervisors WHERE team_id = ?");
    $assignedStmt->bind_param("i", $team_id);
    $assignedStmt->execute();
    $assignedResult = $assignedStmt->get_result();
    $assigned_ids = [];
    while ($row = $assignedResult->fetch_assoc()) {
        $assigned_ids[] = (int)$row['lecturer_id'];
    }
    $assignedStmt->close();

    // Get lecturers and technicians from same department who are supervising less than 5 teams
    $max_teams = 5;
    $assigned_ids_str = implode(',', array_map('intval', $assigned_ids));
    $assigned_filter = !empty($assigned_ids) ? "AND person_id NOT IN ($assigned_ids_str)" : "";

    $stmt = $conn->prepare("
        SELECT 
            person_id as id,
            name,
            email,
            department,
            supervisor_type,
            team_count
        FROM (
            SELECT 
                l.id as person_id,
                l.name COLLATE utf8mb4_unicode_ci as name,
                l.email COLLATE utf8mb4_unicode_ci as email,
                d.name COLLATE utf8mb4_unicode_ci as department,
                'lecturer' COLLATE utf8mb4_unicode_ci as supervisor_type,
                COUNT(DISTINCT ts.team_id) as team_count
            FROM lecturers l
            JOIN departments d ON l.department_id = d.id
            LEFT JOIN team_supervisors ts ON l.id = ts.lecturer_id AND ts.supervisor_type = 'lecturer' AND ts.status = 'approved'
            WHERE l.department_id = ?
              $assigned_filter
            GROUP BY l.id
            
            UNION ALL
            
            SELECT 
                t.id as person_id,
                t.name COLLATE utf8mb4_unicode_ci as name,
                t.email COLLATE utf8mb4_unicode_ci as email,
                d.name COLLATE utf8mb4_unicode_ci as department,
                'technician' COLLATE utf8mb4_unicode_ci as supervisor_type,
                COUNT(DISTINCT ts.team_id) as team_count
            FROM technicians t
            JOIN departments d ON t.department_id = d.id
            LEFT JOIN team_supervisors ts ON t.id = ts.lecturer_id AND ts.supervisor_type = 'technician' AND ts.status = 'approved'
            WHERE t.department_id = ?
              $assigned_filter
            GROUP BY t.id
        ) as supervisors
        WHERE team_count < ?
        ORDER BY name ASC
    ");
    $stmt->bind_param("iii", $department_id, $department_id, $max_teams);
    $stmt->execute();
    $result = $stmt->get_result();

    $lecturers = [];
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'department' => $row['department'],
            'supervisor_type' => $row['supervisor_type'],
            'team_count' => (int)$row['team_count']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'lecturers' => $lecturers
    ]);

} catch (Exception $e) {
    error_log('get_available_supervisors error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
