<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

$selected_course = $_GET['course_id'] ?? null;
$selected_unit = $_GET['unit_id'] ?? null;
$selected_assignment = $_GET['assignment_id'] ?? null;

// Fetch courses taught by lecturer (via units and lecturer_units)
$courseQuery = $conn->prepare("
    SELECT DISTINCT c.id, c.name 
    FROM courses c 
    JOIN units u ON c.id = u.course_id 
    JOIN lecturer_units lu ON u.id = lu.unit_id 
    WHERE lu.lecturer_id = ?
");
$courseQuery->bind_param("i", $lecturer_id);
$courseQuery->execute();
$courses = $courseQuery->get_result();
$courseQuery->close();

// Fetch units for selected course
$unitQuery = $conn->prepare("
    SELECT u.id, u.name 
    FROM units u 
    JOIN lecturer_units lu ON u.id = lu.unit_id 
    WHERE lu.lecturer_id = ? AND u.course_id = ?
");
$unitQuery->bind_param("ii", $lecturer_id, $selected_course);
$unitQuery->execute();
$units = $unitQuery->get_result();
$unitQuery->close();
?>
<!DOCTYPE html>
<html>
<head>
    
    <style>
        body { font-family: Arial; padding: 30px; background: #f9f9f9; }
        .form-container { text-align: center; margin-bottom: 20px; }
        select, button, input[type="submit"] { padding: 10px; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { padding: 12px; border: 1px solid #ccc; }
        th { background: #2c3e50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        h2, h3 { margin-bottom: 10px; }
        .top { display: flex; justify-content: space-between; align-items: center; }
        a.btn { padding: 10px 15px; background: #2980b9; color: white; text-decoration: none; border-radius: 4px; }
        .success { color: green; }
        .error { color: red; }
        textarea { width: 100%; min-height: 50px; resize: vertical; }
    </style>
</head>
<body>

<style>
    /* Dropdown container */
    .dropdown {
        position: relative;
        display: inline-block;
        float: right;
        margin-top: -40px; /* Adjust vertically to align with h2 */
    }
    /* The three dots button */
    .dropbtn {
        font-size: 24px;
        background: none;
        border: none;
        cursor: pointer;
        color: #2980b9;
        user-select: none;
    }
    /* Dropdown content (hidden by default) */
    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: #f9f9f9;
        min-width: 140px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        border-radius: 5px;
        z-index: 1;
    }
    /* Dropdown link style */
    .dropdown-content a {
        color: #2980b9;
        padding: 10px 15px;
        text-decoration: none;
        display: block;
        border-bottom: 1px solid #ddd;
    }
    .dropdown-content a:last-child {
        border-bottom: none;
    }
    .dropdown-content a:hover {
        background-color: #ddd;
    }
    /* Show dropdown on active */
    .show {
        display: block;
    }
</style>
<style>
    /* Dropdown container */
    .dropdown {
        position: relative;
        display: inline-block;
        float: right;
        margin-top: -40px; /* Adjust vertically to align with h2 */
    }
    /* The three dots button */
    .dropbtn {
        font-size: 24px;
        background: none;
        border: none;
        cursor: pointer;
        color: #2980b9;
        user-select: none;
    }
    /* Dropdown content (hidden by default) */
    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: #f9f9f9;
        min-width: 140px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        border-radius: 5px;
        z-index: 1;
    }
    /* Dropdown link style */
    .dropdown-content a {
        color: #2980b9;
        padding: 10px 15px;
        text-decoration: none;
        display: block;
        border-bottom: 1px solid #ddd;
    }
    .dropdown-content a:last-child {
        border-bottom: none;
    }
    .dropdown-content a:hover {
        background-color: #ddd;
    }
    /* Show dropdown on active */
    .show {
        display: block;
    }
</style>

<h2>📄 Assignment Submissions</h2>

<div class="dropdown">
    <button onclick="toggleDropdown()" class="dropbtn" aria-label="Menu options">&#8942;</button>
    <div id="dropdownMenu" class="dropdown-content">
        <a href="dashboard.php">🏠 Back to Home</a>
    </div>
</div>

<script>
    function toggleDropdown() {
        document.getElementById("dropdownMenu").classList.toggle("show");
    }
    // Close dropdown if clicked outside
    window.onclick = function(event) {
        if (!event.target.matches('.dropbtn')) {
            const dropdowns = document.getElementsByClassName("dropdown-content");
            for (let i = 0; i < dropdowns.length; i++) {
                const openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }
</script>


<!-- Success/Error Feedback -->
<?php
if (isset($_SESSION['marks_success'])) {
    echo "<p class='success'>{$_SESSION['marks_success']}</p>";
    unset($_SESSION['marks_success']);
}
if (isset($_SESSION['marks_error'])) {
    echo "<p class='error'>{$_SESSION['marks_error']}</p>";
    unset($_SESSION['marks_error']);
}
?>

<!-- New Form at Upper Center -->
<div class="form-container">
    <form method="GET" action="">
        <label>Course:</label>
        <select name="course_id" onchange="this.form.submit()" required>
            <option value="">-- Select Course --</option>
            <?php while ($c = $courses->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= ($selected_course == $c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Unit:</label>
        <select name="unit_id" onchange="this.form.submit()" required <?= !$selected_course ? 'disabled' : '' ?>>
            <option value="">-- Select Unit --</option>
            <?php if ($selected_course && $units): while ($u = $units->fetch_assoc()): ?>
                <option value="<?= $u['id'] ?>" <?= ($selected_unit == $u['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?>
                </option>
            <?php endwhile; endif; ?>
        </select>

        <label>Assignment:</label>
        <?php
        $assignmentQuery = $conn->prepare("SELECT id, title FROM assignments WHERE unit_id = ?");
        $assignmentQuery->bind_param("i", $selected_unit);
        $assignmentQuery->execute();
        $assignments = $assignmentQuery->get_result();
        $assignmentQuery->close();
        ?>
        <select name="assignment_id" onchange="this.form.submit()" required <?= !$selected_unit ? 'disabled' : '' ?>>
            <option value="">-- Select Assignment --</option>
            <?php if ($selected_unit && $assignments): while ($a = $assignments->fetch_assoc()): ?>
                <option value="<?= $a['id'] ?>" <?= ($selected_assignment == $a['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['title']) ?>
                </option>
            <?php endwhile; endif; ?>
        </select>

        <input type="submit" value="Filter">
    </form>
</div>

<!-- Show Submissions -->
<?php
if ($selected_assignment):
    $infoQuery = $conn->prepare("
        SELECT a.title, u.name AS unit_name 
        FROM assignments a 
        JOIN units u ON a.unit_id = u.id 
        WHERE a.id = ?
    ");
    $infoQuery->bind_param("i", $selected_assignment);
    $infoQuery->execute();
    $assignment_info = $infoQuery->get_result()->fetch_assoc();
    $infoQuery->close();

    $sQuery = $conn->prepare("
        SELECT s.id AS submission_id, st.name AS student_name, st.reg_no, s.file_path, s.marks, s.is_graded, s.comment 
        FROM submissions s 
        JOIN students st ON s.student_id = st.id 
        WHERE s.assignment_id = ?
    ");
    $sQuery->bind_param("i", $selected_assignment);
    $sQuery->execute();
    $submissions = $sQuery->get_result();
?>

<div class="top">
    <div>
        <h3><?= htmlspecialchars($assignment_info['unit_name']) ?> — <?= htmlspecialchars($assignment_info['title']) ?></h3>
    </div>
    <a class="btn" href="../actions.php?action=generate_pdf&assignment_id=<?= $selected_assignment ?>">📥 Generate PDF</a>
</div>

<form method="POST" action="../actions.php">
    <input type="hidden" name="action" value="save_marks">
    <input type="hidden" name="assignment_id" value="<?= $selected_assignment ?>">

    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Reg No</th>
                <th>Submission</th>
                <th>Marks (out of 100)</th>
                <th>Graded</th>
                <th>Comment</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($s = $submissions->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($s['student_name']) ?></td>
                <td><?= htmlspecialchars($s['reg_no']) ?></td>
                <td><a href="../assets/uploads/submissions/<?= htmlspecialchars($s['file_path']) ?>" target="_blank">View</a></td>
                <td>
                    <input type="number" name="marks[<?= $s['submission_id'] ?>]" value="<?= (int)$s['marks'] ?>" min="0" max="100">
                </td>
                <td>
                    <input type="checkbox" name="is_graded[<?= $s['submission_id'] ?>]" <?= $s['is_graded'] ? 'checked' : '' ?>>
                </td>
                <td>
                    <textarea name="comment[<?= $s['submission_id'] ?>]"><?= htmlspecialchars($s['comment'] ?? '') ?></textarea>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <br>
    <button type="submit">💾 Save Marks</button>
</form>

<?php endif; ?>

</body>
</html>