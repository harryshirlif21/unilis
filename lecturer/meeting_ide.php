<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header('Location: ../login.php');
    exit;
}

$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if ($meeting_id <= 0) {
    die('Meeting ID required');
}

header('Location: meeting_host.php?meeting_id=' . $meeting_id);
exit;
