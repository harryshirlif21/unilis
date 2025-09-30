<?php
session_start();
require_once '../config/db.php';

use Firebase\JWT\JWT;

require '../vendor/autoload.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Meeting lookup
$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) {
    die("Meeting ID is required.");
}

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
$userRole = $_SESSION['user_role']; // "lecturer" or "student"

// JWT config (must match your Jitsi server prosody config)
$APP_ID = "your-app-id";       // set in Jitsi prosody
$APP_SECRET = "your-app-secret"; // set in Jitsi prosody
$JITSI_DOMAIN = "your.jitsi.domain"; // self-hosted Jitsi domain, not meet.jit.si

// Create JWT payload
$payload = [
    "aud" => "jitsi",
    "iss" => $APP_ID,
    "sub" => $JITSI_DOMAIN,
    "room" => $roomName,
    "exp" => time() + 3600, // 1 hour validity
    "context" => [
        "user" => [
            "name" => $userName,
            "email" => $_SESSION['email'] ?? '',
            "moderator" => ($userRole === 'lecturer') ? true : false
        ]
    ]
];

// Encode JWT
$jwt = JWT::encode($payload, $APP_SECRET, 'HS256');
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
        const domain = "<?= $JITSI_DOMAIN ?>";
        const options = {
            roomName: "<?= $roomName ?>",
            jwt: "<?= $jwt ?>",
            width: "100%",
            height: "100%",
            parentNode: document.getElementById('jitsi-container'),
            userInfo: {
                displayName: "<?= htmlspecialchars($userName) ?>"
            },
            configOverwrite: {
                prejoinPageEnabled: <?= ($userRole === 'lecturer') ? 'false' : 'true' ?>
            }
        };

        const api = new JitsiMeetExternalAPI(domain, options);

        // Log attendance for both
        api.addEventListener('videoConferenceJoined', () => {
            fetch('../actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=log_attendance&meeting_id=<?= $meeting_id ?>'
            });
        });

        <?php if ($userRole === 'lecturer'): ?>
        // Auto-end meeting after scheduled duration
        const durationMinutes = <?= $meeting['duration'] ?>;
        const autoEndTime = durationMinutes * 60 * 1000;
        setTimeout(() => {
            alert("Meeting time is over. You will be disconnected.");
            api.executeCommand('hangup');
        }, autoEndTime);
        <?php endif; ?>
    </script>
</body>
</html>
