<?php
// Place at: C:\xampp\htdocs\unilis\lecturer\debug_submissions.php
// Visit: http://localhost:8080/unilis/lecturer/debug_submissions.php?unit_id=YOUR_UNIT_ID
// DELETE THIS FILE after debugging.
session_start();
require_once '../config/db.php';
header('Content-Type: text/plain');

$unit_id     = intval($_GET['unit_id'] ?? 0);
$lecturer_id = intval($_SESSION['user_id'] ?? 0);

echo "=== SESSION ===\n";
echo "user_id:   " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "user_role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n\n";

echo "=== UNIT ID: $unit_id ===\n\n";

// 1. Check assessments
echo "--- assessments for unit $unit_id ---\n";
$r = $conn->query("SELECT id, title, type, lecturer_id, is_published FROM assessments WHERE unit_id = $unit_id");
while ($row = $r->fetch_assoc()) print_r($row);

// 2. Check submissions
echo "\n--- ALL submissions for unit's assessments ---\n";
$r = $conn->query("
    SELECT asub.id, asub.assessment_id, asub.student_id, asub.score, asub.status, asub.submitted_at
    FROM assessment_submissions asub
    JOIN assessments a ON a.id = asub.assessment_id
    WHERE a.unit_id = $unit_id
");
while ($row = $r->fetch_assoc()) print_r($row);

// 3. Check enrolled students
echo "\n--- student_unit_enrollments for unit $unit_id ---\n";
$r = $conn->query("SELECT * FROM student_unit_enrollments WHERE unit_id = $unit_id LIMIT 20");
while ($row = $r->fetch_assoc()) print_r($row);

// 4. Check users table columns
echo "\n--- users table columns ---\n";
$r = $conn->query("SHOW COLUMNS FROM users");
while ($row = $r->fetch_assoc()) echo $row['Field']."\t".$row['Type']."\n";

// 5. Check students table columns
echo "\n--- students table columns ---\n";
$r = $conn->query("SHOW COLUMNS FROM students");
while ($row = $r->fetch_assoc()) echo $row['Field']."\t".$row['Type']."\n";

// 6. Check student_progress
echo "\n--- student_progress for unit $unit_id (limit 10) ---\n";
$r = $conn->query("SELECT * FROM student_progress WHERE unit_id = $unit_id LIMIT 10");
while ($row = $r->fetch_assoc()) print_r($row);

echo "\n=== DONE ===\n";