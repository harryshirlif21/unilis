<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/attendance_functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$lecturer_id = (int)$_SESSION['user_id'];

// GET UNIT ID FROM POST (modal) OR GET (direct link)
$unit_id = 0;
if (!empty($_POST['unit_id']) && is_numeric($_POST['unit_id'])) {
    $unit_id = (int)$_POST['unit_id'];
} elseif (!empty($_GET['unit']) && is_numeric($_GET['unit'])) {
    $unit_id = (int)$_GET['unit'];
}

if ($unit_id <= 0) {
    die("<h3 style='color:red;text-align:center;margin-top:100px;'>Invalid unit selected.</h3>");
}

// Verify lecturer teaches this unit
$stmt = $conn->prepare("
    SELECT u.name FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE u.id = ? AND lu.lecturer_id = ?
");
$stmt->bind_param("ii", $unit_id, $lecturer_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    die("<h3 style='color:red;text-align:center;margin-top:100px;'>You are not assigned to this unit.</h3>");
}

$unit_name = htmlspecialchars($res->fetch_assoc()['name']);
$stmt->close();

// === PROCESS FORM SUBMISSION ===
$success = false;
$code = '';
$deadline = '';
$session_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $duration    = max(1, min(120, (int)($_POST['duration'] ?? 10)));
    $send_email  = !empty($_POST['send_email']);

    $result = createAttendanceSession($unit_id, $lecturer_id, $duration, $send_email);

    if ($result && isset($result['session_id'])) {
        $success     = true;
        $session_id  = $result['session_id'];
        $code        = $result['code'];
        $deadline    = $result['deadline'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance • UNILIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f59e0b, #f97316); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .card { background: rgba(255,255,255,0.95); border-radius: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3); max-width: 500px; }
        .code-display { font-size: 5.5rem; font-weight: 900; letter-spacing: 0.3em; color: #f59e0b; text-shadow: 0 4px 10px rgba(245,158,11,0.4); }
        @media (max-width: 576px) { .code-display { font-size: 4rem; } }
    </style>
</head>
<body>

<div class="container">
    <div class="card p-5 text-center">

        <?php if ($success): ?>
            <h1 class="display-5 fw-bold mb-3">Attendance Started!</h1>
            <h4 class="text-muted mb-4"><?= $unit_name ?></h4>

            <div class="code-display my-5">
                <?= htmlspecialchars($code) ?>
            </div>

            <p class="fs-3 text-white fw-bold">Active Now</p>
            <p class="fs-4 text-white">
                Valid until <strong><?= date('h:i A', strtotime($deadline)) ?></strong>
            </p>

            <div class="mt-5">
                <a href="lecturer_attendance_report.php?unit=<?= $unit_id ?>" 
                   class="btn btn-light btn-lg px-5 shadow">View Live Report</a>
                <button onclick="location.reload()" 
                        class="btn btn-outline-light btn-lg px-5 ms-3">Generate New Code</button>
            </div>

            <p class="mt-4 text-white">
                <strong>All students have been notified!</strong>
            </p>

        <?php else: ?>
            <!-- Form shown only if not submitted -->
            <form method="POST" class="text-start">
                <h3 class="text-center mb-4">Start Attendance</h3>
                <p class="text-center text-muted mb-4"><?= $unit_name ?></p>

                <div class="mb-3">
                    <label class="form-label">Duration (minutes)</label>
                    <select name="duration" class="form-select form-select-lg" required>
                        <option value="5">5 minutes</option>
                        <option value="10" selected>10 minutes</option>
                        <option value="15">15 minutes</option>
                        <option value="30">30 minutes</option>
                        <option value="60">60 minutes</option>
                    </select>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" name="send_email" class="form-check-input" id="email">
                    <label class="form-check-label" for="email">Send code via email</label>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-success btn-lg px-5">
                        Generate 6-Digit Code
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

</body>
</html>

<?php ob_end_flush(); ?>