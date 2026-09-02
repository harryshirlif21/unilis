<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$course_id   = (int)($_POST['course_id'] ?? 0);
$module_id   = (int)($_POST['module_id'] ?? 0);
$lesson_id   = (int)($_POST['lesson_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$lesson_number = trim($_POST['lesson_number'] ?? '');
$video_url    = trim($_POST['video_url'] ?? '');
$has_start_date = array_key_exists('start_date', $_POST);
$has_end_date   = array_key_exists('end_date', $_POST);
$start_date     = trim($_POST['start_date'] ?? '');
$end_date       = trim($_POST['end_date'] ?? '');

// For updates, title might not be required if only updating other fields
if (!$course_id || !$module_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date))
    || ($end_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date))) {
    echo json_encode(['success' => false, 'message' => 'Invalid lesson schedule date']);
    exit;
}
if ($start_date !== '' && $end_date !== '' && $start_date > $end_date) {
    echo json_encode(['success' => false, 'message' => 'Start date must be before end date']);
    exit;
}

// Verify module belongs to course
$stmt = $conn->prepare("SELECT id FROM public_course_modules WHERE id = ? AND course_id = ?");
$stmt->bind_param("ii", $module_id, $course_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_row()) {
    echo json_encode(['success' => false, 'message' => 'Module not found in this course']);
    $stmt->close();
    exit;
}
$stmt->close();

if ($lesson_id > 0) {
    // Check granular lesson edit permission for updates
    if (!shortCourseCanEditLesson($conn, $lesson_id)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this lesson']);
        exit;
    }
    
    // Update existing lesson - build dynamic update based on provided fields.
    // NOTE: public_course_lessons stores ordering in `position` (there is no
    // `lesson_number` column), while the JS sends the "lesson number" as
    // `lesson_number`. Map it to `position` so inline renumbering persists.
    $updateFields = [];
    $params = [];
    $types = '';

    if ($title !== '') {
        $updateFields[] = 'title = ?';
        $params[] = $title;
        $types .= 's';
    }
    if ($lesson_number !== '') {
        $updateFields[] = 'position = ?';
        $params[] = (int)$lesson_number;
        $types .= 'i';
    }
    if ($video_url !== '') {
        $updateFields[] = 'video_url = ?';
        $params[] = $video_url;
        $types .= 's';
    }
    if ($has_start_date || $has_end_date) {
        $dateColumns = $conn->query("SHOW COLUMNS FROM public_course_lessons WHERE Field IN ('start_date', 'end_date')");
        if (!$dateColumns || $dateColumns->num_rows !== 2) {
            echo json_encode(['success' => false, 'message' => 'Lesson scheduling has not been installed yet']);
            exit;
        }
        $updateFields[] = 'start_date = NULLIF(?, \'\')';
        $params[] = $start_date;
        $types .= 's';
        $updateFields[] = 'end_date = NULLIF(?, \'\')';
        $params[] = $end_date;
        $types .= 's';
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    $params[] = $lesson_id;
    $params[] = $module_id;
    $types .= 'ii';
    
    $sql = "UPDATE public_course_lessons SET " . implode(', ', $updateFields) . " WHERE id = ? AND module_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->bind_param($types, ...$params);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
        exit;
    }
    $executeResult = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($executeResult) {
        $message = $affected > 0 ? 'Lesson updated' : 'Lesson updated (no changes)';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update lesson']);
    }
} else {
    // Create new lesson - title is required for new lessons
    if (!$title) {
        echo json_encode(['success' => false, 'message' => 'Title is required for new lessons']);
        exit;
    }
    
    $posResult = $conn->query("SELECT COALESCE(MAX(position), -1) + 1 AS next FROM public_course_lessons WHERE module_id = $module_id");
    $posRow = $posResult->fetch_assoc();
    $position = (int)$posRow['next'];
    $posResult->free();

    $stmt = $conn->prepare("INSERT INTO public_course_lessons (module_id, title, position, video_url) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $module_id, $title, $position, $video_url);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Lesson added', 'lesson_id' => $newId]);
}
