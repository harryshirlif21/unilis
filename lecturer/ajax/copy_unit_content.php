<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$source_id   = isset($_POST['source_unit_id']) ? (int)$_POST['source_unit_id'] : 0;
$target_id   = isset($_POST['target_unit_id']) ? (int)$_POST['target_unit_id'] : 0;

// ── Validate ──────────────────────────────────────────────────
if (!$source_id || !$target_id) {
    echo json_encode(['success' => false, 'message' => 'Missing unit IDs']);
    exit;
}
if ($source_id === $target_id) {
    echo json_encode(['success' => false, 'message' => 'Source and target units cannot be the same']);
    exit;
}

// Verify lecturer owns BOTH units
$check = $conn->prepare("
    SELECT unit_id FROM lecturer_units
    WHERE lecturer_id = ? AND unit_id IN (?, ?)
");
$check->bind_param("iii", $lecturer_id, $source_id, $target_id);
$check->execute();
$owned = [];
$r = $check->get_result();
while ($row = $r->fetch_assoc()) $owned[] = (int)$row['unit_id'];
$check->close();

if (!in_array($source_id, $owned) || !in_array($target_id, $owned)) {
    echo json_encode(['success' => false, 'message' => 'Access denied to one or both units']);
    exit;
}

// ── Fetch source modules ──────────────────────────────────────
$src_modules = [];
$stmt = $conn->prepare("
    SELECT id, title, position
    FROM course_modules
    WHERE unit_id = ?
    ORDER BY position ASC, id ASC
");
$stmt->bind_param("i", $source_id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $src_modules[] = $row;
$stmt->close();

if (empty($src_modules)) {
    echo json_encode(['success' => false, 'message' => 'Source unit has no modules to copy']);
    exit;
}

// ── Fetch existing module titles in target (for duplicate check) ──
$existing_module_titles = [];
$stmt = $conn->prepare("SELECT LOWER(title) AS t FROM course_modules WHERE unit_id = ?");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $existing_module_titles[] = $row['t'];
$stmt->close();

// ── Get current max position in target ───────────────────────
$posStmt = $conn->prepare("SELECT COALESCE(MAX(position), 0) AS pos FROM course_modules WHERE unit_id = ?");
$posStmt->bind_param("i", $target_id);
$posStmt->execute();
$next_module_pos = (int)$posStmt->get_result()->fetch_assoc()['pos'] + 1;
$posStmt->close();

// ── Copy loop ─────────────────────────────────────────────────
$stats = ['modules_copied' => 0, 'modules_skipped' => 0, 'lessons_copied' => 0];

foreach ($src_modules as $mod) {

    // Skip if module title already exists in target
    if (in_array(strtolower($mod['title']), $existing_module_titles)) {
        $stats['modules_skipped']++;
        continue;
    }

    // Insert module into target
    $ins = $conn->prepare("
        INSERT INTO course_modules (unit_id, lecturer_id, title, position)
        VALUES (?, ?, ?, ?)
    ");
    $ins->bind_param("iisi", $target_id, $lecturer_id, $mod['title'], $next_module_pos);
    $ins->execute();
    $new_module_id = $ins->insert_id;
    $ins->close();

    $next_module_pos++;
    $stats['modules_copied']++;
    $existing_module_titles[] = strtolower($mod['title']);

    // ── Fetch source lessons for this module ──────────────────
    $lStmt = $conn->prepare("
        SELECT id, title, lesson_number, position
        FROM course_lessons
        WHERE module_id = ?
        ORDER BY position ASC, lesson_number ASC
    ");
    $lStmt->bind_param("i", $mod['id']);
    $lStmt->execute();
    $src_lessons = [];
    $lr = $lStmt->get_result();
    while ($row = $lr->fetch_assoc()) $src_lessons[] = $row;
    $lStmt->close();

    $next_lesson_num = 1;
    $next_lesson_pos = 1;

    foreach ($src_lessons as $lesson) {

        // Insert lesson into target
        $lIns = $conn->prepare("
            INSERT INTO course_lessons (module_id, unit_id, title, lesson_number, position)
            VALUES (?, ?, ?, ?, ?)
        ");
        $lIns->bind_param("iisii", $new_module_id, $target_id, $lesson['title'], $next_lesson_num, $next_lesson_pos);
        $lIns->execute();
        $new_lesson_id = $lIns->insert_id;
        $lIns->close();

        $next_lesson_num++;
        $next_lesson_pos++;
        $stats['lessons_copied']++;

        // ── Copy content blocks ───────────────────────────────
        $bStmt = $conn->prepare("
            SELECT block_type, content, position
            FROM lesson_content_blocks
            WHERE lesson_id = ?
            ORDER BY position ASC
        ");
        $bStmt->bind_param("i", $lesson['id']);
        $bStmt->execute();
        $br = $bStmt->get_result();
        while ($block = $br->fetch_assoc()) {
            $bIns = $conn->prepare("
                INSERT INTO lesson_content_blocks (lesson_id, block_type, content, position)
                VALUES (?, ?, ?, ?)
            ");
            $bIns->bind_param("issi", $new_lesson_id, $block['block_type'], $block['content'], $block['position']);
            $bIns->execute();
            $bIns->close();
        }
        $bStmt->close();
    }
}

// ── Response ──────────────────────────────────────────────────
if ($stats['modules_copied'] === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No new modules to copy — all modules from that unit already exist here.',
        'stats'   => $stats,
    ]);
    exit;
}

$msg_parts = [];
$msg_parts[] = "{$stats['modules_copied']} module" . ($stats['modules_copied'] !== 1 ? 's' : '') . " copied";
if ($stats['lessons_copied'] > 0)
    $msg_parts[] = "{$stats['lessons_copied']} lesson" . ($stats['lessons_copied'] !== 1 ? 's' : '') . " added";
if ($stats['modules_skipped'] > 0)
    $msg_parts[] = "{$stats['modules_skipped']} skipped (already exist)";

echo json_encode([
    'success' => true,
    'message' => implode(', ', $msg_parts),
    'stats'   => $stats,
]);