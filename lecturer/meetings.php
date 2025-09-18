<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Fetch units
$unitQuery = $conn->prepare("
    SELECT u.id, u.name 
    FROM units u 
    JOIN lecturer_units lu ON lu.unit_id = u.id 
    WHERE lu.lecturer_id = ?
");
$unitQuery->bind_param("i", $lecturer_id);
$unitQuery->execute();
$unitResult = $unitQuery->get_result();
$units = $unitResult->fetch_all(MYSQLI_ASSOC);
$unitQuery->close();

// Fetch meetings
$meetingQuery = $conn->prepare("
    SELECT m.id, m.unit_id, m.title, m.scheduled_time, m.duration, m.meeting_link, u.name AS unit_name 
    FROM meetings m 
    JOIN units u ON m.unit_id = u.id 
    WHERE m.lecturer_id = ? 
    ORDER BY m.scheduled_time DESC
");
$meetingQuery->bind_param("i", $lecturer_id);
$meetingQuery->execute();
$meetingResult = $meetingQuery->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Meetings</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f4f4; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #2c3e50; color: white; }
        .btn {
            padding: 8px 12px; background: #3498db; color: white;
            border: none; cursor: pointer; text-decoration: none;
            border-radius: 4px; margin-right: 5px;
        }
        .btn:hover { background: #2980b9; }
        form {
            margin-bottom: 30px; background: #fff; padding: 20px;
            border-radius: 8px; box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        h2, h3 { color: #2c3e50; }
        label { font-weight: bold; }
        input[type="text"], input[type="number"], input[type="datetime-local"], select {
            padding: 8px; width: 60%; margin-bottom: 15px;
        }
    </style>
</head>
<body>

<h2>Welcome, <?= htmlspecialchars($lecturer_name) ?> — Meetings</h2>

<?php
if (isset($_SESSION['meeting_success'])) {
    echo "<p style='color:green;'><strong>{$_SESSION['meeting_success']}</strong></p>";
    if (isset($_SESSION['meeting_link'])) {
        echo "<p><strong>Meeting Link:</strong> <input type='text' id='linkBox' value='{$_SESSION['meeting_link']}' readonly style='width:60%;'> ";
        echo "<button onclick=\"navigator.clipboard.writeText(document.getElementById('linkBox').value)\">Copy</button></p>";
    }
    unset($_SESSION['meeting_success'], $_SESSION['meeting_link']);
}

if (isset($_SESSION['meeting_error'])) {
    echo "<p style='color:red;'><strong>{$_SESSION['meeting_error']}</strong></p>";
    unset($_SESSION['meeting_error']);
}
?>

<h3>📅 Schedule New Meeting</h3>
<form method="POST" action="../actions.php">
    <input type="hidden" name="action" value="schedule_meeting">

    <label>Meeting Title:</label><br>
    <input type="text" name="title" required><br>

    <label>Unit:</label><br>
    <select name="unit_id" required>
        <option value="">-- Select Unit --</option>
        <?php foreach ($units as $unit): ?>
            <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['name']) ?></option>
        <?php endforeach; ?>
    </select><br>

    <label>Date & Time:</label><br>
    <input type="datetime-local" name="scheduled_time" required><br>

    <label>Duration (Minutes):</label><br>
    <input type="number" name="duration" min="5" max="300" value="60" required><br>

    <button type="submit" class="btn">Schedule</button>
</form>


    <h3>📋 Your Scheduled Meetings</h3>
<table>
    <tr>
        <th>Title</th>
        <th>Unit</th>
        <th>Date & Time</th>
        <th>Actions</th>
    </tr>
    <?php while ($meeting = $meetingResult->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($meeting['title']) ?></td>
        <td><?= htmlspecialchars($meeting['unit_name']) ?></td>
        <td><?= date('d M Y, h:i A', strtotime($meeting['scheduled_time'])) ?></td>
        <td>
            <a class="btn" href="meeting_ide.php?meeting_id=<?= $meeting['id'] ?>">🔗 Join Meeting</a>
            <a class="btn" href="../actions.php?action=download_register&type=single&meeting_id=<?= $meeting['id'] ?>">📝 Single Register</a>
            <a class="btn" href="../actions.php?action=download_register&type=full&unit_id=<?= $meeting['unit_id'] ?>">📊 Full Register</a>
            <a class="btn" href="#">🎥 Record</a> <!-- future feature -->
        </td>
    </tr>
    <?php endwhile; ?>
</table>


</body>
</html>
