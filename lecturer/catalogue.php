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
               sct.id AS tutor_id
        FROM short_course_tutors sct
        JOIN public_courses pc ON pc.id = sct.short_course_id
        WHERE sct.lecturer_id = ? AND sct.is_active = 1
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
                 WHERE sct.short_course_id = pc.id AND sct.lecturer_id = ? AND sct.is_active = 1 LIMIT 1) AS tutor_id
        FROM public_course_modules m
        JOIN public_courses pc ON pc.id = m.course_id
        JOIN tutor_module_permissions tmp ON tmp.module_id = m.id
        WHERE tmp.tutor_id = ? AND tmp.can_edit = 1
        ORDER BY pc.updated_at DESC
    ");
    $stmt->bind_param('ii', $actor['id'], $actor['id']);
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
                 WHERE sct.short_course_id = pc.id AND sct.lecturer_id = ? AND sct.is_active = 1 LIMIT 1) AS tutor_id
        FROM public_course_lessons ls
        JOIN public_course_modules m ON m.id = ls.module_id
        JOIN public_courses pc ON pc.id = m.course_id
        JOIN tutor_lesson_permissions tlp ON tlp.lesson_id = ls.id
        WHERE tlp.tutor_id = ? AND tlp.can_edit = 1
        ORDER BY pc.updated_at DESC
    ");
    $stmt->bind_param('ii', $actor['id'], $actor['id']);
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
                                <i class="fas fa-user-graduate"></i> Assigned
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

                    <div class="st-actions" style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
                        <a class="st-btn st-btn-small" href="course_builder.php?course_id=<?= $courseId ?>">
                            <i class="fas fa-pen"></i> Edit Course
                        </a>
                        <div class="st-dropdown" style="position:relative;">
                            <button class="st-btn st-btn-small st-btn-primary" onclick="toggleTeachDropdown(<?= $courseId ?>)">
                                <i class="fas fa-chalkboard-teacher"></i> Teach
                            </button>
                            <div class="st-dropdown-menu" id="teach-dropdown-<?= $courseId ?>" style="display:none; position:absolute; top:100%; left:0; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-sm); min-width:220px; max-height:300px; overflow-y:auto; z-index:10; margin-top:4px; box-shadow:var(--shadow);">
                                <a class="st-dropdown-item" href="#" onclick="openCoursePreview(<?= $courseId ?>); return false;">
                                    <i class="fas fa-book"></i> Course Preview
                                </a>
                                <div id="module-list-<?= $courseId ?>" style="display:none; border-top:1px solid var(--border);">
                                    <!-- Modules will be loaded here -->
                                </div>
                                <div id="lesson-list-<?= $courseId ?>" style="display:none; border-top:1px solid var(--border);">
                                    <!-- Lessons will be loaded here -->
                                </div>
                            </div>
                        </div>
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
<script>
// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.st-dropdown')) {
        document.querySelectorAll('.st-dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});

function toggleTeachDropdown(courseId) {
    const dropdown = document.getElementById('teach-dropdown-' + courseId);
    if (!dropdown) return;
    
    // Close all other dropdowns
    document.querySelectorAll('.st-dropdown-menu').forEach(menu => {
        if (menu.id !== 'teach-dropdown-' + courseId) {
            menu.style.display = 'none';
        }
    });
    
    // Toggle current dropdown
    const isOpening = dropdown.style.display === 'none';
    dropdown.style.display = isOpening ? 'block' : 'none';
    
    // Load modules and lessons when opening
    if (isOpening) {
        loadTeachContent(courseId);
    }
}

function loadTeachContent(courseId) {
    const moduleList = document.getElementById('module-list-' + courseId);
    const lessonList = document.getElementById('lesson-list-' + courseId);
    
    // Show loading state
    moduleList.innerHTML = '<div style="padding:10px 14px;color:var(--text-muted);font-size:0.85rem;">Loading modules...</div>';
    lessonList.innerHTML = '';
    moduleList.style.display = 'block';
    
    // Fetch course structure
    fetch('ajax/get_course_structure_for_teach.php?course_id=' + courseId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderModules(courseId, data.modules);
                renderLessons(courseId, data.lessons);
            } else {
                moduleList.innerHTML = '<div style="padding:10px 14px;color:var(--danger);font-size:0.85rem;">' + (data.message || 'Failed to load') + '</div>';
            }
        })
        .catch(() => {
            moduleList.innerHTML = '<div style="padding:10px 14px;color:var(--danger);font-size:0.85rem;">Failed to load modules</div>';
        });
}

