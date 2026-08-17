<?php
/**
 * Authoring for the public course catalogue.
 *
 * The read side of these tables lives in catalogue.php; this is the write side.
 * They are deliberately separate files: the read side is loaded on every public
 * page, and there is no reason for a page serving a learner to have functions
 * that can publish or delete a course in scope.
 *
 * WHO MAY EDIT WHAT
 *
 * public_courses.created_by_lecturer_id is the owner. A lecturer sees and edits
 * their own courses and nothing else. An admin may edit any course, which is
 * also the only way to reach the rows that were inserted by hand before this UI
 * existed - those have a NULL creator and would otherwise be stranded.
 *
 * ORDERING
 *
 * position is an int with no unique constraint, and reordering swaps two rows
 * rather than renumbering the whole list. Swapping touches two rows and cannot
 * leave a gap; renumbering rewrites every row and turns a concurrent insert
 * into a duplicate position.
 */

/**
 * A URL-safe slug from a title. Not unique on its own - see
 * catalogue_unique_slug().
 */
function catalogue_slugify(string $title): string
{
    $slug = strtolower(trim($title));

    // Transliterate what we can rather than dropping it, so "Café" becomes
    // "cafe" rather than "caf".
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
        if (is_string($converted) && $converted !== '') {
            $slug = strtolower($converted);
        }
    }

    // How //TRANSLIT spells an accent depends on the C library: some write "é"
    // as "e", others as "'e". Those marks are dropped rather than treated as
    // separators, because turning them into hyphens is what produced
    // "intro-to-caf-e-security" from "Intro to Café Security". Apostrophes go
    // the same way, so "lecturer's guide" is not "lecturer-s-guide".
    $slug = str_replace(['\'', '"', '`', '^', '~', '’', '“', '”'], '', $slug);

    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? substr($slug, 0, 150) : 'course';
}

/**
 * A slug not already used by another course.
 *
 * $ignoreCourseId lets a course keep its own slug when its title is edited
 * back to what it already was.
 */
function catalogue_unique_slug(mysqli $conn, string $title, int $ignoreCourseId = 0): string
{
    $base = catalogue_slugify($title);
    $candidate = $base;
    $suffix = 2;

    while (true) {
        $stmt = $conn->prepare("SELECT id FROM public_courses WHERE slug = ? AND id <> ? LIMIT 1");
        $stmt->bind_param('si', $candidate, $ignoreCourseId);
        $stmt->execute();
        $taken = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$taken) {
            return $candidate;
        }

        $candidate = $base . '-' . $suffix;
        $suffix++;

        // A guard rather than a real limit: if a hundred courses share a title
        // the slug stops being meaningful anyway, so fall back to something
        // certainly free.
        if ($suffix > 100) {
            return $base . '-' . bin2hex(random_bytes(4));
        }
    }
}

/**
 * The signed-in author, or null.
 *
 * Returns ['role' => 'lecturer'|'admin', 'id' => int, 'name' => string].
 */
function catalogue_actor(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $role = (string)($_SESSION['user_role'] ?? '');
    $id = (int)($_SESSION['user_id'] ?? 0);

    if ($id <= 0 || !in_array($role, ['lecturer', 'admin', 'department_admin'], true)) {
        return null;
    }

    return [
        'role' => $role,
        'id' => $id,
        'name' => (string)($_SESSION['user_name'] ?? ucfirst($role)),
    ];
}

/**
 * Send the caller to the login page unless a lecturer or admin is signed in.
 */
function catalogue_require_author(): array
{
    $actor = catalogue_actor();
    if ($actor === null) {
        header('Location: ../login.php');
        exit;
    }

    return $actor;
}

/**
 * Whether this actor may edit this course.
 */
function catalogue_can_manage(array $actor, array $course): bool
{
    if ($actor['role'] === 'admin') {
        return true;
    }

    return (int)($course['created_by_lecturer_id'] ?? 0) === $actor['id'];
}

/**
 * CSRF token for the authoring forms.
 *
 * Separate from the learner token in learner_auth.php: the two sessions can
 * coexist in one PHP session, and one token serving both would let a value
 * minted for a public form authorise a publish.
 */
