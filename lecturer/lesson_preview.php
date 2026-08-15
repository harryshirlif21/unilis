<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ajax/short_course_access.php';

if (!shortCourseIsAuthor()) {
    header('Location: ../login.php');
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
$lessonId = (int)($_GET['lesson_id'] ?? 0);
$moduleId = (int)($_GET['module_id'] ?? 0);

if (!$courseId || (!$lessonId && !$moduleId)) {
    http_response_code(400);
    exit('A course and either a lesson or module are required.');
}

if (!shortCourseCanManage($conn, $courseId)) {
    http_response_code(403);
    exit('You do not have access to this lesson.');
}

function previewHasOutlineColumn(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'outline'");
    return $result && $result->num_rows > 0;
}

$courseOutline = previewHasOutlineColumn($conn, 'public_courses') ? ', pc.outline AS course_outline' : ", '' AS course_outline";
$moduleOutline = previewHasOutlineColumn($conn, 'public_course_modules') ? ', m.outline AS module_outline' : ", '' AS module_outline";
$lessonOutline = previewHasOutlineColumn($conn, 'public_course_lessons') ? ', l.outline AS lesson_outline' : ", '' AS lesson_outline";

$stmt = $conn->prepare('SELECT pc.title AS course_title, m.title AS module_title, l.title AS lesson_title, l.content_html' . $courseOutline . $moduleOutline . $lessonOutline . ' FROM public_course_lessons l JOIN public_course_modules m ON m.id = l.module_id JOIN public_courses pc ON pc.id = m.course_id WHERE l.id = ? AND m.course_id = ? LIMIT 1');
$stmt->bind_param('ii', $lessonId, $courseId);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($lessonId && !$lesson) {
    http_response_code(404);
    exit('Lesson not found.');
}

$module = null;
$moduleLessons = [];
if ($moduleId) {
    $stmt = $conn->prepare('SELECT pc.title AS course_title, m.title AS module_title' . $courseOutline . $moduleOutline . ' FROM public_course_modules m JOIN public_courses pc ON pc.id = m.course_id WHERE m.id = ? AND m.course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $moduleId, $courseId);
    $stmt->execute();
    $module = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$module) {
        http_response_code(404);
        exit('Module not found.');
    }

    $stmt = $conn->prepare('SELECT id, title, content_html' . $lessonOutline . ' FROM public_course_lessons l WHERE l.module_id = ? ORDER BY l.position ASC, l.id ASC');
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $moduleLessons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$preview = $module ?: $lesson;

/**
 * Lesson-editor short courses store a PowerPoint block as JSON in content_html.
 * Render it through the vetted presentation preview rather than displaying the
 * JSON text as lesson content.
 */
function renderLessonPreviewContent(string $content): string
{
    $block = json_decode($content, true);
    $source = is_array($block) ? (string)($block['src'] ?? '') : '';
    if (!preg_match('#^uploads/course_presentations/[A-Za-z0-9._-]+\.(ppt|pptx)$#i', $source)) {
        return $content;
    }

    $name = trim((string)($block['name'] ?? basename($source)));
    $caption = trim((string)($block['caption'] ?? ''));
    $viewerUrl = 'ppt_preview.php?file=' . rawurlencode($source) . '&embed=1';
    $downloadUrl = '../' . $source;

    return '<section class="presentation">'
        . '<div class="presentation-bar"><strong>PowerPoint: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<span><a href="ppt_preview.php?file=' . rawurlencode($source) . '" target="_blank" rel="noopener">Open preview</a>'
        . ' <a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Download</a></span></div>'
        . '<iframe src="' . htmlspecialchars($viewerUrl, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"></iframe>'
        . ($caption !== '' ? '<p class="presentation-caption">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</p>' : '')
        . '</section>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($module ? $module['module_title'] : $lesson['lesson_title']) ?> — Preview</title>
    <style>
        body { margin: 0; background: #f5f7fb; color: #1f2937; font-family: Inter, Segoe UI, Arial, sans-serif; }
        header { padding: 16px 24px; color: #fff; background: #182238; }
        header small { display:block; margin-bottom: 4px; color: #b9c4d6; }
        h1 { margin: 0; font-size: 1.35rem; }
        main { max-width: 900px; margin: 32px auto; padding: 32px; background: #fff; box-shadow: 0 4px 18px rgba(15, 23, 42, .08); border-radius: 12px; }
        .meta { margin: 0 0 26px; color: #64748b; font-size: .9rem; }
        .outline { margin: 0 0 22px; padding: 16px; background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 6px; }
        .outline h2 { margin: 0 0 8px; font-size: 1rem; color: #1e40af; }
        .outline p { margin: 0; white-space: pre-wrap; line-height: 1.6; }
        .lesson-section { margin-top: 30px; padding-top: 24px; border-top: 1px solid #e2e8f0; }
        .lesson-section h2 { margin: 0 0 12px; font-size: 1.15rem; }
        .empty { padding: 28px; text-align: center; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b; }
        img, video, iframe { max-width: 100%; }
        pre { overflow: auto; padding: 14px; background: #f1f5f9; border-radius: 6px; }
        .presentation { overflow: hidden; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .presentation-bar { display:flex; justify-content:space-between; gap:14px; align-items:center; padding:10px 14px; background:#f8fafc; font-size:.88rem; }
        .presentation-bar span { display:flex; gap:12px; }
        .presentation-bar a { color:#2563eb; text-decoration:none; }
        .presentation iframe { display:block; width:100%; height:520px; border:0; }
        .presentation-caption { margin:0; padding:10px 14px; color:#64748b; font-size:.88rem; }
    </style>
</head>
<body>
    <header>
        <small><?= htmlspecialchars($preview['course_title']) ?> · <?= htmlspecialchars($preview['module_title']) ?></small>
        <h1><?= htmlspecialchars($module ? $module['module_title'] : $lesson['lesson_title']) ?></h1>
    </header>
    <main>
        <p class="meta"><?= $module ? 'Module preview' : 'Lesson preview' ?></p>
        <?php if (!empty($preview['course_outline'])): ?>
            <section class="outline"><h2>Course outline</h2><p><?= htmlspecialchars($preview['course_outline']) ?></p></section>
        <?php endif; ?>
        <?php if (!empty($preview['module_outline'])): ?>
            <section class="outline"><h2>Module outline</h2><p><?= htmlspecialchars($preview['module_outline']) ?></p></section>
        <?php endif; ?>
        <?php if ($module): ?>
            <?php if (empty($moduleLessons)): ?>
                <div class="empty">This module does not have lessons yet.</div>
            <?php endif; ?>
            <?php foreach ($moduleLessons as $moduleLesson): ?>
                <section class="lesson-section">
                    <h2><?= htmlspecialchars($moduleLesson['title']) ?></h2>
                    <?php if (!empty($moduleLesson['lesson_outline'])): ?>
                        <section class="outline"><h2>Lesson outline</h2><p><?= htmlspecialchars($moduleLesson['lesson_outline']) ?></p></section>
                    <?php endif; ?>
                    <?php if (trim((string)$moduleLesson['content_html']) === ''): ?>
                        <div class="empty">This lesson does not have content yet.</div>
                    <?php else: ?>
                        <?= renderLessonPreviewContent((string)$moduleLesson['content_html']) ?>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php elseif (trim((string)$lesson['content_html']) === ''): ?>
            <?php if (!empty($lesson['lesson_outline'])): ?>
                <section class="outline"><h2>Lesson outline</h2><p><?= htmlspecialchars($lesson['lesson_outline']) ?></p></section>
            <?php endif; ?>
            <div class="empty">This lesson does not have content yet.</div>
        <?php else: ?>
            <?php if (!empty($lesson['lesson_outline'])): ?>
                <section class="outline"><h2>Lesson outline</h2><p><?= htmlspecialchars($lesson['lesson_outline']) ?></p></section>
            <?php endif; ?>
            <?= renderLessonPreviewContent((string)$lesson['content_html']) ?>
        <?php endif; ?>
    </main>
</body>
</html>
