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
   FETCH STUDENTS ENROLLED IN THIS UNIT
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
   FETCH ASSIGNMENTS THAT HAVE SUBMISSIONS (DYNAMIC)
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
   FETCH ALL SUBMISSIONS FOR THESE ASSIGNMENTS
   ============================================================ */
$submissions = [];
if (!empty($assignments)) {
    $assignment_ids = array_column($assignments, 'id');
    $ids_placeholder = implode(',', array_fill(0, count($assignment_ids), '?'));
    
    $types = str_repeat('i', count($assignment_ids));
    $sql = "SELECT student_id, assignment_id, marks, is_graded FROM submissions WHERE assignment_id IN ($ids_placeholder)";
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
<title><?php echo htmlspecialchars($unit_name); ?> - Assignments Report</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
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
.header-section h2 {
    margin: 5px 0;
    color: #2c3e50;
}
.header-section h3 {
    margin: 5px 0;
    color: #34495e;
    font-weight: normal;
}
.btn-pdf {
    background: #3498db;
    color: #fff;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 5px;
    font-size: 16px;
    margin-bottom: 20px;
}
.btn-pdf:hover {
    background: #2980b9;
}
.table-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
table.dataTable thead th {
    background: #3498db;
    color: white;
}
table.dataTable tbody tr:nth-child(even) {
    background-color: #f2f2f2;
}
</style>
</head>
<body>

<div class="header-section">
    <h2><?php echo htmlspecialchars($university_name); ?></h2>
    <h3>Course: <?php echo htmlspecialchars($course_name); ?> | Unit: <?php echo htmlspecialchars($unit_name); ?> (<?php echo htmlspecialchars($unit_code); ?>)</h3>
    <h3>Lecturer: <?php echo htmlspecialchars($lecturer_name); ?></h3>
</div>

<button id="generateAssignmentsPDF" class="btn-pdf">Generate Assignments PDF</button>

<div class="table-container">
    <table id="assignmentsTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Reg No</th>
                <?php 
                // Dynamic assignment columns
                foreach ($assignments as $index => $assignment): 
                    echo "<th title='".htmlspecialchars($assignment['title'])."'>A".($index+1)." Grade</th>";
                endforeach;
                
                // If no assignments, show placeholder message
                if (empty($assignments)):
                    echo "<th>No Assignments</th>";
                endif;
                ?>
            </tr>
        </thead>
        <tbody>
            <?php
            if (empty($students)):
                echo "<tr><td colspan='".(3 + count($assignments))."' style='text-align:center;'>No data available in table</td></tr>";
            else:
                $counter = 1;
                foreach ($students as $student):
                    echo "<tr>";
                    echo "<td>{$counter}</td>";
                    echo "<td>".htmlspecialchars($student['name'])."</td>";
                    echo "<td>".htmlspecialchars($student['reg_no'])."</td>";
                    
                    // Dynamic grade columns
                    if (!empty($assignments)):
                        foreach ($assignments as $assignment):
                            $ass_id = $assignment['id'];
                            if (isset($submissions[$student['id']][$ass_id])):
                                $sub = $submissions[$student['id']][$ass_id];
                                echo "<td>".($sub['is_graded'] ? number_format($sub['marks'], 1) : "Pending")."</td>";
                            else:
                                echo "<td>-</td>";
                            endif;
                        endforeach;
                    else:
                        echo "<td>-</td>";
                    endif;
                    
                    echo "</tr>";
                    $counter++;
                endforeach;
            endif;
            ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function(){
    // Initialize DataTable
    $('#assignmentsTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']] // Sort by student name
    });

    // Generate PDF
    $('#generateAssignmentsPDF').click(function(){
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4'
        });

        // Add header information
        doc.setFontSize(16);
        doc.text('<?php echo addslashes($university_name); ?>', 14, 15);
        
        doc.setFontSize(12);
        doc.text('Course: <?php echo addslashes($course_name); ?>', 14, 22);
        doc.text('Unit: <?php echo addslashes($unit_name); ?> (<?php echo addslashes($unit_code); ?>)', 14, 28);
        doc.text('Lecturer: <?php echo addslashes($lecturer_name); ?>', 14, 34);
        
        doc.setFontSize(10);
        doc.text('Generated: ' + new Date().toLocaleString(), 14, 40);

        // Get table data
        const table = $('#assignmentsTable').DataTable();
        const headers = [];
        const rows = [];

        // Extract headers
        $('#assignmentsTable thead th').each(function(){
            headers.push($(this).text());
        });

        // Extract rows (use DataTable's data to respect sorting/filtering)
        table.rows({search: 'applied'}).every(function(){
            const rowData = [];
            $(this.node()).find('td').each(function(){
                rowData.push($(this).text());
            });
            rows.push(rowData);
        });

        // Generate table in PDF
        doc.autoTable({
            head: [headers],
            body: rows,
            startY: 45,
            theme: 'grid',
            headStyles: {
                fillColor: [52, 152, 219],
                textColor: 255,
                fontStyle: 'bold'
            },
            styles: {
                fontSize: 8,
                cellPadding: 2
            },
            columnStyles: {
                0: { cellWidth: 10 },  // # column
                1: { cellWidth: 40 },  // Student Name
                2: { cellWidth: 30 }   // Reg No
            }
        });

        // Save the PDF
        const filename = 'Assignments_<?php echo preg_replace("/[^a-zA-Z0-9]/", "_", $unit_name); ?>_' + 
                        new Date().toISOString().split('T')[0] + '.pdf';
        doc.save(filename);
    });
});
</script>

</body>
</html>
