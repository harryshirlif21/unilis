<?php
session_start();
include 'db.php';
require_once 'vendor/autoload.php'; // for Dompdf
use Dompdf\Dompdf;

$action = $_REQUEST['action'] ?? '';

// =================== VIEW COURSE UNITS (AJAX) ===================
if ($action === 'get_course_units') {
    $course_id = intval($_REQUEST['course_id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT c.id AS course_id, c.name AS course_name, d.name AS department_name,
               u.id AS unit_id, u.name AS unit_name, u.code AS unit_code, u.year, u.semester
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        LEFT JOIN units u ON c.id = u.course_id
        WHERE c.id = ?
        ORDER BY u.year, u.semester, u.name
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $course = null;
    $units = [];
    while ($row = $result->fetch_assoc()) {
        if (!$course) {
            $course = [
                'course_id' => $row['course_id'],
                'course_name' => $row['course_name'],
                'department_name' => $row['department_name']
            ];
        }
        if ($row['unit_id']) {
            $units[] = [
                'id' => $row['unit_id'],
                'unit_name' => $row['unit_name'],
                'unit_code' => $row['unit_code'],
                'year' => $row['year'],
                'semester' => $row['semester']
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'course' => $course ?: ['course_id'=>$course_id,'course_name'=>'','department_name'=>''],
        'units' => $units
    ]);
    exit;
}

// =================== GENERATE PDF FOR COURSE UNITS ===================
if ($action === 'generate_unit_pdf') {
    $course_id = intval($_REQUEST['course_id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT c.name AS course_name, d.name AS department_name,
               u.name AS unit_name, u.code AS unit_code, u.year, u.semester
        FROM courses c
        JOIN departments d ON c.department_id = d.id
        LEFT JOIN units u ON c.id = u.course_id
        WHERE c.id = ?
        ORDER BY u.year, u.semester, u.name
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $course_name = '';
    $department_name = '';
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $course_name = $row['course_name'];
        $department_name = $row['department_name'];
        if ($row['unit_name']) {
            $units[] = $row;
        }
    }

    $html = "<h2>Units for Course: $course_name</h2>";
    $html .= "<p><strong>Department:</strong> $department_name</p>";
    $html .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    $html .= "<tr><th>Year</th><th>Semester</th><th>Unit Name</th><th>Unit Code</th></tr>";
    foreach ($units as $u) {
        $html .= "<tr>
            <td>{$u['year']}</td>
            <td>{$u['semester']}</td>
            <td>{$u['unit_name']}</td>
            <td>{$u['unit_code']}</td>
        </tr>";
    }
    $html .= "</table>";

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Units_$course_name.pdf", ["Attachment" => true]);
    exit;
}

// =================== DELETE UNIT ===================
if ($action === 'delete_unit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_id = intval($_POST['unit_id'] ?? 0);
    if ($unit_id) {
        $stmt = $conn->prepare("DELETE FROM units WHERE id = ?");
        $stmt->bind_param("i", $unit_id);
        if ($stmt->execute()) {
            echo json_encode(['status'=>'success','message'=>'Unit deleted successfully']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Failed to delete unit']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status'=>'error','message'=>'Invalid unit ID']);
    }
    exit;
}
