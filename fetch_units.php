<?php
require_once 'config/db.php';

$course_id = $_GET['course_id'] ?? 0;
$lecturer_id = $_SESSION['user_id'] ?? 0;

$sql = "SELECT id, name, code FROM units 
        WHERE course_id = ? 
        AND id NOT IN (
            SELECT unit_id FROM lecturer_units WHERE lecturer_id = ?
        )";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $course_id, $lecturer_id);
$stmt->execute();
$result = $stmt->get_result();

$units = [];
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}

echo json_encode($units);
