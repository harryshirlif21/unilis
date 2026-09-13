<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    exit('Access denied.');
}

$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$lecturerId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT a.title, u.name AS unit_name, u.code AS unit_code, c.name AS course_name
    FROM assignments a
    JOIN units u ON u.id = a.unit_id
    LEFT JOIN courses c ON c.id = u.course_id
    JOIN lecturer_units lu ON lu.unit_id = a.unit_id AND lu.lecturer_id = ?
    WHERE a.id = ?
");
$stmt->bind_param('ii', $lecturerId, $assignmentId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found.');
}

$stmt = $conn->prepare("
    SELECT st.name AS student_name, st.reg_no, COALESCE(c.name, '') AS student_course,
           u.name AS unit_name, s.marks
    FROM submissions s
    JOIN students st ON st.id = s.student_id
    LEFT JOIN courses c ON c.id = st.course_id
    JOIN assignments a ON a.id = s.assignment_id
    JOIN units u ON u.id = a.unit_id
    WHERE s.assignment_id = ?
    ORDER BY st.name ASC
");
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$html = '<h1>' . $h($assignment['title']) . '</h1>';
$html .= '<p><strong>Course:</strong> ' . $h($assignment['course_name'] ?: 'Not specified') .
    ' &nbsp; <strong>Unit:</strong> ' . $h($assignment['unit_name']) . ' (' . $h($assignment['unit_code']) . ')' .
    ' &nbsp; <strong>Students submitted:</strong> ' . count($rows) . '</p>';
$html .= '<table><thead><tr><th>Student name</th><th>Registration number</th><th>Course</th><th>Unit name</th><th>Marks awarded</th></tr></thead><tbody>';
foreach ($rows as $row) {
    $html .= '<tr><td>' . $h($row['student_name']) . '</td><td>' . $h($row['reg_no']) .
        '</td><td>' . $h($row['student_course'] ?: $assignment['course_name']) . '</td><td>' . $h($row['unit_name']) .
        '</td><td>' . ($row['marks'] === null ? 'Not graded' : $h($row['marks'])) . '</td></tr>';
}
if (!$rows) {
    $html .= '<tr><td colspan="5">No submissions recorded.</td></tr>';
}
$html .= '</tbody></table>';

$pdf = new Dompdf\Dompdf();
$pdf->loadHtml('<style>body{font-family:Arial,sans-serif;font-size:11px}h1{color:#1e3a8a}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left}th{background:#dbeafe}</style>' . $html);
$pdf->setPaper('A4', 'landscape');
$pdf->render();
$pdf->stream('assignment-submissions-' . $assignmentId . '.pdf', ['Attachment' => true]);
