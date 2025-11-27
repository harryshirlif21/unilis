<?php
session_start();
require_once 'attendance_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $session_id = (int)$_GET['session'];

    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = "Please enter a valid 6-digit code";
    } else {
        $result = submitAttendance($session_id, $student_id, $code);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mark Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <?php if ($message): ?>
                <div class="alert alert-success text-center">
                    <h4>Success!</h4>
                    <p><?= $message ?></p>
                    <a href="student_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>Mark Your Attendance</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4 text-center">
                                <label class="form-label fs-3">Enter 6-Digit Code</label>
                                <input type="text" name="code" class="form-control form-control-lg text-center"
                                       maxlength="6" pattern="\d{6}" inputmode="numeric"
                                       style="letter-spacing: 8px; font-size: 2rem;" required autofocus>
                            </div>
                            <button class="btn btn-success w-100 btn-lg">Submit Attendance</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small class="text-muted">Ask your lecturer for the code</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>