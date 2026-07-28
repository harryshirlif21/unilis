<?php
/**
 * Course page: outline, enrolment, lesson reading and completion.
 *
 * One page rather than a separate lesson view, so a learner keeps the outline in
 * sight while working and "next lesson" is a link rather than a round trip
 * through an index.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/layout.php';

$learner = learn_current($conn);
$slug = (string)($_GET['c'] ?? '');
$course = $slug !== '' ? learn_course_by_slug($conn, $slug) : null;

if ($course === null) {
    learn_head(['title' => 'Course not found', 'learner' => $learner, 'narrow' => true]);
    echo '<div class="ln-empty"><span class="material-symbols-rounded">search_off</span>'
       . '<h2>Course not found</h2><p>It may have been unpublished.</p>'
       . '<p style="margin-top:16px;"><a class="ln-btn ln-btn-primary" href="/learn/">Browse courses</a></p></div>';
    learn_foot();
    exit;
}

$courseId = (int)$course['id'];
$outline = learn_course_outline($conn, $courseId);
$modules = $outline['modules'] ?? [];
$finals = $outline['final_assessments'] ?? [];

$notice = null;
$noticeKind = 'success';

// ── Actions ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($learner === null) {
        header('Location: /learn/login.php?next=' . urlencode('/learn/course.php?c=' . $slug));
        exit;
    }
    if (!learn_csrf_valid($_POST['csrf_token'] ?? null)) {
        $notice = 'Your session expired. Please try again.';
        $noticeKind = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'enrol') {
            learn_enrol($conn, $learner['id'], $courseId);
            $notice = 'You are enrolled. Start with the first lesson below.';
        } elseif ($action === 'complete_lesson') {
            $lessonId = (int)($_POST['lesson_id'] ?? 0);

            // The lesson must belong to this course, or a learner could mark
            // another course's lessons complete by posting its id here.
            $stmt = $conn->prepare("
                SELECT l.id FROM public_course_lessons l
                JOIN public_course_modules m ON m.id = l.module_id
                WHERE l.id = ? AND m.course_id = ? LIMIT 1
            ");
            $stmt->bind_param('ii', $lessonId, $courseId);
            $stmt->execute();
            $valid = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$valid) {
                $notice = 'That lesson is not part of this course.';
                $noticeKind = 'error';
            } elseif (!learn_is_enrolled($conn, $learner['id'], $courseId)) {
                $notice = 'Enrol on the course first.';
                $noticeKind = 'error';
            } else {
                learn_complete_lesson($conn, $learner['id'], $lessonId);
                $notice = 'Lesson marked complete.';

                // Finishing the last item is what triggers the award, so this is
                // checked on every completion rather than on a separate button.
                $awarded = learn_maybe_award_certificate($conn, $learner['id'], $courseId);
                if ($awarded !== null) {
                    $notice = 'Course complete — your certificate is ready.';
                }
            }
        }
    }
}

$enrolled = $learner !== null && learn_is_enrolled($conn, $learner['id'], $courseId);
$progress = $learner !== null ? learn_progress($conn, $learner['id'], $courseId) : null;
$doneLessons = $learner !== null ? learn_completed_lessons($conn, $learner['id'], $courseId) : [];
$certificate = $learner !== null ? learn_certificate_for($conn, $learner['id'], $courseId) : null;

// Which lesson to show. Defaults to the first one the learner has not finished.
$openLessonId = (int)($_GET['lesson'] ?? 0);
$allLessons = [];
foreach ($modules as $module) {
    foreach ($module['lessons'] as $lesson) {
        $allLessons[] = $lesson;
    }
}
if ($openLessonId === 0) {
    foreach ($allLessons as $lesson) {
        if (!in_array((int)$lesson['id'], $doneLessons, true)) {
            $openLessonId = (int)$lesson['id'];
            break;
        }
    }
    if ($openLessonId === 0 && $allLessons) {
        $openLessonId = (int)$allLessons[0]['id'];
    }
}

$openLesson = null;
if ($openLessonId > 0 && $enrolled) {
    $stmt = $conn->prepare("
        SELECT l.* FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE l.id = ? AND m.course_id = ? LIMIT 1
    ");
    $stmt->bind_param('ii', $openLessonId, $courseId);
    $stmt->execute();
    $openLesson = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

learn_head(['title' => $course['title'], 'learner' => $learner]);
?>
<section class="ln-hero">
    <h1><?= learn_e($course['title']) ?></h1>
    <p><?= learn_e($course['summary'] ?? '') ?></p>
    <div class="ln-meta" style="margin-top:14px;">
        <span class="ln-chip">
            <span class="material-symbols-rounded">signal_cellular_alt</span>
            <?= learn_e(ucfirst((string)$course['level'])) ?>
        </span>
        <?php if (!empty($course['estimated_hours'])): ?>
            <span class="ln-chip">
                <span class="material-symbols-rounded">schedule</span>
                <?= learn_e((string)(float)$course['estimated_hours']) ?> hours
            </span>
        <?php endif; ?>
        <?php if ((int)$course['certificate_enabled'] === 1): ?>
            <span class="ln-chip ln-chip-amber">
                <span class="material-symbols-rounded">workspace_premium</span>
                Certificate on completion
            </span>
        <?php endif; ?>
    </div>
</section>

<?php if ($notice !== null) { learn_notice($notice, $noticeKind); } ?>

<?php if ($certificate !== null): ?>
    <div class="ln-alert ln-alert-success">
        <span class="material-symbols-rounded">workspace_premium</span>
        <div>
            You completed this course.
            <a href="/learn/certificate.php?code=<?= learn_e($certificate['verification_code']) ?>">View your certificate</a>.
        </div>
    </div>
<?php endif; ?>

<?php if ($learner === null): ?>
    <div class="ln-card" style="margin-bottom:26px;">
        <h1 style="font-size:1.15rem;">Sign in to start</h1>
        <p class="ln-sub">The outline is below. Create a free account to work through it and earn the certificate.</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="ln-btn ln-btn-primary" href="/learn/register.php">Create account</a>
            <a class="ln-btn ln-btn-ghost"
               href="/learn/login.php?next=<?= learn_e(urlencode('/learn/course.php?c=' . $slug)) ?>">Sign in</a>
        </div>
    </div>
<?php elseif (!$enrolled): ?>
    <form method="post" style="margin-bottom:26px;">
        <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
        <input type="hidden" name="action" value="enrol">
        <button class="ln-btn ln-btn-primary" type="submit">
            <span class="material-symbols-rounded">bookmark_add</span> Enrol on this course
        </button>
    </form>
<?php else: ?>
    <div class="ln-card" style="margin-bottom:26px; padding:20px 24px;">
        <div class="ln-bar" style="margin-top:0;" role="progressbar"
             aria-valuenow="<?= (int)$progress['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="ln-bar-fill" style="width:<?= (int)$progress['percent'] ?>%"></div>
        </div>
        <p style="margin:0; font-size:0.86rem; color:var(--ln-muted);">
            <?= (int)$progress['done_lessons'] ?>/<?= (int)$progress['total_lessons'] ?> lessons
            <?php if ((int)$progress['total_assessments'] > 0): ?>
                · <?= (int)$progress['passed_assessments'] ?>/<?= (int)$progress['total_assessments'] ?> assessments passed
            <?php endif; ?>
            · <?= (int)$progress['percent'] ?>% complete
        </p>
    </div>
<?php endif; ?>

<?php if ($openLesson !== null): ?>
    <article class="ln-card" style="margin-bottom:26px;">
        <h1 style="font-size:1.3rem;"><?= learn_e($openLesson['title']) ?></h1>
        <?php if (!empty($openLesson['video_url'])): ?>
            <p class="ln-sub">
                <a href="<?= learn_e($openLesson['video_url']) ?>" target="_blank" rel="noopener">
                    Watch the video for this lesson
                </a>
            </p>
        <?php endif; ?>

        <?php // Lesson bodies are lecturer-authored rich text from the editor, so
              // they are rendered as markup here by design. ?>
        <div class="ln-lesson-body"><?= $openLesson['content_html'] ?? '' ?></div>

        <?php if (!in_array((int)$openLesson['id'], $doneLessons, true)): ?>
            <form method="post" style="margin-top:22px;">
                <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
                <input type="hidden" name="action" value="complete_lesson">
                <input type="hidden" name="lesson_id" value="<?= (int)$openLesson['id'] ?>">
                <button class="ln-btn ln-btn-primary" type="submit">
                    <span class="material-symbols-rounded">check</span> Mark complete
                </button>
            </form>
        <?php else: ?>
            <p style="margin-top:22px;">
                <span class="ln-chip ln-chip-done">
                    <span class="material-symbols-rounded">check_circle</span> Completed
                </span>
            </p>
        <?php endif; ?>
    </article>
<?php endif; ?>

<h2 style="font-size:1.15rem; color:var(--ln-ink); margin:0 0 14px;">Course outline</h2>

<?php if (!$modules && !$finals): ?>
    <div class="ln-empty">
        <span class="material-symbols-rounded">construction</span>
        <h2>No content yet</h2>
        <p>This course has been published but has no lessons in it.</p>
    </div>
<?php else: ?>
    <div class="ln-card" style="padding:0; overflow:hidden;">
        <?php foreach ($modules as $module): ?>
            <div style="padding:18px 22px; border-bottom:1px solid var(--ln-line);">
                <h3 style="margin:0 0 4px; font-size:1rem; color:var(--ln-ink);">
                    <?= learn_e($module['title']) ?>
                </h3>
                <?php if (!empty($module['summary'])): ?>
                    <p style="margin:0 0 10px; font-size:0.85rem; color:var(--ln-muted);">
                        <?= learn_e($module['summary']) ?>
                    </p>
                <?php endif; ?>

                <ul style="list-style:none; margin:8px 0 0; padding:0; display:flex; flex-direction:column; gap:6px;">
                    <?php foreach ($module['lessons'] as $lesson):
                        $done = in_array((int)$lesson['id'], $doneLessons, true);
                        ?>
                        <li style="display:flex; align-items:center; gap:9px; font-size:0.9rem;">
                            <span class="material-symbols-rounded"
                                  style="font-size:19px; color:<?= $done ? 'var(--ln-success)' : 'var(--ln-line)' ?>;">
                                <?= $done ? 'check_circle' : 'radio_button_unchecked' ?>
                            </span>
                            <?php if ($enrolled): ?>
                                <a href="/learn/course.php?c=<?= learn_e($slug) ?>&lesson=<?= (int)$lesson['id'] ?>">
                                    <?= learn_e($lesson['title']) ?>
                                </a>
                            <?php else: ?>
                                <span><?= learn_e($lesson['title']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($lesson['duration_minutes'])): ?>
                                <span style="color:var(--ln-muted); font-size:0.78rem;">
                                    <?= (int)$lesson['duration_minutes'] ?> min
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>

                    <?php foreach ($module['assessments'] as $assessment): ?>
                        <li style="display:flex; align-items:center; gap:9px; font-size:0.9rem;">
                            <span class="material-symbols-rounded" style="font-size:19px; color:var(--ln-amber);">quiz</span>
                            <?php if ($enrolled): ?>
                                <a href="/learn/assessment.php?a=<?= (int)$assessment['id'] ?>">
                                    <?= learn_e($assessment['title']) ?>
                                </a>
                            <?php else: ?>
                                <span><?= learn_e($assessment['title']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <?php if ($finals): ?>
            <div style="padding:18px 22px;">
                <h3 style="margin:0 0 10px; font-size:1rem; color:var(--ln-ink);">Final assessment</h3>
                <ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px;">
                    <?php foreach ($finals as $assessment): ?>
                        <li style="display:flex; align-items:center; gap:9px; font-size:0.9rem;">
                            <span class="material-symbols-rounded" style="font-size:19px; color:var(--ln-amber);">workspace_premium</span>
                            <?php if ($enrolled): ?>
                                <a href="/learn/assessment.php?a=<?= (int)$assessment['id'] ?>">
                                    <?= learn_e($assessment['title']) ?>
                                </a>
                            <?php else: ?>
                                <span><?= learn_e($assessment['title']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php
learn_foot();
