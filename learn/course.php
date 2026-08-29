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
require_once __DIR__ . '/includes/content_renderer.php';

learn_require_schema($conn);

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

// Get ongoing and upcoming modules for display
$ongoingModules = learn_ongoing_modules($conn, $courseId);
$upcomingModules = learn_upcoming_modules($conn, $courseId);
$completedModules = learn_completed_modules($conn, $courseId);

// Scheduled lessons for this course, bucketed by date for the
// "Upcoming lessons" / "Past lessons" sections on this page.
$courseSchedule = learn_course_lesson_schedule($conn, $courseId);
$courseUpcomingLessons = $courseSchedule['upcoming'];
$coursePastLessons = $courseSchedule['past'];

// Fetch course sponsors from course_sponsors table
$course_sponsors = [];
$checkSponsorsTable = $conn->query("SHOW TABLES LIKE 'course_sponsors'");
if ($checkSponsorsTable && $checkSponsorsTable->num_rows > 0) {
    $stmt = $conn->prepare("SELECT sponsor_name, sponsor_details, sponsor_logo FROM course_sponsors WHERE course_id = ? ORDER BY id");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $course_sponsors[] = $row;
    }
    $stmt->close();
}

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
            // Check if learner is a tutor assigned to this course
            $stmt = $conn->prepare("
                SELECT sct.id 
                FROM short_course_tutors sct
                JOIN lecturers l ON l.id = sct.lecturer_id
                WHERE sct.short_course_id = ? AND l.email = ? AND sct.is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param('is', $courseId, $learner['email']);
            $stmt->execute();
            $is_tutor = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($is_tutor) {
                $notice = 'You are a tutor for this course and cannot enroll as a learner.';
                $noticeKind = 'error';
            } else {
                learn_enrol($conn, $learner['id'], $courseId);
                $notice = 'You are enrolled. Start with the first lesson below.';
            }
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
        } elseif ($action === 'mark_topic_read') {
            $topicId = (int)($_POST['topic_id'] ?? 0);

            // The topic must belong to a lesson within this course, or a learner
            // could mark topics in another course's lessons as read.
            $stmt = $conn->prepare("
                SELECT t.id
                FROM public_course_lesson_topics t
                JOIN public_course_lessons l ON l.id = t.lesson_id
                JOIN public_course_modules m ON m.id = l.module_id
                WHERE t.id = ? AND m.course_id = ? LIMIT 1
            ");
            $stmt->bind_param('ii', $topicId, $courseId);
            $stmt->execute();
            $valid = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$valid) {
                $notice = 'That topic is not part of this course.';
                $noticeKind = 'error';
            } elseif (!learn_is_enrolled($conn, $learner['id'], $courseId)) {
                $notice = 'Enrol on the course first.';
                $noticeKind = 'error';
            } else {
                learn_mark_topic_read($conn, $learner['id'], $topicId);
                $notice = 'Marked as read.';
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

// Topics (and subtopics) authored for this lesson, plus which the learner has
// already marked as read, for the reading tracker + per-topic completion counts.
$openLessonTopics = $openLesson ? learn_lesson_topics($conn, (int)$openLesson['id']) : [];
$readTopicIds = [];
if ($openLesson && $learner !== null) {
    $readTopicIds = learn_read_topic_ids($conn, (int)$learner['id'], (int)$openLesson['id']);
}

// Info for the lesson-tap modal shown in the course outline: each lesson's
// notes (topics/subtopics) and its own assessments (linked by lesson_id).
$lessonInfoMap = [];
if ($learner !== null && $enrolled) {
    foreach ($allLessons as $lesson) {
        $lid = (int)$lesson['id'];
        $notes = learn_lesson_topics($conn, $lid);
        $munits = [];
        foreach ((array)$notes as $t) {
            $entry = ['title' => (string)$t['title'], 'subs' => []];
            foreach ((array)$t['subs'] as $s) {
                $entry['subs'][] = ['title' => (string)$s['title']];
            }
            $munits[] = $entry;
        }

        $asmt = [];
        $stmt = $conn->prepare("
            SELECT id, title, type
            FROM public_course_assessments
            WHERE lesson_id = ? ORDER BY position, id
        ");
        $stmt->bind_param('i', $lid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $a) {
            $asmt[] = ['title' => (string)$a['title'], 'type' => (string)$a['type']];
        }

        $lessonInfoMap[$lid] = [
            'title'       => (string)$lesson['title'],
            'notes'       => $munits,
            'assessments' => $asmt,
        ];
    }
}

// Mirror the course flow on the learner side: each topic (lesson) has a Quiz,
// and each module of lessons ends with a CAT. Surfaced right on the open lesson
// so the flow reads "topic → quiz → next topic … → CAT" as the learner works.
$openLessonQuiz = null;
$openLessonCat = null;
if ($openLesson && $learner !== null) {
    $openModuleId = (int)$openLesson['module_id'];

    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.type, a.module_id, a.lesson_id
        FROM public_course_assessments a
        WHERE a.course_id = ? AND a.type = 'quiz' AND a.lesson_id = ?
        ORDER BY a.position, a.id LIMIT 1
    ");
    $stmt->bind_param('ii', $courseId, $openLessonId);
    $stmt->execute();
    $openLessonQuiz = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.type, a.module_id, a.lesson_id
        FROM public_course_assessments a
        WHERE a.course_id = ? AND a.type = 'cat' AND a.module_id = ? AND a.lesson_id = 0
        ORDER BY a.position, a.id LIMIT 1
    ");
    $stmt->bind_param('ii', $courseId, $openModuleId);
    $stmt->execute();
    $openLessonCat = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    // Learner's passing status for these, so the flow shows what is done.
    $assessmentTargets = [];
    foreach ([$openLessonQuiz, $openLessonCat] as $a) {
        if ($a) {
            $assessmentTargets[(int)$a['id']] = false;
        }
    }
    if (!empty($assessmentTargets)) {
        $ids = array_keys($assessmentTargets);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("
            SELECT assessment_id, MAX(passed) AS passed
            FROM external_assessment_attempts
            WHERE learner_id = ? AND assessment_id IN ($placeholders)
            GROUP BY assessment_id
        ");
        $stmt->bind_param('i' . str_repeat('i', count($ids)), $learner['id'], ...$ids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $assessmentTargets[(int)$row['assessment_id']] = (int)$row['passed'] === 1;
        }
        $stmt->close();
    }
    if ($openLessonQuiz) {
        $openLessonQuiz['passed'] = $assessmentTargets[(int)$openLessonQuiz['id']];
    }
    if ($openLessonCat) {
        $openLessonCat['passed'] = $assessmentTargets[(int)$openLessonCat['id']];
    }
}

learn_head(['title' => $course['title'], 'learner' => $learner]);
?>
<?php if (!empty($course['cover_image']) && $course['cover_image'] !== '0'): ?>
    <?php
    $bannerPath = (string)$course['cover_image'];
    // Remove any relative path components and ensure leading slash
    $bannerPath = preg_replace('#^(?:\.\./)+#', '', $bannerPath);
    if (strpos($bannerPath, 'http') !== 0 && strpos($bannerPath, '/') !== 0) {
        $bannerPath = '/' . ltrim($bannerPath, '/');
    }
    ?>
    <div class="ln-course-banner"><img src="<?= learn_e($bannerPath) ?>" alt=""></div>
<?php endif; ?>
<section class="ln-hero ln-course-intro">
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

<?php if (!empty($course_sponsors)): ?>
    <?php foreach ($course_sponsors as $sponsor): ?>
    <aside class="ln-sponsor" aria-label="Course sponsor">
        <?php if (!empty($sponsor['sponsor_logo'])):
            $sponsorLogoPath = (string)$sponsor['sponsor_logo'];
            if (strpos($sponsorLogoPath, 'http') !== 0 && strpos($sponsorLogoPath, '/') !== 0) $sponsorLogoPath = '/' . ltrim($sponsorLogoPath, '/');
        ?>
            <img src="<?= learn_e($sponsorLogoPath) ?>" alt="<?= learn_e($sponsor['sponsor_name']) ?> logo">
        <?php endif; ?>
        <div><strong>Sponsored by <?= learn_e($sponsor['sponsor_name']) ?></strong><?php if (!empty($sponsor['sponsor_details'])): ?><p><?= learn_e($sponsor['sponsor_details']) ?></p><?php endif; ?></div>
    </aside>
    <?php endforeach; ?>
<?php elseif ((int)($course['is_sponsored'] ?? 0) === 1 && !empty($course['sponsor_name'])): ?>
    <!-- Fallback for legacy single sponsor data -->
    <aside class="ln-sponsor" aria-label="Course sponsor">
        <?php if (!empty($course['sponsor_logo'])):
            $sponsorLogoPath = (string)$course['sponsor_logo'];
            if (strpos($sponsorLogoPath, 'http') !== 0 && strpos($sponsorLogoPath, '/') !== 0) $sponsorLogoPath = '/' . ltrim($sponsorLogoPath, '/');
        ?>
            <img src="<?= learn_e($sponsorLogoPath) ?>" alt="<?= learn_e($course['sponsor_name']) ?> logo">
        <?php endif; ?>
        <div><strong>Sponsored by <?= learn_e($course['sponsor_name']) ?></strong><?php if (!empty($course['sponsor_details'])): ?><p><?= learn_e($course['sponsor_details']) ?></p><?php endif; ?></div>
    </aside>
<?php endif; ?>

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

        <?php // The lesson editor lets an author attach a PDF, so it has to be
              // reachable from here - otherwise the upload goes nowhere a learner
              // can see it. ?>
        <?php if (!empty($openLesson['attachment_path'])): ?>
            <p class="ln-sub">
                <a href="<?= learn_e($openLesson['attachment_path']) ?>" target="_blank" rel="noopener">
                    Download the notes for this lesson (PDF)
                </a>
            </p>
        <?php endif; ?>

        <?php // Lesson bodies are lecturer-authored rich text from the editor, so
              // they are rendered as markup here by design. ?>
        <div class="ln-lesson-body"><?= render_lesson_content($openLesson['content_html'] ?? '') ?></div>

        <?php if ($openLessonTopics): ?>
            <div style="margin-top:20px; border-top:1px solid var(--ln-line); padding-top:14px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                    <h2 style="font-size:1.05rem; color:var(--ln-ink); margin:0;">Reading topics</h2>
                    <?php
                    // Count read topics+subtopics out of the learner's read set.
                    $readCount = count(array_intersect($readTopicIds, array_column(array_merge($openLessonTopics, array_merge(...array_map(fn($t) => $t['subs'], $openLessonTopics))), 'id')));
                    ?>
                    <span style="font-size:0.8rem; color:var(--ln-muted);"><?= $readCount ?> read</span>
                </div>
                <?php foreach ($openLessonTopics as $topic):
                    $tRead = in_array((int)$topic['id'], $readTopicIds, true);
                    ?>
                    <div style="border:1px solid var(--ln-line); border-radius:10px; padding:14px 16px; margin-bottom:12px;">
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <?php if ($learner !== null): ?>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="mark_topic_read">
                                    <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                                    <button type="submit" style="background:none;border:none;padding:0;cursor:pointer;" title="<?= $tRead ? 'Marked as read' : 'Mark this topic as read' ?>">
                                        <span class="material-symbols-rounded" style="font-size:26px; color:<?= $tRead ? 'var(--ln-green-mid)' : 'var(--ln-muted)' ?>;"><?= $tRead ? 'check_circle' : 'radio_button_unchecked' ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:650; color:var(--ln-ink);"><?= learn_e($topic['title']) ?></div>
                                <?php if (!empty($topic['content_html'])): ?>
                                    <p style="margin:6px 0 0; font-size:0.85rem; color:var(--ln-ink); white-space:pre-wrap;"><?= learn_e($topic['content_html']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($topic['subs']): ?>
                            <?php foreach ($topic['subs'] as $sub):
                                $sRead = in_array((int)$sub['id'], $readTopicIds, true);
                                ?>
                                <div style="margin:10px 0 0 26px; padding-top:10px; border-top:1px dashed var(--ln-line);">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
                                            <input type="hidden" name="action" value="mark_topic_read">
                                            <input type="hidden" name="topic_id" value="<?= (int)$sub['id'] ?>">
                                            <button type="submit" class="material-symbols-rounded" style="background:none;border:none;padding:0;cursor:pointer;font-size:18px;color:<?= $sRead ? 'var(--ln-green-mid)' : 'var(--ln-muted)' ?>;"><?= $sRead ? 'check_circle' : 'radio_button_unchecked' ?></button>
                                        </form>
                                        <div style="flex:1; min-width:0;">
                                            <div style="font-size:0.9rem; color:var(--ln-ink);"><?= learn_e($sub['title']) ?></div>
                                            <?php if (!empty($sub['content_html'])): ?>
                                                <p style="margin:4px 0 0; font-size:0.82rem; color:var(--ln-muted); white-space:pre-wrap;"><?= learn_e($sub['content_html']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php // small completion trace at the bottom of each topic ?>
                        <div style="margin-top:10px; padding-top:8px; border-top:1px dashed var(--ln-line); font-size:0.78rem; color:var(--ln-muted);">
                            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle; color:var(--ln-green-mid);">visibility</span>
                            <?= (int)learn_topic_reader_count($conn, (int)$topic['id']) ?> learner<?= learn_topic_reader_count($conn, (int)$topic['id']) === 1 ? '' : 's' ?> have completed reading this topic
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($openLessonQuiz): ?>
            <div class="ln-card" style="margin-top:20px; padding:16px 18px; display:flex; align-items:center; gap:12px;">
                <span class="material-symbols-rounded" style="font-size:24px; color:var(--ln-green-mid);">quiz</span>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:650; color:var(--ln-ink);"><?= learn_e($openLessonQuiz['title']) ?></div>
                    <div style="font-size:0.8rem; color:var(--ln-muted);">Quiz for this topic</div>
                </div>
                <?php if ($openLessonQuiz['passed']): ?>
                    <span class="ln-chip ln-chip-done"><span class="material-symbols-rounded">check_circle</span> Passed</span>
                <?php else: ?>
                    <a class="ln-btn ln-btn-primary" href="/learn/assessment.php?a=<?= (int)$openLessonQuiz['id'] ?>">
                        <span class="material-symbols-rounded">quiz</span> Take Quiz
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (in_array((int)$openLesson['id'], $doneLessons, true)): ?>
            <p style="margin-top:22px;">
                <span class="ln-chip ln-chip-done">
                    <span class="material-symbols-rounded">check_circle</span> Completed
                </span>
            </p>
        <?php endif; ?>

        <?php if ($openLessonCat): ?>
            <div class="ln-card" style="margin-top:20px; padding:16px 18px; display:flex; align-items:center; gap:12px;">
                <span class="material-symbols-rounded" style="font-size:24px; color:var(--ln-amber);">flag</span>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:650; color:var(--ln-ink);"><?= learn_e($openLessonCat['title']) ?></div>
                    <div style="font-size:0.8rem; color:var(--ln-muted);">CAT at the end of this lesson</div>
                </div>
                <?php if ($openLessonCat['passed']): ?>
                    <span class="ln-chip ln-chip-done"><span class="material-symbols-rounded">check_circle</span> Passed</span>
                <?php else: ?>
                    <a class="ln-btn ln-btn-primary" href="/learn/assessment.php?a=<?= (int)$openLessonCat['id'] ?>">
                        <span class="material-symbols-rounded">fact_check</span> Take CAT
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
<?php endif; ?>

<?php if ($learner !== null): ?>
<h2 style="font-size:1.15rem; color:var(--ln-ink); margin:0 0 14px;">Course outline</h2>

<?php if (!$modules && !$finals): ?>
    <div class="ln-empty">
        <span class="material-symbols-rounded">construction</span>
        <h2>No content yet</h2>
        <p>This course has been published but has no lessons in it.</p>
    </div>
<?php else: ?>
    <div class="ln-card" style="padding:0; overflow:hidden;">
        <?php if (!empty($ongoingModules)): ?>
            <div style="padding:16px 22px; background:var(--ln-success-bg); border-bottom:1px solid var(--ln-line);">
                <h3 style="margin:0 0 8px; font-size:0.95rem; color:var(--ln-success); display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-rounded">play_circle</span>
                    Ongoing Modules
                </h3>
            </div>
            <?php foreach ($ongoingModules as $module): ?>
                <?php 
                // Fetch lessons for this module
                $moduleLessons = [];
                $stmt = $conn->prepare("
                    SELECT id, title, duration_minutes 
                    FROM public_course_lessons 
                    WHERE module_id = ? 
                    ORDER BY position, id
                ");
                $stmt->bind_param('i', $module['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $moduleLessons[] = $row;
                }
                $stmt->close();
                ?>
                <div style="padding:18px 22px; border-bottom:1px solid var(--ln-line);">
                    <h3 style="margin:0 0 4px; font-size:1rem; color:var(--ln-ink);">
                        <?= learn_e($module['title']) ?>
                    </h3>
                    <?php if (!empty($module['summary'])): ?>
                        <p style="margin:0 0 10px; font-size:0.85rem; color:var(--ln-muted);">
                            <?= learn_e($module['summary']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($module['start_date']) || !empty($module['end_date'])): ?>
                        <p style="margin:0 0 10px; font-size:0.8rem; color:var(--ln-muted);">
                            <?php if (!empty($module['start_date'])): ?>
                                <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle;">event</span>
                                <?= learn_e(date('M j, Y', strtotime($module['start_date']))) ?>
                            <?php endif; ?>
                            <?php if (!empty($module['end_date'])): ?>
                                <?php if (!empty($module['start_date'])): ?> → <?php endif; ?>
                                <?= learn_e(date('M j, Y', strtotime($module['end_date']))) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <ul style="list-style:none; margin:8px 0 0; padding:0; display:flex; flex-direction:column; gap:6px;">
                        <?php foreach ($moduleLessons as $lesson):
                            $done = in_array((int)$lesson['id'], $doneLessons, true);
                            ?>
                            <li style="display:flex; align-items:center; gap:9px; font-size:0.9rem;">
                                <span class="material-symbols-rounded"
                                      style="font-size:19px; color:<?= $done ? 'var(--ln-success)' : 'var(--ln-line)' ?>;">
                                    <?= $done ? 'check_circle' : 'radio_button_unchecked' ?>
                                </span>
                            <?php if ($enrolled): ?>
                                <a href="javascript:void(0)" onclick="openLessonModal(<?= (int)$lesson['id'] ?>)" style="cursor:pointer;">
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
        <?php endif; ?>

        <?php if (!empty($upcomingModules)): ?>
            <div style="padding:16px 22px; background:var(--ln-amber-bg); border-bottom:1px solid var(--ln-line);">
                <h3 style="margin:0 0 8px; font-size:0.95rem; color:var(--ln-amber); display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-rounded">schedule</span>
                    Upcoming Modules
                </h3>
            </div>
            <?php foreach ($upcomingModules as $module): ?>
                <?php 
                // Fetch lessons for this module
                $moduleLessons = [];
                $stmt = $conn->prepare("
                    SELECT id, title, duration_minutes 
                    FROM public_course_lessons 
                    WHERE module_id = ? 
                    ORDER BY position, id
                ");
                $stmt->bind_param('i', $module['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $moduleLessons[] = $row;
                }
                $stmt->close();
                ?>
                <div style="padding:18px 22px; border-bottom:1px solid var(--ln-line);">
                    <h3 style="margin:0 0 4px; font-size:1rem; color:var(--ln-ink);">
                        <?= learn_e($module['title']) ?>
                    </h3>
                    <?php if (!empty($module['summary'])): ?>
                        <p style="margin:0 0 10px; font-size:0.85rem; color:var(--ln-muted);">
                            <?= learn_e($module['summary']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($module['start_date'])): ?>
                        <p style="margin:0 0 10px; font-size:0.8rem; color:var(--ln-muted);">
                            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle;">event</span>
                            Starts <?= learn_e(date('M j, Y', strtotime($module['start_date']))) ?>
                        </p>
                    <?php endif; ?>
                    <p style="margin:0; font-size:0.85rem; color:var(--ln-muted);">
                        <?= count($moduleLessons) ?> lesson<?= count($moduleLessons) !== 1 ? 's' : '' ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

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
<?php endif; ?>

<?php if ($courseUpcomingLessons || $coursePastLessons): ?>
    <?php
    $learnCourseLessonRow = static function (array $lesson, bool $isPast, bool $canOpen) use ($slug): void {
        $lessonUrl = '/learn/course.php?c=' . rawurlencode($slug) . '&lesson=' . (int)$lesson['id'];
        $start = (string)($lesson['start_date'] ?? '');
        $end = (string)($lesson['end_date'] ?? '');
        $today = date('Y-m-d');

        $dateLabel = '';
        $isActive = false;
        if ($start !== '' && $end !== '') {
            $isActive = (!$isPast && $start <= $today && $end >= $today);
            $dateLabel = ($start === $end)
                ? date('M j, Y', strtotime($start))
                : date('M j', strtotime($start)) . ' – ' . date('M j, Y', strtotime($end));
        } elseif ($start !== '') {
            $dateLabel = date('M j, Y', strtotime($start));
            $isActive = (!$isPast && $start <= $today);
        } elseif ($end !== '') {
            $dateLabel = 'Until ' . date('M j, Y', strtotime($end));
        }
        ?>
        <div style="display:flex; align-items:flex-start; gap:12px; padding:13px 22px; border-bottom:1px solid var(--ln-line);">
            <span class="material-symbols-rounded" style="font-size:21px; color:<?= $isActive ? 'var(--ln-amber)' : ($isPast ? 'var(--ln-muted)' : 'var(--ln-green-mid)') ?>;">
                <?= $isActive ? 'radio_button_checked' : ($isPast ? 'history' : 'event_upcoming') ?>
            </span>
            <div style="flex:1; min-width:0;">
                <?php if ($canOpen): ?>
                    <a href="<?= learn_e($lessonUrl) ?>" style="font-size:0.95rem; font-weight:650; color:var(--ln-ink); text-decoration:none;"><?= learn_e($lesson['title']) ?></a>
                <?php else: ?>
                    <span style="font-size:0.95rem; font-weight:650; color:var(--ln-ink);"><?= learn_e($lesson['title']) ?></span>
                <?php endif; ?>
                <p style="margin:3px 0 0; font-size:0.82rem; color:var(--ln-muted); display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                    <?php if (!empty($lesson['module_title'])): ?>
                        <span class="ln-chip"><span class="material-symbols-rounded" style="font-size:14px;">folder_special</span><?= learn_e($lesson['module_title']) ?></span>
                    <?php endif; ?>
                    <?php if ($dateLabel !== ''): ?>
                        <span class="material-symbols-rounded" style="font-size:15px;">calendar_today</span>
                        <span><?= learn_e($dateLabel) ?></span>
                    <?php endif; ?>
                    <?php if ((int)$lesson['duration_minutes'] > 0): ?>
                        <span class="material-symbols-rounded" style="font-size:15px;">schedule</span>
                        <span><?= (int)$lesson['duration_minutes'] ?> min</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php
    };
    ?>
    <h2 style="font-size:1.15rem; color:var(--ln-ink); margin:26px 0 14px;">Lesson schedule</h2>
    <div style="display:grid; gap:12px; grid-template-columns:repeat(2, 1fr);">
        <?php if ($courseUpcomingLessons): ?>
            <section class="ln-card" style="padding:0; overflow:hidden;">
                <div class="ln-card-head">
                    <span class="material-symbols-rounded">event_upcoming</span>
                    <div>
                        <h3 style="margin:0; font-size:1.02rem; color:var(--ln-ink);">Upcoming lessons</h3>
                        <p style="margin:2px 0 0; font-size:0.8rem; color:var(--ln-muted);">Scheduled and in progress on this course</p>
                    </div>
                </div>
                <?php foreach ($courseUpcomingLessons as $lesson): ?>
                    <?php $learnCourseLessonRow($lesson, false, $enrolled); ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <?php if ($coursePastLessons): ?>
            <section class="ln-card" style="padding:0; overflow:hidden;">
                <div class="ln-card-head">
                    <span class="material-symbols-rounded">history</span>
                    <div>
                        <h3 style="margin:0; font-size:1.02rem; color:var(--ln-ink);">Past lessons</h3>
                        <p style="margin:2px 0 0; font-size:0.8rem; color:var(--ln-muted);">Review what has already been covered</p>
                    </div>
                </div>
                <?php foreach ($coursePastLessons as $lesson): ?>
                    <?php $learnCourseLessonRow($lesson, true, $enrolled); ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>


<!-- Lesson info (notes/assessments) modal -->
<style>
.ln-modal-overlay{position:fixed;inset:0;background:rgba(10,14,22,.66);display:none;align-items:center;justify-content:center;z-index:3000;padding:20px;}
.ln-modal-overlay.open{display:flex;}
.ln-modal{background:#fff;border:1px solid var(--ln-line);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);width:560px;max-width:100%;max-height:88vh;overflow:auto;padding:22px 24px;}
.ln-modal-head{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.ln-modal-head .material-symbols-rounded{color:var(--ln-green-mid);font-size:26px;}
.ln-modal-head h3{margin:0;font-size:1.1rem;color:var(--ln-ink);}
.ln-modal-body{color:var(--ln-ink);}
.ln-modal-sec{margin-bottom:16px;}
.ln-modal-sec h4{font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ln-muted);margin:0 0 8px;}
.ln-modal-note{border:1px solid var(--ln-line);border-radius:10px;padding:10px 12px;margin-bottom:8px;font-size:.88rem;background:#fafbfc;}
.ln-modal-subs{margin:8px 0 0;padding:0 0 0 18px;font-size:.84rem;color:var(--ln-muted);}
.ln-modal-subs li{margin:3px 0;}
.ln-modal-asmt{display:flex;align-items:center;gap:8px;border:1px solid var(--ln-line);border-radius:10px;padding:10px 12px;margin-bottom:8px;font-size:.88rem;}
.ln-modal-asmt .material-symbols-rounded{color:var(--ln-amber);font-size:20px;}
.ln-modal-empty{color:var(--ln-muted);font-size:.9rem;text-align:center;padding:24px;}
.ln-modal-actions{display:flex;justify-content:flex-end;margin-top:18px;}
</style>
<div id="ln-modal" class="ln-modal-overlay">
  <div class="ln-modal" role="dialog" aria-modal="true">
    <div class="ln-modal-head"><span class="material-symbols-rounded">menu_book</span><h3 id="ln-modal-title"></h3></div>
    <div id="ln-modal-body" class="ln-modal-body"></div>
    <div class="ln-modal-actions"><button type="button" class="ln-btn ln-btn-primary" onclick="closeLessonModal()">Close</button></div>
  </div>
</div>
<script>
const LESSON_INFO = <?= json_encode($lessonInfoMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function escTxt(s){return String(s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\"/g,"&quot;");}
function openLessonModal(id){
  const info = LESSON_INFO[id] || {title:"Lesson",notes:[],assessments:[]};
  const notes = Array.isArray(info.notes)?info.notes:[];
  const asmts = Array.isArray(info.assessments)?info.assessments:[];
  let html = "";
  if(notes.length===0 && asmts.length===0){
    html = '<div class="ln-modal-empty">No notes/assessments under this topic.</div>';
  } else {
    if(notes.length){ html += '<div class="ln-modal-sec"><h4>Notes</h4>';
      notes.forEach(function(n){
        html += '<div class="ln-modal-note"><strong>'+escTxt(n.title)+'</strong>';
        if(n.subs && n.subs.length){ html += '<ul class="ln-modal-subs">'+n.subs.map(function(s){return '<li>'+escTxt(s.title)+'</li>';}).join("")+'</ul>'; }
        html += '</div>';
      }); html += '</div>'; }
    if(asmts.length){ html += '<div class="ln-modal-sec"><h4>Assessments</h4>';
      asmts.forEach(function(a){
        const label = (a.type==="cat"?"CAT":(a.type==="quiz"?"Quiz":"Assessment"));
        html += '<div class="ln-modal-asmt"><span class="material-symbols-rounded">quiz</span>'+escTxt(a.title)+' <span class="ln-chip">'+label+'</span></div>';
      }); html += '</div>'; }
  }
  document.getElementById("ln-modal-title").textContent = escTxt(info.title) || "Lesson";
  document.getElementById("ln-modal-body").innerHTML = html;
  const m = document.getElementById("ln-modal");
  m.classList.add("open"); m.style.display = "flex";
  document.getElementById("ln-modal-body").scrollTop = 0;
}
function closeLessonModal(){
  const m = document.getElementById("ln-modal");
  m.classList.remove("open"); m.style.display = "none";
}
</script>

<?php
learn_foot();
