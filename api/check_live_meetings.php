<?php
/**
 * API Endpoint: Check for live meetings for a student
 * Used by student dashboard polling to auto-join live meetings
 */
require_once '../config/db.php';
require_once __DIR__ . '/../config/meeting.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

$response = ['success' => false, 'live_meetings' => [], 'just_went_live' => null];

try {
    // Validate student session
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        throw new Exception('Unauthorized');
    }

    $student_id = (int)$_SESSION['user_id'];
    $last_check = isset($_GET['last_check']) ? $_GET['last_check'] : null;

    $now = date('Y-m-d H:i:s');

    // Find meetings that are now live for this student's enrolled units
    $sql = "
        SELECT m.id, m.title, m.scheduled_time, m.duration, m.meeting_status, 
               u.name AS unit_name, l.name AS lecturer_name,
               m.meeting_link,
               m.started_at
        FROM meetings m
        JOIN units u ON m.unit_id = u.id
        JOIN student_unit_enrollments sue ON sue.unit_id = u.id AND sue.student_id = ?
        LEFT JOIN lecturers l ON m.lecturer_id = l.id
        WHERE COALESCE(m.ended, 0) = 0
          AND (
              m.meeting_status = 'active'
              OR (
                  (m.meeting_status IS NULL OR m.meeting_status = '' OR m.meeting_status = 'scheduled')
                  AND NOW() >= m.scheduled_time 
                  AND NOW() <= DATE_ADD(m.scheduled_time, INTERVAL m.duration MINUTE)
              )
          )
        ORDER BY m.scheduled_time ASC
        LIMIT 10
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $liveMeetings = [];
    while ($row = $result->fetch_assoc()) {
        $row['join_url'] = getMeetingStudentJoinUrl((int)$row['id']);
        $row['is_live'] = true;
        $liveMeetings[] = $row;
    }
    $stmt->close();

    $response['success'] = true;
    $response['live_meetings'] = $liveMeetings;
    $response['count'] = count($liveMeetings);

    // Determine if any meeting just went live (for auto-join notification)
    if (count($liveMeetings) > 0) {
        // Return the most recent one that went live
        $response['just_went_live'] = $liveMeetings[0];
    }

} catch (Exception $e) {
    error_log('check_live_meetings error: ' . $e->getMessage());
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);