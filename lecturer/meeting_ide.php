<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) {
    die("Meeting ID is required.");
}

// Fetch meeting details
$stmt = $conn->prepare("SELECT title, scheduled_time, duration FROM meetings WHERE id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();
$stmt->close();

if (!$meeting) {
    die("Meeting not found.");
}

$roomName = "unilis_meeting_" . $meeting_id;
$userName = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($meeting['title']) ?> - Meeting</title>
    <script src="https://meet.jit.si/external_api.js"></script>
    <style>
        html, body, #jitsi-container {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #000;
        }
    </style>
</head>
<body>
    <div id="jitsi-container"></div>

    <script>
        const domain = "meet.jit.si";
        const options = {
            roomName: "<?= $roomName ?>",
            width: "100%",
            height: "100%",
            parentNode: document.getElementById('jitsi-container'),
            userInfo: {
                displayName: "<?= htmlspecialchars($userName) ?>"
            },
            configOverwrite: {
                prejoinPageEnabled: false
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: true,
                SHOW_WATERMARK_FOR_GUESTS: false,
                DEFAULT_REMOTE_DISPLAY_NAME: 'Participant',
                TOOLBAR_BUTTONS: [
                    'microphone', 'camera', 'desktop', 'fullscreen',
                    'fodeviceselection', 'hangup', 'chat', 'recording',
                    'settings', 'raisehand', 'videoquality', 'tileview'
                ]
            }
        };

        const api = new JitsiMeetExternalAPI(domain, options);

        // Auto-log lecturer attendance
        api.addEventListener('videoConferenceJoined', () => {
            fetch('../actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=log_attendance&meeting_id=<?= $meeting_id ?>'
            });
        });

        // Optional: Auto-end meeting after duration (in milliseconds)
        const durationMinutes = <?= $meeting['duration'] ?>;
        const autoEndTime = durationMinutes * 60 * 1000;
        setTimeout(() => {
            alert("Meeting time is over. You will be disconnected.");
            api.executeCommand('hangup');
        }, autoEndTime);
    </script>
</body>
</html>
