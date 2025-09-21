<?php
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

$response = ["status" => "error", "message" => "Unknown error"];

try {
    $course_id = intval($_GET['course_id'] ?? 0);

    if (!$course_id) {
        echo json_encode([
            "status" => "error",
            "message" => "Missing course_id"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT u.id, u.name AS unit_name, u.code AS unit_code, u.year, u.semester,
               c.name AS course_name, d.name AS department_name
        FROM units u
        JOIN courses c ON u.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        WHERE u.course_id = ?
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $units = [];
    $course_name = "";
    $department_name = "";

    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
        // Capture course/department from first row
        if (!$course_name && !$department_name) {
            $course_name = $row['course_name'];
            $department_name = $row['department_name'];
        }
    }

    // If no units found, still fetch course + department name
    if (empty($units)) {
        $stmt2 = $conn->prepare("
            SELECT c.name AS course_name, d.name AS department_name
            FROM courses c
            JOIN departments d ON c.department_id = d.id
            WHERE c.id = ?
        ");
        $stmt2->bind_param("i", $course_id);
        $stmt2->execute();
        $courseResult = $stmt2->get_result();
        if ($courseRow = $courseResult->fetch_assoc()) {
            $course_name = $courseRow['course_name'];
            $department_name = $courseRow['department_name'];
        }
        $stmt2->close();
    }

    $response = [
        "status" => "success",
        "course" => [
            "id"              => $course_id,
            "course_name"     => $course_name,
            "department_name" => $department_name
        ],
        "units" => $units // will be [] if no rows
    ];

} catch (Exception $e) {
    $response = [
        "status" => "error",
        "message" => $e->getMessage()
    ];
}

echo json_encode($response);
exit;
?>
