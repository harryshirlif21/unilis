<?php
//require_once '../config/db.php';
//require_once '../tcpdf/tcpdf.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    die("Access denied.");
}

if (!isset($_GET['unit_id'])) {
    die("Unit not specified.");
}

$unit_id = (int)$_GET['unit_id'];

// Fetch unit name
$stmt = $conn->prepare("SELECT name FROM units WHERE id = ?");
$stmt->bind_param("i", $unit_id);
$stmt->execute();
$stmt->bind_result($unit_name);
$stmt->fetch();
$stmt->close();

// Fetch all assignments for the unit
$assignments = [];
$res = $conn->query("SELECT id, title FROM assignments WHERE unit_id = $unit_id ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $assignments[] = $row;
}

// Fetch students who have submitted or are enrolled for this unit (using distinct on submissions)
$students = [];
$sql = "SELECT DISTINCT s.id, s.name, s.reg_no FROM students s 
        JOIN submissions sub ON sub.student_id = s.id 
        JOIN assignments a ON sub.assignment_id = a.id 
        WHERE a.unit_id = $unit_id";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $students[] = $row;
}

// Initialize TCPDF
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

$html = "<h2>Submission Report - $unit_name</h2>";
$html .= "<table border='1' cellpadding='4'>";

// Header row
$html .= "<tr><th>Student Name</th><th>Reg No</th>";
foreach ($assignments as $a) {
    $html .= "<th>" . htmlspecialchars($a['title']) . "<br>✔️</th>";
    $html .= "<th>Marks</th><th>Comment</th><th>View?</th>";
}
$html .= "<th>Total Submitted</th><th>Out Of</th></tr>";

foreach ($students as $s) {
    $submitted_count = 0;
    $html .= "<tr><td>" . htmlspecialchars($s['name']) . "</td><td>" . htmlspecialchars($s['reg_no']) . "</td>";

    foreach ($assignments as $a) {
        $stmt = $conn->prepare("SELECT file_path, marks, comment, is_graded FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1");
        $stmt->bind_param("ii", $a['id'], $s['id']);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $submitted_count++;
            $mark = $data['is_graded'] ? $data['marks'] : '-';
            $comment = $data['is_graded'] ? htmlspecialchars($data['comment']) : '-';
            $view = $data['is_graded'] ? '✅' : '❌';
            $html .= "<td>✔️</td><td>$mark</td><td>$comment</td><td>$view</td>";
        } else {
            $html .= "<td>❌</td><td>-</td><td>-</td><td>❌</td>";
        }
        $stmt->close();
    }

    $html .= "<td>$submitted_count</td><td>" . count($assignments) . "</td></tr>";
}

$html .= "</table>";
$pdf->writeHTML($html);
$pdf->Output("submission_report_unit_$unit_id.pdf", 'I');
exit;