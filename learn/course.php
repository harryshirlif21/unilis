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
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Course not found · UNILIS Learning</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
        <link rel="stylesheet" href="<?= learn_asset('assets/learn.css') ?>">
    </head>
    <body>
    <main class="ln-main">
    <div class="ln-empty"><span class="material-symbols-rounded">search_off</span>
        <h2>Course not found</h2><p>It may have been unpublished.</p>
        <p style="margin-top:16px;"><a class="ln-btn ln-btn-primary" href="/learn/">Browse courses</a></p>
    </div>
    </main>
    <footer class="ln-footer">
        <p>UNILIS Learning · open courses from JHUB Africa</p>
        <p><a href="/">Back to UNILIS</a> · <a href="/login.php">Student &amp; staff sign in</a></p>
    </footer>
    </body>
    </html>
    <?php
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
$courseOngoingLessons = $courseSchedule['ongoing'] ?? [];
$courseUpcomingLessons = $courseSchedule['future'] ?? $courseSchedule['upcoming'];
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

        if ($action === 'complete_lesson') {
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
$viewParamLesson = isset($_GET['view']) ? ((strpos((string)$_GET['view'], 'lesson-') === 0) ? (int)substr((string)$_GET['view'], 7) : 0) : 0;
$openLessonId = $viewParamLesson ?: (int)($_GET['lesson'] ?? 0);
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

// Build lesson navigation sequence for prev/next
$lessonSequence = [];
foreach ($modules as $moduleId => $moduleData) {
    foreach ($moduleData['lessons'] as $lesson) {
        $lessonSequence[] = ['type' => 'lesson', 'id' => (int)$lesson['id'], 'title' => (string)$lesson['title'], 'module_id' => (int)$moduleId];
    }
}

$currentIndex = -1;
if ($openLessonId > 0) {
    foreach ($lessonSequence as $i => $item) {
        if ($item['id'] === $openLessonId) {
            $currentIndex = $i;
            break;
        }
    }
}

$prevLesson = ($currentIndex > 0) ? $lessonSequence[$currentIndex - 1] : null;
$nextLesson = ($currentIndex >= 0 && $currentIndex < count($lessonSequence) - 1) ? $lessonSequence[$currentIndex + 1] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= learn_e($course['title']) ?> · UNILIS Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="<?= learn_asset('assets/learn.css') ?>">
    <style>
        .course-schedule-float { position:fixed; right:24px; bottom:24px; z-index:1000; width:min(390px, calc(100vw - 32px)); max-height:min(70vh, 620px); overflow:hidden; background:#fff; border:1px solid #dfe4ee; border-radius:14px; box-shadow:0 18px 48px rgba(24,33,55,.22); color:#1a1d29; }
        .course-schedule-float__head { display:flex; align-items:center; gap:9px; padding:14px 16px; background:#162238; color:#fff; }
        .course-schedule-float__title { flex:1; font-size:.92rem; font-weight:700; }
        .course-schedule-float__close { border:0; background:transparent; color:#fff; cursor:pointer; padding:3px; line-height:1; border-radius:4px; }
        .course-schedule-float__close:hover { background:rgba(255,255,255,.16); }
        .course-schedule-float__body { max-height:calc(min(70vh, 620px) - 52px); overflow-y:auto; padding:12px; }
        .course-schedule-group + .course-schedule-group { margin-top:14px; }
        .course-schedule-group h3 { margin:0 0 7px; font-size:.73rem; text-transform:uppercase; letter-spacing:.07em; color:#677086; }
        .course-schedule-item { display:flex; align-items:flex-start; gap:9px; padding:9px; border-radius:8px; color:inherit; text-decoration:none; }
        .course-schedule-item:hover { background:#f3f6fb; }
        .course-schedule-dot { width:9px; height:9px; flex:0 0 9px; border-radius:50%; margin-top:5px; background:#4f8ef7; }
        .course-schedule-group--ongoing .course-schedule-dot { background:#1caa78; }
        .course-schedule-group--ended .course-schedule-dot { background:#9aa3b5; }
        .course-schedule-item strong { display:block; font-size:.84rem; line-height:1.3; }
        .course-schedule-item small { display:block; margin-top:2px; font-size:.76rem; color:#6b7280; }
        @media (max-width:600px) { .course-schedule-float { right:16px; bottom:16px; } }
    </style>
</head>
<body>
<main class="ln-main">

<?php if ($courseOngoingLessons || $courseUpcomingLessons || $coursePastLessons): ?>
    <aside class="course-schedule-float" id="course-schedule-float" aria-label="Lesson schedule">
        <div class="course-schedule-float__head">
            <span class="material-symbols-rounded" aria-hidden="true">calendar_month</span>
            <span class="course-schedule-float__title">Lesson schedule</span>
            <button class="course-schedule-float__close" type="button" aria-label="Close lesson schedule" onclick="document.getElementById('course-schedule-float').remove()">
                <span class="material-symbols-rounded" aria-hidden="true">close</span>
            </button>
        </div>
        <div class="course-schedule-float__body">
            <?php
            $scheduleGroups = [
                'ongoing' => ['title' => 'Ongoing now', 'items' => $courseOngoingLessons],
                'upcoming' => ['title' => 'Upcoming lessons', 'items' => $courseUpcomingLessons],
                'ended' => ['title' => 'Ended lessons', 'items' => $coursePastLessons],
            ];
            foreach ($scheduleGroups as $key => $group):
                if (!$group['items']) continue;
            ?>
                <section class="course-schedule-group course-schedule-group--<?= $key ?>">
                    <h3><?= learn_e($group['title']) ?></h3>
                    <?php foreach ($group['items'] as $scheduledLesson):
                        $start = (string)($scheduledLesson['start_date'] ?? '');
                        $end = (string)($scheduledLesson['end_date'] ?? '');
                        $dateLabel = $start !== '' ? date('M j, Y', strtotime($start)) : date('M j, Y', strtotime($end));
                        if ($end !== '' && $end !== $start) $dateLabel .= ' – ' . date('M j, Y', strtotime($end));
                    ?>
                        <a class="course-schedule-item" href="<?= learn_e('/learn/course.php?c=' . $slug . '&view=lesson-' . (int)$scheduledLesson['id']) ?>">
                            <span class="course-schedule-dot" aria-hidden="true"></span>
                            <span><strong><?= learn_e($scheduledLesson['title']) ?></strong><small><?= learn_e($scheduledLesson['module_title']) ?> · <?= learn_e($dateLabel) ?></small></span>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
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
    <section style="margin: 40px auto; max-width: 600px; text-align: center;">
        <h1 style="font-size: 1.5rem; margin-bottom: 12px;"><?= learn_e($course['title']) ?></h1>
        <p style="color: var(--ln-muted); margin-bottom: 24px;"><?= learn_e($course['summary'] ?? '') ?></p>
        <div style="display:flex; gap:10px; justify-content: center; flex-wrap:wrap;">
            <a class="ln-btn ln-btn-primary" href="/learn/register.php?course=<?= learn_e(urlencode($slug)) ?>">Create account</a>
            <a class="ln-btn ln-btn-ghost"
               href="/learn/login.php?next=<?= learn_e(urlencode('/learn/course.php?c=' . $slug)) ?>">Sign in</a>
        </div>
    </section>
<?php elseif (!$enrolled): ?>
    <section style="margin: 40px auto; max-width: 600px; text-align: center;">
        <h1 style="font-size: 1.5rem; margin-bottom: 12px;"><?= learn_e($course['title']) ?></h1>
        <p style="color: var(--ln-muted); margin-bottom: 24px;">
            Enrolment is created automatically when you confirm the email link sent after registering for this course.
        </p>
        <a class="ln-btn ln-btn-primary" href="/learn/dashboard.php" style="font-size: 1rem; padding: 12px 28px;">
            <span class="material-symbols-rounded">dashboard</span> Go to my courses
        </a>
    </section>
<?php else: ?>

<style>
/* teach.php design language on a light background */
:root {
    --lt-bg: #ffffff; --lt-page: #f5f6fa; --lt-surface2: #f0f2f8;
    --lt-surface3: #e8ebf4; --lt-border: #e2e5ee;
    --lt-accent: #4f8ef7; --lt-accent2: #1caa78; --lt-accent3: #f0883f;
    --lt-text: #1a1d29; --lt-muted: #6b7280; --lt-dim: #a3a9bd;
    --lt-radius: 10px; --lt-radius-sm: 6px;
}
.ln-layout-wrapper { display:flex; gap:24px; padding:0; background:var(--lt-page); border-radius:var(--lt-radius); }
.ln-sidebar-col { width:280px; min-width:280px; padding:16px 0 16px 16px; }
.ln-main-col { flex:1; min-width:0; padding:16px 16px 16px 0; }

/* Sidebar � same structure as teach.php .sidebar */
.ln-sidebar {
    border:1px solid var(--lt-border); border-radius:var(--lt-radius);
    background:#fff; overflow:hidden; position:sticky; top:20px;
    max-height:calc(100vh - 40px); display:flex; flex-direction:column;
    box-shadow:0 1px 3px rgba(20,24,40,.05);
}
.ln-sidebar-header {
    padding:16px; border-bottom:1px solid var(--lt-border);
    background:var(--lt-surface2); flex-shrink:0;
}
.ln-sidebar-header h3 {
    font-family:'Syne','Inter',sans-serif; font-weight:800; font-size:0.95rem;
    color:var(--lt-text); margin:0; display:flex; align-items:center; gap:8px;
}
.ln-sidebar-content { overflow-y:auto; flex:1; padding:14px; }

/* Module block � mirrors teach.php .module-block / .module-head */
.ln-module-block {
    margin:0 0 12px; border:1px solid var(--lt-border);
    border-radius:var(--lt-radius-sm); overflow:hidden;
    background:rgba(20,24,40,.012);
}
.ln-module-title {
    padding:10px 12px; font-family:'Syne','Inter',sans-serif;
    font-weight:700; font-size:0.82rem; color:var(--lt-muted);
    border-bottom:1px solid var(--lt-border);
    display:flex; align-items:center; gap:6px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.ln-module-title::before {
    font-family:'Material Symbols Rounded'; content:"folder_open";
    font-size:16px; color:var(--lt-accent); flex-shrink:0;
}

/* Lesson link � mirrors teach.php .lesson-toggle */
.ln-lesson-link {
    display:flex; align-items:center; gap:8px; width:100%;
    padding:8px 12px 8px 14px; color:var(--lt-text); text-decoration:none;
    font-size:0.86rem; border-left:3px solid transparent;
    background:transparent; border-top:none; border-right:none; border-bottom:none;
    font:inherit; text-align:left; cursor:pointer; transition:all .2s;
}
.ln-lesson-link:hover { background:var(--lt-surface2); }
.ln-lesson-link.active {
    background:rgba(79,142,247,.10); border-left-color:var(--lt-accent);
    color:var(--lt-accent); font-weight:700;
}

/* Page head � mirrors teach.php .page-head */
.ln-page-head { margin-bottom:18px; padding:0 4px; }
.ln-page-head h2 {
    font-family:'Syne','Inter',sans-serif; font-weight:800; font-size:1.15rem;
    color:var(--lt-text); margin:0 0 4px; display:flex; align-items:center; gap:8px;
}
.ln-page-head p { color:var(--lt-muted); font-size:0.85rem; margin:0; }

/* Content card � mirrors teach.php .content-card */
.ln-content-card {
    border:1px solid var(--lt-border); border-radius:var(--lt-radius);
    background:#fff; padding:28px; margin-bottom:20px;
    box-shadow:0 1px 3px rgba(20,24,40,.05);
}
.ln-content-card h1 {
    font-family:'Syne','Inter',sans-serif; font-weight:800; font-size:1.3rem;
    color:var(--lt-text); margin-top:0; margin-bottom:12px;
}
.ln-content-card .ln-sub { display:flex; align-items:center; gap:8px; font-size:0.9rem; color:var(--lt-accent); text-decoration:none; }

.ln-section-divider { margin:24px 0; padding-top:20px; border-top:1px solid var(--lt-border); }

.ln-topic-item { border:1px solid var(--lt-border); border-radius:var(--lt-radius-sm); background:#fff; padding:14px 16px; margin-bottom:12px; }
.ln-topic-header { display:flex; align-items:center; gap:12px; }
.ln-topic-content { flex:1; min-width:0; }
.ln-topic-title { font-weight:700; color:var(--lt-text); margin:0; }
.ln-topic-desc { font-size:0.85rem; color:var(--lt-muted); margin:6px 0 0; white-space:pre-wrap; }

.ln-assessment-card { display:flex; align-items:center; gap:12px; padding:16px; border:1px solid var(--lt-border); border-radius:var(--lt-radius-sm); margin-bottom:12px; }
.ln-assessment-icon { font-size:24px; flex-shrink:0; }
.ln-assessment-info { flex:1; min-width:0; }
.ln-assessment-title { font-weight:700; color:var(--lt-text); margin:0; }
.ln-assessment-type { font-size:0.8rem; color:var(--lt-muted); margin:4px 0 0; }

/* Nav row � mirrors teach.php .nav-row / .btn */
.ln-nav-buttons { display:flex; gap:10px; margin-top:24px; }
.ln-nav-button {
    flex:1; padding:10px 18px; border:1px solid var(--lt-border);
    border-radius:var(--lt-radius-sm); background:#fff; color:var(--lt-muted);
    text-decoration:none; text-align:center; font-size:0.85rem; font-weight:600;
    transition:all .2s; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;
}
.ln-nav-button:hover:not(:disabled) { background:var(--lt-surface2); color:var(--lt-text); }
.ln-nav-button.primary { background:var(--lt-accent); color:#fff; border-color:var(--lt-accent); }
.ln-nav-button.primary:hover:not(:disabled) { opacity:.92; }
.ln-nav-button:disabled { opacity:.4; cursor:not-allowed; }

.ln-progress-bar { background:var(--lt-surface3); height:8px; border-radius:4px; overflow:hidden; margin-bottom:8px; }
.ln-progress-fill { background:var(--lt-accent); height:100%; transition:width .3s; }
.ln-progress-text { font-size:0.8rem; color:var(--lt-muted); }
</style>

<div class="ln-page-head">
        <h2><span class="material-symbols-rounded" style="font-size:22px;color:var(--lt-accent)">school</span><?= learn_e($course['title']) ?></h2>
        <p>You are enrolled &mdash; working through <?= count($modules) ?> module<?= count($modules) === 1 ? '' : 's' ?>.</p>
    </div>
<div class="ln-layout-wrapper">
    <!-- Sidebar Navigation -->
    <div class="ln-sidebar-col">
        <div class="ln-sidebar">
            <div class="ln-sidebar-header">
                <h3>
                    <span class="material-symbols-rounded">library_books</span>
                    <?= learn_e($course['title']) ?>
                </h3>
            </div>
            <div class="ln-sidebar-content">
                <?php foreach ($modules as $moduleId => $moduleData): ?>
                    <div class="ln-module-block">
                        <div class="ln-module-title" title="<?= learn_e($moduleData['title']) ?>">
                            <?= learn_e($moduleData['title']) ?>
                        </div>
                        
                        <!-- Lessons in this module -->
                        <?php foreach ($moduleData['lessons'] as $lesson):
                            $lessonId = (int)$lesson['id'];
                            $isCurrentLesson = $lessonId === $openLessonId;
                            $lessonDone = in_array($lessonId, $doneLessons, true);
                            $lessonTopics = learn_lesson_topics($conn, $lessonId);
                        ?>
                            <!-- Lesson Link -->
                            <a href="<?= learn_e('/learn/course.php?c=' . $slug . '&view=lesson-' . $lessonId) ?>"
                               class="ln-lesson-link <?= $isCurrentLesson ? 'active' : '' ?>"
                               style="padding-left: 16px;">
                                <span class="material-symbols-rounded" style="font-size: 16px;">
                                    <?= $lessonDone ? 'check_circle' : 'circle' ?>
                                </span>
                                <span><?= learn_e($lesson['title']) ?></span>
                            </a>
                            
                            <!-- Topics & Subtopics (indented) -->
                            <?php if ($isCurrentLesson && $lessonTopics): ?>
                                <?php foreach ($lessonTopics as $topic):
                                    $tRead = in_array((int)$topic['id'], $readTopicIds, true);
                                ?>
                                    <div style="padding: 6px 16px 6px 48px; font-size: 0.82rem; color: var(--ln-muted); display: flex; align-items: center; gap: 6px;">
                                        <span class="material-symbols-rounded" style="font-size: 14px; flex-shrink: 0;">
                                            <?= $tRead ? 'check_circle' : 'radio_button_unchecked' ?>
                                        </span>
                                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= learn_e($topic['title']) ?>">
                                            <?= learn_e($topic['title']) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Subtopics -->
                                    <?php if ($topic['subs']): ?>
                                        <?php foreach ($topic['subs'] as $sub):
                                            $sRead = in_array((int)$sub['id'], $readTopicIds, true);
                                        ?>
                                            <div style="padding: 4px 16px 4px 68px; font-size: 0.75rem; color: var(--ln-muted); display: flex; align-items: center; gap: 6px;">
                                                <span class="material-symbols-rounded" style="font-size: 12px; flex-shrink: 0;">
                                                    <?= $sRead ? 'check_circle' : 'radio_button_unchecked' ?>
                                                </span>
                                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= learn_e($sub['title']) ?>">
                                                    <?= learn_e($sub['title']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <!-- Quiz for this lesson -->
                                <?php if ($openLessonQuiz): ?>
                                    <div style="padding: 6px 16px 6px 48px; font-size: 0.82rem; color: var(--ln-muted); display: flex; align-items: center; gap: 6px;">
                                        <span class="material-symbols-rounded" style="font-size: 14px; flex-shrink: 0; color: var(--ln-green-mid);">
                                            <?= $openLessonQuiz['passed'] ? 'check_circle' : 'radio_button_unchecked' ?>
                                        </span>
                                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Quiz</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- CAT for this module -->
                        <?php if ($openLessonCat && $openLesson): 
                            $openModuleId = (int)$openLesson['module_id'];
                            if ($openModuleId == $moduleId):
                        ?>
                            <div style="padding: 6px 16px 6px 48px; font-size: 0.82rem; color: var(--ln-muted); display: flex; align-items: center; gap: 6px;">
                                <span class="material-symbols-rounded" style="font-size: 14px; flex-shrink: 0; color: var(--ln-amber);">
                                    <?= $openLessonCat['passed'] ? 'check_circle' : 'radio_button_unchecked' ?>
                                </span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">CAT: <?= learn_e($openLessonCat['title']) ?></span>
                            </div>
                        <?php endif; endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($learner !== null): ?>
                <div style="padding: 16px; border-top: 1px solid var(--ln-line); background: var(--ln-bg);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--ln-line);">
                        <span class="material-symbols-rounded" style="font-size: 20px; color: var(--ln-muted);">account_circle</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--ln-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= learn_e($learner['name']) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--ln-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= learn_e($learner['email']) ?>
                            </div>
                        </div>
                    </div>
                    <a href="/learn/logout.php" class="ln-btn ln-btn-ghost" style="width: 100%; text-align: center;">
                        <span class="material-symbols-rounded">logout</span>
                        Sign out
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ln-main-col">
        <div class="ln-content-card">
            <h1><?= learn_e($openLesson['title']) ?></h1>
            
            <!-- Progress -->
            <div style="margin: 16px 0 20px;">
                <div class="ln-progress-bar">
                    <div class="ln-progress-fill" style="width: <?= (int)$progress['percent'] ?>%"></div>
                </div>
                <p class="ln-progress-text">
                    <?= (int)$progress['done_lessons'] ?>/<?= (int)$progress['total_lessons'] ?> lessons
                    <?php if ((int)$progress['total_assessments'] > 0): ?>
                        · <?= (int)$progress['passed_assessments'] ?>/<?= (int)$progress['total_assessments'] ?> passed
                    <?php endif; ?>
                    · <?= (int)$progress['percent'] ?>% complete
                </p>
            </div>

            <!-- Video & PDF Links -->
            <?php if (!empty($openLesson['video_url']) || !empty($openLesson['attachment_path'])): ?>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
                    <?php if (!empty($openLesson['video_url'])): ?>
                        <a href="<?= learn_e($openLesson['video_url']) ?>" target="_blank" rel="noopener" class="ln-sub">
                            <span class="material-symbols-rounded">play_circle</span>
                            Watch video
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($openLesson['attachment_path'])): ?>
                        <a href="<?= learn_e($openLesson['attachment_path']) ?>" target="_blank" rel="noopener" class="ln-sub">
                            <span class="material-symbols-rounded">description</span>
                            Download PDF
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Lesson Content -->
            <div class="ln-lesson-body"><?= render_lesson_content($openLesson['content_html'] ?? '') ?></div>

            <!-- Reading Topics -->
            <?php if ($openLessonTopics): ?>
                <div class="ln-section-divider">
                    <h2 style="font-size: 1.05rem; color: var(--ln-ink); margin: 0 0 16px;">Reading Topics</h2>
                    <p class="ln-progress-text" style="margin-bottom: 16px;">
                        <?php 
                        $readCount = count(array_intersect($readTopicIds, array_column(array_merge($openLessonTopics, array_merge(...array_map(fn($t) => $t['subs'], $openLessonTopics))), 'id')));
                        ?>
                        <?= $readCount ?> of <?= count(array_merge($openLessonTopics, array_merge(...array_map(fn($t) => $t['subs'], $openLessonTopics)))) ?> marked as read
                    </p>
                    
                    <?php foreach ($openLessonTopics as $topic):
                        $tRead = in_array((int)$topic['id'], $readTopicIds, true);
                    ?>
                        <div class="ln-topic-item">
                            <div class="ln-topic-header">
                                <?php if ($learner !== null): ?>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="mark_topic_read">
                                        <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                                        <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer; display: flex; align-items: center;">
                                            <span class="material-symbols-rounded" style="font-size: 24px; color: <?= $tRead ? 'var(--ln-green-mid)' : 'var(--ln-muted)' ?>;">
                                                <?= $tRead ? 'check_circle' : 'radio_button_unchecked' ?>
                                            </span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <div class="ln-topic-content">
                                    <div class="ln-topic-title"><?= learn_e($topic['title']) ?></div>
                                    <?php if (!empty($topic['content_html'])): ?>
                                        <p class="ln-topic-desc"><?= learn_e($topic['content_html']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($topic['subs']): ?>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--ln-line); margin-left: 32px;">
                                    <?php foreach ($topic['subs'] as $sub):
                                        $sRead = in_array((int)$sub['id'], $readTopicIds, true);
                                    ?>
                                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                                            <form method="post" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
                                                <input type="hidden" name="action" value="mark_topic_read">
                                                <input type="hidden" name="topic_id" value="<?= (int)$sub['id'] ?>">
                                                <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer; display: flex; align-items: center;">
                                                    <span class="material-symbols-rounded" style="font-size: 18px; color: <?= $sRead ? 'var(--ln-green-mid)' : 'var(--ln-muted)' ?>;">
                                                        <?= $sRead ? 'check_circle' : 'radio_button_unchecked' ?>
                                                    </span>
                                                </button>
                                            </form>
                                            <div>
                                                <div style="font-size: 0.9rem; color: var(--ln-ink);"><?= learn_e($sub['title']) ?></div>
                                                <?php if (!empty($sub['content_html'])): ?>
                                                    <p style="margin: 4px 0 0; font-size: 0.82rem; color: var(--ln-muted); white-space: pre-wrap;"><?= learn_e($sub['content_html']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Quiz -->
            <?php if ($openLessonQuiz): ?>
                <div class="ln-section-divider">
                    <div class="ln-assessment-card" style="background: rgba(79, 142, 247, 0.05);">
                        <span class="ln-assessment-icon" style="color: var(--ln-green-mid);">
                            <span class="material-symbols-rounded">quiz</span>
                        </span>
                        <div class="ln-assessment-info">
                            <div class="ln-assessment-title"><?= learn_e($openLessonQuiz['title']) ?></div>
                            <div class="ln-assessment-type">Quiz for this topic</div>
                        </div>
                        <?php if ($openLessonQuiz['passed']): ?>
                            <span class="ln-chip ln-chip-done" style="flex-shrink: 0;">
                                <span class="material-symbols-rounded">check_circle</span> Passed
                            </span>
                        <?php else: ?>
                            <a class="ln-btn ln-btn-primary" href="/learn/assessment.php?a=<?= (int)$openLessonQuiz['id'] ?>" style="flex-shrink: 0;">
                                <span class="material-symbols-rounded">quiz</span> Take
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Completion Badge -->
            <?php if (in_array((int)$openLesson['id'], $doneLessons, true)): ?>
                <p style="margin-top: 20px;">
                    <span class="ln-chip ln-chip-done">
                        <span class="material-symbols-rounded">check_circle</span> Lesson Completed
                    </span>
                </p>
            <?php endif; ?>

            <!-- CAT -->
            <?php if ($openLessonCat): ?>
                <div class="ln-section-divider">
                    <div class="ln-assessment-card" style="background: rgba(247, 147, 79, 0.05);">
                        <span class="ln-assessment-icon" style="color: var(--ln-amber);">
                            <span class="material-symbols-rounded">flag</span>
                        </span>
                        <div class="ln-assessment-info">
                            <div class="ln-assessment-title"><?= learn_e($openLessonCat['title']) ?></div>
                            <div class="ln-assessment-type">Module assessment</div>
                        </div>
                        <?php if ($openLessonCat['passed']): ?>
                            <span class="ln-chip ln-chip-done" style="flex-shrink: 0;">
                                <span class="material-symbols-rounded">check_circle</span> Passed
                            </span>
                        <?php else: ?>
                            <a class="ln-btn ln-btn-primary" href="/learn/assessment.php?a=<?= (int)$openLessonCat['id'] ?>" style="flex-shrink: 0;">
                                <span class="material-symbols-rounded">fact_check</span> Take
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="ln-nav-buttons">
                <?php if ($prevLesson): ?>
                    <a href="<?= learn_e('/learn/course.php?c=' . $slug . '&view=lesson-' . $prevLesson['id']) ?>" class="ln-nav-button">
                        <span class="material-symbols-rounded">chevron_left</span>
                        Previous
                    </a>
                <?php else: ?>
                    <button class="ln-nav-button" disabled>
                        <span class="material-symbols-rounded">chevron_left</span>
                        Previous
                    </button>
                <?php endif; ?>
                
                <?php if ($nextLesson): ?>
                    <a href="<?= learn_e('/learn/course.php?c=' . $slug . '&view=lesson-' . $nextLesson['id']) ?>" class="ln-nav-button primary">
                        Next
                        <span class="material-symbols-rounded">chevron_right</span>
                    </a>
                <?php else: ?>
                    <button class="ln-nav-button primary" disabled>
                        Next
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
</main>
<footer class="ln-footer">
    <p>UNILIS Learning · open courses from JHUB Africa</p>
    <p><a href="/">Back to UNILIS</a> · <a href="/login.php">Student &amp; staff sign in</a></p>
</footer>
</body>
</html>
