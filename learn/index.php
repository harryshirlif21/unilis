<?php
/**
 * The public course catalogue.
 *
 * Readable without an account - people should be able to see what is on offer
 * before deciding to register. Enrolling is what needs a sign-in.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/layout.php';

$ready = learn_schema_ready($conn);
$learner = $ready ? learn_current($conn) : null;
$search = trim((string)($_GET['q'] ?? ''));
$courses = $ready ? learn_catalogue($conn, $search) : [];

learn_head(['title' => 'Open courses', 'learner' => $learner]);
?>
<section class="ln-hero">
    <h1>Learn with UNILIS</h1>
    <p>
        Self-paced courses open to everyone — no student number required. Work through
        the material at your own pace, pass the assessments, and earn a verifiable
        certificate.
    </p>
</section>

<?php if (!$ready): ?>
    <div class="ln-empty">
        <span class="material-symbols-rounded">database</span>
        <h2>Not set up yet</h2>
        <p>An administrator needs to run <code>migrate_external_learners.php</code> once.</p>
    </div>
<?php else: ?>

    <form method="get" style="margin:0 0 24px; display:flex; gap:10px; max-width:460px;">
        <input name="q" type="search" placeholder="Search courses" value="<?= learn_e($search) ?>"
               style="flex:1; padding:11px 14px; border:1px solid var(--ln-line); border-radius:10px; font:inherit;">
        <button class="ln-btn ln-btn-ghost" type="submit">
            <span class="material-symbols-rounded">search</span>
        </button>
    </form>

    <?php if (!$courses): ?>
        <div class="ln-empty">
            <span class="material-symbols-rounded">menu_book</span>
            <h2><?= $search !== '' ? 'Nothing matches that search' : 'No courses published yet' ?></h2>
            <p>
                <?= $search !== ''
                    ? 'Try a different word, or browse the full catalogue.'
                    : 'Courses appear here as soon as a lecturer publishes one.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="ln-grid">
            <?php foreach ($courses as $course): ?>
                <a class="ln-course" href="/learn/course.php?c=<?= learn_e($course['slug']) ?>">
                    <div class="ln-course-cover">
                        <?php if (!empty($course['cover_image']) && $course['cover_image'] !== '0'): ?>
                            <?php
                            // Normalize the image path: stored as relative "uploads/..." but
                            // the learn pages live under /learn/, so prefix with / to resolve
                            // from the site root.
                            $coverPath = $course['cover_image'];
                            if (strpos($coverPath, 'http') !== 0 && strpos($coverPath, '/') !== 0) {
                                $coverPath = '/' . $coverPath;
                            }
                            ?>
                            <img src="<?= learn_e($coverPath) ?>" alt="">
                        <?php else: ?>
                            <span class="material-symbols-rounded">school</span>
                        <?php endif; ?>
                    </div>
                    <div class="ln-course-body">
                        <h3><?= learn_e($course['title']) ?></h3>
                        <p><?= learn_e($course['summary'] ?? '') ?></p>
                        <div class="ln-meta">
                            <span class="ln-chip">
                                <span class="material-symbols-rounded">signal_cellular_alt</span>
                                <?= learn_e(ucfirst((string)$course['level'])) ?>
                            </span>
                            <span class="ln-chip">
                                <span class="material-symbols-rounded">play_lesson</span>
                                <?= (int)$course['lesson_count'] ?> lesson<?= (int)$course['lesson_count'] === 1 ? '' : 's' ?>
                            </span>
                            <?php if ((int)$course['certificate_enabled'] === 1): ?>
                                <span class="ln-chip ln-chip-amber">
                                    <span class="material-symbols-rounded">workspace_premium</span>
                                    Certificate
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
learn_foot();
