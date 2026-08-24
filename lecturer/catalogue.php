<?php
/**
 * Open Courses Studio — the course list.
 * Redesigned with glassmorphism UI.
 * Shows short courses assigned to the lecturer via short_course_tutors.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../learn/config.php';
require_once __DIR__ . '/../learn/includes/authoring.php';
require_once __DIR__ . '/catalogue_layout.php';

$actor = catalogue_require_author();
studio_require_schema($conn);

// ── Create New Course ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!catalogue_csrf_valid($_POST['csrf_token'] ?? null)) {
        studio_flash('Your session expired. Please try again.', 'error');
        header('Location: catalogue.php');
        exit;
    }

    $check = catalogue_validate_course($_POST);

    if ($check['errors']) {
        studio_flash('The course could not be created.', 'error', $check['errors']);
        header('Location: catalogue.php');
        exit;
    }

    $courseId = catalogue_create_course($conn, $actor, $check['values']);
    studio_flash('Draft created. Add modules and lessons, then publish it.');
    header('Location: catalogue_builder.php?course_id=' . $courseId);
    exit;
}

// ── Get Courses ────────────────────────────────────────────────────────────
// Get all courses the lecturer owns (created by them)
$owned_courses = catalogue_courses_for($conn, $actor);

// Get short courses assigned to this lecturer via short_course_tutors
$assigned_courses = [];
if ($actor['role'] === 'lecturer') {
    $stmt = $conn->prepare("
        SELECT pc.*, 
               (SELECT COUNT(*) FROM public_course_modules m WHERE m.course_id = pc.id) AS module_count,
               (SELECT COUNT(*) FROM public_course_lessons l
                  JOIN public_course_modules m ON m.id = l.module_id
                 WHERE m.course_id = pc.id) AS lesson_count,
               (SELECT COUNT(*) FROM public_course_assessments a WHERE a.course_id = pc.id) AS assessment_count,
               (SELECT COUNT(*) FROM external_enrollments e WHERE e.course_id = pc.id) AS learner_count,
               (SELECT COUNT(*) FROM certificates t WHERE t.course_id = pc.id AND t.revoked_at IS NULL) AS certificate_count,
               sct.id AS tutor_id,
               sct.is_active AS is_primary_tutor
        FROM short_course_tutors sct
        JOIN public_courses pc ON pc.id = sct.short_course_id
        WHERE sct.lecturer_id = ?
        ORDER BY pc.updated_at DESC
    ");
    $stmt->bind_param('i', $actor['id']);
    $stmt->execute();
    $assigned_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Also list courses where this lecturer holds module/lesson-level assignment
// (they can open them in the builder but only edit what they are assigned).
$checkTmp = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
if ($checkTmp && $checkTmp->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT DISTINCT pc.*,
               (SELECT COUNT(*) FROM public_course_modules m WHERE m.course_id = pc.id) AS module_count,
               (SELECT COUNT(*) FROM public_course_lessons l
                  JOIN public_course_modules m ON m.id = l.module_id
                 WHERE m.course_id = pc.id) AS lesson_count,
               (SELECT COUNT(*) FROM public_course_assessments a WHERE a.course_id = pc.id) AS assessment_count,
               (SELECT COUNT(*) FROM external_enrollments e WHERE e.course_id = pc.id) AS learner_count,
               (SELECT COUNT(*) FROM certificates t WHERE t.course_id = pc.id AND t.revoked_at IS NULL) AS certificate_count,
               (SELECT COALESCE(sct.id, 0) FROM short_course_tutors sct
                 WHERE sct.short_course_id = pc.id AND sct.lecturer_id = ? LIMIT 1) AS tutor_id,
               (SELECT sct.is_active FROM short_course_tutors sct
                 WHERE sct.short_course_id = pc.id AND sct.lecturer_id = ? LIMIT 1) AS is_primary_tutor
        FROM public_course_modules m
        JOIN public_courses pc ON pc.id = m.course_id
        JOIN tutor_module_permissions tmp ON tmp.module_id = m.id
        WHERE tmp.tutor_id = ? AND tmp.can_edit = 1
        ORDER BY pc.updated_at DESC
    ");
    $stmt->bind_param('iii', $actor['id'], $actor['id'], $actor['id']);
    $stmt->execute();
    $extra = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $assigned_courses = array_merge($assigned_courses, $extra);
}

$checkTlp = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
if ($checkTlp && $checkTlp->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT DISTINCT pc.*,
               (SELECT COUNT(*) FROM public_course_modules m WHERE m.course_id = pc.id) AS module_count,
               (SELECT COUNT(*) FROM public_course_lessons l
                  JOIN public_course_modules m ON m.id = l.module_id
                 WHERE m.course_id = pc.id) AS lesson_count,
               (SELECT COUNT(*) FROM public_course_assessments a WHERE a.course_id = pc.id) AS assessment_count,
               (SELECT COUNT(*) FROM external_enrollments e WHERE e.course_id = pc.id) AS learner_count,
               (SELECT COUNT(*) FROM certificates t WHERE t.course_id = pc.id AND t.revoked_at IS NULL) AS certificate_count,
               (SELECT COALESCE(sct.id, 0) FROM short_course_tutors sct
                 WHERE sct.short_course_id = pc.id AND sct.lecturer_id = ? LIMIT 1) AS tutor_id,
               (SELECT sct.is_active FROM short_course_tutors sct
                 WHERE sct.short_course_id = pc.id AND sct.lecturer_id = ? LIMIT 1) AS is_primary_tutor
        FROM public_course_lessons ls
        JOIN public_course_modules m ON m.id = ls.module_id
        JOIN public_courses pc ON pc.id = m.course_id
        JOIN tutor_lesson_permissions tlp ON tlp.lesson_id = ls.id
        WHERE tlp.tutor_id = ? AND tlp.can_edit = 1
        ORDER BY pc.updated_at DESC
    ");
    $stmt->bind_param('iii', $actor['id'], $actor['id'], $actor['id']);
    $stmt->execute();
    $extra = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $assigned_courses = array_merge($assigned_courses, $extra);
}

// Merge: owned courses + assigned courses (deduplicate by id)
$seen = [];
$all_courses = [];
foreach (array_merge($owned_courses, $assigned_courses) as $c) {
    $id = (int)$c['id'];
    if (!isset($seen[$id])) {
        $seen[$id] = true;
        $all_courses[] = $c;
    }
}

$published = count(array_filter($all_courses, static fn($c) => (int)$c['is_published'] === 1));

studio_head('My open courses');
?>
<div class="st-page-head">
    <div>
        <h1>Open Courses Studio</h1>
        <p class="st-sub">
            Learning opportunities for everyone.
            <?= count($all_courses) ?> course<?= count($all_courses) === 1 ? '' : 's' ?>,
            <?= $published ?> published.
            <?php if ($actor['role'] === 'admin'): ?>
                <br>You are viewing every course, because you are an administrator.
            <?php endif; ?>
        </p>
    </div>
    <div class="st-actions">
        <span class="st-chip st-chip-info">
            <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($actor['name'] ?? 'Lecturer') ?>
        </span>
    </div>
</div>

<?php if (!$all_courses): ?>
    <div class="st-card">
        <div class="st-empty">
            <i class="fas fa-globe"></i>
            <h2>No open courses yet</h2>
            <p>Create one below. It stays a draft — invisible in the catalogue — until you publish it.</p>
        </div>
    </div>
<?php else: ?>
    <div class="st-courses">
        <?php foreach ($all_courses as $course):
            $courseId = (int)$course['id'];
            $isPublished = (int)$course['is_published'] === 1;
            $isAssigned = ($course['tutor_id'] ?? null) !== null;
            $isPrimary = !empty($course['is_primary_tutor']);
            $courseSlug = $course['slug'] ?? '';
            ?>
            <article class="st-course" data-course-id="<?= $courseId ?>" data-course-slug="<?= htmlspecialchars($courseSlug) ?>">
                <div class="st-course-cover">
                    <?php if (!empty($course['cover_image'])): ?>
                        <img src="<?= studio_e(studio_asset_url($course['cover_image'])) ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap"></i>
                    <?php endif; ?>
                </div>
                <div class="st-course-body">
                    <h3>
                        <a href="catalogue_builder.php?course_id=<?= $courseId ?>"><?= studio_e($course['title']) ?></a>
                    </h3>
                    <?php if (!empty($course['summary'])): ?>
                        <p class="st-sub"><?= studio_e($course['summary']) ?></p>
                    <?php endif; ?>

                    <div class="st-meta">
                        <span class="st-chip <?= $isPublished ? 'st-chip-live' : 'st-chip-draft' ?>">
                            <i class="fas <?= $isPublished ? 'fa-circle-check' : 'fa-pen-ruler' ?>"></i>
                            <?= $isPublished ? 'Published' : 'Draft' ?>
                        </span>
                        <?php if ($isAssigned): ?>
                            <span class="st-chip st-chip-info">
                                <i class="fas fa-user-graduate"></i> <?= $isPrimary ? 'Primary Tutor' : 'Contributor' ?>
                            </span>
                        <?php endif; ?>
                        <span class="st-chip"><?= studio_e(ucfirst((string)$course['level'])) ?></span>
                        <span><?= (int)$course['module_count'] ?> modules</span>
                        <span>· <?= (int)$course['lesson_count'] ?> lessons</span>
                        <span>· <?= (int)$course['learner_count'] ?> enrolled</span>
                        <?php if ((int)$course['certificate_count'] > 0): ?>
                            <span class="st-chip st-chip-info">
                                <i class="fas fa-award"></i> <?= (int)$course['certificate_count'] ?> certified
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="st-actions" style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap; overflow:visible;">
                        <a class="st-btn st-btn-small" href="course_builder.php?course_id=<?= $courseId ?>">
                            <i class="fas fa-pen"></i> Edit Course
                        </a>
                        <a class="st-btn st-btn-small st-btn-primary" href="course_builder.php?course_id=<?= $courseId ?>">
                            <i class="fas fa-chalkboard-teacher"></i> Teach
                        </a>
                        <?php if ($isPublished): ?>
                            <a class="st-btn st-btn-small st-btn-ghost" target="_blank" rel="noopener"
                               href="/learn/course.php?c=<?= urlencode((string)$course['slug']) ?>">
                                <i class="fas fa-arrow-up-right-from-square"></i> View live
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
studio_foot();
?>
