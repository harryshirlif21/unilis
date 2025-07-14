
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 60); // 60 seconds timeout
ini_set('memory_limit', '256M'); // Increase memory limit

session_start();
require_once '../config/db.php';

// Check if lecturer is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    error_log("Session check failed: user_id=" . ($_SESSION['user_id'] ?? 'unset') . ", user_role=" . ($_SESSION['user_role'] ?? 'unset'));
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Fetch only units assigned to this lecturer
$units = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name 
    FROM units u
    INNER JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE lu.lecturer_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to prepare units query: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Dashboard - UNILIS</title>
    <style>
        body { display: flex; font-family: Arial, sans-serif; margin: 0; }
        .sidebar {
            width: 250px; background-color: #2c3e50; color: white;
            min-height: 100vh; padding: 20px;
        }
        .sidebar h2 { font-size: 20px; }
        .sidebar p { margin: 5px 0 20px; font-size: 14px; color: #ccc; }
        .sidebar button {
            width: 100%; margin: 8px 0; padding: 10px; border: none;
            background-color: #34495e; color: white; cursor: pointer; border-radius: 5px;
        }
        .sidebar button:hover { background-color: #1abc9c; }
        .content { flex-grow: 1; padding: 30px; background-color: #ecf0f1; }
        .modal {
            display: none; position: fixed; z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            background-color: white; margin: 10% auto; padding: 20px;
            border: 1px solid #888; width: 50%; border-radius: 10px;
        }
        .close {
            color: #aaa; float: right; font-size: 24px; font-weight: bold;
        }
        .close:hover, .close:focus {
            color: black; text-decoration: none; cursor: pointer;
        }
        form input, form select, form textarea, form button {
            display: block; width: 100%; margin-bottom: 10px; padding: 8px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2><?= htmlspecialchars($lecturer_name) ?></h2>
    <p>Lecturer - UNILIS</p>
    <button onclick="showModal('uploadModal')">Upload Notes</button>
    <button onclick="showModal('viewNotesModal')">View Notes</button>
    <button onclick="showModal('assignmentModal')">Create Assignment</button>
    <button onclick="showModal('submissionModal')">View Submissions</button>
    <button onclick="window.location.href='../logout.php'">Logout</button>
</div>

<div class="content">
    <h2>Welcome, <?= htmlspecialchars($lecturer_name) ?>!</h2>
    <p>Use the buttons on the left to manage notes and assignments.</p>
</div>

<!-- Upload Notes Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('uploadModal')">×</span>
        <h3>Upload Notes</h3>
        <form action="../actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_notes">
            <label>Unit:</label>
            <select name="unit_id" required>
                <option value="">-- Select Unit --</option>
                <?php
                foreach ($units as $unit) {
                    echo "<option value='{$unit['id']}'>" . htmlspecialchars($unit['name']) . "</option>";
                }
                ?>
            </select>
            <label>Upload File:</label>
            <input type="file" name="notes_file" required>
            <button type="submit">Upload</button>
        </form>
    </div>
</div>

<!-- View Notes Modal -->
<div id="viewNotesModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('viewNotesModal')">×</span>
        <h3>Uploaded Notes</h3>
        <ul>
            <?php
            $stmt = $conn->prepare("
                SELECT notes.file_path, units.name AS unit 
                FROM notes 
                JOIN units ON notes.unit_id = units.id 
                JOIN lecturer_units lu ON lu.unit_id = units.id
                WHERE lu.lecturer_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $lecturer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $file = htmlspecialchars($row['file_path']);
                    $unit = htmlspecialchars($row['unit']);
                    echo "<li><strong>$unit</strong>: <a href='../assets/uploads/$file' target='_blank'>View</a></li>";
                }
                $stmt->close();
            }
            ?>
        </ul>
    </div>
</div>

<!-- Assignment Modal -->
<div id="assignmentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('assignmentModal')">×</span>
        <h3>Create Assignment</h3>
        <form action="../actions.php" method="POST">
            <input type="hidden" name="action" value="create_assignment">
            <label>Unit:</label>
            <select name="unit_id" required>
                <option value="">-- Select Unit --</option>
                <?php
                foreach ($units as $unit) {
                    echo "<option value='{$unit['id']}'>" . htmlspecialchars($unit['name']) . "</option>";
                }
                ?>
            </select>
            <label>Instructions:</label>
            <textarea name="instructions" required></textarea>
            <label>Due Date:</label>
            <input type="date" name="due_date" required>
            <button type="submit">Create Assignment</button>
        </form>
    </div>
</div>

<!-- View Submissions Modal -->
<div id="submissionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="hideModal('submissionModal')">×</span>
        <h3>Student Submissions</h3>
        <ul>
            <?php
            $stmt = $conn->prepare("
                SELECT s.file_path, st.name AS student, u.name AS unit 
                FROM submissions s 
                JOIN students st ON s.student_id = st.id 
                JOIN assignments a ON s.assignment_id = a.id 
                JOIN units u ON a.unit_id = u.id 
                JOIN lecturer_units lu ON u.id = lu.unit_id
                WHERE lu.lecturer_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $lecturer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $student = htmlspecialchars($row['student']);
                    $unit = htmlspecialchars($row['unit']);
                    $file = htmlspecialchars($row['file_path']);
                    echo "<li><strong>$student</strong> - $unit: <a href='../assets/uploads/submissions/$file' target='_blank'>Download</a></li>";
                }
                $stmt->close();
            }
            ?>
        </ul>
    </div>
</div>

<script>
    function showModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'block';
    }
    function hideModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    }
</script>

</body>
</html>
