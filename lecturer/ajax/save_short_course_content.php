<?php
// lecturer/ajax/save_short_course_content.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lesson_id   = (int)($_POST['lesson_id'] ?? 0);
$block_type  = trim($_POST['block_type'] ?? '');
$content     = $_POST['content'] ?? '';

if (!$lesson_id || !$block_type) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

try {
    // Resolve the parent course first, then apply the same access rule used by
    // every short-course builder action.
    $stmt = $conn->prepare("
        SELECT l.id, l.content_html, l.video_url, l.attachment_path, m.course_id
        FROM public_course_lessons l
        JOIN public_course_modules m ON l.module_id = m.id
        WHERE l.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$lesson || !shortCourseCanManage($conn, (int)$lesson['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found or access denied']); exit;
    }

    // For short courses, content is stored in public_course_lessons.content_html
    // For text blocks, save the HTML directly. For other block types, we store
    // the JSON content in content_html as well (the learn page renders it).
    $content_html = $content;

    // If this is a text block, save it directly as content_html
    // If it's another type (image, video, etc.), we still store it in content_html
    // so the learn page can render it
    $stmt = $conn->prepare("
        UPDATE public_course_lessons
        SET content_html = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $content_html, $lesson_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Content saved', 'block_id' => 0]);
} catch (mysqli_sql_exception $e) {
    error_log("save_short_course_content: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
