<?php
require_once '../config/db.php';
session_start();

// Assume student is logged in
$student_id = $_SESSION['student_id'] ?? 1;

// Fetch student info
$stmt = $conn->prepare("SELECT course_id, year_of_study FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$course_id = $student['course_id'];
$year_of_study = $student['year_of_study'];
$stmt->close();

// Fetch units for this student (course + year)
$units_stmt = $conn->prepare("
    SELECT id, name, code
    FROM units
    WHERE course_id = ? AND year = ?
    ORDER BY name
");
$units_stmt->bind_param("ii", $course_id, $year_of_study);
$units_stmt->execute();
$units_result = $units_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Notes</title>
<style>
    .units-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .unit-tile { background: #fff; padding: 1rem; border-radius: 1rem; box-shadow: 0 2px 6px rgba(0,0,0,0.1); cursor: pointer; }
    .unit-tile:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    .card { background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-bottom: 2rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .table-row-hover:hover { background: #f9fafb; }
</style>
</head>
<body>

<h1>Student Notes</h1>

<div class="units-grid">
    <?php while ($unit = $units_result->fetch_assoc()): ?>
        <div class="unit-tile" data-unit-id="<?= $unit['id'] ?>">
            <h3><?= htmlspecialchars($unit['name']) ?></h3>
            <p><?= htmlspecialchars($unit['code']) ?></p>
        </div>
    <?php endwhile; ?>
</div>

<div id="notes-content"></div>

<script>
document.querySelectorAll('.unit-tile').forEach(tile => {
    tile.addEventListener('click', () => {
        const unitId = tile.dataset.unitId;
        const formData = new FormData();
        formData.append('unit_id', unitId);

        fetch('', {method: 'POST', body: formData})
            .then(res => res.text())
            .then(html => {
                document.getElementById('notes-content').innerHTML = html;
            });
    });
});
</script>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unit_id'])):
    $unit_id = intval($_POST['unit_id']);
    // Fetch notes with progress
    $stmt = $conn->prepare("
        SELECT cn.id AS classnote_id, cn.title, cn.subtopics_json, cn.file_path, cn.uploaded_at, 
               scp.status
        FROM classnotes cn
        LEFT JOIN student_classnotes_progress scp 
            ON scp.classnote_id = cn.id AND scp.student_id = ?
        WHERE cn.unit_id = ?
        ORDER BY cn.uploaded_at DESC
    ");
    $stmt->bind_param("ii", $student_id, $unit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    ?>

    <section class="card">
        <h2>Uploaded Notes</h2>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Topics</th>
                        <th>Uploaded At</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res->num_rows > 0): ?>
                        <?php while ($note = $res->fetch_assoc()): 
                            $file = htmlspecialchars($note['file_path']);
                            $full_path = "../assets/uploads/" . $file;
                            $fileExists = file_exists($full_path);
                            $subtopics = json_decode($note['subtopics_json'], true) ?? [];
                        ?>
                            <tr class='table-row-hover'>
                                <td><?= htmlspecialchars($note['title']) ?></td>
                                <td>
                                    <?php foreach ($subtopics as $topic => $subs): ?>
                                        <div><strong style="color:red"><?= htmlspecialchars($topic) ?></strong></div>
                                        <?php if (is_array($subs)): ?>
                                            <?php foreach ($subs as $sub): ?>
                                                <div><strong style="color:green">&nbsp;&nbsp;- <?= htmlspecialchars($sub) ?></strong></div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= date("d M Y, h:i A", strtotime($note['uploaded_at'])) ?></td>
                                <td><?= $note['status'] ?? 'not_started' ?></td>
                                <td>
                                    <?php if ($fileExists): ?>
                                        <a href='<?= $full_path ?>' target='_blank'>View</a> | 
                                        <a href='<?= $full_path ?>' download>Download</a>
                                    <?php else: ?>
                                        <span style='color: red;'>File missing</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan='5' style="text-align:center;">No notes uploaded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php
    $stmt->close();
endif;
$conn->close();
?>

</body>
</html>

