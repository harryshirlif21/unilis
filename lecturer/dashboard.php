<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

// Ensure lecturer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Get units taught by lecturer
$units = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name 
    FROM units u
    JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE lu.lecturer_id = ?
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Dashboard - UNILIS</title>
    <style>
        body { display: flex; font-family: Arial; margin: 0; }
        .sidebar { width: 250px; background: #2c3e50; color: white; padding: 20px; min-height: 100vh; }
        .sidebar button { width: 100%; margin: 8px 0; padding: 10px; border: none; background: #34495e; color: white; border-radius: 5px; cursor: pointer; }
        .sidebar button:hover { background-color: #1abc9c; }
        .content { flex: 1; padding: 30px; background: #ecf0f1; }
        .modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; width: 50%; border-radius: 10px; }
        .close { float: right; font-size: 24px; cursor: pointer; color: #aaa; }
        .close:hover { color: black; }
        form input, form select, form textarea, form button { width: 100%; margin-bottom: 10px; padding: 8px; }
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
    <button onclick="showModal('addUnitModal')">Add My Units</button>
	<button onclick="window.location.href='assignment_submissions.php'">View Submission Stats</button>
    <button onclick="window.location.href='../logout.php'">Logout</button>
</div>

<div class="content">
    <h2>Welcome, <?= htmlspecialchars($lecturer_name) ?>!</h2>
    <p>Use the buttons on the left to manage notes, assignments, and submissions.</p>
</div>

<!-- Upload Notes Modal -->
<div id="uploadModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="hideModal('uploadModal')">&times;</span>
    <h3>Upload Notes</h3>
    <form action="../actions.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_notes">
        <label>Unit:</label>
        <select name="unit_id" required>
            <option value="">-- Select Unit --</option>
            <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
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
    <span class="close" onclick="hideModal('viewNotesModal')">&times;</span>
    <h3>Uploaded Notes</h3>
    <ul>
        <?php
        $stmt = $conn->prepare("
            SELECT n.file_path, u.name AS unit 
            FROM notes n
            JOIN units u ON n.unit_id = u.id
            JOIN lecturer_units lu ON lu.unit_id = u.id
            WHERE lu.lecturer_id = ?
        ");
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($note = $res->fetch_assoc()) {
            echo "<li><strong>" . htmlspecialchars($note['unit']) . "</strong>: 
                  <a href='../assets/uploads/" . htmlspecialchars($note['file_path']) . "' target='_blank'>View</a></li>";
        }
        $stmt->close();
        ?>
    </ul>
  </div>
</div>

<!-- Create Assignment Modal -->
<div id="assignmentModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="hideModal('assignmentModal')">&times;</span>
    <h3>Create Assignment</h3>
    <form action="../actions.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_assignment">
        <label>Unit:</label>
        <select name="unit_id" required>
            <option value="">-- Select Unit --</option>
            <?php foreach ($units as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Title:</label>
        <input type="text" name="title" required>
        <label>Description:</label>
        <textarea name="description" required></textarea>
        <label>Deadline:</label>
        <input type="datetime-local" name="deadline" required>
        <label>Attach File (optional):</label>
        <input type="file" name="assignment_file">
        <button type="submit">Create Assignment</button>
    </form>
  </div>
</div>

<!-- View Submissions Modal -->
<div id="submissionModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="hideModal('submissionModal')">&times;</span>
    <h3>Student Submissions</h3>
    <ul>
        <?php
        $stmt = $conn->prepare("
            SELECT s.file_path, st.name AS student, u.name AS unit 
            FROM submissions s 
            JOIN students st ON s.student_id = st.id
            JOIN assignments a ON s.assignment_id = a.id
            JOIN units u ON a.unit_id = u.id
            JOIN lecturer_units lu ON lu.unit_id = u.id
            WHERE lu.lecturer_id = ?
        ");
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            echo "<li><strong>" . htmlspecialchars($row['student']) . "</strong> - " . 
                 htmlspecialchars($row['unit']) . ": <a href='../assets/uploads/submissions/" . 
                 htmlspecialchars($row['file_path']) . "' target='_blank'>Download</a></li>";
        }
        $stmt->close();
        ?>
    </ul>
  </div>
</div>

<!-- Add Units Modal -->
<div id="addUnitModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="hideModal('addUnitModal')">&times;</span>
    <h3>Add Unit You Teach</h3>
    <form action="../actions.php" method="POST">
        <input type="hidden" name="action" value="add_single_lecturer_unit">

        <label>Select Course:</label>
        <select name="course_id" id="courseSelect" required>
            <option value="">-- Select Course --</option>
            <?php
            $courseRes = $conn->query("SELECT id, name FROM courses");
            while ($course = $courseRes->fetch_assoc()) {
                echo "<option value='{$course['id']}'>" . htmlspecialchars($course['name']) . "</option>";
            }
            ?>
        </select>

        <label>Select Unit:</label>
        <select name="unit_id" id="unitSelect" required>
            <option value="">-- Select Unit --</option>
        </select>

        <button type="submit">Add Unit</button>
    </form>
  </div>
</div>

<!-- JavaScript -->
<script>
function showModal(id) {
    document.getElementById(id).style.display = 'block';
}
function hideModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Load units when course is selected
document.getElementById('courseSelect').addEventListener('change', function () {
    const courseId = this.value;
    const unitSelect = document.getElementById('unitSelect');
    unitSelect.innerHTML = '<option value="">Loading...</option>';

    fetch(`../load_units.php?course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            unitSelect.innerHTML = '<option value="">-- Select Unit --</option>';
            data.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.id;
                option.textContent = unit.name;
                unitSelect.appendChild(option);
            });
        });
});
</script>

</body>
</html>
