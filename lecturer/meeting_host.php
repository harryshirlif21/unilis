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
     LEFT JOIN units u ON m.unit_id = u.id
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

$displayName = $meeting['lecturer_name'] ?? ($_SESSION['user_name'] ?? 'Lecturer');
$backUrl = getMeetingAppBaseUrl() . '/lecturer/meetings.php';

// Build URLs for both UIs
$pythonUrl = buildMeetingPythonUiUrl('lecturer', $meeting, $lecturer_id, $displayName, $backUrl);
$frontendUrl = buildMeetingFrontendUrl('lecturer', $meeting, $lecturer_id, $displayName, $backUrl);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .card h1 {
            font-size: 24px;
            color: #202124;
            margin-bottom: 8px;
        }
        .card .subtitle {
            color: #5f6368;
            font-size: 14px;
            margin-bottom: 24px;
        }
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
        .meeting-info .row .label {
            color: #5f6368;
        }
        .meeting-info .row .value {
            font-weight: 600;
            color: #202124;
        }
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
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: #1a73e8;
            color: #ffffff;
        }
        .btn-secondary {
            background: #ffffff;
            color: #1a73e8;
            border: 2px solid #1a73e8;
        }
        .btn-ghost {
            background: transparent;
            color: #5f6368;
            font-size: 14px;
        }
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 0;
            color: #9aa0a6;
            font-size: 13px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e8eaed;
        }
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
        .loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <h1>🎥 Join Meeting</h1>
        <p class="subtitle">Launch the UNILIS Google Meet-style conferencing interface</p>

        <div class="meeting-info">
            <div class="row"><span class="label">Meeting</span><span class="value"><?= htmlspecialchars($meeting['title']) ?></span></div>
            <div class="row"><span class="label">Unit</span><span class="value"><?= htmlspecialchars($meeting['unit_name']) ?></span></div>
            <div class="row"><span class="label">Role</span><span class="value">Lecturer <span class="badge">Host</span></span></div>
            <div class="row"><span class="label">Duration</span><span class="value"><?= (int)$meeting['duration'] ?> min</span></div>
        </div>

        <!-- Primary: Launch the new Google Meet-style UI (served directly by Apache) -->
        <a class="btn btn-primary" href="<?= htmlspecialchars($frontendUrl) ?>" target="_blank">
            🚀 Launch UNILIS Meeting UI
        </a>

        <div class="divider">OR</div>

        <!-- Secondary: Legacy Python UI -->
        <a class="btn btn-secondary" href="<?= htmlspecialchars($pythonUrl) ?>" target="_blank">
            🔄 Launch Legacy Python UI
        </a>

        <a class="btn btn-ghost" href="<?= htmlspecialchars($backUrl) ?>">
            ← Back to Meetings
        </a>

        <p style="font-size: 12px; color: #9aa0a6; margin-top: 16px;">
            💡 The new UI supports WebRTC peer-to-peer video, chat, polls, whiteboard, screen sharing, and more.
            <br>Keyboard shortcuts: <kbd>M</kbd> mic · <kbd>V</kbd> camera · <kbd>C</kbd> chat · <kbd>D</kbd> sidebar
        </p>
    </div>
</body>
</html>
