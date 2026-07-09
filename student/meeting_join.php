<?php
session_start();
require_once '../config/db.php';
require_once '../config/meeting.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$meeting_id = (int)($_GET['meeting_id'] ?? 0);
$enrollmentTable = getStudentEnrollmentTable($conn);

if ($meeting_id <= 0) {
    die('Meeting ID is required');
}

if ($enrollmentTable === null) {
    die('Student enrollment table is not available.');
}

$stmt = $conn->prepare(
    'SELECT m.*, u.name AS unit_name, l.name AS lecturer_name
     FROM meetings m
     JOIN units u ON m.unit_id = u.id
     JOIN lecturers l ON m.lecturer_id = l.id
     JOIN ' . $enrollmentTable . ' sue ON sue.unit_id = u.id
     WHERE m.id = ? AND sue.student_id = ?'
);
$stmt->bind_param('ii', $meeting_id, $user_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die('Meeting not found or you are not enrolled in this unit');
}

$pythonUrl = buildMeetingPythonUiUrl(
    'student',
    $meeting,
    $user_id,
    $_SESSION['user_name'] ?? 'Student',
    getMeetingAppBaseUrl() . '/student/dashboard.php'
);

header('Location: ' . $pythonUrl);
exit;
