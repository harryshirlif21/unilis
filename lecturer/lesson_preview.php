<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ajax/short_course_access.php';
// Any uncaught runtime exception (e.g. a mysqli failure from a schema mismatch)
// must never silently produce a blank iframe/page. Log the real cause and
// render a clear HTTP 500 instead. This does NOT weaken authorization: the
// explicit 403/400/404 http_response_code(...) + exit() guards below never
// throw, so they still enforce per-item assignment checks as intended.
set_exception_handler(function (Throwable $e): void {
    error_log('[lesson_preview.php] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8">'
        . '<h2 style="font:600 1.2rem/1.4 Segoe UI, Arial, sans-serif;color:#b00020;margin:24px;">'
        . 'Something went wrong rendering this preview. The error has been logged.</h2>'
        . '<pre style="white-space:pre-wrap;margin:0 24px 24px;font-family:Consolas,monospace;font-size:0.9rem;">'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . ' @ ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine()
        . '</pre>';
});

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

$itemAuthorized = $moduleId
    ? shortCourseIsAssignedToModule($conn, $moduleId)
    : shortCourseIsAssignedToLesson($conn, $lessonId);

if (!$itemAuthorized) {
    http_response_code(403);
    exit('You are not assigned to this content.');
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
 * Short-course file blocks are stored as JSON in content_html.  The database
 * does not retain the block type, so determine it from the vetted upload path
 * before rendering it.  This prevents a file block from appearing as raw JSON
 * in the lesson preview.
 */
function renderLessonPreviewContent(string $content): string
{
    $block = json_decode($content, true);
    if (!is_array($block)) {
        return $content;
    }

    // Older blocks saved paths with "../" while newer file blocks do not.
    $source = preg_replace('#^(?:\.\./)+#', '', trim((string)($block['src'] ?? '')));
    if (!is_string($source) || !preg_match('#^uploads/(course_images|course_audio|course_diagrams|course_videos|course_pdfs|course_presentations)/[A-Za-z0-9._-]+\.(jpg|jpeg|png|gif|webp|svg|mp3|wav|ogg|m4a|mp4|webm|ogv|mov|avi|mkv|3gp|flv|wmv|mpeg|mpg|m4v|pdf|ppt|pptx)$#i', $source, $matches)) {
        return $content;
    }

    $folder = strtolower($matches[1]);
    $extension = strtolower($matches[2]);
    $name = trim((string)($block['name'] ?? basename($source)));
    $caption = trim((string)($block['caption'] ?? ''));
    $downloadUrl = '../' . $source;
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeDownloadUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');
    $safeCaption = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');

    if ($folder === 'course_presentations') {
        $viewerUrl = 'ppt_preview.php?file=' . rawurlencode($source) . '&embed=1';
        return '<section class="presentation">'
            . '<div class="presentation-bar"><strong>PowerPoint: ' . $safeName . '</strong>'
            . '<span><a href="ppt_preview.php?file=' . rawurlencode($source) . '" target="_blank" rel="noopener">Open preview</a>'
            . ' <a href="' . $safeDownloadUrl . '" target="_blank" rel="noopener">Download</a></span></div>'
            . '<iframe src="' . htmlspecialchars($viewerUrl, ENT_QUOTES, 'UTF-8') . '" title="' . $safeName . '"></iframe>'
            . ($caption !== '' ? '<p class="presentation-caption">' . $safeCaption . '</p>' : '')
            . '</section>';
    }

    if ($folder === 'course_pdfs' || ($folder === 'course_diagrams' && $extension === 'pdf')) {
        return '<section class="document-preview">'
            . '<div class="presentation-bar"><strong>PDF: ' . $safeName . '</strong>'
            . '<a href="' . $safeDownloadUrl . '" target="_blank" rel="noopener">Open / Download</a></div>'
            . '<iframe src="' . $safeDownloadUrl . '#toolbar=1&navpanes=1" title="' . $safeName . '"></iframe>'
            . ($caption !== '' ? '<p class="presentation-caption">' . $safeCaption . '</p>' : '')
            . '</section>';
    }

    if ($folder === 'course_images' || $folder === 'course_diagrams') {
        return '<figure class="media-preview"><img src="' . $safeDownloadUrl . '" alt="' . $safeName . '">'
            . ($caption !== '' ? '<figcaption>' . $safeCaption . '</figcaption>' : '') . '</figure>';
    }

    if ($folder === 'course_audio') {
        return '<section class="media-preview"><strong>Audio: ' . $safeName . '</strong><audio controls src="' . $safeDownloadUrl . '"></audio></section>';
    }

    if ($folder === 'course_videos') {
        return '<section class="media-preview"><strong>Video: ' . $safeName . '</strong><video controls preload="metadata" src="' . $safeDownloadUrl . '"></video></section>';
    }

    return $content;
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
        .presentation, .document-preview { overflow: hidden; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .presentation-bar { display:flex; justify-content:space-between; gap:14px; align-items:center; padding:10px 14px; background:#f8fafc; font-size:.88rem; }
        .presentation-bar span { display:flex; gap:12px; }
        .presentation-bar a { color:#2563eb; text-decoration:none; }
        .presentation iframe, .document-preview iframe { display:block; width:100%; height:520px; border:0; }
        .presentation-caption { margin:0; padding:10px 14px; color:#64748b; font-size:.88rem; }
        .media-preview { margin:0; padding:14px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
        .media-preview strong { display:block; margin-bottom:10px; }
        .media-preview img, .media-preview video { display:block; max-width:100%; max-height:560px; margin:auto; }
        .media-preview audio { display:block; width:100%; }
        .media-preview figcaption { margin-top:10px; color:#64748b; font-size:.88rem; }
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
