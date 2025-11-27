<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') header('Location: login.php');
require_once 'attendance_functions.php';

$lecturer_id = $_SESSION['user_id'];
$unit_id = (int)$_GET['unit'];

if ($_POST) {
    $duration = (int)$_POST['duration'];
    $send_email = isset($_POST['send_email']);
    $result = createAttendanceSession($unit_id, $lecturer_id, $duration, $send_email);
}
?>
<!DOCTYPE html>
<html>
<head><title>Take Attendance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php if (isset($result)): ?>
                <div class="alert alert-success text-center fs-1">
                    <strong><?= $result['code'] ?></strong><br>
                    <small>Valid until <?= date('h:i A', strtotime($result['deadline'])) ?></small>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Start Attendance Session</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>Duration</label>
                            <select name="duration" class="form-select" required>
                                <option value="5">5 minutes</option>
                                <option value="10" selected>10 minutes</option>
                                <option value="15">15 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="60">60 minutes</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="send_email" class="form-check-input" id="email">
                            <label for="email">Send code via email</label>
                        </div>
                        <button class="btn btn-primary w-100">Generate Code</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>