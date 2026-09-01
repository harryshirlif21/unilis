<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lesson_id = (int)($_GET['lesson_id'] ?? ($_POST['lesson_id'] ?? 0));

// ---- GET: list topics + subtopics for this lesson ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$lesson_id) {
        echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
        exit;
    }
    if (!shortCourseCanEditLesson($conn, $lesson_id)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    require_once __DIR__ . '/../../learn/includes/catalogue.php'; // learn_lesson_topics
    $topics = learn_lesson_topics($conn, $lesson_id);
    echo json_encode(['success' => true, 'topics' => $topics]);
    exit;
}

// ---- POST: add / delete ----
if (!$lesson_id) {
    echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
    exit;
}
if (!shortCourseCanEditLesson($conn, $lesson_id)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $title     = trim((string)($_POST['title'] ?? ''));
    $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
    if ($title === '') {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        exit;
    }
    if ($parent_id) {
        // Parent must belong to this same lesson.
        $ck = $conn->prepare('SELECT id FROM public_course_lesson_topics WHERE id = ? AND lesson_id = ? LIMIT 1');
        $ck->bind_param('ii', $parent_id, $lesson_id);
        $ck->execute();
        if (!$ck->get_result()->fetch_row()) {
            echo json_encode(['success' => false, 'message' => 'Parent topic not found in this lesson']);
            exit;
        }
        $ck->close();
    }
    $q = 'SELECT COALESCE(MAX(position), -1) + 1 AS p FROM public_course_lesson_topics WHERE lesson_id = ?'
       . ($parent_id ? ' AND parent_id = ?' : ' AND parent_id IS NULL');
    $pos = $conn->prepare($q);
    if ($parent_id) { $pos->bind_param('ii', $lesson_id, $parent_id); }
    else { $pos->bind_param('i', $lesson_id); }
    $pos->execute();
    $nextPos = (int)$pos->get_result()->fetch_assoc()['p'];
    $pos->close();

    $ins = $conn->prepare('INSERT INTO public_course_lesson_topics (lesson_id, parent_id, title, content_html, position) VALUES (?, ?, ?, NULL, ?)');
    $ins->bind_param('iisi', $lesson_id, $parent_id, $title, $nextPos);
    if ($ins->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added', 'topic_id' => $ins->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $ins->error]);
    }
    $ins->close();
    exit;
}

if ($action === 'delete') {
    $topic_id = (int)($_POST['topic_id'] ?? 0);
    if (!$topic_id) {
        echo json_encode(['success' => false, 'message' => 'Topic ID required']);
        exit;
    }

    $check = $conn->prepare('SELECT id FROM public_course_lesson_topics WHERE id = ? AND lesson_id = ? LIMIT 1');
    $check->bind_param('ii', $topic_id, $lesson_id);
    $check->execute();
    if (!$check->get_result()->fetch_row()) {
        $check->close();
        echo json_encode(['success' => false, 'message' => 'Topic not found in this lesson']);
        exit;
    }
    $check->close();

    $ids = [$topic_id];
    $seen = [$topic_id => true];
    while ($ids) {
        $current = array_pop($ids);
        $children = $conn->prepare('SELECT id FROM public_course_lesson_topics WHERE lesson_id = ? AND parent_id = ?');
        $children->bind_param('ii', $lesson_id, $current);
        $children->execute();
        foreach ($children->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $childId = (int)$row['id'];
            if (!isset($seen[$childId])) {
                $seen[$childId] = true;
                $ids[] = $childId;
            }
        }
        $children->close();
    }

    $deleteIds = array_keys($seen);
    sort($deleteIds, SORT_NUMERIC);

    $conn->begin_transaction();
    try {
        $progressTable = $conn->query("SHOW TABLES LIKE 'external_lesson_topic_progress'");
        if ($progressTable && $progressTable->num_rows > 0) {
            $ph = implode(',', array_fill(0, count($deleteIds), '?'));
            $progress = $conn->prepare('DELETE FROM external_lesson_topic_progress WHERE topic_id IN (' . $ph . ')');
            $progress->bind_param(str_repeat('i', count($deleteIds)), ...$deleteIds);
            $progress->execute();
            $progress->close();
        }

        $ph = implode(',', array_fill(0, count($deleteIds), '?'));
        $del = $conn->prepare('DELETE FROM public_course_lesson_topics WHERE id IN (' . $ph . ')');
        $del->bind_param(str_repeat('i', count($deleteIds)), ...$deleteIds);
        $del->execute();
        $del->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Deleted']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);