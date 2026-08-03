<?php
/**
 * POST /teams/api/request_supervisor.php
 * 
 * Requests a supervisor for a team (lecturer or team lead)
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function tableColumnExists(mysqli $conn, string $table, string $column): bool
{
    $escapedTable = $conn->real_escape_string($table);
    $escapedColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");
    return $result ? $result->num_rows > 0 : false;
}

function tableExists(mysqli $conn, string $table): bool
{
    $escapedTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$escapedTable}'");
    return $result ? $result->num_rows > 0 : false;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$team_id = (int)($input['team_id'] ?? 0);
$person_id = (int)($input['lecturer_id'] ?? 0);
$supervisor_type = $input['supervisor_type'] ?? 'lecturer';

if ($team_id <= 0 || $person_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if (!in_array($supervisor_type, ['lecturer', 'technician', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid supervisor type']);
    exit;
}

try {
    // Get team info
    $teamStmt = $conn->prepare("SELECT unit_id, assessment_type FROM teams WHERE id = ?");
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

    // Check if person is from same department
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($supervisor_type === 'lecturer') {
        $personDeptStmt = $conn->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $personDeptStmt->bind_param("i", $person_id);
        $personDeptStmt->execute();
        $personDept = $personDeptStmt->get_result()->fetch_assoc();
        $personDeptStmt->close();
    } elseif ($supervisor_type === 'technician') {
        if (!tableExists($conn, 'technicians')) {
            echo json_encode(['success' => false, 'message' => 'Technician supervision is unavailable because the technicians table is missing']);
            exit;
        }

        if (!tableColumnExists($conn, 'technicians', 'department_id')) {
            echo json_encode(['success' => false, 'message' => 'Technician supervision is unavailable because the technicians table is missing required schema']);
            exit;
        }

        $personDeptStmt = $conn->prepare("SELECT department_id FROM technicians WHERE id = ?");
        $personDeptStmt->bind_param("i", $person_id);
        $personDeptStmt->execute();
        $personDept = $personDeptStmt->get_result()->fetch_assoc();
        $personDeptStmt->close();
    } else {
        if (tableColumnExists($conn, 'admins', 'department_id')) {
            $personDeptStmt = $conn->prepare("SELECT department_id FROM admins WHERE id = ?");
        } elseif (tableExists($conn, 'department_admins')) {
            $personDeptStmt = $conn->prepare(
                "SELECT da.department_id
                 FROM admins a
                 JOIN department_admins da ON a.id = da.admin_id AND da.is_active = 1
                 WHERE a.id = ?
                 LIMIT 1"
            );
        } else {
            echo json_encode(['success' => false, 'message' => 'Admin department lookup not available']);
            exit;
        }

        $personDeptStmt->bind_param("i", $person_id);
        $personDeptStmt->execute();
        $personDept = $personDeptStmt->get_result()->fetch_assoc();
        $personDeptStmt->close();
    }

    if (!$personDept || $personDept['department_id'] != $department_id) {
        echo json_encode(['success' => false, 'message' => 'Supervisor must be from the same department']);
        exit;
    }

    // Check if person is already supervising this team
    $existingStmt = $conn->prepare("SELECT id, status FROM team_supervisors WHERE team_id = ? AND lecturer_id = ? AND supervisor_type = ?");
    $existingStmt->bind_param("iis", $team_id, $person_id, $supervisor_type);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'This person is already assigned to this team']);
        exit;
    }

    // Check person's current supervision count (max 5)
    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM team_supervisors WHERE lecturer_id = ? AND supervisor_type = ? AND status = 'approved'");
    $countStmt->bind_param("is", $person_id, $supervisor_type);
    $countStmt->execute();
    $count = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    if ((int)$count['cnt'] >= 5) {
        echo json_encode(['success' => false, 'message' => 'This person is already supervising the maximum number of teams (5)']);
        exit;
    }

    // Determine if this is a secondary supervisor assignment by the unit lecturer
    $user_role = $_SESSION['user_role'];
    $is_primary = false; // secondary supervisor always uses false here
    $status = 'pending';
    $approved_by = null;
    $approved_at = null;

    if ($user_role === 'lecturer') {
        $lecturerUnitStmt = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
        $lecturerUnitStmt->bind_param("ii", $userId, $team['unit_id']);
        $lecturerUnitStmt->execute();
        $isUnitLecturer = $lecturerUnitStmt->get_result()->num_rows > 0;
        $lecturerUnitStmt->close();

        if (!$isUnitLecturer) {
            echo json_encode(['success' => false, 'message' => 'Only the lecturer teaching this unit can assign a supervisor']);
            exit;
        }
    }

    if (in_array($user_role, ['lecturer', 'admin', 'technician'], true)) {
        $status = 'approved';
        $approved_by = $userId;
        $approved_at = date('Y-m-d H:i:s');
    }

    $requested_by = $userId;

    // Insert supervisor assignment
    $insertStmt = $conn->prepare(" 
        INSERT INTO team_supervisors (team_id, lecturer_id, supervisor_type, is_primary, status, requested_by, approved_by, approved_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$insertStmt) {
        throw new Exception('Insert prepare failed: ' . $conn->error);
    }

    if (!$insertStmt->bind_param("iisisiis", $team_id, $person_id, $supervisor_type, $is_primary, $status, $requested_by, $approved_by, $approved_at)) {
        throw new Exception('Insert bind failed: ' . $insertStmt->error);
    }
    
    if (!$insertStmt->execute()) {
        error_log('request_supervisor insert error: ' . $insertStmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to assign supervisor: ' . $insertStmt->error]);
        exit;
    }
    $insertStmt->close();

    echo json_encode([
        'success' => true,
        'message' => $status === 'approved' ? 'Supervisor assigned successfully' : 'Supervisor requested successfully'
    ]);

} catch (Exception $e) {
    error_log('request_supervisor error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
