<?php
// ============================================================
// HELPER SNIPPET — include or copy into any file that needs
// the student's currently enrolled units.
// ============================================================

// Assumes: $conn, $student_id, and optionally $semester (default 1)
// and $academic_year (default current year/next year)

$semester      = intval($_GET['semester'] ?? $_SESSION['semester'] ?? 1);
$academic_year = $_SESSION['academic_year'] ?? (date('Y') . '/' . (date('Y') + 1));

$enrolled_units = [];
try {
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.code
        FROM units u
        JOIN student_unit_enrollments sue
             ON sue.unit_id = u.id
        WHERE sue.student_id    = ?
          AND sue.semester      = ?
          AND sue.academic_year = ?
        ORDER BY u.name ASC
    ");
    $stmt->bind_param("iis", $student_id, $semester, $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $enrolled_units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("enrolled_units fetch: " . $e->getMessage());
}

// $enrolled_units now contains all units the student is doing this semester.
// Use $enrolled_units in dropdowns, course_view, lesson_view, etc.