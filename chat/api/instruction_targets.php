<?php
/**
 * GET /chat/api/instruction_targets.php
 *
 * What a lecturer may address an instruction to: the units they teach, and the
 * courses those units belong to (whole course, or one year of it). Populates
 * the target picker in the instruction composer.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    if ($chatUser['role'] !== 'lecturer') {
        chat_json(['success' => false, 'error' => 'Only lecturers can post instructions'], 403);
    }

    $enrolments = chat_enrollment_table($conn);
    $verified = chat_verified_student_clause($conn, 's');

    // Recipient counts come from the same tables the fan-out will use, so the
    // number shown in the composer is the number that will actually be reached.
    $unitCount = $enrolments !== null
        ? "(SELECT COUNT(*) FROM `$enrolments` e JOIN students s ON s.id = e.student_id
            WHERE e.unit_id = u.id $verified)"
        : '0';

    $stmt = $conn->prepare("
        SELECT u.id, u.code, u.name, u.course_id, c.name AS course_name,
               $unitCount AS student_count
        FROM lecturer_units lu
        JOIN units u ON u.id = lu.unit_id
        LEFT JOIN courses c ON c.id = u.course_id
        WHERE lu.lecturer_id = ?
        ORDER BY u.code, u.name
    ");
    $stmt->bind_param('i', $chatUser['id']);
    $stmt->execute();
    $unitRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $units = [];
    $courses = [];

    foreach ($unitRows as $row) {
        $units[] = [
            'unit_id' => (int)$row['id'],
            'code' => (string)$row['code'],
            'name' => (string)$row['name'],
            'label' => trim((string)$row['code'] . ' ' . (string)$row['name']),
            'student_count' => (int)$row['student_count'],
        ];

        if ($row['course_id'] !== null) {
            $courses[(int)$row['course_id']] = (string)($row['course_name'] ?? 'Course');
        }
    }

    $courseTargets = [];
    foreach ($courses as $courseId => $courseName) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total FROM students s WHERE s.course_id = ? $verified
        ");
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT s.year_of_study, COUNT(*) AS total
            FROM students s
            WHERE s.course_id = ? AND s.year_of_study IS NOT NULL AND s.year_of_study > 0 $verified
            GROUP BY s.year_of_study
            ORDER BY s.year_of_study
        ");
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $years = array_map(
            static fn($r) => ['year' => (int)$r['year_of_study'], 'student_count' => (int)$r['total']],
            $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
        );
        $stmt->close();

        $courseTargets[] = [
            'course_id' => $courseId,
            'name' => $courseName,
            'student_count' => $total,
            'years' => $years,
        ];
    }

    chat_json([
        'success' => true,
        'units' => $units,
        'courses' => $courseTargets,
    ]);
} catch (Throwable $e) {
    error_log('chat/instruction_targets: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load instruction targets'], 500);
}
