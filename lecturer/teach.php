<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ajax/short_course_access.php';
// Any uncaught runtime exception (e.g. a mysqli failure from a schema mismatch)
// must never silently produce a blank page. Log the real cause and render a
// clear HTTP 500 with a message instead. This does NOT weaken authorization:
// the explicit http_response_code(...) + exit() checks below still short-circuit
// to their intended status (403/404/400) because they never throw.
set_exception_handler(function (Throwable $e): void {
    error_log('[teach.php] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8">'
        . '<h2 style="font:600 1.2rem/1.4 Segoe UI, Arial, sans-serif;color:#b00020;margin:24px;">'
        . 'Something went wrong rendering this page. The error has been logged.</h2>'
        . '<pre style="white-space:pre-wrap;margin:0 24px 24px;font-family:Consolas,monospace;font-size:0.9rem;">'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . ' @ ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine()
        . '</pre>';
});

if (!shortCourseIsAuthor()) {
    header('Location: ../login.php');
    exit;
}

$course_id = (int)($_GET['course_id'] ?? 0);
if (!$course_id) {
    http_response_code(400);
    exit('A course_id is required.');
}

if (!shortCourseCanView($conn, $course_id)) {
    http_response_code(403);
    exit('You do not have access to this course.');
}

$stmt = $conn->prepare('SELECT id, title, slug FROM public_courses WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['user_role'] ?? '';
$isPrimary = false;
if ($role === 'admin' || $role === 'department_admin') {
    $isPrimary = true;
} else {
    $stmt = $conn->prepare('SELECT is_active FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $course_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && (int)$row['is_active'] === 1) {
        $isPrimary = true;
    }
    $ownerStmt = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ? AND created_by_lecturer_id = ? LIMIT 1');
    $ownerStmt->bind_param('ii', $course_id, $userId);
    $ownerStmt->execute();
    if ($ownerStmt->get_result()->fetch_row()) {
        $isPrimary = true;
    }
    $ownerStmt->close();
}

// Load every module + lesson in the course, unfiltered — filtering happens
// per-item below so locked items can still be *shown*, just not openable.
$modules = [];
$stmt = $conn->prepare('SELECT id, title, position FROM public_course_modules WHERE course_id = ? ORDER BY position ASC, id ASC');
$stmt->bind_param('i', $course_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['lessons'] = [];
    $modules[$row['id']] = $row;
}
$stmt->close();

if (!empty($modules)) {
    $moduleIds = implode(',', array_map('intval', array_keys($modules)));
    $lessonResult = $conn->query("SELECT id, module_id, title, position FROM public_course_lessons WHERE module_id IN ($moduleIds) ORDER BY position ASC, id ASC");
    while ($lesson = $lessonResult->fetch_assoc()) {
        $mid = $lesson['module_id'];
        if (isset($modules[$mid])) {
            $modules[$mid]['lessons'][] = $lesson;
        }
    }
}

// Per-item authorization: mark each module/lesson unlocked or locked.
// A module is unlocked if the tutor is authorized on the module itself.
// A lesson is unlocked if authorized on the lesson OR its parent module.
foreach ($modules as $mid => &$mod) {
    $mod['unlocked'] = $isPrimary || shortCourseIsAssignedToModule($conn, (int)$mid);
    foreach ($mod['lessons'] as &$lesson) {
        $lesson['unlocked'] = $isPrimary || $mod['unlocked'] || shortCourseIsAssignedToLesson($conn, (int)$lesson['id']);
    }
    unset($lesson);
}
unset($mod);

// Flatten into an ordered sequence of only the UNLOCKED items, for
// Previous/Next — locked items are never part of this navigable path.
$sequence = [];
foreach ($modules as $mid => $mod) {
    if ($mod['unlocked']) {
        // A fully-unlocked module counts as a single navigable stop unless
        // it has lessons, in which case each unlocked lesson is a stop.
        if (empty($mod['lessons'])) {
            $sequence[] = ['type' => 'module', 'id' => (int)$mid];
        }
    }
    foreach ($mod['lessons'] as $lesson) {
        if ($lesson['unlocked']) {
            $sequence[] = ['type' => 'lesson', 'id' => (int)$lesson['id'], 'module_id' => (int)$mid];
        }
    }
}

// Resolve the requested current item (module_id or lesson_id from the query
// string), re-validating server-side regardless of what was requested.
$reqModuleId = (int)($_GET['module_id'] ?? 0);
$reqLessonId = (int)($_GET['lesson_id'] ?? 0);
$currentIndex = -1;
foreach ($sequence as $i => $item) {
    if ($reqLessonId && $item['type'] === 'lesson' && $item['id'] === $reqLessonId) { $currentIndex = $i; break; }
    if ($reqModuleId && $item['type'] === 'module' && $item['id'] === $reqModuleId) { $currentIndex = $i; break; }
}
// If nothing requested or the requested item wasn't authorized, default to
// the first authorized item in the sequence.
if ($currentIndex === -1 && !empty($sequence)) {
    $currentIndex = 0;
}

$prevItem = ($currentIndex > 0) ? $sequence[$currentIndex - 1] : null;
$nextItem = ($currentIndex >= 0 && $currentIndex < count($sequence) - 1) ? $sequence[$currentIndex + 1] : null;
$currentItem = ($currentIndex >= 0) ? $sequence[$currentIndex] : null;

function teachNavUrl(int $courseId, array $item): string {
    return $item['type'] === 'lesson'
        ? "teach.php?course_id={$courseId}&lesson_id={$item['id']}"
        : "teach.php?course_id={$courseId}&module_id={$item['id']}";
}

function escHtml($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teach — <?= escHtml($course['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg: #0d0f14; --surface: #161921; --surface2: #1e2230; --surface3: #262c3d;
    --border: #2a3148; --accent: #4f8ef7; --accent2: #38d9a9; --accent3: #f7934f;
    --text: #e8eaf0; --text-muted: #7a82a0; --text-dim: #4a5270;
    --radius: 10px; --radius-sm: 6px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 32px; height: 60px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; }
.topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--accent); }
.topbar-brand span { color: var(--text-muted); font-weight: 400; margin-left: 8px; font-size: 0.85rem; }
.btn-nav { background: var(--surface3); border: 1px solid var(--border); color: var(--text-muted); padding: 6px 14px; border-radius: var(--radius-sm); font-size: 0.8rem; text-decoration: none; }
.btn-nav:hover { background: var(--surface2); color: var(--text); }
.layout { display: flex; min-height: calc(100vh - 60px); }
.sidebar { width: 280px; min-width: 280px; background: var(--surface); border-right: 1px solid var(--border); padding: 18px 14px; overflow-y: auto; }
.module-block { margin-bottom: 10px; }
.module-head { padding: 9px 10px; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.82rem; display: flex; align-items: center; gap: 6px; color: var(--text-muted); }
.module-head.locked { opacity: 0.5; }
.lesson-link { display: flex; align-items: center; gap: 8px; padding: 7px 10px 7px 20px; font-size: 0.84rem; border-radius: var(--radius-sm); text-decoration: none; color: var(--text); }
.lesson-link:hover { background: var(--surface2); }
.lesson-link.active { background: rgba(79,142,247,0.12); color: var(--accent); }
.lesson-link.locked { color: var(--text-dim); cursor: not-allowed; opacity: 0.6; }
.lesson-link.locked:hover { background: none; }
.lock-icon { font-size: 0.7rem; }
.main { flex: 1; padding: 28px 36px; max-width: 820px; }
.page-head h1 { font-family: 'Syne', sans-serif; font-size: 1.25rem; margin-bottom: 4px; }
.page-head p { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px; }
.content-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 20px; min-height: 200px; }
.nav-row { display: flex; justify-content: space-between; gap: 10px; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 500; cursor: pointer; border: none; text-decoration: none; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.btn:disabled, .btn.disabled { opacity: 0.4; pointer-events: none; }
.empty-state { text-align: center; padding: 60px; color: var(--text-dim); }
iframe.content-frame { width: 100%; height: 640px; border: 0; border-radius: var(--radius-sm); }
</style>
</head>
<body>
<header class="topbar">
    <div class="topbar-brand">UNILIS <span>Teach — Preview Mode</span></div>
    <a href="catalogue.php" class="btn-nav"><i class="fas fa-arrow-left"></i> Back to Short Courses</a>
</header>

<div class="layout">
    <aside class="sidebar">
        <?php if (empty($modules)): ?>
            <p style="color:var(--text-dim);font-size:0.85rem;padding:10px;">No modules in this course yet.</p>
        <?php else: foreach ($modules as $mid => $mod): ?>
            <div class="module-block">
                <div class="module-head <?= $mod['unlocked'] ? '' : 'locked' ?>">
                    <?= $mod['unlocked'] ? '<i class="fas fa-folder-open"></i>' : '<i class="fas fa-lock lock-icon"></i>' ?>
                    <?= escHtml($mod['title']) ?>
                </div>
                <?php foreach ($mod['lessons'] as $lesson):
                    $isCurrent = $currentItem && $currentItem['type'] === 'lesson' && $currentItem['id'] === (int)$lesson['id'];
                    ?>
                    <?php if ($lesson['unlocked']): ?>
                        <a class="lesson-link <?= $isCurrent ? 'active' : '' ?>" href="teach.php?course_id=<?= $course_id ?>&lesson_id=<?= $lesson['id'] ?>">
                            <i class="fas fa-file-alt"></i> <?= escHtml($lesson['title']) ?>
                        </a>
                    <?php else: ?>
                        <span class="lesson-link locked" title="Not assigned to you">
                            <i class="fas fa-lock lock-icon"></i> <?= escHtml($lesson['title']) ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($mod['lessons']) && !$mod['unlocked']): ?>
                    <span class="lesson-link locked" title="Not assigned to you">
                        <i class="fas fa-lock lock-icon"></i> Whole module locked
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </aside>

    <main class="main">
        <div class="page-head">
            <h1><i class="fas fa-chalkboard-teacher"></i> <?= escHtml($course['title']) ?></h1>
            <p><?= $isPrimary ? 'You are the primary tutor — full course accessible.' : 'Showing only what is assigned to you. Locked items are not accessible.' ?></p>
        </div>

        <div class="content-card">
            <?php if (!$currentItem): ?>
                <div class="empty-state">
                    <i class="fas fa-layer-group" style="font-size:2rem;opacity:0.4;display:block;margin-bottom:14px;"></i>
                    <p>Nothing assigned to you yet in this course.</p>
                </div>
            <?php else:
                $iframeSrc = $currentItem['type'] === 'lesson'
                    ? "lesson_preview.php?course_id={$course_id}&lesson_id={$currentItem['id']}"
                    : "lesson_preview.php?course_id={$course_id}&module_id={$currentItem['id']}";
                ?>
                <iframe class="content-frame" src="<?= escHtml($iframeSrc) ?>"></iframe>
            <?php endif; ?>
        </div>

        <div class="nav-row">
            <?php if ($prevItem): ?>
                <a class="btn btn-ghost" href="<?= escHtml(teachNavUrl($course_id, $prevItem)) ?>"><i class="fas fa-chevron-left"></i> Previous</a>
            <?php else: ?>
                <span class="btn btn-ghost disabled"><i class="fas fa-chevron-left"></i> Previous</span>
            <?php endif; ?>
            <?php if ($nextItem): ?>
                <a class="btn btn-primary" href="<?= escHtml(teachNavUrl($course_id, $nextItem)) ?>">Next <i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <span class="btn btn-primary disabled">Next <i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
