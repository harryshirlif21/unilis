<?php
/**
 * The public course catalogue, learner progress, and certificate awarding.
 *
 * Completion rule, as chosen: a learner has finished a course when every lesson
 * is marked complete AND every assessment has at least one passing attempt.
 * Both halves are required, so a learner cannot certify by clicking through the
 * lessons without passing anything, nor by passing the quizzes without reading.
 */

/**
 * Published courses, newest first, with lesson and assessment counts.
 */
function learn_catalogue(mysqli $conn, string $search = ''): array
{
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

    $stmt = $conn->prepare("
        SELECT
            c.id, c.slug, c.title, c.summary, c.level, c.estimated_hours,
            c.certificate_enabled, c.cover_image,
            (SELECT COUNT(*) FROM public_course_lessons l
               JOIN public_course_modules m ON m.id = l.module_id
              WHERE m.course_id = c.id) AS lesson_count,
            (SELECT COUNT(*) FROM public_course_assessments a
              WHERE a.course_id = c.id) AS assessment_count,
            (SELECT COUNT(*) FROM external_enrollments e
              WHERE e.course_id = c.id) AS learner_count
        FROM public_courses c
        WHERE c.is_published = 1
          AND (c.title LIKE ? OR c.summary LIKE ?)
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/**
 * One published course by slug, or null.
 */
function learn_course_by_slug(mysqli $conn, string $slug): ?array
{
    $stmt = $conn->prepare("
        SELECT * FROM public_courses WHERE slug = ? AND is_published = 1 LIMIT 1
    ");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $course ?: null;
}

/**
 * A course's modules, each with its lessons and assessments, in order.
 */
function learn_course_outline(mysqli $conn, int $courseId): array
{
    $stmt = $conn->prepare("
        SELECT id, title, summary, position
        FROM public_course_modules WHERE course_id = ? ORDER BY position, id
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $modules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$modules) {
        return [];
    }

    $moduleIds = array_map(static fn($m) => (int)$m['id'], $modules);
    $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
    $types = str_repeat('i', count($moduleIds));

    $stmt = $conn->prepare("
        SELECT id, module_id, title, duration_minutes, position
        FROM public_course_lessons
        WHERE module_id IN ($placeholders)
        ORDER BY position, id
    ");
    $stmt->bind_param($types, ...$moduleIds);
    $stmt->execute();
    $lessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT id, course_id, module_id, title, pass_mark, position
        FROM public_course_assessments WHERE course_id = ? ORDER BY position, id
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($modules as &$module) {
        $mid = (int)$module['id'];
        $module['lessons'] = array_values(array_filter($lessons, static fn($l) => (int)$l['module_id'] === $mid));
        $module['assessments'] = array_values(array_filter($assessments, static fn($a) => (int)$a['module_id'] === $mid));
    }
    unset($module);

    return [
        'modules' => $modules,
        // module_id NULL means course-level, e.g. a final exam.
        'final_assessments' => array_values(array_filter($assessments, static fn($a) => $a['module_id'] === null)),
    ];
}

function learn_is_enrolled(mysqli $conn, int $learnerId, int $courseId): bool
{
    $stmt = $conn->prepare("
        SELECT 1 FROM external_enrollments WHERE learner_id = ? AND course_id = ? LIMIT 1
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $found;
}

/**
 * Enrol a learner, idempotently.
 */
function learn_enrol(mysqli $conn, int $learnerId, int $courseId): void
{
    $stmt = $conn->prepare("
        INSERT IGNORE INTO external_enrollments (learner_id, course_id) VALUES (?, ?)
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Mark a lesson complete. Idempotent: the unique key means a double-click
 * cannot inflate progress.
 */
function learn_complete_lesson(mysqli $conn, int $learnerId, int $lessonId): void
{
    $stmt = $conn->prepare("
        INSERT IGNORE INTO external_lesson_progress (learner_id, lesson_id) VALUES (?, ?)
    ");
    $stmt->bind_param('ii', $learnerId, $lessonId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Lesson ids in a course that this learner has completed.
 */
function learn_completed_lessons(mysqli $conn, int $learnerId, int $courseId): array
{
    $stmt = $conn->prepare("
        SELECT p.lesson_id
        FROM external_lesson_progress p
        JOIN public_course_lessons l ON l.id = p.lesson_id
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE p.learner_id = ? AND m.course_id = ?
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn($r) => (int)$r['lesson_id'], $rows);
}

/**
 * Where a learner stands on a course.
 *
 * Returns lesson and assessment tallies, a percentage, and whether the course
 * is complete under the both-halves rule.
 */
function learn_progress(mysqli $conn, int $learnerId, int $courseId): array
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE m.course_id = ?
    ");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $totalLessons = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $doneLessons = count(learn_completed_lessons($conn, $learnerId, $courseId));

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM public_course_assessments WHERE course_id = ?");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $totalAssessments = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // DISTINCT because attempts are kept rather than overwritten, so one
    // assessment can have several passing rows.
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT a.assessment_id) AS passed
        FROM external_assessment_attempts a
        JOIN public_course_assessments ca ON ca.id = a.assessment_id
        WHERE a.learner_id = ? AND ca.course_id = ? AND a.passed = 1
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $passedAssessments = (int)$stmt->get_result()->fetch_assoc()['passed'];
    $stmt->close();

    // Lessons and assessments are weighted by count rather than 50/50, so a
    // course with twenty lessons and one quiz does not treat the quiz as half
    // the work.
    $totalItems = $totalLessons + $totalAssessments;
    $doneItems = $doneLessons + $passedAssessments;
    $percent = $totalItems > 0 ? (int)round(($doneItems / $totalItems) * 100) : 0;

    // An empty course is never complete: otherwise a published shell with no
    // content would hand out certificates on enrolment.
    $complete = $totalLessons > 0
        && $doneLessons >= $totalLessons
        && $passedAssessments >= $totalAssessments;

    return [
        'total_lessons' => $totalLessons,
        'done_lessons' => $doneLessons,
        'total_assessments' => $totalAssessments,
        'passed_assessments' => $passedAssessments,
        'percent' => $percent,
        'complete' => $complete,
    ];
}

/**
 * Best percentage across a learner's passing attempts on a course, used as the
 * mark printed on the certificate.
 */
function learn_final_percentage(mysqli $conn, int $learnerId, int $courseId): ?float
{
    $stmt = $conn->prepare("
        SELECT AVG(best) AS final FROM (
            SELECT MAX(a.percentage) AS best
            FROM external_assessment_attempts a
            JOIN public_course_assessments ca ON ca.id = a.assessment_id
            WHERE a.learner_id = ? AND ca.course_id = ?
            GROUP BY a.assessment_id
        ) AS per_assessment
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row && $row['final'] !== null ? round((float)$row['final'], 2) : null;
}

/**
 * Award a certificate if the course is complete and one has not been issued.
 *
 * Returns the certificate row, or null when the learner has not finished or the
 * course does not offer one. Safe to call repeatedly: the unique key on
 * (learner_id, course_id) is what makes it idempotent even under a double
 * submit, and an existing row is returned rather than a second being minted.
 */
function learn_maybe_award_certificate(mysqli $conn, int $learnerId, int $courseId): ?array
{
    $existing = learn_certificate_for($conn, $learnerId, $courseId);
    if ($existing !== null) {
        return $existing;
    }

    $stmt = $conn->prepare("SELECT id, title, certificate_enabled FROM public_courses WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$course || (int)$course['certificate_enabled'] !== 1) {
        return null;
    }

    $progress = learn_progress($conn, $learnerId, $courseId);
    if (!$progress['complete']) {
        return null;
    }

    $stmt = $conn->prepare("SELECT name FROM external_learners WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $learnerId);
    $stmt->execute();
    $learner = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$learner) {
        return null;
    }

    $final = learn_final_percentage($conn, $learnerId, $courseId);

    // The serial is what gets printed and read aloud, so it is short and
    // structured. The verification code is what a third party types in, so it
    // is long and random - a guessable code would let anyone confirm a
    // certificate that was never issued.
    $serial = 'UNL-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $verification = bin2hex(random_bytes(24));

    $stmt = $conn->prepare("
        INSERT INTO certificates
            (learner_id, course_id, serial, verification_code,
             learner_name, course_title, final_percentage)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iissssd',
        $learnerId, $courseId, $serial, $verification,
        $learner['name'], $course['title'], $final
    );

    try {
        $stmt->execute();
    } catch (Throwable $e) {
        // Almost certainly the unique key: two requests finished the course at
        // once. Whichever lost the race just reads the winner's row.
        $stmt->close();
        return learn_certificate_for($conn, $learnerId, $courseId);
    }
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE external_enrollments SET completed_at = NOW()
        WHERE learner_id = ? AND course_id = ? AND completed_at IS NULL
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $stmt->close();

    return learn_certificate_for($conn, $learnerId, $courseId);
}

function learn_certificate_for(mysqli $conn, int $learnerId, int $courseId): ?array
{
    $stmt = $conn->prepare("
        SELECT * FROM certificates
        WHERE learner_id = ? AND course_id = ? AND revoked_at IS NULL LIMIT 1
    ");
    $stmt->bind_param('ii', $learnerId, $courseId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Look a certificate up by its public verification code.
 */
function learn_certificate_by_code(mysqli $conn, string $code): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT c.*, p.slug
        FROM certificates c
        LEFT JOIN public_courses p ON p.id = c.course_id
        WHERE c.verification_code = ? OR c.serial = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $code, $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Courses a learner is enrolled on, with progress and any certificate.
 */
function learn_my_courses(mysqli $conn, int $learnerId): array
{
    $stmt = $conn->prepare("
        SELECT c.id, c.slug, c.title, c.summary, c.level, c.cover_image,
               e.enrolled_at, e.completed_at
        FROM external_enrollments e
        JOIN public_courses c ON c.id = e.course_id
        WHERE e.learner_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->bind_param('i', $learnerId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['progress'] = learn_progress($conn, $learnerId, (int)$row['id']);
        $row['certificate'] = learn_certificate_for($conn, $learnerId, (int)$row['id']);
    }
    unset($row);

    return $rows;
}
