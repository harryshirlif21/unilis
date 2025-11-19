<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // DOMPDF autoload

use Dompdf\Dompdf;

// Check if lecturer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Get selected unit
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

$unit_name = $unitInfo['name'];
$course_id = $unitInfo['course_id'];

// --- Fetch course info ---
$courseQuery = $conn->prepare("SELECT name, university_id FROM courses WHERE id = ?");
$courseQuery->bind_param("i", $course_id);
$courseQuery->execute();
$courseInfo = $courseQuery->get_result()->fetch_assoc();
$courseQuery->close();

$course_name = $courseInfo['name'];
$university_id = $courseInfo['university_id'];

// --- Fetch university name ---
$uniQuery = $conn->prepare("SELECT name FROM universities WHERE id = ?");
$uniQuery->bind_param("i", $university_id);
$uniQuery->execute();
$university_name = $uniQuery->get_result()->fetch_assoc()['name'];
$uniQuery->close();

// --- Fetch all students in this unit (assuming all students enrolled in unit) ---
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

// --- Fetch up to 6 assignments for this unit ---
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

// --- Prepare HTML content for PDF ---
$html = '
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h2, h3 { text-align: center; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #000; }
        th, td { padding: 5px; text-align: center; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>' . htmlspecialchars($university_name) . '</h2>
    <h3>Course: ' . htmlspecialchars($course_name) . ' | Unit: ' . htmlspecialchars($unit_name) . '</h3>
    <h3>Lecturer: ' . htmlspecialchars($lecturer_name) . '</h3>
    <p style="text-align:center;">Generated on: ' . date('d-m-Y H:i') . '</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Reg No</th>';

// --- Table headers for A1 to A6 ---
for ($i = 1; $i <= 6; $i++) {
    $html .= '<th>A' . $i . ' Grade</th>';
}

$html .= '
            </tr>
        </thead>
        <tbody>
';

// --- Fill table rows for each student ---
$counter = 1;
while ($student = $students->fetch_assoc()) {
    $html .= '<tr>';
    $html .= '<td>' . $counter . '</td>';
    $html .= '<td>' . htmlspecialchars($student['name']) . '</td>';
    $html .= '<td>' . htmlspecialchars($student['reg_no']) . '</td>';

    // Loop through each assignment (1-6)
    for ($j = 0; $j < 6; $j++) {
        if (isset($assignments[$j])) {
            $ass_id = $assignments[$j]['id'];

            // Fetch submission info
            $subQuery = $conn->prepare("
                SELECT marks, is_graded 
                FROM submissions 
                WHERE student_id = ? AND assignment_id = ?
            ");
            $subQuery->bind_param("ii", $student['id'], $ass_id);
            $subQuery->execute();
            $sub = $subQuery->get_result()->fetch_assoc();
            $subQuery->close();

            if (!$sub) {
                $html .= '<td>Not Submitted</td>';
            } else if ($sub['is_graded']) {
                $html .= '<td>' . intval($sub['marks']) . '</td>';
            } else {
                $html .= '<td>Pending</td>';
            }
        } else {
            $html .= '<td>N/A</td>';
        }
    }

    $html .= '</tr>';
    $counter++;
}

$html .= '
        </tbody>
    </table>
</body>
</html>
';

// --- Generate PDF using DOMPDF ---
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$filename = 'Assignment_Report_' . preg_replace('/\s+/', '_', $unit_name) . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
exit;
?>

