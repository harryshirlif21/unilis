<?php

/**
 * Returns true when the student is already a member of another team in the same unit.
 * This allows the same team to be reused in other units/years, but keeps membership
 * limited to one team per unit for each student.
 */
function team_membership_has_other_team_for_unit(mysqli $conn, int $teamId, int $studentId, ?int $unitId = null): bool
{
    if ($unitId === null || $unitId <= 0) {
        require_once __DIR__ . '/ensure_team_registrations.php';
        ensure_team_registrations_tables($conn);

        $teamStmt = $conn->prepare('SELECT unit_id FROM teams WHERE id = ? LIMIT 1');
        if (!$teamStmt) {
            throw new Exception('Failed to resolve team unit: ' . $conn->error);
        }

        $teamStmt->bind_param('i', $teamId);
        $teamStmt->execute();
        $teamRow = $teamStmt->get_result()->fetch_assoc();
        $teamStmt->close();

        $unitId = (int) ($teamRow['unit_id'] ?? 0);

        if ($unitId <= 0) {
            $regStmt = $conn->prepare('
                SELECT unit_id
                FROM team_registrations
                WHERE team_id = ? AND status = \'active\'
                ORDER BY id ASC
                LIMIT 1
            ');
            if ($regStmt) {
                $regStmt->bind_param('i', $teamId);
                $regStmt->execute();
                $regRow = $regStmt->get_result()->fetch_assoc();
                $regStmt->close();
                $unitId = (int) ($regRow['unit_id'] ?? 0);
            }
        }
    }

    if ($unitId <= 0) {
        return false;
    }

    require_once __DIR__ . '/ensure_team_registrations.php';
    ensure_team_registrations_tables($conn);

    $stmt = $conn->prepare(
        "SELECT 1
         FROM team_members tm
         JOIN team_registrations tr ON tr.team_id = tm.team_id AND tr.status = 'active'
         WHERE tm.student_id = ?
           AND tr.unit_id = ?
           AND tm.team_id != ?
         LIMIT 1"
    );
    if (!$stmt) {
        throw new Exception('Failed to check team membership: ' . $conn->error);
    }

    $stmt->bind_param('iii', $studentId, $unitId, $teamId);
    $stmt->execute();
    $hasRow = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $hasRow;
}

/**
 * Throw if the student is already a member of another team in the same unit.
 */
function ensure_student_not_in_other_team_for_unit(mysqli $conn, int $teamId, int $studentId, ?int $unitId = null): void
{
    if (team_membership_has_other_team_for_unit($conn, $teamId, $studentId, $unitId)) {
        throw new Exception('This student is already a member of another team in this unit. Each student can only be a member of one team per unit.');
    }
}

/**
 * Resolve a student's course and year of study.
 *
 * @return array{course_id:int,year_of_study:int}
 */
function team_get_student_course_year(mysqli $conn, int $studentId): array
{
    $stmt = $conn->prepare('SELECT course_id, year_of_study FROM students WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to load student profile: ' . $conn->error);
    }

    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception('Student record not found');
    }

    $courseId = (int) ($row['course_id'] ?? 0);
    $yearOfStudy = (int) ($row['year_of_study'] ?? 0);

    if ($courseId <= 0 || $yearOfStudy <= 0) {
        throw new Exception('Could not determine your course or year');
    }

    return [
        'course_id' => $courseId,
        'year_of_study' => $yearOfStudy,
    ];
}

/**
 * Units the student is registered for (same rules as team creation).
 *
 * @return list<array<string, mixed>>
 */
function team_get_enrolled_units_for_student(mysqli $conn, int $studentId): array
{
    $profile = team_get_student_course_year($conn, $studentId);

    $stmt = $conn->prepare("
        SELECT id, code, name, semester
        FROM units
        WHERE course_id = ?
          AND year = ?
          AND semester IN (1, 2)
        ORDER BY semester ASC, code ASC
    ");
    if (!$stmt) {
        throw new Exception('Failed to load enrolled units: ' . $conn->error);
    }

    $stmt->bind_param('ii', $profile['course_id'], $profile['year_of_study']);
    $stmt->execute();
    $result = $stmt->get_result();

    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    $stmt->close();

    return $units;
}

/**
 * Ensure the unit belongs to the student's registered course/year.
 */
function team_validate_unit_for_student(mysqli $conn, int $studentId, int $unitId): void
{
    if ($unitId <= 0) {
        throw new Exception('A valid unit is required');
    }

    $profile = team_get_student_course_year($conn, $studentId);

    $stmt = $conn->prepare('SELECT id FROM units WHERE id = ? AND course_id = ? AND year = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to validate unit: ' . $conn->error);
    }

    $stmt->bind_param('iii', $unitId, $profile['course_id'], $profile['year_of_study']);
    $stmt->execute();
    $hasUnit = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$hasUnit) {
        throw new Exception('Selected unit is not one of your registered units');
    }
}

/**
 * Ensure every current team member can belong to a team in the target unit.
 */
function ensure_team_members_can_use_unit(mysqli $conn, int $teamId, int $unitId): void
{
    $stmt = $conn->prepare('SELECT student_id FROM team_members WHERE team_id = ?');
    if (!$stmt) {
        throw new Exception('Failed to load team members: ' . $conn->error);
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        ensure_student_not_in_other_team_for_unit($conn, $teamId, (int) $row['student_id'], $unitId);
    }

    $stmt->close();
}

function team_student_is_member_of_team(mysqli $conn, int $teamId, int $studentId): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $teamId, $studentId);
    $stmt->execute();
    $isMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $isMember;
}
