<?php
// teams/api/supervisors.php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

switch ($action) {
    case 'get_available_supervisors':
        get_available_supervisors($conn, $userId, $userRole);
        break;
    case 'request_supervisor':
        request_supervisor($conn, $userId, $userRole);
        break;
        case 'assign_supervisor':
        assign_supervisor($conn, $userId, $userRole);
        break;
    case 'review_supervisor_request':
        review_supervisor_request($conn, $userId, $userRole);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function review_supervisor_request($conn, $userId, $userRole)
{
    if ($userRole !== 'lecturer') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only lecturers can review supervisor requests.']);
        return;
    }

    $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    if ($requestId === 0 || !in_array($status, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Request ID and a valid status (approved/rejected) are required.']);
        return;
    }

    try {
        // 1. Get team_id from the request
        $stmt = $conn->prepare("SELECT team_id FROM team_supervisors WHERE id = ?");
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Supervisor request not found.']);
            return;
        }

        // 2. Get unit_id from team_id
        $stmt = $conn->prepare("SELECT unit_id FROM teams WHERE id = ?");
        $stmt->bind_param('i', $request['team_id']);
        $stmt->execute();
        $team = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$team) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Team not found.']);
            return;
        }

        // 3. Check if the lecturer is the global admin for the unit
        $stmt = $conn->prepare("SELECT 1 FROM lecturer_units WHERE unit_id = ? AND lecturer_id = ?");
        $stmt->bind_param('ii', $team['unit_id'], $userId);
        $stmt->execute();
        $isGlobalAdmin = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$isGlobalAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not authorized to review supervisor requests for this team.']);
            return;
        }

        // 4. Update the request
        $stmt = $conn->prepare("UPDATE team_supervisors SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->bind_param('sii', $status, $userId, $requestId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => "Supervisor request has been {$status}."]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}


function assign_supervisor($conn, $userId, $userRole)
{
    if ($userRole !== 'lecturer') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only lecturers can assign a supervisor.']);
        return;
    }

    $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    $supervisorId = isset($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : 0;
    $supervisorType = isset($_POST['supervisor_type']) ? $_POST['supervisor_type'] : '';

    if ($teamId === 0 || $supervisorId === 0 || !in_array($supervisorType, ['lecturer', 'admin', 'technician'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Team ID, Supervisor ID, and a valid Supervisor Type are required.']);
        return;
    }

    try {
        // 1. Get unit_id from team_id
        $stmt = $conn->prepare("SELECT unit_id FROM teams WHERE id = ?");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $team = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$team) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Team not found']);
            return;
        }

        // 2. Check if the lecturer is the global admin for the unit
        $stmt = $conn->prepare("SELECT 1 FROM lecturer_units WHERE unit_id = ? AND lecturer_id = ?");
        $stmt->bind_param('ii', $team['unit_id'], $userId);
        $stmt->execute();
        $isGlobalAdmin = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$isGlobalAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not authorized to assign supervisors for this team.']);
            return;
        }

        // 3. Check if a secondary supervisor is already assigned
        $stmt = $conn->prepare("SELECT 1 FROM team_supervisors WHERE team_id = ? AND is_primary = 0");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $hasSecondary = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($hasSecondary) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A secondary supervisor has already been assigned to this team.']);
            return;
        }

        // 4. Insert the assignment
        $stmt = $conn->prepare("INSERT INTO team_supervisors (team_id, lecturer_id, supervisor_type, is_primary, status, approved_by) VALUES (?, ?, ?, 0, 'approved', ?)");
        $stmt->bind_param('iisi', $teamId, $supervisorId, $supervisorType, $userId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Supervisor assigned successfully.']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function request_supervisor($conn, $userId, $userRole)
{
    if ($userRole !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only students can request a supervisor.']);
        return;
    }

    $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    $lecturerId = isset($_POST['lecturer_id']) ? (int)$_POST['lecturer_id'] : 0;

    if ($teamId === 0 || $lecturerId === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Team ID and Lecturer ID are required.']);
        return;
    }

    try {
        // 1. Verify student is a member of the team
        $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->bind_param('ii', $teamId, $userId);
        $stmt->execute();
        $isMember = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$isMember) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not a member of this team.']);
            return;
        }

        // 2. Verify lecturer and student are in the same department
        $stmt = $conn->prepare("SELECT department_id FROM students WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $student_dept = $stmt->get_result()->fetch_assoc()['department_id'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $stmt->bind_param('i', $lecturerId);
        $stmt->execute();
        $lecturer_dept = $stmt->get_result()->fetch_assoc()['department_id'];
        $stmt->close();

        if ($student_dept !== $lecturer_dept) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'The selected lecturer is not from your department.']);
            return;
        }

        // 3. Check if a primary supervisor is already requested/assigned
        $stmt = $conn->prepare("SELECT 1 FROM team_supervisors WHERE team_id = ? AND is_primary = 1");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $hasPrimary = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($hasPrimary) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A primary supervisor has already been requested or assigned to this team.']);
            return;
        }

        // 4. Insert the request
        $stmt = $conn->prepare("INSERT INTO team_supervisors (team_id, lecturer_id, supervisor_type, is_primary, status, requested_by) VALUES (?, ?, 'lecturer', 1, 'pending', ?)");
        $stmt->bind_param('iii', $teamId, $lecturerId, $userId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Supervisor request sent successfully.']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function get_available_supervisors($conn, $userId, $userRole)
{
    $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
    if ($teamId === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Team ID is required']);
        return;
    }

    try {
        $supervisors = [];

        if ($userRole === 'student') {
            // Logic for student to find first supervisor
            $stmt = $conn->prepare("SELECT department_id FROM students WHERE id = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($student) {
                $departmentId = $student['department_id'];
                $stmt = $conn->prepare("SELECT id, name, email, 'lecturer' as type FROM lecturers WHERE department_id = ?");
                $stmt->bind_param('i', $departmentId);
                $stmt->execute();
                $supervisors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        } elseif ($userRole === 'lecturer') {
            // Logic for global admin to find second supervisor
            // 1. Get unit_id from team_id
            $stmt = $conn->prepare("SELECT unit_id FROM teams WHERE id = ?");
            $stmt->bind_param('i', $teamId);
            $stmt->execute();
            $team = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$team) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Team not found']);
                return;
            }

            // 2. Check if the lecturer is the global admin for the unit
            $stmt = $conn->prepare("SELECT 1 FROM lecturer_units WHERE unit_id = ? AND lecturer_id = ?");
            $stmt->bind_param('ii', $team['unit_id'], $userId);
            $stmt->execute();
            $isGlobalAdmin = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($isGlobalAdmin) {
                // 3. Fetch all lecturers
                $lecturers_query = "SELECT id, name, email, 'lecturer' as type FROM lecturers";
                $lecturers_result = $conn->query($lecturers_query);
                $lecturers = $lecturers_result->fetch_all(MYSQLI_ASSOC);

                // 4. Fetch all department admins
                $admins_query = "SELECT a.id, a.name, a.email, 'admin' as type FROM admins a JOIN department_admins da ON a.id = da.admin_id";
                $admins_result = $conn->query($admins_query);
                $admins = $admins_result->fetch_all(MYSQLI_ASSOC);

                // 5. Fetch all technicians
                $technicians_query = "SELECT id, name, email, 'technician' as type FROM technicians";
                $technicians_result = $conn->query($technicians_query);
                $technicians = $technicians_result->fetch_all(MYSQLI_ASSOC);

                $supervisors = array_merge($lecturers, $admins, $technicians);
            }
        }

        echo json_encode(['success' => true, 'supervisors' => $supervisors]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
