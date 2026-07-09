<?php
session_start();

$meetingId = (int)($_GET['meeting_id'] ?? 0);
if ($meetingId <= 0) {
    die('Meeting ID is required');
}

if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] === 'lecturer') {
    header('Location: lecturer/meeting_host.php?meeting_id=' . $meetingId);
    exit;
}

if ($_SESSION['user_role'] === 'student') {
    header('Location: student/meeting_join.php?meeting_id=' . $meetingId);
    exit;
}

die('Unsupported meeting role.');