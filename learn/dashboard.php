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

$learner = learn_require_login($conn);
$courses = learn_my_courses($conn, $learner['id']);

$certificates = array_values(array_filter($courses, static fn($c) => $c['certificate'] !== null));

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
<?php
learn_foot();
