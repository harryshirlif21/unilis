<?php
/**
 * A learner's own courses, progress and certificates.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/layout.php';

learn_require_schema($conn);

$learner = learn_require_login($conn);
$courses = learn_my_courses($conn, $learner['id']);

$certificates = array_values(array_filter($courses, static fn($c) => $c['certificate'] !== null));

// Scheduled lessons across the learner's enrolled courses, split into
// what is ahead and what has passed, for the calendar-style sections below.
$schedule = learn_lesson_schedule($conn, $learner['id']);
$upcomingLessons = $schedule['upcoming'];
$pastLessons = $schedule['past'];

/**
 * Render a single scheduled lesson row for the dashboard. Shared by the
 * upcoming and past lists so both read the same way.
 */
$learnRenderLessonRow = static function (array $lesson, bool $isPast): void {
    $lessonUrl = '/learn/course.php?c=' . rawurlencode($lesson['course_slug'])
               . '&lesson=' . (int)$lesson['id'];
    $start = (string)($lesson['start_date'] ?? '');
    $end = (string)($lesson['end_date'] ?? '');
    $today = date('Y-m-d');

    // Work out the human-facing date string.
    $dateLabel = '';
    $isActive = false;
    if ($start !== '' && $end !== '') {
        $from = date('M j', strtotime($start));
        $to = date('M j, Y', strtotime($end));
        $dateLabel = ($start === $end) ? date('M j, Y', strtotime($start)) : $from . ' – ' . $to;
        $isActive = (!$isPast && $start <= $today && $end >= $today);
    } elseif ($start !== '') {
        $dateLabel = date('M j, Y', strtotime($start));
        $isActive = (!$isPast && $start <= $today);
    } elseif ($end !== '') {
        $dateLabel = 'Until ' . date('M j, Y', strtotime($end));
    }
    ?>
    <li class="ln-schedule-item" style="display:flex; align-items:flex-start; gap:12px; padding:13px 16px;">
        <span class="ln-schedule-date">
            <?php if ($isActive): ?>
                <span class="material-symbols-rounded" style="font-size:21px; color:var(--ln-amber);">radio_button_checked</span>
            <?php else: ?>
                <span class="material-symbols-rounded" style="font-size:21px; color:<?= $isPast ? 'var(--ln-muted)' : 'var(--ln-green-mid)' ?>;"><?= $isPast ? 'history' : 'event_upcoming' ?></span>
            <?php endif; ?>
        </span>
        <div style="flex:1; min-width:0;">
            <a class="ln-schedule-title" href="<?= learn_e($lessonUrl) ?>"
               style="font-size:0.97rem; font-weight:650; color:var(--ln-ink); text-decoration:none; display:block;">
                <?= learn_e($lesson['title']) ?>
            </a>
            <p style="margin:0; font-size:0.82rem; color:var(--ln-muted); display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <span><?= learn_e($lesson['course_title']) ?></span>
                <?php if (!empty($lesson['module_title'])): ?>
                    <span class="ln-chip">
                        <span class="material-symbols-rounded" style="font-size:14px;">folder_special</span>
                        <?= learn_e($lesson['module_title']) ?>
                    </span>
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
        <a class="ln-btn ln-btn-ghost" href="<?= learn_e($lessonUrl) ?>" style="padding:7px 14px; flex-shrink:0;">
            <span class="material-symbols-rounded" style="font-size:17px;"><?= $isPast ? 'replay' : 'play_arrow' ?></span>
            <?= $isPast ? 'Review' : 'Open' ?>
        </a>
    </li>
    <?php
};

learn_head(['title' => 'My learning', 'learner' => $learner]);
?>
<section class="ln-hero">
    <h1>Welcome back, <?= learn_e(explode(' ', $learner['name'])[0]) ?></h1>
    <p>
        <?= count($courses) ?> course<?= count($courses) === 1 ? '' : 's' ?> on your list<?php
        if ($certificates) {
            echo ' · ' . count($certificates) . ' certificate' . (count($certificates) === 1 ? '' : 's') . ' earned';
        } ?>.
    </p>
</section>

