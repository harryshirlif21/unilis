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

$displayName = $_SESSION['user_name'] ?? 'Student';
$backUrl = getMeetingAppBaseUrl() . '/student/dashboard.php';

// Build URLs for both UIs
$pythonUrl = buildMeetingPythonUiUrl('student', $meeting, $user_id, $displayName, $backUrl);
$frontendUrl = buildMeetingFrontendUrl('student', $meeting, $user_id, $displayName, $backUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Meeting - UNILIS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Google Sans', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #34a853 0%, #1a73e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 40px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            text-align: center;
        }
        .card h1 { font-size: 24px; color: #202124; margin-bottom: 8px; }
        .card .subtitle { color: #5f6368; font-size: 14px; margin-bottom: 24px; }
        .meeting-info {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }
        .meeting-info .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }
        .meeting-info .row .label { color: #5f6368; }
        .meeting-info .row .value { font-weight: 600; color: #202124; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 24px;
            border-radius: 50px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 12px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-primary { background: #1a73e8; color: #ffffff; }
        .btn-secondary { background: #ffffff; color: #1a73e8; border: 2px solid #1a73e8; }
        .btn-ghost { background: transparent; color: #5f6368; font-size: 14px; }
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 0;
            color: #9aa0a6;
            font-size: 13px;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e8eaed; }
        .badge {
            display: inline-block;
            background: #e8f0fe;
            color: #1a73e8;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 50px;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🎥 Join Meeting</h1>
        <p class="subtitle">Enter the UNILIS video conferencing room</p>

        <div class="meeting-info">
            <div class="row"><span class="label">Meeting</span><span class="value"><?= htmlspecialchars($meeting['title']) ?></span></div>
            <div class="row"><span class="label">Unit</span><span class="value"><?= htmlspecialchars($meeting['unit_name']) ?></span></div>
            <div class="row"><span class="label">Lecturer</span><span class="value"><?= htmlspecialchars($meeting['lecturer_name'] ?? 'N/A') ?></span></div>
            <div class="row"><span class="label">Role</span><span class="value">Student</span></div>
            <div class="row"><span class="label">Duration</span><span class="value"><?= (int)$meeting['duration'] ?> min</span></div>
        </div>

        <!-- Primary: New Google Meet-style UI -->
        <a class="btn btn-primary" href="<?= htmlspecialchars($frontendUrl) ?>" target="_blank">
            🚀 Join with UNILIS Meeting UI
        </a>

        <div class="divider">OR</div>

        <!-- Fallback: Legacy Python UI -->
        <a class="btn btn-secondary" href="<?= htmlspecialchars($pythonUrl) ?>" target="_blank">
            🔄 Join with Legacy UI
        </a>

        <a class="btn btn-ghost" href="<?= htmlspecialchars($backUrl) ?>">
            ← Back to Dashboard
        </a>
    </div>
</body>
</html>