<?php
session_start();
require_once '../config/db.php';

/* ============================================================
   AUTHENTICATION
   ============================================================ */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? '';

/* ============================================================
   GET UNIT ID
   ============================================================ */
$unit_id = $_GET['unit_id'] ?? null;
if (!$unit_id) {
    die("Unit ID not provided.");
}

/* ============================================================
   FETCH UNIT INFO
   ============================================================ */
$unitQuery = $conn->prepare("SELECT name, code, course_id FROM units WHERE id = ?");
$unitQuery->bind_param("i", $unit_id);
$unitQuery->execute();
$unitInfo = $unitQuery->get_result()->fetch_assoc();
$unitQuery->close();

if (!$unitInfo) die("Unit not found.");

$unit_name = $unitInfo['name'];
$unit_code = $unitInfo['code'];
$course_id = $unitInfo['course_id'];

/* ============================================================
   FETCH COURSE & UNIVERSITY INFO
   ============================================================ */
$courseQuery = $conn->prepare("
    SELECT c.name AS course_name, d.university_id 
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE c.id = ?
");
$courseQuery->bind_param("i", $course_id);
$courseQuery->execute();
$courseInfo = $courseQuery->get_result()->fetch_assoc();
$courseQuery->close();

$course_name = $courseInfo['course_name'] ?? 'Unknown Course';
$university_id = $courseInfo['university_id'] ?? null;

$university_name = "Unknown University";
if ($university_id) {
    $uniQuery = $conn->prepare("SELECT name FROM universities WHERE id = ?");
    $uniQuery->bind_param("i", $university_id);
    $uniQuery->execute();
    $uniInfo = $uniQuery->get_result()->fetch_assoc();
    $uniQuery->close();
    if ($uniInfo) $university_name = $uniInfo['name'];
}

/* ============================================================
   FETCH STUDENTS IN THIS UNIT
   ============================================================ */
$studentQuery = $conn->prepare("
    SELECT st.id, st.name, st.reg_no
    FROM students st
    JOIN student_units su ON su.student_id = st.id
    WHERE su.unit_id = ?
    ORDER BY st.name ASC
");
$studentQuery->bind_param("i", $unit_id);
$studentQuery->execute();
$students = $studentQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$studentQuery->close();

/* ============================================================
   FETCH ASSIGNMENTS FOR THE UNIT (ONLY WITH SUBMISSIONS)
   ============================================================ */
$assignmentsQuery = $conn->prepare("
    SELECT DISTINCT a.id, a.title
    FROM assignments a
    INNER JOIN submissions sub ON a.id = sub.assignment_id
    WHERE a.unit_id = ?
    ORDER BY a.id ASC
");
$assignmentsQuery->bind_param("i", $unit_id);
$assignmentsQuery->execute();
$assignments = $assignmentsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$assignmentsQuery->close();

/* ============================================================
   FETCH SUBMISSIONS FOR THESE ASSIGNMENTS
   ============================================================ */
$submissions = [];

if (!empty($assignments)) {

    $assignment_ids = array_column($assignments, 'id');
    $placeholders = implode(',', array_fill(0, count($assignment_ids), '?'));
    $types = str_repeat('i', count($assignment_ids));

    $sql = "SELECT student_id, assignment_id, marks, is_graded 
            FROM submissions 
            WHERE assignment_id IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$assignment_ids);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $submissions[$row['student_id']][$row['assignment_id']] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($unit_name); ?> - Assignments Report</title>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- PDF Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<!-- Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f5f5f5;
}
.header-section {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.header-section h2, .header-section h3 {
    margin: 5px 0;
}
.btn {
    background: #3498db;
    color: #fff;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 5px;
    font-size: 16px;
    margin-right: 10px;
    margin-bottom: 20px;
}
.btn.excel {
    background: #27ae60;
}
.btn:hover { opacity: 0.8; }

.table-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
}
table.dataTable th, table.dataTable td {
    border: 1px solid #ccc !important;
    padding: 8px;
}
table.dataTable {
    border-collapse: collapse !important;
}
table.dataTable thead th {
    background: #3498db;
    color: white;
}
</style>
</head>
<body>

<div class="header-section">
    <h2><?= htmlspecialchars($university_name); ?></h2>
    <h3>Course: <?= htmlspecialchars($course_name); ?></h3>
    <h3>Unit: <?= htmlspecialchars($unit_name); ?> (<?= htmlspecialchars($unit_code); ?>)</h3>
    <h3>Lecturer: <?= htmlspecialchars($lecturer_name); ?></h3>
</div>

<button id="generatePDF" class="btn">Download PDF</button>
<button id="downloadExcel" class="btn excel">Download Excel</button>

<div class="table-container">
    <table id="assignmentsTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Reg No</th>

                <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $a): ?>
                        <th><?= htmlspecialchars($a['title']); ?></th>
                    <?php endforeach; ?>
                <?php else: ?>
                    <th>No Assignments</th>
                <?php endif; ?>
            </tr>
        </thead>

        <tbody>
            <?php 
            $i = 1;
            foreach ($students as $st):
            ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($st['name']); ?></td>
                <td><?= htmlspecialchars($st['reg_no']); ?></td>

                <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $a): 
                        $aid = $a['id'];
                    ?>
                        <td>
                            <?php
                            if (isset($submissions[$st['id']][$aid])) {
                                $s = $submissions[$st['id']][$aid];
                                echo $s['is_graded'] ? number_format($s['marks'], 1) : "Pending";
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                <?php else: ?>
                    <td>-</td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>

    </table>
</div>

<script>
$(document).ready(function(){
    $('#assignmentsTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']]
    });
});

/* ==========================================
   PDF EXPORT
   ========================================== */
document.getElementById('generatePDF').addEventListener('click', function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    doc.setFontSize(16);
    doc.text('<?= addslashes($university_name); ?>', 14, 15);

    doc.setFontSize(12);
    doc.text('Course: <?= addslashes($course_name); ?>', 14, 22);
    doc.text('Unit: <?= addslashes($unit_name); ?> (<?= addslashes($unit_code); ?>)', 14, 28);
    doc.text('Lecturer: <?= addslashes($lecturer_name); ?>', 14, 34);

    const table = $('#assignmentsTable').DataTable();
    const headers = [];
    const rows = [];

    $('#assignmentsTable thead th').each(function(){
        headers.push($(this).text());
    });

    table.rows({ search: 'applied' }).every(function(){
        const rowData = [];
        $(this.node()).find('td').each(function(){
            rowData.push($(this).text());
        });
        rows.push(rowData);
    });

    doc.autoTable({
        head: [headers],
        body: rows,
        startY: 45,
        theme: 'grid',
        headStyles: { fillColor: [52, 152, 219], textColor: 255 }
    });

    doc.save("Assignments_Report.pdf");
});

/* ==========================================
   EXCEL EXPORT
   ========================================== */
document.getElementById('downloadExcel').addEventListener('click', function () {
    let table = document.getElementById('assignmentsTable');
    let wb = XLSX.utils.table_to_book(table, {sheet: "Assignments"});
    XLSX.writeFile(wb, "Assignments_Report.xlsx");
});
</script>

</body>
</html>
