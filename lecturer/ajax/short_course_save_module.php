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
$title       = trim($_POST['title'] ?? '');
$summary     = trim($_POST['summary'] ?? '');
$start_date  = trim($_POST['start_date'] ?? '');
$end_date    = trim($_POST['end_date'] ?? '');

if (!$course_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!shortCourseCanManage($conn, $course_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($module_id > 0) {
    // Check granular module edit permission for updates
    if (!shortCourseCanEditModule($conn, $module_id)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this module']);
        exit;
    }
    
    // Update existing module — only touch fields that were actually submitted.
    // The inline title rename posts only `title`; the module modal posts summary + dates too.
    $updateFields = [];
    $params = [];
    $types = '';

    // Title is always present for this endpoint.
    $updateFields[] = 'title = ?';
    $params[] = $title;
    $types .= 's';

    if (isset($_POST['summary'])) {
        $updateFields[] = 'summary = ?';
        $params[] = trim($_POST['summary']);
        $types .= 's';
    }
    // date columns: convert empty submission to NULL so we never write '' into a DATE field.
    if (isset($_POST['start_date'])) {
        $updateFields[] = 'start_date = ?';
        $params[] = trim($_POST['start_date']) !== '' ? trim($_POST['start_date']) : null;
        $types .= 's';
    }
    if (isset($_POST['end_date'])) {
        $updateFields[] = 'end_date = ?';
        $params[] = trim($_POST['end_date']) !== '' ? trim($_POST['end_date']) : null;
        $types .= 's';
    }

    $params[] = $module_id;
    $params[] = $course_id;
    $types .= 'ii';

    $sql = "UPDATE public_course_modules SET " . implode(', ', $updateFields) . " WHERE id = ? AND course_id = ?";
    $stmt = $conn->prepare($sql);
    $bindOk = $stmt->bind_param($types, ...$params);
    if (!$bindOk) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare module update']);
        exit;
    }
    $result = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($result) {
        $message = $affected > 0 ? 'Module updated' : 'Module updated (no changes)';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update module']);
    }
} else {
    // Create new module
    // Get next position
    $posResult = $conn->query("SELECT COALESCE(MAX(position), -1) + 1 AS next FROM public_course_modules WHERE course_id = $course_id");
    $posRow = $posResult->fetch_assoc();
    $position = (int)$posRow['next'];
    $posResult->free();

    $stmt = $conn->prepare("INSERT INTO public_course_modules (course_id, title, summary, start_date, end_date, position) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $course_id, $title, $summary, $start_date, $end_date, $position);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Module added', 'module_id' => $newId]);
}
