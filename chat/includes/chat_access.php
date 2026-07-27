<?php
/**
 * Identity and permission rules for the Chat module.
 *
 * A "user" here is always the pair (id, role). Student ids and lecturer ids are
 * separate auto-increment sequences, so student 7 and lecturer 7 are different
 * people and an id alone is never enough to identify anyone.
 */

/**
 * The signed-in chat user, or null when nobody is signed in or the role cannot
 * take part in chat.
 *
 * Admins are deliberately excluded: they have no course, no units and no teams,
 * so they are in nobody's directory and would see an empty product.
 */
function chat_current_user(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $id = (int)($_SESSION['user_id'] ?? 0);
    $role = (string)($_SESSION['user_role'] ?? '');

    if ($id <= 0 || !in_array($role, ['student', 'lecturer'], true)) {
        return null;
    }

    return ['id' => $id, 'role' => $role];
}

/**
 * Emit a JSON response and stop.
 */
function chat_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

/**
 * The signed-in chat user, or a 401 JSON response. For API endpoints.
 */
function chat_require_user(): array
{
    $user = chat_current_user();
    if ($user === null) {
        chat_json(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    return $user;
}

/**
 * Request body for an API endpoint: JSON if sent that way, else form fields.
 */
function chat_request_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST;
}

/**
 * The session CSRF token, minting one if this session has none yet.
 */
function chat_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Reject the request unless it carries the session CSRF token.
 */
function chat_require_csrf(array $input): void
{
    $token = (string)($input['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        chat_json(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }
}

/**
 * Name and email for a user, or null when the row is gone.
 */
function chat_user_profile(mysqli $conn, int $userId, string $role): ?array
{
    $table = $role === 'lecturer' ? 'lecturers' : 'students';

    $stmt = $conn->prepare("SELECT id, name, email FROM `$table` WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'role' => $role,
        'name' => (string)$row['name'],
        'email' => (string)$row['email'],
    ];
}

/**
 * The caller's participant row for a conversation, or null when they are not a
 * member. Membership is the only read permission in this module - if you are
 * not in chat_participants for a conversation, it does not exist for you.
 */
function chat_participant_row(mysqli $conn, int $conversationId, array $user): ?array
{
    $stmt = $conn->prepare("
        SELECT id, conversation_id, can_post, last_read_message_id, muted
        FROM chat_participants
        WHERE conversation_id = ? AND user_id = ? AND user_role = ?
        LIMIT 1
    ");
    $stmt->bind_param('iis', $conversationId, $user['id'], $user['role']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Unit ids a lecturer teaches.
 */
function chat_lecturer_unit_ids(mysqli $conn, int $lecturerId): array
{
    $stmt = $conn->prepare("SELECT unit_id FROM lecturer_units WHERE lecturer_id = ?");
    $stmt->bind_param('i', $lecturerId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn($r) => (int)$r['unit_id'], $rows);
}

/**
 * Unit ids a student is enrolled in. Empty when the install has no enrolment
 * table at all.
 */
function chat_student_unit_ids(mysqli $conn, int $studentId): array
{
    $table = chat_enrollment_table($conn);
    if ($table === null) {
        return [];
    }

    $stmt = $conn->prepare("SELECT unit_id FROM `$table` WHERE student_id = ?");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn($r) => (int)$r['unit_id'], $rows);
}

/**
 * Whether the caller may open a direct thread with the given person.
 *
 * Students may reach anyone they study alongside - same unit, same team or same
 * course - plus the lecturers teaching the units they are enrolled in.
 * Lecturers may reach students enrolled in the units they teach, plus lecturers
 * they share a unit or a department with.
 *
 * Each rule is an EXISTS probe rather than a directory scan, so the check stays
 * cheap on a course with thousands of students.
 */
function chat_can_contact(mysqli $conn, array $user, int $targetId, string $targetRole): bool
{
    if (!in_array($targetRole, ['student', 'lecturer'], true)) {
        return false;
    }
    if ($targetId === $user['id'] && $targetRole === $user['role']) {
        return false; // No talking to yourself.
    }
    if (chat_user_profile($conn, $targetId, $targetRole) === null) {
        return false;
    }

    $enrolments = chat_enrollment_table($conn);

    if ($user['role'] === 'student' && $targetRole === 'student') {
        // Same course.
        $stmt = $conn->prepare("
            SELECT 1 FROM students me
            JOIN students them ON them.course_id = me.course_id
            WHERE me.id = ? AND them.id = ? AND me.course_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->bind_param('ii', $user['id'], $targetId);
        $stmt->execute();
        $hit = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($hit) {
            return true;
        }

        // Same unit.
        if ($enrolments !== null) {
            $stmt = $conn->prepare("
                SELECT 1 FROM `$enrolments` mine
                JOIN `$enrolments` theirs ON theirs.unit_id = mine.unit_id
                WHERE mine.student_id = ? AND theirs.student_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ii', $user['id'], $targetId);
            $stmt->execute();
            $hit = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($hit) {
                return true;
            }
        }

        // Same team.
        if (chat_teams_available($conn)) {
            $stmt = $conn->prepare("
                SELECT 1 FROM team_members mine
                JOIN team_members theirs ON theirs.team_id = mine.team_id
                WHERE mine.student_id = ? AND theirs.student_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ii', $user['id'], $targetId);
            $stmt->execute();
            $hit = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($hit) {
                return true;
            }
        }

        return false;
    }

    // Student -> lecturer, and lecturer -> student, are the same relationship
    // read from either end: the lecturer teaches a unit the student is in.
    if ($user['role'] === 'student' && $targetRole === 'lecturer') {
        return chat_teaches_student($conn, $targetId, $user['id']);
    }
    if ($user['role'] === 'lecturer' && $targetRole === 'student') {
        return chat_teaches_student($conn, $user['id'], $targetId);
    }

    // Lecturer -> lecturer: a shared unit, or the same department.
    $stmt = $conn->prepare("
        SELECT 1
        FROM lecturer_units mine
        JOIN lecturer_units theirs ON theirs.unit_id = mine.unit_id
        WHERE mine.lecturer_id = ? AND theirs.lecturer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $user['id'], $targetId);
    $stmt->execute();
    $hit = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($hit) {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT 1 FROM lecturers me
        JOIN lecturers them ON them.department_id = me.department_id
        WHERE me.id = ? AND them.id = ? AND me.department_id IS NOT NULL
        LIMIT 1
    ");
    $stmt->bind_param('ii', $user['id'], $targetId);
    $stmt->execute();
    $hit = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $hit;
}

/**
 * Whether a lecturer teaches a unit the student is enrolled in.
 */
function chat_teaches_student(mysqli $conn, int $lecturerId, int $studentId): bool
{
    $enrolments = chat_enrollment_table($conn);
    if ($enrolments === null) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM lecturer_units lu
        JOIN `$enrolments` e ON e.unit_id = lu.unit_id
        WHERE lu.lecturer_id = ? AND e.student_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $lecturerId, $studentId);
    $stmt->execute();
    $hit = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $hit;
}

/**
 * People the caller may start a direct chat with, newest rules mirrored from
 * chat_can_contact(). $search filters on name, registration number or email.
 *
 * Results are capped: a whole-course directory is long, and the picker is a
 * search box rather than a browsable roster.
 */
function chat_directory(mysqli $conn, array $user, string $search = '', int $limit = 40): array
{
    $like = '%' . $search . '%';
    $verified = chat_verified_student_clause($conn, 's');
    $enrolments = chat_enrollment_table($conn);
    $people = [];

    /** Merge rows in, keeping one entry per (role, id). */
    $collect = static function (array $rows, string $role, string $relation) use (&$people): void {
        foreach ($rows as $row) {
            $key = $role . ':' . $row['id'];
            if (isset($people[$key])) {
                continue;
            }
            $people[$key] = [
                'id' => (int)$row['id'],
                'role' => $role,
                'name' => (string)$row['name'],
                'subtitle' => trim((string)($row['subtitle'] ?? '')) ?: $relation,
                'relation' => $relation,
            ];
        }
    };

    if ($user['role'] === 'student') {
        // Classmates on the same course.
        $sql = "
            SELECT DISTINCT s.id, s.name, CONCAT(COALESCE(s.reg_no, ''), ' · Year ', COALESCE(s.year_of_study, '?')) AS subtitle
            FROM students me
            JOIN students s ON s.course_id = me.course_id
            WHERE me.id = ? AND me.course_id IS NOT NULL AND s.id <> me.id
              AND (s.name LIKE ? OR s.reg_no LIKE ? OR s.email LIKE ?)
              $verified
            ORDER BY s.name
            LIMIT ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isssi', $user['id'], $like, $like, $like, $limit);
        $stmt->execute();
        $collect($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'student', 'Classmate');
        $stmt->close();

        // Students sharing a unit but not necessarily the course.
        if ($enrolments !== null) {
            $sql = "
                SELECT DISTINCT s.id, s.name, CONCAT(COALESCE(s.reg_no, ''), ' · ', u.code) AS subtitle
                FROM `$enrolments` mine
                JOIN `$enrolments` theirs ON theirs.unit_id = mine.unit_id
                JOIN students s ON s.id = theirs.student_id
                JOIN units u ON u.id = mine.unit_id
                WHERE mine.student_id = ? AND s.id <> ?
                  AND (s.name LIKE ? OR s.reg_no LIKE ? OR s.email LIKE ?)
                  $verified
                ORDER BY s.name
                LIMIT ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iisssi', $user['id'], $user['id'], $like, $like, $like, $limit);
            $stmt->execute();
            $collect($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'student', 'Shares a unit');
            $stmt->close();

            // Lecturers teaching the student's units.
            $sql = "
                SELECT DISTINCT l.id, l.name, GROUP_CONCAT(DISTINCT u.code ORDER BY u.code SEPARATOR ', ') AS subtitle
                FROM `$enrolments` e
                JOIN lecturer_units lu ON lu.unit_id = e.unit_id
                JOIN lecturers l ON l.id = lu.lecturer_id
                JOIN units u ON u.id = e.unit_id
                WHERE e.student_id = ?
                  AND (l.name LIKE ? OR l.email LIKE ?)
                GROUP BY l.id, l.name
                ORDER BY l.name
                LIMIT ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issi', $user['id'], $like, $like, $limit);
            $stmt->execute();
            $collect($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'lecturer', 'Lecturer');
            $stmt->close();
        }

        // Teammates, last so a shared team does not overwrite a richer subtitle.
        if (chat_teams_available($conn)) {
            $sql = "
                SELECT DISTINCT s.id, s.name, t.title AS subtitle
                FROM team_members mine
                JOIN team_members theirs ON theirs.team_id = mine.team_id
                JOIN students s ON s.id = theirs.student_id
                JOIN teams t ON t.id = mine.team_id
                WHERE mine.student_id = ? AND s.id <> ?
                  AND (s.name LIKE ? OR s.reg_no LIKE ? OR s.email LIKE ?)
                  $verified
                ORDER BY s.name
                LIMIT ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iisssi', $user['id'], $user['id'], $like, $like, $like, $limit);
            $stmt->execute();
            $collect($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'student', 'Teammate');
            $stmt->close();
        }
    } else {
        // Lecturer: students in the units they teach.
        if ($enrolments !== null) {
            $sql = "
                SELECT DISTINCT s.id, s.name, CONCAT(COALESCE(s.reg_no, ''), ' · ', u.code) AS subtitle
                FROM lecturer_units lu
                JOIN `$enrolments` e ON e.unit_id = lu.unit_id
                JOIN students s ON s.id = e.student_id
                JOIN units u ON u.id = lu.unit_id
                WHERE lu.lecturer_id = ?
                  AND (s.name LIKE ? OR s.reg_no LIKE ? OR s.email LIKE ?)
                  $verified
                ORDER BY s.name
                LIMIT ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isssi', $user['id'], $like, $like, $like, $limit);
            $stmt->execute();
            $collect($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'student', 'Student');
            $stmt->close();
        }

        // Colleagues: shared unit or shared department.
        $sql = "
            SELECT DISTINCT l.id, l.name, COALESCE(d.name, 'Lecturer') AS subtitle
            FROM lecturers me
            JOIN lecturers l ON (
                l.department_id = me.department_id
                OR l.id IN (
                    SELECT theirs.lecturer_id
                    FROM lecturer_units mine
                    JOIN lecturer_units theirs ON theirs.unit_id = mine.unit_id
                    WHERE mine.lecturer_id = me.id
                )
            )
            LEFT JOIN departments d ON d.id = l.department_id
            WHERE me.id = ? AND l.id <> me.id
              AND (l.name LIKE ? OR l.email LIKE ?)
            ORDER BY l.name
            LIMIT ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issi', $user['id'], $like, $like, $limit);
        $stmt->execute();
        $collect($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'lecturer', 'Colleague');
        $stmt->close();
    }

    $list = array_values($people);
    usort($list, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

    return array_slice($list, 0, $limit);
}
