<?php
session_start();
require_once '../config/db.php';

/* AUTHENTICATION */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}
require_once 'attendance_functions.php';

$lecturer_id = $_SESSION['user_id'];
$unit_id = (int)($_GET['unit'] ?? $_POST['unit_id'] ?? 0);

if (!$unit_id) {
    die("Invalid unit");
}

if ($_POST) {
    $duration = (int)$_POST['duration'];
    $send_email = isset($_POST['send_email']);
    $result = createAttendanceSession($unit_id, $lecturer_id, $duration, $send_email);

    if ($result) {
        // Show success with big code
        echo "<!DOCTYPE html><html><head><title>Attendance Code</title>
              <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
              </head><body class='bg-light'>
              <div class='container py-5 text-center'>
                <div class='alert alert-success display-1 fw-bold text-success'>
                  " . $result['code'] . "
                </div>
                <h4>Code sent to all students!</h4>
                <p class='text-muted'>Valid until " . date('h:i A', strtotime($result['deadline'])) . "</p>
                <a href='lecturer_attendance_report.php?unit=$unit_id' class='btn btn-primary btn-lg'>
                  View Attendance Report
                </a>
              </div></body></html>";
        exit;
    }
}
?>