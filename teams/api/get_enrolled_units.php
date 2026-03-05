<?php
require_once '../../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get course/year from session or DB
$course_id = $_SESSION['course_id'] ?? 0;
$year_of_study = $_SESSION['year_of_study'] ?? 0;

if (!$course_id || !$year_of_study) {
    $stmt = $conn->prepare("SELECT course_id, year_of_study FROM students WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $student = $res->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode(['success'=>false,'message'=>'Student not found']);
        exit;
    }

    $course_id = $student['course_id'];
    $year_of_study = $student['year_of_study'];

    $_SESSION['course_id'] = $course_id;
    $_SESSION['year_of_study'] = $year_of_study;
}

// Fetch all units for semester 1 and 2
$stmt = $conn->prepare("
    SELECT id, code, name, semester
    FROM units
    WHERE course_id = ?
      AND year = ?
      AND semester IN (1,2)
    ORDER BY semester ASC, code ASC
");
$stmt->bind_param("ii", $course_id, $year_of_study);
$stmt->execute();
$result = $stmt->get_result();

$units = [];
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();

echo json_encode(['success'=>true,'units'=>$units]);