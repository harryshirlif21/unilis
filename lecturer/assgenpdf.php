<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // DOMPDF autoload

use Dompdf\Dompdf;

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

if (!$unitInfo) {
    die("Unit not found.");
}

$unit_name = $unitInfo['name'];
$course_id = $unitInfo['course_id'];

// --- Fetch course info (and its department for university) ---
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

if (!$courseInfo) {
    die("Course not found.");
}

$course_name = $courseInfo['course_name'];
$university_id = $courseInfo['university_id'];

// --- Fetch university name ---
$university_name = "Unknown University";
if ($university_id) {
    $uniQuery = $conn->prepare("SELECT name FROM universities WHERE id = ?");
    $uniQuery->bind_param("i", $university_id);
    $uniQuery->execute();
    $uniInfo = $uniQuery->get_result()->fetch_assoc();
    $uniQuery->close();

    if ($uniInfo) {
        $university_name = $uniInfo['name'];
    }
}

// --- Fetch all students in this unit ---
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

// --- Generate HTML ---
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

// --- Preload all submissions for all students for efficiency ---
$assignment_ids = array_column($assignments, 'id');
$submissions = [];

if (!empty($assignment_ids)) {
    $in = implode(',', array_fill(0, count($assignment_ids), '?'));
    $types = str_repeat('i', count($assignment_ids));
    
    $stmt = $conn->prepare("
        SELECT student_id, assignment_id, marks, is_graded
        FROM submissions
        WHERE assignment_id IN ($in)
    ");
    $stmt->bind_param($types, ...$assignment_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $submissions[$row['student_id']][$row['assignment_id']] = $row;
    }
    $stmt->close();
}

// --- Fill table rows for each student ---
$counter = 1;
while ($student = $students->fetch_assoc()) {
    $html .= '<tr>';
    $html .= '<td>' . $counter . '</td>';
    $html .= '<td>' . htmlspecialchars($student['name']) . '</td>';
    $html .= '<td>' . htmlspecialchars($student['reg_no']) . '</td>';

    for ($j = 0; $j < 6; $j++) {
        if (isset($assignments[$j])) {
            $ass_id = $assignments[$j]['id'];

            if (isset($submissions[$student['id']][$ass_id])) {
                $sub = $submissions[$student['id']][$ass_id];
                if ($sub['is_graded']) {
                    $html .= '<td>' . intval($sub['marks']) . '</td>';
                } else {
                    $html .= '<td>Pending</td>';
                }
            } else {
                $html .= '<td>Not Submitted</td>';
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

// --- Generate PDF ---
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$filename = 'Assignment_Report_' . preg_replace('/\s+/', '_', $unit_name) . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
exit;
?>
