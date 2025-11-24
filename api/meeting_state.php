<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

$response = ['success' => false, 'data' => null, 'error' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? $input['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('No action specified');
    }
    
    $user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? 0;
    $meeting_id = $input['meeting_id'] ?? $_GET['meeting_id'] ?? 0;
    
    if (!$user_id || !$meeting_id) {
        throw new Exception('Invalid user or meeting ID');
    }
    
    switch ($action) {
        case 'start_meeting':
            $response = startMeeting($user_id, $meeting_id);
            break;
            
        case 'end_meeting':
            $response = endMeeting($user_id, $meeting_id);
            break;
            
        case 'join_meeting':
            $response = joinMeeting($user_id, $meeting_id, $input);
            break;
            
        case 'leave_meeting':
            $response = leaveMeeting($user_id, $meeting_id);
            break;
            
        case 'list_participants':
            $response = listParticipants($user_id, $meeting_id);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Meeting state API error: " . $e->getMessage());
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);

function startMeeting($user_id, $meeting_id) {
    // Validate lecturer access
    if (!validateUserMeetingAccess($user_id, $meeting_id, 'lecturer')) {
        throw new Exception('Unauthorized to start meeting');
    }
    
    $sql = "UPDATE meetings SET meeting_status = 'active', started_at = NOW() WHERE id = ?";
    $result = executeQuery($sql, [$meeting_id], "i");
    
    if ($result) {
        // Record attendance for lecturer
        recordAttendance($user_id, $meeting_id, 'joined');
    }
    
    return ['success' => (bool)$result];
}

function endMeeting($user_id, $meeting_id) {
    // Validate lecturer access
    if (!validateUserMeetingAccess($user_id, $meeting_id, 'lecturer')) {
        throw new Exception('Unauthorized to end meeting');
    }
    
    $sql = "UPDATE meetings SET meeting_status = 'ended', ended_at = NOW() WHERE id = ?";
    $result = executeQuery($sql, [$meeting_id], "i");
    
    // Mark all participants as left
    $sql = "UPDATE meeting_attendance SET left_at = NOW() 
            WHERE meeting_id = ? AND left_at IS NULL";
    executeQuery($sql, [$meeting_id], "i");
    
    return ['success' => (bool)$result];
}

function joinMeeting($user_id, $meeting_id, $data) {
    // Validate user has access to this meeting
    $user_role = $_SESSION['role'] ?? $data['role'] ?? '';
    
    if ($user_role === 'lecturer') {
        if (!validateUserMeetingAccess($user_id, $meeting_id, 'lecturer')) {
            throw new Exception('Unauthorized to join meeting as lecturer');
        }
    } else {
        if (!validateUserMeetingAccess($user_id, $meeting_id, 'student')) {
            throw new Exception('Unauthorized to join meeting as student');
        }
    }
    
    // Record attendance
    recordAttendance($user_id, $meeting_id, 'joined');
    
    return ['success' => true];
}

function leaveMeeting($user_id, $meeting_id) {
    recordAttendance($user_id, $meeting_id, 'left');
    return ['success' => true];
}

function listParticipants($user_id, $meeting_id) {
    // Validate user has access to this meeting
    if (!validateUserMeetingAccess($user_id, $meeting_id, 'lecturer') && 
        !validateUserMeetingAccess($user_id, $meeting_id, 'student')) {
        throw new Exception('Unauthorized to view participants');
    }
    
    $sql = "SELECT u.id, u.name, u.role, u.email, 
                   ma.joined_at, ma.left_at,
                   CASE WHEN ma.left_at IS NULL THEN 'online' ELSE 'offline' END as status
            FROM meeting_attendance ma
            JOIN users u ON u.id = ma.user_id
            WHERE ma.meeting_id = ?
            ORDER BY ma.joined_at DESC";
    
    $participants = executeQuery($sql, [$meeting_id], "i");
    
    return ['success' => true, 'participants' => $participants ?: []];
}

function recordAttendance($user_id, $meeting_id, $action) {
    if ($action === 'joined') {
        // Check if already joined
        $sql = "SELECT id FROM meeting_attendance 
                WHERE meeting_id = ? AND user_id = ? AND left_at IS NULL";
        $existing = executeQuery($sql, [$meeting_id, $user_id], "ii");
        
        if (empty($existing)) {
            $sql = "INSERT INTO meeting_attendance (meeting_id, user_id, joined_at) 
                    VALUES (?, ?, NOW())";
            executeQuery($sql, [$meeting_id, $user_id], "ii");
        }
    } else if ($action === 'left') {
        $sql = "UPDATE meeting_attendance SET left_at = NOW() 
                WHERE meeting_id = ? AND user_id = ? AND left_at IS NULL";
        executeQuery($sql, [$meeting_id, $user_id], "ii");
    }
}
?>