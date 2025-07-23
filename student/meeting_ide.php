<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

$meeting_id = $_GET['meeting_id'] ?? null;
if (!$meeting_id) die("Meeting ID is required.");

$stmt = $conn->prepare("SELECT title FROM meetings WHERE id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();
$stmt->close();

if (!$meeting) die("Meeting not found.");

$roomName = "unilis_meeting_" . $meeting_id;
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($meeting['title']) ?> - Meeting</title>
    <script src='https://meet.jit.si/external_api.js'></script>
    <style>
        html, body, #jitsi-container {
            height: 100%;
            margin: 0;
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
            }
        };

        const api = new JitsiMeetExternalAPI(domain, options);

        api.addEventListener('videoConferenceJoined', () => {
            fetch('../actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=log_attendance&meeting_id=<?= $meeting_id ?>&user_type=student'
            });
        });
    </script>
</body>
</html>