function catalogue_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['catalogue_csrf'])) {
        $_SESSION['catalogue_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['catalogue_csrf'];
}

function catalogue_csrf_valid(?string $token): bool
{
    return !empty($_SESSION['catalogue_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['catalogue_csrf'], $token);
}

/**
 * Courses this actor may edit, newest first, with content tallies.
 */
function catalogue_courses_for(mysqli $conn, array $actor): array
{
    $counts = "
        (SELECT COUNT(*) FROM public_course_modules m WHERE m.course_id = c.id) AS module_count,
        (SELECT COUNT(*) FROM public_course_lessons l
           JOIN public_course_modules m ON m.id = l.module_id
          WHERE m.course_id = c.id) AS lesson_count,
        (SELECT COUNT(*) FROM public_course_assessments a WHERE a.course_id = c.id) AS assessment_count,
        (SELECT COUNT(*) FROM external_enrollments e WHERE e.course_id = c.id) AS learner_count,
        (SELECT COUNT(*) FROM certificates t WHERE t.course_id = c.id AND t.revoked_at IS NULL) AS certificate_count
    ";

    if ($actor['role'] === 'admin') {
        $sql = "SELECT c.*, {$counts}, l.name AS author_name
                FROM public_courses c
                LEFT JOIN lecturers l ON l.id = c.created_by_lecturer_id
                ORDER BY c.updated_at DESC";
        $result = $conn->query($sql);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $rows;
    }

    $stmt = $conn->prepare("
        SELECT c.*, {$counts}, l.name AS author_name
        FROM public_courses c
        LEFT JOIN lecturers l ON l.id = c.created_by_lecturer_id
        WHERE c.created_by_lecturer_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->bind_param('i', $actor['id']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/**
 * One course by id in any publish state, or null.
 *
 * catalogue.php's learn_course_by_slug() only returns published courses, which
 * is right for a learner and useless for an author editing a draft.
 */
function catalogue_course(mysqli $conn, int $courseId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM public_courses WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $course ?: null;
}

/**
 * The course this actor asked for, or a 403/404 that stops the request.
 */
function catalogue_require_course(mysqli $conn, array $actor, int $courseId): array
{
    $course = $courseId > 0 ? catalogue_course($conn, $courseId) : null;

    if ($course === null || !catalogue_can_manage($actor, $course)) {
        // One response for "does not exist" and "not yours", so this cannot be
        // used to count courses that belong to someone else.
        http_response_code(404);
        echo 'Course not found.';
        exit;
    }

    return $course;
}

/**
 * Validate the course detail fields shared by create and update.
 *
 * Returns ['errors' => string[], 'values' => array].
 */
function catalogue_validate_course(array $input): array
{
    $errors = [];

    $title = trim((string)($input['title'] ?? ''));
    $summary = trim((string)($input['summary'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $level = (string)($input['level'] ?? 'beginner');
    $hoursRaw = trim((string)($input['estimated_hours'] ?? ''));
    $passMark = (int)($input['pass_mark'] ?? 70);

    if ($title === '' || mb_strlen($title) < 3) {
        $errors[] = 'Give the course a title of at least 3 characters.';
    }
    if (mb_strlen($title) > 200) {
        $errors[] = 'That title is too long (200 characters maximum).';
    }
    if (mb_strlen($summary) > 400) {
        $errors[] = 'The summary is too long (400 characters maximum).';
    }
    if (!in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
        $errors[] = 'Pick a level.';
    }
    if ($passMark < 1 || $passMark > 100) {
        $errors[] = 'The pass mark must be between 1 and 100.';
    }

    $hours = null;
    if ($hoursRaw !== '') {
        if (!is_numeric($hoursRaw) || (float)$hoursRaw <= 0 || (float)$hoursRaw > 9999) {
            $errors[] = 'Estimated hours must be a positive number.';
        } else {
            $hours = round((float)$hoursRaw, 1);
        }
    }

    return [
        'errors' => $errors,
        'values' => [
            'title' => $title,
            'summary' => $summary !== '' ? $summary : null,
            'description' => $description !== '' ? $description : null,
            'level' => $level,
            'estimated_hours' => $hours,
            'pass_mark' => $passMark,
            'certificate_enabled' => !empty($input['certificate_enabled']) ? 1 : 0,
        ],
    ];
}

/**
 * Create a draft course. Never publishes: a course with no content in the
 * catalogue is worse than no course.
 *
 * Returns the new course id.
 */
function catalogue_create_course(mysqli $conn, array $actor, array $values): int
{
    $slug = catalogue_unique_slug($conn, $values['title']);
    // A course an admin creates has no lecturer owner, which is fine - admins
    // are matched by role, not by id.
    $ownerId = $actor['role'] === 'lecturer' ? $actor['id'] : null;

    $stmt = $conn->prepare("
        INSERT INTO public_courses
            (slug, title, summary, description, level, estimated_hours,
             is_published, certificate_enabled, pass_mark, created_by_lecturer_id)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sssssdiii',
        $slug,
        $values['title'],
        $values['summary'],
        $values['description'],
        $values['level'],
        $values['estimated_hours'],
        $values['certificate_enabled'],
        $values['pass_mark'],
        $ownerId
    );
    $stmt->execute();
    $courseId = (int)$conn->insert_id;
    $stmt->close();

    return $courseId;
}

/**
 * Update the course details.
 *
 * The slug follows the title, except once the course has been published: the
 * slug is then a live URL that learners have bookmarked and search engines have
 * indexed, and silently moving it would break both.
 */
function catalogue_update_course(mysqli $conn, int $courseId, array $values, bool $isPublished): void
{
    if ($isPublished) {
        $stmt = $conn->prepare("
            UPDATE public_courses
            SET title = ?, summary = ?, description = ?, level = ?,
                estimated_hours = ?, certificate_enabled = ?, pass_mark = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            'ssssdiii',
            $values['title'],
            $values['summary'],
            $values['description'],
            $values['level'],
            $values['estimated_hours'],
            $values['certificate_enabled'],
            $values['pass_mark'],
            $courseId
        );
    } else {
        $slug = catalogue_unique_slug($conn, $values['title'], $courseId);
        $stmt = $conn->prepare("
            UPDATE public_courses
            SET slug = ?, title = ?, summary = ?, description = ?, level = ?,
                estimated_hours = ?, certificate_enabled = ?, pass_mark = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            'sssssdiii',
            $slug,
            $values['title'],
            $values['summary'],
            $values['description'],
            $values['level'],
            $values['estimated_hours'],
            $values['certificate_enabled'],
            $values['pass_mark'],
            $courseId
        );
    }

    $stmt->execute();
    $stmt->close();
}

function catalogue_set_cover(mysqli $conn, int $courseId, ?string $path): void
{
    $stmt = $conn->prepare("UPDATE public_courses SET cover_image = ? WHERE id = ?");
    $stmt->bind_param('si', $path, $courseId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Reasons this course cannot go into the catalogue yet.
 *
 * Returns an empty array when it is ready. The rules mirror the completion rule
 * in catalogue.php: a learner finishes a course by completing every lesson and
 * passing every assessment, so a published course needs at least one lesson,
 * and every assessment needs answerable questions - otherwise nobody can ever
 * finish it and the certificate is unreachable.
 */
function catalogue_publish_blockers(mysqli $conn, int $courseId): array
{
    $blockers = [];

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE m.course_id = ?
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $lessons = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($lessons === 0) {
        $blockers[] = 'The course needs at least one lesson. A course with no lessons can never be completed, so no certificate could ever be issued.';
    }

    // An assessment with no questions scores 0 out of 0 and can never be
    // passed, which blocks completion of the whole course.
    $stmt = $conn->prepare("
        SELECT a.title
        FROM public_course_assessments a
        WHERE a.course_id = ?
          AND (SELECT COUNT(*) FROM public_course_questions q WHERE q.assessment_id = a.id) = 0
        ORDER BY a.position, a.id
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $empty = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($empty as $row) {
        $blockers[] = 'Assessment "' . $row['title'] . '" has no questions, so nobody could pass it.';
    }

    // Same trap one level down: a question with no correct answer is unpassable.
    $stmt = $conn->prepare("
        SELECT q.question, a.title AS assessment_title
        FROM public_course_questions q
        JOIN public_course_assessments a ON a.id = q.assessment_id
        WHERE a.course_id = ?
          AND (q.correct_answer IS NULL OR q.correct_answer = '')
        ORDER BY a.position, q.position
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $unanswerable = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($unanswerable as $row) {
        $blockers[] = 'A question in "' . $row['assessment_title'] . '" has no correct answer set: '
            . mb_substr(strip_tags((string)$row['question']), 0, 60);
    }

    return $blockers;
}

/**
 * Publish or unpublish.
 *
 * Returns ['ok' => bool, 'blockers' => string[]]. Publishing is refused while
 * there are blockers; unpublishing never is, because pulling a broken course
 * out of the catalogue must always be possible.
 */
function catalogue_set_published(mysqli $conn, int $courseId, bool $publish): array
{
    if ($publish) {
        $blockers = catalogue_publish_blockers($conn, $courseId);
        if ($blockers) {
            return ['ok' => false, 'blockers' => $blockers];
        }
    }

    $flag = $publish ? 1 : 0;
    $stmt = $conn->prepare("UPDATE public_courses SET is_published = ? WHERE id = ?");
    $stmt->bind_param('ii', $flag, $courseId);
    $stmt->execute();
    $stmt->close();

    return ['ok' => true, 'blockers' => []];
}

/**
 * Whether anyone has started this course.
 *
 * Deleting a course cascades to enrolments, progress, attempts and
 * certificates, so an enrolled course must not be deletable by one stray click.
 */
function catalogue_course_has_learners(mysqli $conn, int $courseId): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM external_enrollments WHERE course_id = ? LIMIT 1");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $found;
}

/**
 * Delete a course, unless learners have enrolled on it.
 *
 * Returns ['ok' => bool, 'error' => ?string].
 */
function catalogue_delete_course(mysqli $conn, int $courseId): array
{
    if (catalogue_course_has_learners($conn, $courseId)) {
        return [
            'ok' => false,
            'error' => 'Learners have enrolled on this course. Unpublish it instead: '
                . 'deleting it would erase their progress and revoke their certificates.',
        ];
    }

    $stmt = $conn->prepare("DELETE FROM public_courses WHERE id = ?");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $stmt->close();

    return ['ok' => true, 'error' => null];
}

// ── Modules ───────────────────────────────────────────────────────────────────

/**
 * The full editable outline: modules with their lessons and assessments, plus
 * course-level assessments.
 *
 * Distinct from learn_course_outline(), which returns only the columns a learner
 * page needs. An author needs the bodies too.
 */
function catalogue_outline(mysqli $conn, int $courseId): array
{
    $stmt = $conn->prepare("
        SELECT * FROM public_course_modules WHERE course_id = ? ORDER BY position, id
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT a.*, (SELECT COUNT(*) FROM public_course_questions q WHERE q.assessment_id = a.id) AS question_count
        FROM public_course_assessments a WHERE a.course_id = ? ORDER BY a.position, a.id
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $lessons = [];
    if ($modules) {
        $moduleIds = array_map(static fn($m) => (int)$m['id'], $modules);
        $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));

        $stmt = $conn->prepare("
            SELECT * FROM public_course_lessons
            WHERE module_id IN ($placeholders) ORDER BY position, id
        ");
        $stmt->bind_param(str_repeat('i', count($moduleIds)), ...$moduleIds);
        $stmt->execute();
        $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    foreach ($modules as &$module) {
        $mid = (int)$module['id'];
        $module['lessons'] = array_values(array_filter($lessons, static fn($l) => (int)$l['module_id'] === $mid));
        $module['assessments'] = array_values(array_filter($assessments, static fn($a) => (int)$a['module_id'] === $mid));
    }
    unset($module);

    return [
        'modules' => $modules,
        'final_assessments' => array_values(array_filter($assessments, static fn($a) => $a['module_id'] === null)),
    ];
}

/**
 * Next position at the end of a list.
 */
function catalogue_next_position(mysqli $conn, string $table, string $parentColumn, ?int $parentId): int
{
    // $table and $parentColumn are never request data - every caller passes a
    // literal - so they can be interpolated where a placeholder is not allowed.
    if ($parentId === null) {
        $result = $conn->query("SELECT COALESCE(MAX(position), -1) + 1 AS next FROM `$table` WHERE `$parentColumn` IS NULL");
        $next = (int)$result->fetch_assoc()['next'];
        $result->free();

        return $next;
    }

    $stmt = $conn->prepare("SELECT COALESCE(MAX(position), -1) + 1 AS next FROM `$table` WHERE `$parentColumn` = ?");
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $next = (int)$stmt->get_result()->fetch_assoc()['next'];
    $stmt->close();

    return $next;
}

function catalogue_add_module(mysqli $conn, int $courseId, string $title, ?string $summary): int
{
    $position = catalogue_next_position($conn, 'public_course_modules', 'course_id', $courseId);

    $stmt = $conn->prepare("
        INSERT INTO public_course_modules (course_id, title, summary, position) VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('issi', $courseId, $title, $summary, $position);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();

    return $id;
}

/**
 * A module, but only if it belongs to this course.
 *
 * Every mutation goes through this rather than trusting the posted module_id:
 * without it, an author could edit another course's module by changing one
 * hidden field.
 */
function catalogue_module_in_course(mysqli $conn, int $moduleId, int $courseId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM public_course_modules WHERE id = ? AND course_id = ? LIMIT 1");
    $stmt->bind_param('ii', $moduleId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function catalogue_update_module(mysqli $conn, int $moduleId, string $title, ?string $summary, ?string $start_date = null, ?string $end_date = null): void
{
    $stmt = $conn->prepare("UPDATE public_course_modules SET title = ?, summary = ?, start_date = ?, end_date = ? WHERE id = ?");
    $stmt->bind_param('ssssi', $title, $summary, $start_date, $end_date, $moduleId);
    $stmt->execute();
    $stmt->close();
}

function catalogue_delete_module(mysqli $conn, int $moduleId): void
{
    // Lessons cascade from the foreign key. Module-level assessments do not:
    // fk_pca_course points at the course, not the module, so they would be left
    // pointing at a module that no longer exists and would vanish from the
    // outline while still blocking completion. Detach them to course level.
    $stmt = $conn->prepare("UPDATE public_course_assessments SET module_id = NULL WHERE module_id = ?");
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM public_course_modules WHERE id = ?");
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Swap a row with its neighbour in the given direction.
 *
 * $direction is 'up' or 'down'. Works on any of the ordered tables, keyed by a
 * parent column, and is a no-op at the ends of the list.
 */
function catalogue_move(
    mysqli $conn,
    string $table,
    string $parentColumn,
    int $rowId,
    ?int $parentId,
    string $direction
): void {
    // Literals from the caller, never request data.
    $stmt = $conn->prepare("SELECT position FROM `$table` WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $rowId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return;
    }
    $position = (int)$row['position'];

    // Order by id as well as position: rows added before this feature existed
    // can share a position, and without the tiebreak a swap between two rows at
    // the same position would do nothing.
    $comparison = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';
    $parentClause = $parentId === null ? "`$parentColumn` IS NULL" : "`$parentColumn` = ?";

    $sql = "SELECT id, position FROM `$table`
            WHERE {$parentClause}
              AND (position {$comparison} ? OR (position = ? AND id {$comparison} ?))
            ORDER BY position {$order}, id {$order} LIMIT 1";

    $stmt = $conn->prepare($sql);
    if ($parentId === null) {
        $stmt->bind_param('iii', $position, $position, $rowId);
    } else {
        $stmt->bind_param('iiii', $parentId, $position, $position, $rowId);
    }
    $stmt->execute();
    $neighbour = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$neighbour) {
        return;
    }

    // Equal positions would swap to no effect, so force them apart.
    $neighbourPosition = (int)$neighbour['position'];
    if ($neighbourPosition === $position) {
        $neighbourPosition = $direction === 'up' ? $position - 1 : $position + 1;
    }

    $stmt = $conn->prepare("UPDATE `$table` SET position = ? WHERE id = ?");
    $stmt->bind_param('ii', $neighbourPosition, $rowId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE `$table` SET position = ? WHERE id = ?");
    $stmt->bind_param('ii', $position, $neighbour['id']);
    $stmt->execute();
    $stmt->close();
}

// ── Lessons ───────────────────────────────────────────────────────────────────

/**
 * Validate lesson fields. Returns ['errors' => string[], 'values' => array].
 *
 * content_html is stored and rendered as HTML, exactly as the Live Engagement
 * slide editor already does for its own content_html. Authors are lecturers and
 * admins, so this is an authoring feature rather than a hole - but it does mean
 * lesson bodies are trusted markup, and it is why nothing here accepts content
 * from a learner.
 */
function catalogue_validate_lesson(array $input): array
{
    $errors = [];

    $title = trim((string)($input['title'] ?? ''));
    $content = trim((string)($input['content_html'] ?? ''));
    $video = trim((string)($input['video_url'] ?? ''));
    $durationRaw = trim((string)($input['duration_minutes'] ?? ''));

    if ($title === '') {
        $errors[] = 'The lesson needs a title.';
    }
    if (mb_strlen($title) > 200) {
        $errors[] = 'That lesson title is too long (200 characters maximum).';
    }
    if ($video !== '' && !filter_var($video, FILTER_VALIDATE_URL)) {
        $errors[] = 'The video URL is not a valid URL.';
    }
    if (mb_strlen($video) > 500) {
        $errors[] = 'That video URL is too long.';
    }

    $duration = null;
    if ($durationRaw !== '') {
        if (!ctype_digit($durationRaw) || (int)$durationRaw <= 0) {
            $errors[] = 'Lesson length must be a whole number of minutes.';
        } else {
            $duration = (int)$durationRaw;
        }
    }

    return [
        'errors' => $errors,
        'values' => [
            'title' => $title,
            'content_html' => $content !== '' ? $content : null,
            'video_url' => $video !== '' ? $video : null,
            'duration_minutes' => $duration,
        ],
    ];
}

function catalogue_add_lesson(mysqli $conn, int $moduleId, array $values): int
{
    $position = catalogue_next_position($conn, 'public_course_lessons', 'module_id', $moduleId);

    $stmt = $conn->prepare("
        INSERT INTO public_course_lessons
            (module_id, title, content_html, video_url, duration_minutes, position)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'isssii',
        $moduleId,
        $values['title'],
        $values['content_html'],
        $values['video_url'],
        $values['duration_minutes'],
        $position
    );
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();

    return $id;
}

/**
 * A lesson, but only if it belongs to this course.
 */
function catalogue_lesson_in_course(mysqli $conn, int $lessonId, int $courseId): ?array
{
    $stmt = $conn->prepare("
        SELECT l.* FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE l.id = ? AND m.course_id = ? LIMIT 1
    ");
    $stmt->bind_param('ii', $lessonId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function catalogue_update_lesson(mysqli $conn, int $lessonId, array $values, ?string $start_date = null, ?string $end_date = null): void
{
    $stmt = $conn->prepare("
        UPDATE public_course_lessons
        SET title = ?, content_html = ?, video_url = ?, duration_minutes = ?, start_date = ?, end_date = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'ssssisi',
        $values['title'],
        $values['content_html'],
        $values['video_url'],
        $values['duration_minutes'],
        $start_date,
        $end_date,
        $lessonId
    );
    $stmt->execute();
    $stmt->close();
}

function catalogue_set_lesson_attachment(mysqli $conn, int $lessonId, ?string $path): void
{
    $stmt = $conn->prepare("UPDATE public_course_lessons SET attachment_path = ? WHERE id = ?");
    $stmt->bind_param('si', $path, $lessonId);
    $stmt->execute();
    $stmt->close();
}

function catalogue_reposition_lessons(mysqli $conn, int $moduleId): void
{
    $stmt = $conn->prepare("
        SELECT id FROM public_course_lessons 
        WHERE module_id = ? 
        ORDER BY position ASC, id ASC
    ");
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($lessons as $index => $lesson) {
        $update = $conn->prepare("UPDATE public_course_lessons SET position = ? WHERE id = ?");
        $update->bind_param('ii', $index, $lesson['id']);
        $update->execute();
        $update->close();
    }
}

function tutor_can_edit_module(mysqli $conn, int $tutorId, int $moduleId): bool
{
    // Check if tutor has explicit permission to edit this module
    $checkTable = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT can_edit FROM tutor_module_permissions
            WHERE tutor_id = ? AND module_id = ? AND can_edit = 1
            LIMIT 1
        ");
        $stmt->bind_param('ii', $tutorId, $moduleId);
        $stmt->execute();
        $hasPermission = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if ($hasPermission) {
            return true;
        }
    }
    
    // Fallback: Check if tutor is assigned to the course containing this module
    $stmt = $conn->prepare("
        SELECT sct.id FROM short_course_tutors sct
        JOIN public_course_modules m ON m.course_id = sct.short_course_id
        WHERE sct.lecturer_id = ? AND m.id = ? AND sct.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param('ii', $tutorId, $moduleId);
    $stmt->execute();
    $isAssigned = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    return $isAssigned;
}

function tutor_can_view_module(mysqli $conn, int $tutorId, int $moduleId): bool
{
    // Tutors can always view modules in courses they're assigned to
    $stmt = $conn->prepare("
        SELECT sct.id FROM short_course_tutors sct
        JOIN public_course_modules m ON m.course_id = sct.short_course_id
        WHERE sct.lecturer_id = ? AND m.id = ? AND sct.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param('ii', $tutorId, $moduleId);
    $stmt->execute();
    $canView = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    return $canView;
}

function catalogue_delete_lesson(mysqli $conn, int $lessonId): void
{
    // external_lesson_progress cascades from the foreign key, so a deleted
    // lesson stops counting towards completion for everyone.
    $stmt = $conn->prepare("DELETE FROM public_course_lessons WHERE id = ?");
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $stmt->close();
}

// ── Assessments and questions ────────────────────────────────────────────────

function catalogue_validate_assessment(array $input): array
{
    $errors = [];

    $title = trim((string)($input['title'] ?? ''));
    $instructions = trim((string)($input['instructions'] ?? ''));
    $passRaw = trim((string)($input['pass_mark'] ?? ''));
    $attempts = (int)($input['max_attempts'] ?? 0);

    if ($title === '') {
        $errors[] = 'The assessment needs a title.';
    }
    if (mb_strlen($title) > 200) {
        $errors[] = 'That assessment title is too long (200 characters maximum).';
    }
    if ($attempts < 0 || $attempts > 100) {
        $errors[] = 'Attempts must be between 0 (unlimited) and 100.';
    }

    // Null means "use the course pass mark", which is different from 0.
    $passMark = null;
    if ($passRaw !== '') {
        if (!ctype_digit($passRaw) || (int)$passRaw < 1 || (int)$passRaw > 100) {
            $errors[] = 'The assessment pass mark must be between 1 and 100, or blank to use the course pass mark.';
        } else {
            $passMark = (int)$passRaw;
        }
    }

    return [
        'errors' => $errors,
        'values' => [
            'title' => $title,
            'instructions' => $instructions !== '' ? $instructions : null,
            'pass_mark' => $passMark,
            'max_attempts' => $attempts,
        ],
    ];
}

function catalogue_add_assessment(mysqli $conn, int $courseId, ?int $moduleId, array $values): int
{
    $position = catalogue_next_position($conn, 'public_course_assessments', 'course_id', $courseId);

    $stmt = $conn->prepare("
        INSERT INTO public_course_assessments
            (course_id, module_id, title, instructions, pass_mark, max_attempts, position)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iissiii',
        $courseId,
        $moduleId,
        $values['title'],
        $values['instructions'],
        $values['pass_mark'],
        $values['max_attempts'],
        $position
    );
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();

    return $id;
}

function catalogue_assessment_in_course(mysqli $conn, int $assessmentId, int $courseId): ?array
{
    $stmt = $conn->prepare("
        SELECT * FROM public_course_assessments WHERE id = ? AND course_id = ? LIMIT 1
    ");
    $stmt->bind_param('ii', $assessmentId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function catalogue_update_assessment(mysqli $conn, int $assessmentId, ?int $moduleId, array $values): void
{
    $stmt = $conn->prepare("
        UPDATE public_course_assessments
        SET module_id = ?, title = ?, instructions = ?, pass_mark = ?, max_attempts = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'issiii',
        $moduleId,
        $values['title'],
        $values['instructions'],
        $values['pass_mark'],
        $values['max_attempts'],
        $assessmentId
    );
    $stmt->execute();
    $stmt->close();
}

function catalogue_delete_assessment(mysqli $conn, int $assessmentId): void
{
    $stmt = $conn->prepare("DELETE FROM public_course_assessments WHERE id = ?");
    $stmt->bind_param('i', $assessmentId);
    $stmt->execute();
    $stmt->close();
}

function catalogue_questions(mysqli $conn, int $assessmentId): array
{
    $stmt = $conn->prepare("
        SELECT * FROM public_course_questions WHERE assessment_id = ? ORDER BY position, id
    ");
    $stmt->bind_param('i', $assessmentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/**
 * Validate a question and normalise its options and correct answer.
 *
 * The answer format has to match what learn/assessment.php marks against, which
 * is why this normalises rather than storing what was typed:
 *   single / true_false  -> the option text
 *   multiple             -> option texts joined with ','
 *   short_text           -> the expected text
 *
 * The comma is not a choice made here: learn_answer_is_correct() splits a
 * multiple-answer key on ',' and any question already written by hand uses that
 * format. So multiple-answer options cannot themselves contain a comma, and this
 * rejects them rather than silently storing a key that would mark every attempt
 * wrong. Single-answer keys are never split, so commas are fine there.
 */
function catalogue_validate_question(array $input): array
{
    $errors = [];

    $question = trim((string)($input['question'] ?? ''));
    $type = (string)($input['type'] ?? 'single');
    $marks = (int)($input['marks'] ?? 1);

    if ($question === '') {
        $errors[] = 'The question needs some text.';
    }
    if (!in_array($type, ['single', 'multiple', 'true_false', 'short_text'], true)) {
        $errors[] = 'Pick a question type.';
    }
    if ($marks < 1 || $marks > 1000) {
        $errors[] = 'Marks must be between 1 and 1000.';
    }

    $options = null;
    $correct = null;

    // Each type reads its own field. One shared field would have to be filled in
    // by script when the type changes, and would save an empty or stale answer
    // whenever that script did not run.
    if ($type === 'true_false') {
        $options = ['True', 'False'];
        $answer = (string)($input['correct_answer_tf'] ?? $input['correct_answer'] ?? '');
        $correct = $answer === 'False' ? 'False' : 'True';
    } elseif ($type === 'short_text') {
        $correct = trim((string)($input['correct_answer_text'] ?? $input['correct_answer'] ?? ''));
        if ($correct === '') {
            $errors[] = 'Give the answer this question is marked against.';
        }
    } else {
        // Choice questions. Blank rows are dropped so an author can leave the
        // spare option boxes empty.
        $raw = $input['options'] ?? [];
        $options = [];
        foreach (is_array($raw) ? $raw : [] as $option) {
            $option = trim((string)$option);
            if ($option !== '') {
                $options[] = $option;
            }
        }
        $options = array_values(array_unique($options));

        if (count($options) < 2) {
            $errors[] = 'A choice question needs at least two options.';
        }
        if (count($options) > 10) {
            $errors[] = 'Ten options is the most a question can have.';
        }

        // Correct answers arrive as indexes into the options the form rendered.
        // Those indexes are only meaningful against the same list, so they are
        // resolved to option text here and stored as text.
        $chosen = $input['correct'] ?? [];
        $chosen = is_array($chosen) ? $chosen : [$chosen];
        $chosenText = [];
        foreach ($chosen as $index) {
            $index = (int)$index;
            if (isset($options[$index])) {
                $chosenText[] = $options[$index];
            }
        }
        $chosenText = array_values(array_unique($chosenText));

        if (!$chosenText) {
            $errors[] = 'Mark at least one option as correct.';
        }
        if ($type === 'single' && count($chosenText) > 1) {
            $errors[] = 'A single-answer question can only have one correct option.';
        }

        if ($type === 'multiple') {
            foreach ($options as $option) {
                if (strpos($option, ',') !== false) {
                    $errors[] = 'Options for a multiple-answer question cannot contain a comma — '
                        . 'the answer key is a comma-separated list. Reword the option, or make it '
                        . 'a single-answer question.';
                    break;
                }
            }
        }

        $correct = implode(',', $chosenText);
    }

    return [
        'errors' => $errors,
        'values' => [
            'question' => $question,
            'type' => $type,
            'options' => $options !== null ? json_encode($options, JSON_UNESCAPED_UNICODE) : null,
            'correct_answer' => $correct,
            'marks' => $marks,
        ],
    ];
}

function catalogue_add_question(mysqli $conn, int $assessmentId, array $values): int
{
    $position = catalogue_next_position($conn, 'public_course_questions', 'assessment_id', $assessmentId);

    $stmt = $conn->prepare("
        INSERT INTO public_course_questions
            (assessment_id, question, type, options, correct_answer, marks, position)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'issssii',
        $assessmentId,
        $values['question'],
        $values['type'],
        $values['options'],
        $values['correct_answer'],
        $values['marks'],
        $position
    );
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();

    return $id;
}

function catalogue_question_in_course(mysqli $conn, int $questionId, int $courseId): ?array
{
    $stmt = $conn->prepare("
        SELECT q.* FROM public_course_questions q
        JOIN public_course_assessments a ON a.id = q.assessment_id
        WHERE q.id = ? AND a.course_id = ? LIMIT 1
    ");
    $stmt->bind_param('ii', $questionId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function catalogue_update_question(mysqli $conn, int $questionId, array $values): void
{
    $stmt = $conn->prepare("
        UPDATE public_course_questions
        SET question = ?, type = ?, options = ?, correct_answer = ?, marks = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'ssssii',
        $values['question'],
        $values['type'],
        $values['options'],
        $values['correct_answer'],
        $values['marks'],
        $questionId
    );
    $stmt->execute();
    $stmt->close();
}

function catalogue_delete_question(mysqli $conn, int $questionId): void
{
    $stmt = $conn->prepare("DELETE FROM public_course_questions WHERE id = ?");
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $stmt->close();
}

// ── Uploads ───────────────────────────────────────────────────────────────────

/**
 * Store an uploaded cover image or lesson attachment.
 *
 * Returns ['ok' => true, 'path' => string] or ['ok' => false, 'error' => string].
 * The path is root-absolute ('/uploads/...') rather than relative, because the
 * learner pages that render it live under /learn/ and a relative path would
 * resolve to /learn/uploads/.
 *
 * The rules mirror lecturer/ajax/upload_block_file.php: extension whitelist
 * first, then the real MIME from finfo, so a .php renamed to .png is rejected
 * on content rather than on trust.
 */
function catalogue_store_upload(array $file, string $kind): array
{
    $rules = [
        'cover' => [
            'folder' => 'course_images',
            'exts' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'limit' => 5 * 1024 * 1024,
        ],
        'attachment' => [
            'folder' => 'course_pdfs',
            'exts' => ['pdf'],
            'mimes' => ['application/pdf'],
            'limit' => 50 * 1024 * 1024,
        ],
    ];

    if (!isset($rules[$kind])) {
        return ['ok' => false, 'error' => 'Unknown upload type.'];
    }
    $rule = $rules[$kind];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'That file is larger than the server accepts (upload_max_filesize is '
                . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE => 'That file is larger than this form accepts.',
            UPLOAD_ERR_PARTIAL => 'The upload was cut short. Try again.',
            UPLOAD_ERR_NO_FILE => 'No file was received.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temp directory for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        ];

        return ['ok' => false, 'error' => $messages[$file['error'] ?? -1] ?? 'The upload failed.'];
    }

    if (($file['size'] ?? 0) > $rule['limit']) {
        return [
            'ok' => false,
            'error' => 'That file is too large. The limit is ' . round($rule['limit'] / 1024 / 1024) . 'MB.',
        ];
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $rule['exts'], true)) {
        return ['ok' => false, 'error' => 'Allowed file types: ' . implode(', ', $rule['exts']) . '.'];
    }

    $mime = null;
    if (class_exists('finfo')) {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        } catch (Throwable $e) {
            error_log('catalogue_store_upload: finfo failed: ' . $e->getMessage());
            $mime = null;
        }
    }

    if ($mime === null || $mime === false || $mime === '') {
        // fileinfo unavailable — stop with a clear, actionable message instead
        // of a raw server 500.
        return ['ok' => false, 'error' => 'The server could not verify the file type because the fileinfo (finfo) extension is disabled. Enable fileinfo in php.ini and try again.'];
    }

    if (!in_array($mime, $rule['mimes'], true)) {
        return ['ok' => false, 'error' => 'That file is not really a ' . $ext . ' (it looks like ' . $mime . ').'];
    }

    $directory = APP_ROOT . '/uploads/' . $rule['folder'];
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        return ['ok' => false, 'error' => 'The upload directory could not be created.'];
    }

    $name = 'pc_' . $kind . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file((string)$file['tmp_name'], $directory . '/' . $name)) {
        error_log('catalogue_store_upload: move_uploaded_file failed for ' . $directory . '/' . $name);

        return ['ok' => false, 'error' => 'The file could not be saved.'];
    }

    return ['ok' => true, 'path' => '/uploads/' . $rule['folder'] . '/' . $name];
}

/**
 * Delete a file this module stored, when it is replaced or removed.
 *
 * The path comes from our own database rather than from a request, but it is
 * still checked against /uploads/ before anything is unlinked: a stray value in
 * that column must not be able to delete a file elsewhere on the server.
 */
function catalogue_discard_upload(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }
    if (strpos($path, '/uploads/') !== 0 || strpos($path, '..') !== false) {
        return;
    }

    $absolute = APP_ROOT . $path;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}
