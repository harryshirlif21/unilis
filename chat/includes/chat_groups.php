<?php
/**
 * Auto-created groups and their membership.
 *
 * Groups are not maintained by hooks on team/enrolment changes - there are too
 * many places in this codebase that write to team_members and enrolments for
 * that to stay correct. Instead each group is rebuilt from the source of truth
 * when it is next touched, throttled by chat_conversations.members_synced_at.
 * Joining a team therefore puts you in its chat group without anything having
 * had to remember to tell chat about it.
 *
 * Group kinds, keyed by the unique group_key column:
 *   team:{id}              members of a team, from team_members
 *   course:{id}:all        every student on a course, plus its lecturers
 *   course:{id}:y{n}       one year group of a course, plus its lecturers
 *   unit:{id}:announce     a unit's instruction channel; lecturers post,
 *                          enrolled students read
 *   dm:student:7|student:9 a direct thread; participant keys sorted so either
 *                          direction resolves to the same row
 */

/**
 * Create a conversation if its group_key is new, and return its id either way.
 *
 * ON DUPLICATE KEY UPDATE ... LAST_INSERT_ID(id) makes this a single atomic
 * statement: two users opening chat at the same moment cannot create two rows
 * for the same group, which a SELECT-then-INSERT would allow.
 */
function chat_upsert_conversation(mysqli $conn, string $groupKey, string $type, array $attrs = []): int
{
    $title = $attrs['title'] ?? null;
    $teamId = isset($attrs['team_id']) ? (int)$attrs['team_id'] : null;
    $courseId = isset($attrs['course_id']) ? (int)$attrs['course_id'] : null;
    $unitId = isset($attrs['unit_id']) ? (int)$attrs['unit_id'] : null;
    $year = (int)($attrs['year_of_study'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO chat_conversations
            (group_key, type, title, team_id, course_id, unit_id, year_of_study)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            title = VALUES(title)
    ");
    $stmt->bind_param('sssiiii', $groupKey, $type, $title, $teamId, $courseId, $unitId, $year);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();

    return $id;
}

/**
 * Add members to a conversation, refreshing can_post for anyone already in it.
 *
 * $members is a list of ['id' => int, 'role' => 'student'|'lecturer',
 * 'can_post' => bool].
 *
 * can_post is deliberately updated rather than left alone. This used to be an
 * INSERT IGNORE, which meant a change to the posting rules only reached people
 * who had not joined the conversation yet - when instruction channels became
 * two-way, every student already in one would have stayed silenced with no
 * explanation. Membership is derived state, so the sync owns it.
 */
function chat_add_participants(mysqli $conn, int $conversationId, array $members): int
{
    if (empty($members)) {
        return 0;
    }

    $stmt = $conn->prepare("
        INSERT INTO chat_participants
            (conversation_id, user_id, user_role, can_post)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE can_post = VALUES(can_post)
    ");

    $added = 0;
    foreach ($members as $member) {
        $id = (int)$member['id'];
        $role = $member['role'];
        $canPost = !empty($member['can_post']) ? 1 : 0;
        if ($id <= 0 || !in_array($role, ['student', 'lecturer'], true)) {
            continue;
        }
        $stmt->bind_param('iisi', $conversationId, $id, $role, $canPost);
        $stmt->execute();
        // affected_rows is 1 for a fresh insert and 2 for an update, so only
        // count the inserts as new members.
        $added += $stmt->affected_rows === 1 ? 1 : 0;
    }
    $stmt->close();

    return $added;
}

/**
 * Drop participants who are no longer in the source of truth - a student who
 * left a team, or transferred course. Direct threads are never pruned.
 */
function chat_prune_participants(mysqli $conn, int $conversationId, array $members): int
{
    $keep = [];
    foreach ($members as $member) {
        $keep[$member['role'] . ':' . (int)$member['id']] = true;
    }

    $stmt = $conn->prepare("
        SELECT id, user_id, user_role FROM chat_participants WHERE conversation_id = ?
    ");
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stale = [];
    foreach ($existing as $row) {
        if (!isset($keep[$row['user_role'] . ':' . (int)$row['user_id']])) {
            $stale[] = (int)$row['id'];
        }
    }

    if (empty($stale)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($stale), '?'));
    $stmt = $conn->prepare("DELETE FROM chat_participants WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($stale)), ...$stale);
    $stmt->execute();
    $removed = $stmt->affected_rows;
    $stmt->close();

    return $removed;
}

/**
 * The membership a group should have, read from team_members / students /
 * enrolments. Returns a list of ['id', 'role', 'can_post'].
 */
function chat_expected_members(mysqli $conn, array $conversation): array
{
    $verified = chat_verified_student_clause($conn, 's');
    $members = [];

    switch ($conversation['type']) {
        case 'team':
            if (!chat_teams_available($conn)) {
                return [];
            }
            $stmt = $conn->prepare("
                SELECT s.id
                FROM team_members tm
                JOIN students s ON s.id = tm.student_id
                WHERE tm.team_id = ? $verified
            ");
            $stmt->bind_param('i', $conversation['team_id']);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $members[] = ['id' => (int)$row['id'], 'role' => 'student', 'can_post' => true];
            }
            $stmt->close();
            break;

        case 'course':
        case 'course_year':
            $yearFilter = $conversation['type'] === 'course_year'
                ? ' AND s.year_of_study = ?'
                : '';

            $sql = "SELECT s.id FROM students s WHERE s.course_id = ?$yearFilter $verified";
            $stmt = $conn->prepare($sql);
            if ($yearFilter !== '') {
                $stmt->bind_param('ii', $conversation['course_id'], $conversation['year_of_study']);
            } else {
                $stmt->bind_param('i', $conversation['course_id']);
            }
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $members[] = ['id' => (int)$row['id'], 'role' => 'student', 'can_post' => true];
            }
            $stmt->close();

            // Lecturers of any unit on the course. This is what lets a lecturer
            // address a course they teach into without being enrolled on it.
            $stmt = $conn->prepare("
                SELECT DISTINCT lu.lecturer_id AS id
                FROM units u
                JOIN lecturer_units lu ON lu.unit_id = u.id
                WHERE u.course_id = ?
            ");
            $stmt->bind_param('i', $conversation['course_id']);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $members[] = ['id' => (int)$row['id'], 'role' => 'lecturer', 'can_post' => true];
            }
            $stmt->close();
            break;

        case 'unit_announce':
            $enrolments = chat_enrollment_table($conn);
            if ($enrolments !== null) {
                $stmt = $conn->prepare("
                    SELECT s.id
                    FROM `$enrolments` e
                    JOIN students s ON s.id = e.student_id
                    WHERE e.unit_id = ? $verified
                ");
                $stmt->bind_param('i', $conversation['unit_id']);
                $stmt->execute();
                foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                    // Students may reply. This channel started out read-only,
                    // which left them reading instructions with no way to ask a
                    // question about them - so it is a conversation now, and the
                    // lecturer's posts are still marked as instructions.
                    $members[] = ['id' => (int)$row['id'], 'role' => 'student', 'can_post' => true];
                }
                $stmt->close();
            }

            $stmt = $conn->prepare("SELECT lecturer_id AS id FROM lecturer_units WHERE unit_id = ?");
            $stmt->bind_param('i', $conversation['unit_id']);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $members[] = ['id' => (int)$row['id'], 'role' => 'lecturer', 'can_post' => true];
            }
            $stmt->close();
            break;

        case 'direct':
        default:
            return [];
    }

    return $members;
}

/**
 * Rebuild a group's membership from the source of truth.
 *
 * Skipped when it ran within CHAT_MEMBER_SYNC_TTL_SECONDS unless $force, so
 * that polling does not re-run a course-sized membership query every few
 * seconds. Returns ['added' => int, 'removed' => int, 'skipped' => bool].
 */
function chat_sync_conversation_members(mysqli $conn, int $conversationId, bool $force = false): array
{
    $stmt = $conn->prepare("
        SELECT id, type, title, team_id, course_id, unit_id, year_of_study, members_synced_at
        FROM chat_conversations WHERE id = ? LIMIT 1
    ");
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $conversation = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$conversation || $conversation['type'] === 'direct') {
        return ['added' => 0, 'removed' => 0, 'skipped' => true];
    }

    if (!$force && $conversation['members_synced_at'] !== null) {
        $age = time() - strtotime($conversation['members_synced_at']);
        if ($age < CHAT_MEMBER_SYNC_TTL_SECONDS) {
            return ['added' => 0, 'removed' => 0, 'skipped' => true];
        }
    }

    $expected = chat_expected_members($conn, $conversation);

    // An empty expected set means the source data has gone (team deleted,
    // course emptied). Leave the existing members alone rather than silently
    // emptying a group that still holds history.
    if (empty($expected)) {
        $stmt = $conn->prepare("UPDATE chat_conversations SET members_synced_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $conversationId);
        $stmt->execute();
        $stmt->close();

        return ['added' => 0, 'removed' => 0, 'skipped' => false];
    }

    $added = chat_add_participants($conn, $conversationId, $expected);
    $removed = chat_prune_participants($conn, $conversationId, $expected);

    $stmt = $conn->prepare("UPDATE chat_conversations SET members_synced_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $stmt->close();

    return ['added' => $added, 'removed' => $removed, 'skipped' => false];
}

/**
 * Ensure the groups this user belongs to exist and are populated.
 *
 * Called when the conversation list is loaded, not on every message poll.
 * Returns the conversation ids touched.
 */
function chat_sync_for_user(mysqli $conn, array $user, bool $force = false): array
{
    return $user['role'] === 'student'
        ? chat_sync_for_student($conn, (int)$user['id'], $force)
        : chat_sync_for_lecturer($conn, (int)$user['id'], $force);
}

/**
 * A student's groups: one per team they are in, plus their course year group
 * and their whole-course group.
 */
function chat_sync_for_student(mysqli $conn, int $studentId, bool $force = false): array
{
    $touched = [];

    if (chat_teams_available($conn)) {
        $stmt = $conn->prepare("
            SELECT t.id, t.title
            FROM team_members tm
            JOIN teams t ON t.id = tm.team_id
            WHERE tm.student_id = ?
        ");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($teams as $team) {
            $id = chat_upsert_conversation($conn, 'team:' . (int)$team['id'], 'team', [
                // The group is named from teams.title, and picks up a rename on
                // the next sync because the upsert refreshes the title.
                'title' => (string)$team['title'],
                'team_id' => (int)$team['id'],
            ]);
            chat_sync_conversation_members($conn, $id, $force);
            $touched[] = $id;
        }
    }

    $stmt = $conn->prepare("
        SELECT s.course_id, s.year_of_study, c.name AS course_name
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        WHERE s.id = ? LIMIT 1
    ");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($student && $student['course_id'] !== null) {
        $courseId = (int)$student['course_id'];
        $courseName = (string)($student['course_name'] ?? 'Course');

        $id = chat_upsert_conversation($conn, "course:$courseId:all", 'course', [
            'title' => $courseName . ' · All years',
            'course_id' => $courseId,
        ]);
        chat_sync_conversation_members($conn, $id, $force);
        $touched[] = $id;

        $year = (int)($student['year_of_study'] ?? 0);
        if ($year > 0) {
            $id = chat_upsert_conversation($conn, "course:$courseId:y$year", 'course_year', [
                'title' => $courseName . ' · Year ' . $year,
                'course_id' => $courseId,
                'year_of_study' => $year,
            ]);
            chat_sync_conversation_members($conn, $id, $force);
            $touched[] = $id;
        }
    }

    return $touched;
}

/**
 * A lecturer's groups: an instruction channel per unit they teach, plus the
 * course groups for those units' courses - whole-course and one per year that
 * actually has students on it.
 */
function chat_sync_for_lecturer(mysqli $conn, int $lecturerId, bool $force = false): array
{
    $touched = [];

    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.code, u.course_id, c.name AS course_name
        FROM lecturer_units lu
        JOIN units u ON u.id = lu.unit_id
        LEFT JOIN courses c ON c.id = u.course_id
        WHERE lu.lecturer_id = ?
    ");
    $stmt->bind_param('i', $lecturerId);
    $stmt->execute();
    $units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $courses = [];

    foreach ($units as $unit) {
        $unitId = (int)$unit['id'];
        $label = trim((string)$unit['code'] . ' ' . (string)$unit['name']);

        $id = chat_upsert_conversation($conn, "unit:$unitId:announce", 'unit_announce', [
            'title' => $label . ' · Instructions',
            'unit_id' => $unitId,
            'course_id' => $unit['course_id'] !== null ? (int)$unit['course_id'] : null,
        ]);
        chat_sync_conversation_members($conn, $id, $force);
        $touched[] = $id;

        if ($unit['course_id'] !== null) {
            $courses[(int)$unit['course_id']] = (string)($unit['course_name'] ?? 'Course');
        }
    }

    foreach ($courses as $courseId => $courseName) {
        $id = chat_upsert_conversation($conn, "course:$courseId:all", 'course', [
            'title' => $courseName . ' · All years',
            'course_id' => $courseId,
        ]);
        chat_sync_conversation_members($conn, $id, $force);
        $touched[] = $id;

        // Only years that have students, so a 4-year course with two intakes
        // does not produce four groups of which two are empty.
        $stmt = $conn->prepare("
            SELECT DISTINCT year_of_study
            FROM students
            WHERE course_id = ? AND year_of_study IS NOT NULL AND year_of_study > 0
            ORDER BY year_of_study
        ");
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $years = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($years as $row) {
            $year = (int)$row['year_of_study'];
            $id = chat_upsert_conversation($conn, "course:$courseId:y$year", 'course_year', [
                'title' => $courseName . ' · Year ' . $year,
                'course_id' => $courseId,
                'year_of_study' => $year,
            ]);
            chat_sync_conversation_members($conn, $id, $force);
            $touched[] = $id;
        }
    }

    return $touched;
}

/**
 * The direct thread between two people, created on first use.
 *
 * The key sorts both participants so that A messaging B and B messaging A
 * resolve to one conversation rather than two half-empty ones.
 */
function chat_direct_conversation(mysqli $conn, array $a, array $b): int
{
    $keys = [
        $a['role'] . ':' . (int)$a['id'],
        $b['role'] . ':' . (int)$b['id'],
    ];
    sort($keys);

    $conversationId = chat_upsert_conversation($conn, 'dm:' . implode('|', $keys), 'direct');

    chat_add_participants($conn, $conversationId, [
        ['id' => (int)$a['id'], 'role' => $a['role'], 'can_post' => true],
        ['id' => (int)$b['id'], 'role' => $b['role'], 'can_post' => true],
    ]);

    return $conversationId;
}
