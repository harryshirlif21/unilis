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
                <div class="ln-course-shell">
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
                <?php if ((int)($course['is_sponsored'] ?? 0) === 1 && !empty($course['sponsor_name'])): ?>
                    <button class="ln-sponsored-btn" type="button"
                            data-sponsor-name="<?= learn_e($course['sponsor_name']) ?>"
                            data-sponsor-details="<?= learn_e($course['sponsor_details'] ?? '') ?>"
                            data-sponsor-logo="<?= learn_e($course['sponsor_logo'] ?? '') ?>"
                            aria-haspopup="dialog">
                        <span class="material-symbols-rounded">volunteer_activism</span> Sponsored
                    </button>
                <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ln-sponsor-modal" id="sponsorModal" role="dialog" aria-modal="true" aria-labelledby="sponsorModalTitle" hidden>
            <div class="ln-sponsor-modal-panel">
                <button class="ln-sponsor-modal-close" type="button" aria-label="Close sponsor details">
                    <span class="material-symbols-rounded">close</span>
                </button>
                <img class="ln-sponsor-modal-logo" id="sponsorModalLogo" alt="" hidden>
                <p class="ln-sponsor-modal-label">Course sponsor</p>
                <h2 id="sponsorModalTitle"></h2>
                <p id="sponsorModalDetails"></p>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<script>
(() => {
    const modal = document.getElementById('sponsorModal');
    if (!modal) return;

    const title = document.getElementById('sponsorModalTitle');
    const details = document.getElementById('sponsorModalDetails');
    const logo = document.getElementById('sponsorModalLogo');
    const close = modal.querySelector('.ln-sponsor-modal-close');
    let opener = null;

    document.querySelectorAll('.ln-sponsored-btn').forEach(button => {
        button.addEventListener('click', () => {
            opener = button;
            title.textContent = button.dataset.sponsorName || 'Sponsor';
            details.textContent = button.dataset.sponsorDetails || 'This course is supported by this sponsor.';
            if (button.dataset.sponsorLogo) {
                logo.src = button.dataset.sponsorLogo;
                logo.hidden = false;
            } else {
                logo.removeAttribute('src');
                logo.hidden = true;
            }
            modal.hidden = false;
            close.focus();
        });
    });

    const closeModal = () => {
        modal.hidden = true;
        if (opener) opener.focus();
    };
    close.addEventListener('click', closeModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
</script>
<?php
learn_foot();
