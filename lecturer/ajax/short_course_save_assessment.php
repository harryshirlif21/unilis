<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
        'debug_user_id' => $_SESSION['user_id'] ?? null,
        'debug_user_role' => $_SESSION['user_role'] ?? null,
        'debug_session_id' => session_id(),
    ]);
    exit;
}

$assessment_id = (int)($_POST['assessment_id'] ?? 0);
$course_id     = (int)($_POST['course_id'] ?? 0);
$module_id     = (int)($_POST['module_id'] ?? 0) ?: null;
$lesson_id     = (int)($_POST['lesson_id'] ?? 0) ?: null;
$title         = trim((string)($_POST['title'] ?? ''));
$type          = ($_POST['type'] ?? '') === 'assignment' ? 'assignment' : 'cat';
$instructions  = trim((string)($_POST['instructions'] ?? ''));
$pass_mark     = (int)($_POST['pass_mark'] ?? 0);
$max_attempts  = (int)($_POST['max_attempts'] ?? ($type === 'cat' ? 1 : 0));
$time_limit    = $type === 'cat' ? ((int)($_POST['time_limit_minutes'] ?? 0) ?: null) : null;
$submission_type = $type === 'assignment' ? (in_array($_POST['submission_type'] ?? '', ['file', 'text', 'both'], true) ? $_POST['submission_type'] : 'both') : null;
$due_date      = trim((string)($_POST['due_date'] ?? '')) ?: null;

if (!$course_id || !$title || (!$module_id && !$lesson_id)) {
    echo json_encode(['success' => false, 'message' => 'Course, a target (module or lesson), and a title are required.']);
    exit;
}

$allowed = $lesson_id
    ? shortCourseCanEditLesson($conn, $lesson_id)
    : shortCourseCanEditModule($conn, $module_id);

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($assessment_id) {
    $check = $conn->prepare('SELECT id FROM public_course_assessments WHERE id = ? AND course_id = ? LIMIT 1');
    $check->bind_param('ii', $assessment_id, $course_id);
    $check->execute();
    $exists = $check->get_result()->fetch_row();
    $check->close();
    if (!$exists) {
        echo json_encode(['success' => false, 'message' => 'Assessment not found in this course.']);
        exit;
    }

    $stmt = $conn->prepare('
        UPDATE public_course_assessments
        SET module_id = ?, lesson_id = ?, title = ?, type = ?, instructions = ?,
            pass_mark = ?, max_attempts = ?, time_limit_minutes = ?, submission_type = ?, due_date = ?
        WHERE id = ?
    ');
    $stmt->bind_param(
        'iisssiiissi',
        $module_id, $lesson_id, $title, $type, $instructions,
        $pass_mark, $max_attempts, $time_limit, $submission_type, $due_date,
        $assessment_id
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' updated', 'assessment_id' => $assessment_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    $posRes = $conn->prepare('SELECT COALESCE(MAX(position), -1) + 1 AS next_pos FROM public_course_assessments WHERE course_id = ? AND ' . ($lesson_id ? 'lesson_id = ?' : 'module_id = ?'));
    $target = $lesson_id ?: $module_id;
    $posRes->bind_param('ii', $course_id, $target);
    $posRes->execute();
    $position = (int)$posRes->get_result()->fetch_assoc()['next_pos'];
    $posRes->close();

    $stmt = $conn->prepare('
        INSERT INTO public_course_assessments
            (course_id, module_id, lesson_id, title, type, instructions, pass_mark, max_attempts, position, time_limit_minutes, submission_type, due_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->bind_param(
        'iiisssiiiiss',
        $course_id, $module_id, $lesson_id, $title, $type, $instructions,
        $pass_mark, $max_attempts, $position, $time_limit, $submission_type, $due_date
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => ucfirst($type) . ' added', 'assessment_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    }
    $stmt->close();
}
