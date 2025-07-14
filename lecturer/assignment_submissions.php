<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

$selected_unit = $_GET['unit_id'] ?? null;
$selected_assignment = $_GET['assignment_id'] ?? null;

// Fetch units the lecturer teaches
$unitResult = $conn->prepare("
    SELECT u.id, u.name 
    FROM units u 
    JOIN lecturer_units lu ON u.id = lu.unit_id 
    WHERE lu.lecturer_id = ?
");
$unitResult->bind_param("i", $lecturer_id);
$unitResult->execute();
$units = $unitResult->get_result();
$unitResult->close();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Assignment Submissions</title>
    <style>
        body { font-family: Arial; padding: 30px; background: #f9f9f9; }
        select, button { padding: 10px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        th, td { padding: 12px; border: 1px solid #ccc; }
        th { background: #2c3e50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        h2 { margin-bottom: 10px; }
        .top { display: flex; justify-content: space-between; align-items: center; }
        a.btn { padding: 10px 15px; background: #2980b9; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>

<h2>Assignment Submissions</h2>

<form method="GET" action="">
    <label>Select Unit:</label>
    <select name="unit_id" onchange="this.form.submit()" required>
        <option value="">-- Select Unit --</option>
        <?php while ($u = $units->fetch_assoc()): ?>
            <option value="<?= $u['id'] ?>" <?= ($selected_unit == $u['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

<?php
if ($selected_unit):
    // Fetch assignments for selected unit
    $aQuery = $conn->prepare("SELECT id, title FROM assignments WHERE unit_id = ?");
    $aQuery->bind_param("i", $selected_unit);
    $aQuery->execute();
    $assignments = $aQuery->get_result();
    $aQuery->close();
?>

<form method="GET" action="">
    <input type="hidden" name="unit_id" value="<?= $selected_unit ?>">
    <label>Select Assignment:</label>
    <select name="assignment_id" onchange="this.form.submit()" required>
        <option value="">-- Select Assignment --</option>
        <?php while ($a = $assignments->fetch_assoc()): ?>
            <option value="<?= $a['id'] ?>" <?= ($selected_assignment == $a['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['title']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

<?php endif; ?>

<?php
if ($selected_assignment):
    // Get assignment and unit info
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

    // Get student submissions
    $sQuery = $conn->prepare("
        SELECT s.id AS submission_id, st.name AS student_name, st.reg_no, s.file_path, s.marks 
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
    <a class="btn" href="generate_pdf.php?assignment_id=<?= $selected_assignment ?>" target="_blank">Generate PDF</a>
</div>

<form action="save_marks.php" method="POST">
    <input type="hidden" name="assignment_id" value="<?= $selected_assignment ?>">
    <table>
        <tr>
            <th>Student Name</th>
            <th>Reg No</th>
            <th>Submission</th>
            <th>Marks</th>
        </tr>
        <?php while ($s = $submissions->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($s['student_name']) ?></td>
            <td><?= htmlspecialchars($s['reg_no']) ?></td>
            <td><a href="../assets/uploads/submissions/<?= htmlspecialchars($s['file_path']) ?>" target="_blank">View</a></td>
            <td>
                <input type="number" name="marks[<?= $s['submission_id'] ?>]" value="<?= $s['marks'] ?>" min="0" max="100">
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <br>
    <button type="submit">Save Marks</button>
</form>

<?php endif; ?>



</body>
</html>
