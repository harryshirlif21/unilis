<?php
/**
 * GET /teams/api/search_supervisor.php
 * 
 * Searches for supervisors by email (lecturers and technicians)
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

function bindStmtParams(mysqli_stmt $stmt, string $types, array &$params): bool
{
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'student'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$team_id = (int)($_GET['team_id'] ?? 0);
$email = trim($_GET['email'] ?? '');

if ($team_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid team ID']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
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
    $unitStmt = $conn->prepare("
        SELECT c.department_id
        FROM units u
        LEFT JOIN courses c ON u.course_id = c.id
        WHERE u.id = ?
    ");
    $unitStmt->bind_param("i", $team['unit_id']);
    $unitStmt->execute();
    $unit = $unitStmt->get_result()->fetch_assoc();
    $unitStmt->close();

    $department_id = $unit['department_id'] ?? 0;

    if ($department_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unit has no department']);
        exit;
    }

    $adminDepartmentJoin = '';
    $adminDepartmentWhere = 'a.department_id = ?';
    if (tableColumnExists($conn, 'admins', 'department_id')) {
        $adminDepartmentJoin = '';
        $adminDepartmentWhere = 'a.department_id = ?';
    } elseif (tableExists($conn, 'department_admins')) {
        $adminDepartmentJoin = 'JOIN department_admins da ON a.id = da.admin_id AND da.is_active = 1';
        $adminDepartmentWhere = 'da.department_id = ?';
    } else {
        // In case admins are not department-linked, exclude admin search gracefully
        $adminDepartmentJoin = '';
        $adminDepartmentWhere = '1 = 0';
    }

    $hasTechnicians = tableExists($conn, 'technicians') && tableColumnExists($conn, 'technicians', 'department_id');

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

    // Search for lecturers, technicians, and department admins by email in same department
    $max_teams = 5;
    $emailPattern = '%' . $email . '%';

    // Build filter for assigned IDs
    $assigned_filter_lecturer = !empty($assigned_ids) ? "AND l.id NOT IN (" . implode(',', array_map('intval', $assigned_ids)) . ")" : "";
    $assigned_filter_admin = !empty($assigned_ids) ? "AND a.id NOT IN (" . implode(',', array_map('intval', $assigned_ids)) . ")" : "";

    $technicianUnion = '';
    if ($hasTechnicians) {
        $assigned_filter_technician = !empty($assigned_ids) ? "AND t.id NOT IN (" . implode(',', array_map('intval', $assigned_ids)) . ")" : "";
        $technicianUnion = "
            UNION ALL
            SELECT 
                t.id as person_id,
                t.name COLLATE utf8mb4_unicode_ci as name,
                t.email COLLATE utf8mb4_unicode_ci as email,
                'technician' COLLATE utf8mb4_unicode_ci as supervisor_type,
                COUNT(DISTINCT ts.team_id) as team_count
            FROM technicians t
            LEFT JOIN team_supervisors ts ON t.id = ts.lecturer_id AND ts.supervisor_type = 'technician' AND ts.status = 'approved'
            WHERE t.department_id = ?
              AND t.email COLLATE utf8mb4_unicode_ci LIKE ?
              $assigned_filter_technician
            GROUP BY t.id
        ";
    }

    $query = "
        SELECT 
            person_id as id,
            name COLLATE utf8mb4_unicode_ci as name,
            email COLLATE utf8mb4_unicode_ci as email,
            supervisor_type,
            team_count
        FROM (
            SELECT 
                l.id as person_id,
                l.name COLLATE utf8mb4_unicode_ci as name,
                l.email COLLATE utf8mb4_unicode_ci as email,
                'lecturer' COLLATE utf8mb4_unicode_ci as supervisor_type,
                COUNT(DISTINCT ts.team_id) as team_count
            FROM lecturers l
            LEFT JOIN team_supervisors ts ON l.id = ts.lecturer_id AND ts.supervisor_type = 'lecturer' AND ts.status = 'approved'
            WHERE l.department_id = ?
              AND l.email COLLATE utf8mb4_unicode_ci LIKE ?
              $assigned_filter_lecturer
            GROUP BY l.id
            $technicianUnion
            UNION ALL
            SELECT 
                a.id as person_id,
                a.name COLLATE utf8mb4_unicode_ci as name,
                a.email COLLATE utf8mb4_unicode_ci as email,
                'admin' COLLATE utf8mb4_unicode_ci as supervisor_type,
                COUNT(DISTINCT ts.team_id) as team_count
            FROM admins a
            {$adminDepartmentJoin}
            LEFT JOIN team_supervisors ts ON a.id = ts.lecturer_id AND ts.supervisor_type = 'admin' AND ts.status = 'approved'
            WHERE {$adminDepartmentWhere}
              AND a.email COLLATE utf8mb4_unicode_ci LIKE ?
              $assigned_filter_admin
            GROUP BY a.id
        ) as supervisors
        WHERE team_count < ?
        ORDER BY name ASC
    ";

    $params = [
        $department_id,
        $emailPattern,
    ];
    $types = 'is';

    if ($hasTechnicians) {
        $params[] = $department_id;
        $params[] = $emailPattern;
        $types .= 'is';
    }

    if ($adminDepartmentWhere !== '1 = 0') {
        $types .= 'i';
        $params[] = $department_id;
    }

    $types .= 'si';
    $params[] = $emailPattern;
    $params[] = $max_teams;

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    if (!bindStmtParams($stmt, $types, $params)) {
        throw new Exception('Bind failed: ' . $stmt->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'supervisor_type' => $row['supervisor_type'],
            'team_count' => (int)$row['team_count']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (Exception $e) {
    error_log('search_supervisor error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
