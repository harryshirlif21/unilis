<?php
/**
 * GET /teams/api/get_team_supervisors.php
 * 
 * Returns all supervisors for a team with their status
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'student'])) {
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
    $teamStmt = $conn->prepare("SELECT unit_id, course_id FROM teams WHERE id = ?");
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

    // Get all supervisors for this team
    $stmt = $conn->prepare("
        SELECT 
            ts.id,
            ts.lecturer_id,
            ts.supervisor_type,
            ts.is_primary,
            ts.status,
            ts.requested_at,
            ts.approved_at,
            CASE 
                WHEN ts.supervisor_type = 'lecturer' THEN l.name
                WHEN ts.supervisor_type = 'technician' THEN t.name
                WHEN ts.supervisor_type = 'admin' THEN a.name
            END as name,
            CASE 
                WHEN ts.supervisor_type = 'lecturer' THEN l.email
                WHEN ts.supervisor_type = 'technician' THEN t.email
                WHEN ts.supervisor_type = 'admin' THEN a.email
            END as email,
            CASE 
                WHEN ts.supervisor_type = 'lecturer' THEN l.department_id
                WHEN ts.supervisor_type = 'technician' THEN t.department_id
                WHEN ts.supervisor_type = 'admin' THEN a.department_id
            END as department_id
        FROM team_supervisors ts
        LEFT JOIN lecturers l ON ts.lecturer_id = l.id AND ts.supervisor_type = 'lecturer'
        LEFT JOIN technicians t ON ts.lecturer_id = t.id AND ts.supervisor_type = 'technician'
        LEFT JOIN admins a ON ts.lecturer_id = a.id AND ts.supervisor_type = 'admin'
        WHERE ts.team_id = ?
        ORDER BY ts.is_primary DESC, ts.requested_at ASC
    ");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Determine the unit lecturer for global supervisor fallback
    $unitLecturerStmt = $conn->prepare(
        "SELECT l.id, l.name, l.email
         FROM lecturer_units lu
         JOIN lecturers l ON lu.lecturer_id = l.id
         WHERE lu.unit_id = ?
         LIMIT 1"
    );
    $unitLecturerStmt->bind_param("i", $team['unit_id']);
    $unitLecturerStmt->execute();
    $unitLecturer = $unitLecturerStmt->get_result()->fetch_assoc();
    $unitLecturerStmt->close();
    $unitLecturerId = $unitLecturer['id'] ?? 0;

    $supervisors = [];
    $hasPrimary = false;
    $unitLecturerIndex = null;
    while ($row = $result->fetch_assoc()) {
        $isPrimary = (bool)$row['is_primary'];
        if ($isPrimary) {
            $hasPrimary = true;
        }

        $supervisors[] = [
            'id' => (int)$row['id'],
            'lecturer_id' => (int)$row['lecturer_id'],
            'supervisor_type' => $row['supervisor_type'],
            'name' => $row['name'],
            'email' => $row['email'],
            'is_primary' => $isPrimary,
            'status' => $row['status'],
            'requested_at' => $row['requested_at'],
            'approved_at' => $row['approved_at']
        ];

        if ($row['supervisor_type'] === 'lecturer' && (int)$row['lecturer_id'] === (int)$unitLecturerId) {
            $unitLecturerIndex = count($supervisors) - 1;
        }
    }
    $stmt->close();

    if (!$hasPrimary && $unitLecturerIndex !== null) {
        $supervisors[$unitLecturerIndex]['is_primary'] = true;
        $unitLecturer = $supervisors[$unitLecturerIndex];
        array_splice($supervisors, $unitLecturerIndex, 1);
        array_unshift($supervisors, $unitLecturer);
    } elseif (!$hasPrimary && $unitLecturerId > 0) {
        array_unshift($supervisors, [
            'id' => 0,
            'lecturer_id' => (int)$unitLecturer['id'],
            'supervisor_type' => 'lecturer',
            'name' => $unitLecturer['name'],
            'email' => $unitLecturer['email'],
            'is_primary' => true,
            'status' => 'approved',
            'requested_at' => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s')
        ]);
    }

    echo json_encode([
        'success' => true,
        'supervisors' => $supervisors
    ]);

} catch (Exception $e) {
    error_log('get_team_supervisors error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
