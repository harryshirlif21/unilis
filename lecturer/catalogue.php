<?php
/**
 * Open Courses Studio — the course list.
 *
 * Until this page existed, a course in the public catalogue could only be
 * created with an INSERT, which meant nobody without database access could add
 * one. This is the way in: create a draft here, fill it in on the builder, then
 * publish.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../learn/config.php';
require_once __DIR__ . '/../learn/includes/authoring.php';
require_once __DIR__ . '/catalogue_layout.php';

$actor = catalogue_require_author();
studio_require_schema($conn);

// ── Create ────────────────────────────────────────────────────────────────
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

$courses = catalogue_courses_for($conn, $actor);
$published = count(array_filter($courses, static fn($c) => (int)$c['is_published'] === 1));

studio_head('My open courses');
?>
<div class="st-page-head">
    <div>
        <h1>Open courses</h1>
        <p class="st-sub">
            Self-paced courses for learners outside the university.
            <?= count($courses) ?> course<?= count($courses) === 1 ? '' : 's' ?>,
            <?= $published ?> published.
            <?php if ($actor['role'] === 'admin'): ?>
                You are viewing every course, because you are an administrator.
            <?php endif; ?>
        </p>
    </div>
    <div class="st-actions">
        <a class="st-btn" href="#new-course"><i class="fas fa-plus"></i> New course</a>
    </div>
</div>

<?php if (!$courses): ?>
    <div class="st-card">
        <div class="st-empty">
            <p style="font-size:2rem; margin:0 0 6px;"><i class="fas fa-globe" style="color:#c7d2fe;"></i></p>
            <h2 style="margin:0 0 4px; color:var(--st-ink);">No open courses yet</h2>
            <p>Create one below. It stays a draft — invisible in the catalogue — until you publish it.</p>
        </div>
    </div>
<?php else: ?>
    <div class="st-courses">
        <?php foreach ($courses as $course):
            $courseId = (int)$course['id'];
            $isPublished = (int)$course['is_published'] === 1;
            ?>
            <article class="st-course">
                <div class="st-course-cover">
                    <?php if (!empty($course['cover_image'])): ?>
                        <img src="<?= studio_e($course['cover_image']) ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-book-open" style="font-size:1.4rem;"></i>
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
                        <span class="st-chip"><?= studio_e(ucfirst((string)$course['level'])) ?></span>
                        <span><?= (int)$course['module_count'] ?> modules</span>
                        <span>· <?= (int)$course['lesson_count'] ?> lessons</span>
                        <span>· <?= (int)$course['assessment_count'] ?> assessments</span>
                        <span>· <?= (int)$course['learner_count'] ?> enrolled</span>
                        <?php if ((int)$course['certificate_count'] > 0): ?>
                            <span class="st-chip st-chip-info">
                                <i class="fas fa-award"></i> <?= (int)$course['certificate_count'] ?> certified
                            </span>
                        <?php endif; ?>
                        <?php if ($actor['role'] === 'admin'): ?>
                            <span>· by <?= studio_e($course['author_name'] ?? 'no owner') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="st-actions" style="margin-top:12px;">
                        <a class="st-btn st-btn-small" href="catalogue_builder.php?course_id=<?= $courseId ?>">
                            <i class="fas fa-pen"></i> Edit
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

<div class="st-card" id="new-course">
    <h2>New course</h2>
    <p class="st-sub">
        Everything here can be changed later. The public URL is generated from the title
        and stops following it once the course is published, so bookmarks keep working.
    </p>

    <form method="post" style="margin-top:16px;">
        <input type="hidden" name="csrf_token" value="<?= studio_e(catalogue_csrf_token()) ?>">

        <div class="st-field">
            <label for="title">Title</label>
            <input id="title" name="title" type="text" required maxlength="200"
                   placeholder="e.g. Introduction to Cyber Hygiene">
        </div>

        <div class="st-field">
            <label for="summary">Summary</label>
            <input id="summary" name="summary" type="text" maxlength="400"
                   placeholder="One line for the catalogue card">
        </div>

        <div class="st-field">
            <label for="description">Description</label>
            <textarea id="description" name="description"
                      placeholder="What the course covers and who it is for."></textarea>
        </div>

        <div class="st-row">
            <div class="st-field">
                <label for="level">Level</label>
                <select id="level" name="level">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                </select>
            </div>
            <div class="st-field">
                <label for="estimated_hours">Estimated hours</label>
                <input id="estimated_hours" name="estimated_hours" type="number" step="0.5" min="0.5"
                       placeholder="e.g. 6">
            </div>
            <div class="st-field">
                <label for="pass_mark">Pass mark (%)</label>
                <input id="pass_mark" name="pass_mark" type="number" min="1" max="100" value="70" required>
                <span class="st-hint">Each assessment must reach this to count as passed.</span>
            </div>
        </div>

        <label class="st-check">
            <input type="checkbox" name="certificate_enabled" value="1" checked>
            <div>
                Award a certificate on completion
                <span>Issued once every lesson is complete and every assessment passed.</span>
            </div>
        </label>

        <button class="st-btn" type="submit"><i class="fas fa-plus"></i> Create draft</button>
    </form>
</div>
<?php
studio_foot();
