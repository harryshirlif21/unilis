<?php
// debug_supervisor.php
require_once __DIR__ . '/config/db.php';

$unit_id = 65;

$sql = "
    SELECT l.id, l.name, l.email
    FROM lecturers l
    JOIN lecturer_units lu ON l.id = lu.lecturer_id
    WHERE lu.unit_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $unit_id);
$stmt->execute();
$result = $stmt->get_result();
$lecturer = $result->fetch_assoc();
$stmt->close();

if ($lecturer) {
    echo "<h2>Lecturer for Unit ID: $unit_id</h2>";
    echo "<pre>";
    print_r($lecturer);
    echo "</pre>";
} else {
    echo "<h2>No lecturer found for Unit ID: $unit_id</h2>";
}
