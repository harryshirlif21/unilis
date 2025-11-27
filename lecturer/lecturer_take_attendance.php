<?php
session_start();
require_once '../config/db.php';
require_once '/attendance_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Get unit_id safely
$unit_id = 0;
if (!empty($_POST['unit_id']) && is_numeric($_POST['unit_id'])) {
    $unit_id = (int)$_POST['unit_id'];
} elseif (!empty($_GET['unit']) && is_numeric($_GET['unit'])) {
    $unit_id = (int)$_GET['unit'];
}

if ($unit_id <= 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Invalid unit selected.</h3>");
}

// Verify lecturer teaches this unit
$stmt = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
$stmt->bind_param("ii", $lecturer_id, $unit_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Unauthorized access.</h3>");
}
$stmt->close();

// PROCESS FORM
$success = false;
$code = $deadline = $unit_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duration   = max(1, min(120, (int)($_POST['duration'] ?? 10)));
    $send_email = !empty($_POST['send_email']);

    $result = createAttendanceSession($unit_id, $lecturer_id, $duration, $send_email);

    if ($result && !empty($result['code'])) {
        $success   = true;
        $code      = $result['code'];
        $deadline  = $result['deadline'];

        $res = $conn->query("SELECT name FROM units WHERE id = " . (int)$unit_id);
        $unit_name = $res->fetch_assoc()['name'] ?? "Unit #$unit_id";
        $unit_name = htmlspecialchars($unit_name);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Code • UNILIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f59e0b, #f97316); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .card { background: rgba(255,255,255,0.95); border-radius: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .code-display { font-size: 6rem; font-weight: 900; letter-spacing: 0.3em; color: #f59e0b; text-shadow: 0 4px 10px rgba(245,158,11,0.3); }
        @media (max-width: 576px) { .code-display { font-size: 4rem; } }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card p-5 text-center">

                <?php if ($success): ?>
                    <h1 class="display-5 fw-bold text-dark mb-2">Attendance Started!</h1>
                    <p class="text-muted fs-5"><?= $unit_name ?></p>

                    <div class="code-display my-5">
                        <?= $code ?>
                    </div>

                    <p class="fs-3 text-success fw-bold">Active Now</p>
                    <p class="fs-4 text-muted">
                        Valid until <strong><?= date('h:i A', strtotime($deadline)) ?></strong><br>
                        <small><?= date('d M Y') ?></small>
                    </p>

                    <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
                        <a href="lecturer_attendance_report.php?unit=<?= $unit_id ?>"
                           class="btn btn-primary btn-lg px-5 shadow">View Live Report</a>
                        <button onclick="window.location.reload()"
                                class="btn btn-outline-warning btn-lg px-5">New Code</button>
                    </div>

                    <div class="mt-4 text-success">
                        <strong>All students notified instantly!</strong>
                    </div>

                <?php else: ?>
                    <div class="alert alert-danger">
                        <h4>Failed to start attendance</h4>
                        <p>Please try again.</p>
                        <a href="javascript:history.back()" class="btn btn-light">Go Back</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
</body>
</html>

<?php ob_end_flush(); ?>