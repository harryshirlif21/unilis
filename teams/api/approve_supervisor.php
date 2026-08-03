<?php
/**
 * POST /teams/api/approve_supervisor.php
 * 
 * Approves or rejects a supervisor request (lecturer only)
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - lecturers, admins, technicians, and approved supervisors can manage approvals
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'technician'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$supervisor_id = (int)($input['supervisor_id'] ?? 0);
$approved = filter_var($input['approved'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($supervisor_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid supervisor ID']);
    exit;
}

try {
    // Get supervisor assignment
    $stmt = $conn->prepare("SELECT ts.*, t.unit_id FROM team_supervisors ts JOIN teams t ON ts.team_id = t.id WHERE ts.id = ?");
    $stmt->bind_param("i", $supervisor_id);
    $stmt->execute();
    $supervisor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$supervisor) {
        echo json_encode(['success' => false, 'message' => 'Supervisor assignment not found']);
        exit;
    }

    // Cannot approve/reject primary supervisors (unit lecturers)
    if ($supervisor['is_primary']) {
        echo json_encode(['success' => false, 'message' => 'Cannot modify primary supervisor']);
        exit;
    }

    // Allow the unit lecturer/admin or any approved supervisor of the team to manage supervisor requests.
    $canManage = false;
    if ($_SESSION['user_role'] === 'admin') {
        $canManage = true;
    } elseif ($_SESSION['user_role'] === 'lecturer') {
        $lecturerUnitStmt = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
        $lecturerUnitStmt->bind_param("ii", $_SESSION['user_id'], $supervisor['unit_id']);
        $lecturerUnitStmt->execute();
        $isUnitLecturer = $lecturerUnitStmt->get_result()->num_rows > 0;
        $lecturerUnitStmt->close();

        if ($isUnitLecturer) {
            $canManage = true;
        }
    } else {
        $supervisorStmt = $conn->prepare("SELECT 1 FROM team_supervisors WHERE team_id = ? AND lecturer_id = ? AND supervisor_type = ? AND status = 'approved' LIMIT 1");
        $supervisorStmt->bind_param("iis", $supervisor['team_id'], $_SESSION['user_id'], $_SESSION['user_role']);
        $supervisorStmt->execute();
        $canManage = $supervisorStmt->get_result()->num_rows > 0;
        $supervisorStmt->close();
    }

    if (!$canManage) {
        echo json_encode(['success' => false, 'message' => 'Only the unit lecturer, admin, or approved supervisor can manage this team supervisor']);
        exit;
    }

    // Update supervisor status
    $status = $approved ? 'approved' : 'rejected';
    $approved_by = $approved ? $_SESSION['user_id'] : null;
    $approved_at = $approved ? date('Y-m-d H:i:s') : null;

    $updateStmt = $conn->prepare("
        UPDATE team_supervisors 
        SET status = ?, approved_by = ?, approved_at = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param("sisi", $status, $approved_by, $approved_at, $supervisor_id);
    
    if (!$updateStmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update supervisor']);
        exit;
    }
    $updateStmt->close();

    echo json_encode([
        'success' => true,
        'message' => $approved ? 'Supervisor approved' : 'Supervisor rejected'
    ]);

} catch (Exception $e) {
    error_log('approve_supervisor error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
