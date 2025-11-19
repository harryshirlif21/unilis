<?php
session_start();
require_once '../config/db.php';

// --- Check if lecturer is logged in ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'] ?? '';

// --- Get selected unit ---
$unit_id = $_GET['unit_id'] ?? null;
if (!$unit_id) {
    die("Unit ID not provided.");
}

// --- Fetch unit info ---
$unitQuery = $conn->prepare("SELECT name, course_id FROM units WHERE id = ?");
$unitQuery->bind_param("i", $unit_id);
$unitQuery->execute();
$unitInfo = $unitQuery->get_result()->fetch_assoc();
$unitQuery->close();

if (!$unitInfo) die("Unit not found.");

$unit_name = $unitInfo['name'];
$course_id = $unitInfo['course_id'];

// --- Fetch course info ---
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

$course_name = $courseInfo['course_name'];
$university_id = $courseInfo['university_id'] ?? null;

// --- Fetch university name ---
$university_name = "Unknown University";
if ($university_id) {
    $uniQuery = $conn->prepare("SELECT name FROM universities WHERE id = ?");
    $uniQuery->bind_param("i", $university_id);
    $uniQuery->execute();
    $uniInfo = $uniQuery->get_result()->fetch_assoc();
    $uniQuery->close();

    if ($uniInfo) $university_name = $uniInfo['name'];
}

// --- Fetch students ---
$studentQuery = $conn->prepare("
    SELECT st.id, st.name, st.reg_no
    FROM students st
    JOIN student_units su ON su.student_id = st.id
    WHERE su.unit_id = ?
    ORDER BY st.name ASC
");
$studentQuery->bind_param("i", $unit_id);
$studentQuery->execute();
$students = $studentQuery->get_result();
$studentQuery->close();

// --- Fetch up to 6 assignments ---
$assignmentQuery = $conn->prepare("
    SELECT id, title
    FROM assignments
    WHERE unit_id = ?
    ORDER BY id ASC
    LIMIT 6
");
$assignmentQuery->bind_param("i", $unit_id);
$assignmentQuery->execute();
$assignments = $assignmentQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$assignmentQuery->close();

// --- Preload submissions ---
$assignment_ids = array_column($assignments, 'id');
$submissions = [];
if (!empty($assignment_ids)) {
    $in = implode(',', array_fill(0, count($assignment_ids), '?'));
    $types = str_repeat('i', count($assignment_ids));
    
    $stmt = $conn->prepare("SELECT student_id, assignment_id, marks, is_graded FROM submissions WHERE assignment_id IN ($in)");
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
<html>
<head>
    <title><?php echo htmlspecialchars($unit_name); ?> - Assignments</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
<h2><?php echo htmlspecialchars($university_name); ?></h2>
<h3>Course: <?php echo htmlspecialchars($course_name); ?> | Unit: <?php echo htmlspecialchars($unit_name); ?></h3>
<h3>Lecturer: <?php echo htmlspecialchars($lecturer_name); ?></h3>

<!-- Buttons -->
<button id="generateAssignmentsPDF">Generate Assignments PDF</button>
<button id="generateSubmissionsPDF">Generate Submissions PDF</button>

<!-- Table -->
<table id="assignmentsTable" border="1" cellspacing="0" cellpadding="5" style="margin-top:20px;width:100%">
    <thead>
        <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Reg No</th>
            <?php for ($i=1; $i<=6; $i++) echo "<th>A$i Grade</th>"; ?>
        </tr>
    </thead>
    <tbody>
        <?php
        $counter = 1;
        while ($student = $students->fetch_assoc()):
            echo "<tr>";
            echo "<td>{$counter}</td>";
            echo "<td>".htmlspecialchars($student['name'])."</td>";
            echo "<td>".htmlspecialchars($student['reg_no'])."</td>";
            for ($j=0; $j<6; $j++):
                if (isset($assignments[$j])):
                    $ass_id = $assignments[$j]['id'];
                    if (isset($submissions[$student['id']][$ass_id])):
                        $sub = $submissions[$student['id']][$ass_id];
                        echo "<td>".($sub['is_graded'] ? intval($sub['marks']) : "Pending")."</td>";
                    else:
                        echo "<td>Not Submitted</td>";
                    endif;
                else:
                    echo "<td>N/A</td>";
                endif;
            endfor;
            echo "</tr>";
            $counter++;
        endwhile;
        ?>
    </tbody>
</table>

<script>
$(document).ready(function(){
    $('#assignmentsTable').DataTable();

    function generatePDF(filename){
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({orientation:'landscape'});
        doc.autoTable({ html: '#assignmentsTable' });
        doc.save(filename);
    }

    $('#generateAssignmentsPDF').click(function(){
        generatePDF('Assignments_<?php echo preg_replace("/\s+/", "_", $unit_name); ?>.pdf');
    });

    $('#generateSubmissionsPDF').click(function(){
        // Here you can make another table or fetch submissions data if needed
        alert('You can implement submissions PDF similarly.');
    });
});
</script>
</body>
</html>
