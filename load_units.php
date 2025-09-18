<?php
require_once 'config/db.php';

if (isset($_GET['course_id'])) {
    $course_id = intval($_GET['course_id']);
    $stmt = $conn->prepare("SELECT id, name FROM units WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }

    echo json_encode($units);
}
?>
