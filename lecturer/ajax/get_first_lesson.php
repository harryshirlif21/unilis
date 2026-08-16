<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

$course_id = intval($_GET['course_id'] ?? 0);

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Course ID required']);
    exit;
}

// Verify access to the course
if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Get the first lesson from the first module of the course
    $stmt = $conn->prepare("
        SELECT l.id, l.title, l.module_id, m.title as module_title
        FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        WHERE m.course_id = ?
        ORDER BY m.position ASC, m.id ASC, l.position ASC, l.id ASC
        LIMIT 1
    ");
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($lesson) {
        echo json_encode([
            'success' => true,
            'lesson_id' => (int)$lesson['id'],
            'lesson_title' => $lesson['title'],
            'module_id' => (int)$lesson['module_id'],
            'module_title' => $lesson['module_title']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No lessons found']);
    }
} catch (mysqli_sql_exception $e) {
    error_log('get_first_lesson: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
