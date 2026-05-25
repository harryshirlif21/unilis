<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$student_id  = $_SESSION['user_id'];
$lesson_id   = intval($_GET['lesson_id'] ?? 0);
$unit_id     = intval($_GET['unit_id']   ?? 0);

if (!$lesson_id) { header("Location: course_view.php"); exit; }

// Fetch lesson
$lesson = null;
try {
    $stmt = $conn->prepare("
        SELECT l.*, m.title AS module_title, m.id AS module_id
        FROM course_lessons l
        JOIN course_modules m ON l.module_id = m.id
        WHERE l.id = ? AND l.unit_id = ?
    ");
    $stmt->bind_param("ii", $lesson_id, $unit_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

if (!$lesson) { header("Location: course_view.php?unit_id=$unit_id"); exit; }

// Fetch content blocks
$blocks = [];
try {
    $stmt = $conn->prepare("SELECT id, block_type, content, position FROM lesson_content_blocks WHERE lesson_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $blocks[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Fetch all lessons in module (for prev/next nav)
$module_lessons = [];
try {
    $stmt = $conn->prepare("SELECT id, title, lesson_number FROM course_lessons WHERE module_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $lesson['module_id']);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $module_lessons[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

$current_idx = -1;
foreach ($module_lessons as $i => $ml) { if ($ml['id'] == $lesson_id) { $current_idx = $i; break; } }
$prev_lesson = $current_idx > 0 ? $module_lessons[$current_idx - 1] : null;
$next_lesson = $current_idx < count($module_lessons) - 1 ? $module_lessons[$current_idx + 1] : null;

// Check if already marked complete
$is_completed = false;
$completed_lessons = []; // all completed lessons in this module (for sidebar checks)
try {
    $stmt = $conn->prepare("SELECT id FROM student_progress WHERE student_id = ? AND lesson_id = ? AND event_type = 'lesson_completed' LIMIT 1");
    $stmt->bind_param("ii", $student_id, $lesson_id);
    $stmt->execute();
    $is_completed = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch all completed lesson IDs in this module for sidebar
    if (!empty($module_lessons)) {
        $mids  = array_column($module_lessons, 'id');
        $ph    = implode(',', array_fill(0, count($mids), '?'));
        $types = 'i' . str_repeat('i', count($mids));
        $stmt  = $conn->prepare("
            SELECT lesson_id FROM student_progress
            WHERE student_id = ? AND lesson_id IN ($ph) AND event_type = 'lesson_completed'
        ");
        $stmt->bind_param($types, $student_id, ...$mids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) $completed_lessons[$row['lesson_id']] = true;
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Mark as viewed (upsert â€” update completed_at if already exists)
try {
    $stmt = $conn->prepare("
        INSERT INTO student_progress (student_id, unit_id, lesson_id, event_type, completed_at)
        VALUES (?, ?, ?, 'lesson_viewed', NOW())
        ON DUPLICATE KEY UPDATE completed_at = NOW()
    ");
    $stmt->bind_param("iii", $student_id, $unit_id, $lesson_id);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// Unit name
$unit_name = '';
try {
    $stmt = $conn->prepare("SELECT name FROM units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $unit_name = $row['name'] ?? '';
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log($e->getMessage()); }

// â”€â”€ Fetch assessments tied to this lesson (quiz/cat linked via lesson_id OR module_id) â”€â”€
// Also fetch unit-wide quizzes not yet submitted
$lesson_assessments = [];
try {
    // 1. Directly linked to this lesson
    // 2. Linked to the same module as this lesson
    // Both only quiz/cat types, published, not yet submitted by this student
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.type, a.time_limit_mins, a.total_marks, a.pass_mark
        FROM assessments a
        LEFT JOIN assessment_submissions asub
            ON asub.assessment_id = a.id AND asub.student_id = ?
        WHERE a.unit_id      = ?
          AND a.is_published  = 1
          AND a.type         IN ('quiz', 'cat')
          AND asub.id        IS NULL
          AND (
              a.lesson_id  = ?
              OR a.module_id = ?
          )
        ORDER BY a.type ASC, a.created_at ASC
    ");
    $stmt->bind_param("iiii", $student_id, $unit_id, $lesson_id, $lesson['module_id']);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $lesson_assessments[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log("lesson_assessments: " . $e->getMessage()); }

require_once __DIR__ . '/includes/lesson_bento_renderer.php';

$total_module_lessons = count($module_lessons);
$completed_count      = count($completed_lessons);
$module_progress_pct  = $total_module_lessons > 0
    ? round(($completed_count / $total_module_lessons) * 100)
    : ($is_completed ? 100 : 0);

$lesson_chart = [];
foreach ($module_lessons as $ml) {
    $lesson_chart[] = [
        'num'     => (int)$ml['lesson_number'],
        'done'    => isset($completed_lessons[$ml['id']]),
        'current' => ((int)$ml['id'] === $lesson_id),
    ];
}

include __DIR__ . '/views/lesson_dashboard_shell.php';