function renderModules(courseId, modules) {
    const moduleList = document.getElementById('module-list-' + courseId);
    if (!modules || modules.length === 0) {
        moduleList.innerHTML = '<div style="padding:10px 14px;color:var(--text-muted);font-size:0.85rem;">No modules available</div>';
        return;
    }
    
    let html = '<div style="padding:8px 14px;background:var(--surface2);font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Modules</div>';
    modules.forEach(mod => {
        html += `
            <a class="st-dropdown-item" href="#" onclick="openModuleTeach(${courseId}, ${mod.id}, '${encodeURIComponent(mod.title)}'); return false;">
                <i class="fas fa-layer-group"></i>
                <span>${escapeHtml(mod.title)}</span>
                ${!mod.can_edit ? '<span style="font-size:0.7rem;color:var(--text-muted);margin-left:auto;">(View only)</span>' : ''}
            </a>
        `;
    });
    moduleList.innerHTML = html;
}

function renderLessons(courseId, lessons) {
    const lessonList = document.getElementById('lesson-list-' + courseId);
    if (!lessons || lessons.length === 0) {
        lessonList.innerHTML = '';
        return;
    }
    
    let html = '<div style="padding:8px 14px;background:var(--surface2);font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Lessons</div>';
    lessons.forEach(lesson => {
        html += `
            <a class="st-dropdown-item" href="#" onclick="openLessonTeach(${courseId}, ${lesson.id}, '${encodeURIComponent(lesson.title)}'); return false;">
                <i class="fas fa-file-alt"></i>
                <span>${escapeHtml(lesson.title)}</span>
                ${!lesson.can_edit ? '<span style="font-size:0.7rem;color:var(--text-muted);margin-left:auto;">(View only)</span>' : ''}
            </a>
        `;
    });
    lessonList.innerHTML = html;
}

function openModuleTeach(courseId, moduleId, title) {
    window.open('course_builder.php?course_id=' + courseId + '#module-' + moduleId, '_blank');
}

function openLessonTeach(courseId, lessonId, title) {
    updateLessonNumbering(courseId);
    window.open('lesson_editor.php?course_id=' + courseId + '&lesson_id=' + lessonId, '_blank');
}

function openCoursePreview(courseId) {
    const courseElement = document.querySelector(`[data-course-id="${courseId}"]`);
    const slug = courseElement ? courseElement.dataset.courseSlug : '';
    
    if (slug) {
        window.open('/learn/course.php?c=' + encodeURIComponent(slug), '_blank');
    } else {
        alert('Course not published yet. Publish the course to enable preview.');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = decodeURIComponent(text);
    return div.innerHTML;
}

function updateLessonNumbering(courseId) {
    // Call backend to update lesson numbering
    fetch('ajax/update_lesson_numbering.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'course_id=' + courseId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            console.log('Lesson numbering updated successfully');
        }
    })
    .catch(() => console.log('Failed to update lesson numbering'));
}
</script>
<style>
.st-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.85rem;
    transition: var(--tr);
    border-bottom: 1px solid var(--border);
}

.st-dropdown-item:last-child {
    border-bottom: none;
}

.st-dropdown-item:hover {
    background: var(--surface2);
    color: var(--text);
}

.st-dropdown-item i {
    width: 16px;
    text-align: center;
}
</style>
