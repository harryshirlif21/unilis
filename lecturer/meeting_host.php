<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../config/meeting.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$meeting_id = (int)($_GET['meeting_id'] ?? 0);

if ($meeting_id <= 0) {
    die('Meeting ID is required');
}

$stmt = $conn->prepare(
    'SELECT m.*, u.name AS unit_name, l.name AS lecturer_name
     FROM meetings m
     JOIN units u ON m.unit_id = u.id
     JOIN lecturers l ON m.lecturer_id = l.id
     WHERE m.id = ? AND m.lecturer_id = ?'
);
$stmt->bind_param('ii', $meeting_id, $lecturer_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die('Meeting not found or access denied');
}

$pythonUrl = buildMeetingPythonUiUrl(
    'lecturer',
    $meeting,
    $lecturer_id,
    $meeting['lecturer_name'] ?? ($_SESSION['user_name'] ?? 'Lecturer'),
    getMeetingAppBaseUrl() . '/lecturer/meetings.php'
);

header('Location: ' . $pythonUrl);
exit;
