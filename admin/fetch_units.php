<?php
include 'db.php';
$course_id = intval($_GET['course_id']);

$stmt = $conn->prepare("SELECT u.id, u.name AS unit_name, u.code AS unit_code, u.year, u.semester,
                        c.name AS course_name, d.name AS department
                        FROM units u
                        JOIN courses c ON u.course_id = c.id
                        JOIN departments d ON c.department_id = d.id
                        WHERE u.course_id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

$units = [];
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
echo json_encode($units);
?>