<?php if (!$courses): ?>
    <div class="ln-empty">
        <span class="material-symbols-rounded">explore</span>
        <h2>You have not started a course yet</h2>
        <p>Pick something from the catalogue and it will show up here.</p>
        <p style="margin-top:16px;"><a class="ln-btn ln-btn-primary" href="/learn/">Browse courses</a></p>
    </div>
<?php else: ?>
    <div class="ln-grid">
        <?php foreach ($courses as $course):
            $p = $course['progress'];
            ?>
            <div class="ln-course" style="cursor:default;">
                <div class="ln-course-cover">
                    <?php if (!empty($course['cover_image'])): ?>
                        <img src="<?= learn_e($course['cover_image']) ?>" alt="">
                    <?php else: ?>
                        <span class="material-symbols-rounded">school</span>
                    <?php endif; ?>
                </div>
                <div class="ln-course-body">
                    <h3><?= learn_e($course['title']) ?></h3>

                    <div class="ln-bar" role="progressbar" aria-valuenow="<?= (int)$p['percent'] ?>"
                         aria-valuemin="0" aria-valuemax="100">
                        <div class="ln-bar-fill" style="width:<?= (int)$p['percent'] ?>%"></div>
                    </div>
                    <p style="flex:0; margin:0 0 12px; font-size:0.82rem;">
                        <?= (int)$p['done_lessons'] ?>/<?= (int)$p['total_lessons'] ?> lessons
                        <?php if ((int)$p['total_assessments'] > 0): ?>
                            · <?= (int)$p['passed_assessments'] ?>/<?= (int)$p['total_assessments'] ?> assessments passed
                        <?php endif; ?>
                        · <?= (int)$p['percent'] ?>%
                    </p>

                    <div class="ln-meta" style="margin-bottom:12px;">
                        <?php if ($course['certificate'] !== null): ?>
                            <span class="ln-chip ln-chip-done">
                                <span class="material-symbols-rounded">verified</span> Completed
                            </span>
                        <?php elseif ($p['complete']): ?>
                            <span class="ln-chip ln-chip-amber">
                                <span class="material-symbols-rounded">hourglass_top</span> Ready to certify
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a class="ln-btn ln-btn-primary" href="/learn/course.php?c=<?= learn_e($course['slug']) ?>">
                            <span class="material-symbols-rounded">play_arrow</span>
                            <?= (int)$p['percent'] > 0 ? 'Continue' : 'Start' ?>
                        </a>
                        <?php if ($course['certificate'] !== null): ?>
                            <a class="ln-btn ln-btn-amber"
                               href="/learn/certificate.php?code=<?= learn_e($course['certificate']['verification_code']) ?>">
                                <span class="material-symbols-rounded">workspace_premium</span> Certificate
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($upcomingLessons || $pastLessons): ?>
    <section class="ln-schedule-grid" style="margin-top:34px; display:grid; gap:12px; grid-template-columns:repeat(2, 1fr);">
        <?php if ($upcomingLessons): ?>
        <section class="ln-card" style="padding:0; overflow:hidden;">
            <div class="ln-card-head">
                <span class="material-symbols-rounded">event_upcoming</span>
                <div>
                    <h3 style="margin:0; font-size:1.05rem; color:var(--ln-ink);">Upcoming lessons</h3>
                    <p style="margin:2px 0 0; font-size:0.8rem; color:var(--ln-muted);">Scheduled and in progress on your courses</p>
                </div>
            </div>
            <ul class="ln-schedule-list">
                <?php foreach ($upcomingLessons as $lesson): ?>
                    <?php $learnRenderLessonRow($lesson, false); ?>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($pastLessons): ?>
        <section class="ln-card" style="padding:0; overflow:hidden;">
            <div class="ln-card-head">
                <span class="material-symbols-rounded">history</span>
                <div>
                    <h3 style="margin:0; font-size:1.05rem; color:var(--ln-ink);">Past lessons</h3>
                    <p style="margin:2px 0 0; font-size:0.8rem; color:var(--ln-muted);">Review what you have covered</p>
                </div>
            </div>
            <ul class="ln-schedule-list">
                <?php foreach ($pastLessons as $lesson): ?>
                    <?php $learnRenderLessonRow($lesson, true); ?>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php
learn_foot();
