<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/ajax/short_course_access.php';

if (!shortCourseIsAuthor()) {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

$unit_id   = intval($_GET['unit_id']   ?? 0);
$course_id = intval($_GET['course_id'] ?? 0);
$lesson_id = intval($_GET['lesson_id'] ?? 0);
$mode      = $course_id > 0 ? 'short_course' : 'iclm';

// ── Short course mode: verify access, load course info ────────────────────
$course_info = null;
$can_edit = false;
$has_access = false;

if ($mode === 'short_course') {
    // Check if course author (created_by_lecturer_id — author_id doesn't exist)
    $isAuthor = false;
    try {
        $checkAuthor = $conn->prepare("SELECT id FROM public_courses WHERE id = ? AND created_by_lecturer_id = ?");
        $checkAuthor->bind_param("ii", $course_id, $lecturer_id);
        $checkAuthor->execute();
        if ($checkAuthor->get_result()->fetch_row()) {
            $isAuthor = true;
            $can_edit = true;
            $has_access = true;
        }
        $checkAuthor->close();
    } catch (Exception $e) {
        error_log("Error checking author: " . $e->getMessage());
    }

    // Global admins and department admins can manage any (department) short course.
    if (!$isAuthor) {
        try {
            $roleAllowed = shortCourseCanManage($conn, $course_id);
            if ($roleAllowed) {
                $isAuthor = true;      // treat like the author for full edit access
                $can_edit = true;
                $has_access = true;
            }
        } catch (Exception $e) {
            error_log("Error checking role access: " . $e->getMessage());
        }
    }

    // If not author, check module/lesson permissions
    if (!$isAuthor) {
        // Check if assigned as course tutor
        try {
            $checkTutor = $conn->prepare("SELECT id FROM short_course_tutors WHERE short_course_id = ? AND lecturer_id = ?");
            $checkTutor->bind_param("ii", $course_id, $lecturer_id);
            $checkTutor->execute();
            if ($checkTutor->get_result()->fetch_row()) {
                // Course-level tutor - can edit everything
                $can_edit = true;
                $has_access = true;
            }
            $checkTutor->close();
        } catch (Exception $e) {
            error_log("Error checking tutor assignment: " . $e->getMessage());
        }

        // If not course-level tutor, check module permissions
        if (!$can_edit && $lesson_id > 0) {
            // Get lesson's module
            try {
                $getModule = $conn->prepare("SELECT module_id FROM public_course_lessons WHERE id = ?");
                $getModule->bind_param("i", $lesson_id);
                $getModule->execute();
                $modRow = $getModule->get_result()->fetch_assoc();
                $getModule->close();

                if ($modRow) {
                    $moduleId = $modRow['module_id'];
                    
                    // Check module permission
                    try {
                        $checkModPerm = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
                        if ($checkModPerm && $checkModPerm->num_rows > 0) {
                            $modPerm = $conn->prepare("SELECT can_edit FROM tutor_module_permissions WHERE tutor_id = ? AND module_id = ?");
                            $modPerm->bind_param("ii", $lecturer_id, $moduleId);
                            $modPerm->execute();
                            $permRow = $modPerm->get_result()->fetch_assoc();
                            $modPerm->close();
                            
                            if ($permRow) {
                                $has_access = true;
                                if ($permRow['can_edit']) {
                                    $can_edit = true;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error checking module permission: " . $e->getMessage());
                    }

                    // Check lesson permission
                    try {
                        $checkLessonPerm = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
                        if ($checkLessonPerm && $checkLessonPerm->num_rows > 0) {
                            $lessonPerm = $conn->prepare("SELECT can_edit FROM tutor_lesson_permissions WHERE tutor_id = ? AND lesson_id = ?");
                            $lessonPerm->bind_param("ii", $lecturer_id, $lesson_id);
                            $lessonPerm->execute();
                            $permRow = $lessonPerm->get_result()->fetch_assoc();
                            $lessonPerm->close();
                            
                            if ($permRow) {
                                $has_access = true;
                                if ($permRow['can_edit']) {
                                    $can_edit = true;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error checking lesson permission: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log("Error getting lesson module: " . $e->getMessage());
            }
        }
    }

    // Load course info if has any access (view or edit)
    if ($isAuthor || $has_access) {
        try {
            $stmt = $conn->prepare('SELECT * FROM public_courses WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $course_id);
            $stmt->execute();
            $course_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error loading course info: " . $e->getMessage());
        }
    }

    if (!$course_info) {
        header("Location: catalogue.php");
        exit;
    }
}

// ── ICLM mode: fetch units for the dropdown ──────────────────────────────
$units = [];
if ($mode === 'iclm') {
    try {
        $stmt = $conn->prepare("
            SELECT u.id, u.name
            FROM units u
            JOIN lecturer_units lu ON u.id = lu.unit_id
            WHERE lu.lecturer_id = ?
            ORDER BY u.name ASC
        ");
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $units[] = $row;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("lesson_editor units: " . $e->getMessage());
    }
}

// ── Fetch modules + lessons ──────────────────────────────────────────────
$modules_with_lessons = [];
if ($mode === 'short_course' && $course_id) {
    // Short course: load from public_course_modules / public_course_lessons
    try {
        $stmt = $conn->prepare("
            SELECT id, title, position FROM public_course_modules
            WHERE course_id = ?
            ORDER BY position ASC, id ASC
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['lessons'] = [];
            $modules_with_lessons[$row['id']] = $row;
        }
        $stmt->close();

        if (!empty($modules_with_lessons)) {
            $mids = array_keys($modules_with_lessons);
            $ph   = implode(',', array_fill(0, count($mids), '?'));
            $types = str_repeat('i', count($mids));
            $stmt = $conn->prepare("
                SELECT id, module_id, title, position
                FROM public_course_lessons
                WHERE module_id IN ($ph)
                ORDER BY position ASC, id ASC
            ");
            $stmt->bind_param($types, ...$mids);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (isset($modules_with_lessons[$row['module_id']])) {
                    $modules_with_lessons[$row['module_id']]['lessons'][] = $row;
                }
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("lesson_editor short course modules: " . $e->getMessage());
    }
} elseif ($unit_id) {
    // ICLM: load from course_modules / course_lessons
    try {
        $stmt = $conn->prepare("
            SELECT id, title, position FROM course_modules
            WHERE unit_id = ? AND lecturer_id = ?
            ORDER BY position ASC, id ASC
        ");
        $stmt->bind_param("ii", $unit_id, $lecturer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['lessons'] = [];
            $modules_with_lessons[$row['id']] = $row;
        }
        $stmt->close();

        if (!empty($modules_with_lessons)) {
            $mids = array_keys($modules_with_lessons);
            $ph   = implode(',', array_fill(0, count($mids), '?'));
            $types = str_repeat('i', count($mids));
            $params = array_merge([$unit_id], $mids);
            $stmt = $conn->prepare("
                SELECT id, module_id, title, lesson_number, position
                FROM course_lessons
                WHERE unit_id = ? AND module_id IN ($ph)
                ORDER BY module_id ASC, position ASC
            ");
            $stmt->bind_param('i'.$types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (isset($modules_with_lessons[$row['module_id']])) {
                    $modules_with_lessons[$row['module_id']]['lessons'][] = $row;
                }
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log("lesson_editor modules: " . $e->getMessage());
    }
}

// ── Fetch lesson info + content blocks ───────────────────────────────────
$current_lesson = null;
$content_blocks = [];
if ($lesson_id) {
    try {
        if ($mode === 'short_course') {
            // Access to the parent course was already verified above.
            $stmt = $conn->prepare("
                SELECT l.id, l.title, l.module_id, l.content_html, l.video_url, l.attachment_path,
                       m.title AS module_title, m.course_id
                FROM public_course_lessons l
                JOIN public_course_modules m ON l.module_id = m.id
                WHERE l.id = ? AND m.course_id = ?
            ");
            $stmt->bind_param("ii", $lesson_id, $course_id);
            $stmt->execute();
            $current_lesson = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // For short courses, content is stored in public_course_lessons.content_html
            // Convert it to a single block. File blocks retain their JSON so a
            // PowerPoint upload can be reopened and edited rather than turning
            // into raw text on the next visit.
            if ($current_lesson && !empty($current_lesson['content_html'])) {
                $storedContent = $current_lesson['content_html'];
                $storedType = 'text';
                $storedData = json_decode($storedContent, true);
                if (is_array($storedData) && !empty($storedData['src'])) {
                    $storedPath = preg_replace('#^(?:\.\./)+#', '', (string)$storedData['src']);
                    if (preg_match('#^uploads/course_presentations/#', $storedPath)) {
                        $storedType = 'ppt';
                    } elseif (preg_match('#^uploads/course_pdfs/#', $storedPath)) {
                        $storedType = 'pdf';
                    } elseif (preg_match('#^uploads/course_images/#', $storedPath)) {
                        $storedType = 'image';
                    } elseif (preg_match('#^uploads/course_audio/#', $storedPath)) {
                        $storedType = 'audio';
                    } elseif (preg_match('#^uploads/course_diagrams/#', $storedPath)) {
                        $storedType = 'diagram';
                    } elseif (preg_match('#^uploads/course_videos/#', $storedPath)) {
                        $storedType = 'video';
                    }
                }
                $content_blocks[] = [
                    'id' => 0,
                    'block_type' => $storedType,
                    'content' => $storedContent,
                    'position' => 0,
                ];
            }
        } else {
            // ICLM: ownership verified via lecturer_units
            $stmt = $conn->prepare("
                SELECT cl.id, cl.title, cl.lesson_number, cl.module_id, cl.unit_id,
                       cm.title AS module_title
                FROM course_lessons cl
                JOIN course_modules cm ON cl.module_id = cm.id
                JOIN lecturer_units lu ON lu.unit_id = cl.unit_id
                WHERE cl.id = ? AND cl.unit_id = ? AND lu.lecturer_id = ?
            ");
            $stmt->bind_param("iii", $lesson_id, $unit_id, $lecturer_id);
            $stmt->execute();
            $current_lesson = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($current_lesson) {
                $stmt = $conn->prepare("
                    SELECT id, block_type, content, position
                    FROM lesson_content_blocks
                    WHERE lesson_id = ?
                    ORDER BY position ASC, id ASC
                ");
                $stmt->bind_param("i", $lesson_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) $content_blocks[] = $row;
                $stmt->close();
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("lesson_editor blocks: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lesson Editor — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg:         #0b0d11;
    --surface:    #13161e;
    --surface2:   #1a1f2e;
    --surface3:   #222840;
    --border:     #282e45;
    --accent:     #6c8ef5;
    --accent2:    #3ecf8e;
    --accent3:    #f5a623;
    --accent4:    #e879f9;
    --danger:     #f56565;
    --text:       #e2e8f0;
    --text-muted: #718096;
    --text-dim:   #404968;
    --shadow:     0 8px 32px rgba(0,0,0,0.5);
    --radius:     12px;
    --radius-sm:  7px;
    --tr:         0.15s ease;

    /* Block type colours */
    --c-text:    #6c8ef5;
    --c-image:   #3ecf8e;
    --c-video:   #f56565;
    --c-audio:   #f5a623;
    --c-diagram: #e879f9;
    --c-pdf:     #f97316;
    --c-ppt:     #d24726;
    --c-quiz:    #22c1a0;
    --c-cat:     #e879f9;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

/* ── TOPBAR ── */
.topbar {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 0 28px; height: 58px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
}
.topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1rem; color: var(--accent); letter-spacing: 0.04em; }
.topbar-brand span { color: var(--text-muted); font-weight: 400; font-size: 0.8rem; margin-left: 8px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.btn-nav { background: var(--surface3); border: 1px solid var(--border); color: var(--text-muted); padding: 6px 13px; border-radius: var(--radius-sm); font-size: 0.79rem; cursor: pointer; text-decoration: none; transition: var(--tr); font-family: 'DM Sans', sans-serif; }
.btn-nav:hover { color: var(--text); background: var(--surface2); }

/* ── LAYOUT ── */
.layout { display: flex; height: calc(100vh - 58px); }

/* ── LEFT PANEL (lesson tree) ── */
.left-panel {
    width: 280px; min-width: 280px;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow: hidden;
}
.panel-header {
    padding: 16px 18px 12px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.panel-header label { font-family: 'Syne', sans-serif; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-dim); display: block; margin-bottom: 8px; }
.styled-select { width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text); padding: 9px 12px; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.84rem; outline: none; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%23718096' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; padding-right: 28px; }
.styled-select:focus { border-color: var(--accent); }

.lesson-tree { flex: 1; overflow-y: auto; padding: 10px 10px 20px; }
.module-group { margin-bottom: 6px; }
.module-label {
    font-family: 'Syne', sans-serif; font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-dim);
    padding: 8px 10px 4px; display: flex; align-items: center; gap: 6px; cursor: pointer;
}
.module-label i { transition: transform var(--tr); }
.module-label.collapsed i { transform: rotate(-90deg); }
.module-label:hover { color: var(--text-muted); }

.lesson-link {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 10px; border-radius: var(--radius-sm);
    text-decoration: none; color: var(--text-muted); font-size: 0.83rem;
    transition: var(--tr); border-left: 2px solid transparent; margin: 1px 0;
    cursor: pointer;
}
.lesson-link:hover { background: var(--surface2); color: var(--text); border-left-color: var(--border); }
.lesson-link.active { background: rgba(108,142,245,0.12); color: var(--accent); border-left-color: var(--accent); font-weight: 500; }
.lesson-num-badge { font-family: 'JetBrains Mono', monospace; font-size: 0.65rem; color: var(--text-dim); min-width: 22px; }

/* ── EDITOR PANEL ── */
.editor-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

.editor-toolbar {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 10px 24px; display: flex; align-items: center; gap: 10px;
    flex-wrap: wrap; flex-shrink: 0;
}
.toolbar-label { font-family: 'Syne', sans-serif; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-dim); margin-right: 4px; }
.block-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 13px; border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif; font-size: 0.8rem; font-weight: 500;
    cursor: pointer; border: 1px solid var(--border); background: var(--surface2);
    color: var(--text-muted); transition: var(--tr);
}
.block-btn:hover { transform: translateY(-1px); }
.block-btn[data-type="text"]    { border-color: rgba(108,142,245,0.4); }
.block-btn[data-type="text"]:hover    { background: rgba(108,142,245,0.12); color: var(--c-text); border-color: var(--c-text); }
.block-btn[data-type="image"]   { border-color: rgba(62,207,142,0.4); }
.block-btn[data-type="image"]:hover   { background: rgba(62,207,142,0.12); color: var(--c-image); border-color: var(--c-image); }
.block-btn[data-type="video"]   { border-color: rgba(245,101,101,0.4); }
.block-btn[data-type="video"]:hover   { background: rgba(245,101,101,0.12); color: var(--c-video); border-color: var(--c-video); }
.block-btn[data-type="audio"]   { border-color: rgba(245,166,35,0.4); }
.block-btn[data-type="audio"]:hover   { background: rgba(245,166,35,0.12); color: var(--c-audio); border-color: var(--c-audio); }
.block-btn[data-type="diagram"] { border-color: rgba(232,121,249,0.4); }
.block-btn[data-type="diagram"]:hover { background: rgba(232,121,249,0.12); color: var(--c-diagram); border-color: var(--c-diagram); }
.block-btn[data-type="pdf"]     { border-color: rgba(249,115,22,0.4); }
.block-btn[data-type="pdf"]:hover     { background: rgba(249,115,22,0.12); color: var(--c-pdf); border-color: var(--c-pdf); }
.block-btn[data-type="ppt"]     { border-color: rgba(210,71,38,0.4); }
.block-btn[data-type="ppt"]:hover     { background: rgba(210,71,38,0.12); color: var(--c-ppt); border-color: var(--c-ppt); }
.block-btn[data-type="quiz"]    { border-color: rgba(34,193,160,0.4); }
.block-btn[data-type="quiz"]:hover    { background: rgba(34,193,160,0.12); color: var(--c-quiz); border-color: var(--c-quiz); }
.block-btn[data-type="cat"]     { border-color: rgba(232,121,249,0.4); }
.block-btn[data-type="cat"]:hover     { background: rgba(232,121,249,0.12); color: var(--c-cat); border-color: var(--c-cat); }

/* ── VIDEO BLOCK ───────────────────────────────────────────── */
.vid-tabs { display:flex; gap:0; margin-bottom:12px; border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; }
.vid-tab  { flex:1; padding:7px 14px; font-size:.8rem; font-weight:600; font-family:'DM Sans',sans-serif;
            background:var(--surface2); color:var(--text-muted); border:none; cursor:pointer;
            transition:var(--tr); letter-spacing:.03em; }
.vid-tab.active { background:var(--c-video); color:#fff; }
.vid-tab-panel  { display:none; } .vid-tab-panel.active { display:block; }
.url-input  { width:100%; background:var(--surface2); border:1px solid var(--border); color:var(--text);
              padding:9px 13px; border-radius:var(--radius-sm); font-family:'DM Sans',sans-serif;
              font-size:.87rem; outline:none; transition:border-color var(--tr); }
.url-input:focus { border-color:var(--c-video); }
.video-preview { margin-top:12px; border-radius:var(--radius-sm); overflow:hidden; background:#000; aspect-ratio:16/9; display:none; }
.video-preview iframe,.video-preview video { width:100%; height:100%; border:none; display:block; }
.vid-upload-area { border:2px dashed var(--border); border-radius:var(--radius-sm); padding:24px;
                   text-align:center; cursor:pointer; transition:var(--tr); color:var(--text-muted); }
.vid-upload-area:hover { border-color:var(--c-video); color:var(--c-video); background:rgba(245,101,101,.04); }
.vid-upload-area input { display:none; }
.vid-upload-area i { font-size:1.8rem; margin-bottom:8px; display:block; }
.vid-progress { margin-top:8px; height:4px; background:var(--border); border-radius:999px; overflow:hidden; display:none; }
.vid-progress-fill { height:100%; background:var(--c-video); border-radius:999px; transition:width .2s; width:0; }
.vid-player { width:100%; margin-top:10px; border-radius:var(--radius-sm); overflow:hidden;
              background:#000; aspect-ratio:16/9; display:none; }
.vid-player video { width:100%; height:100%; display:block; }

/* ── PDF BLOCK ─────────────────────────────────────────────── */
.pdf-upload-area { border:2px dashed var(--border); border-radius:var(--radius-sm); padding:24px;
                   text-align:center; cursor:pointer; transition:var(--tr); color:var(--text-muted); }
.pdf-upload-area:hover { border-color:var(--c-pdf); color:var(--c-pdf); background:rgba(249,115,22,.04); }
.pdf-upload-area input { display:none; }
.pdf-upload-area i { font-size:1.8rem; margin-bottom:8px; display:block; }
.pdf-preview-pane { margin-top:12px; border:1px solid var(--border); border-radius:var(--radius-sm);
                    overflow:hidden; display:none; background:var(--surface2); }
.pdf-preview-bar  { display:flex; align-items:center; gap:10px; padding:9px 14px;
                    background:var(--surface3); border-bottom:1px solid var(--border); }
.pdf-preview-bar .pdf-name { flex:1; font-size:.83rem; color:var(--text-muted); white-space:nowrap;
                              overflow:hidden; text-overflow:ellipsis; }
.pdf-preview-bar a { font-size:.78rem; padding:4px 10px; background:transparent; border:1px solid var(--border);
                     color:var(--text-muted); border-radius:var(--radius-sm); text-decoration:none;
                     transition:var(--tr); display:inline-flex; align-items:center; gap:5px; }
.pdf-preview-bar a:hover { border-color:var(--c-pdf); color:var(--c-pdf); }
.pdf-preview-pane iframe { width:100%; height:520px; border:none; display:block; }
.ppt-preview-pane { margin-top:12px; border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; display:none; background:var(--surface2); }
.ppt-preview-pane iframe { width:100%; height:440px; border:none; display:block; background:#fff; }

.toolbar-sep { width: 1px; height: 24px; background: var(--border); margin: 0 4px; }

/* ── BLOCKS CANVAS ── */
.blocks-canvas { flex: 1; overflow-y: auto; padding: 24px 28px; }
.canvas-inner  { max-width: 820px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }

/* ── BLOCK CARD ── */
.block-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: border-color var(--tr), box-shadow var(--tr);
    animation: fadeIn 0.2s ease;
    position: relative;
}
.block-card:hover   { border-color: var(--surface3); }
.block-card.dragging { opacity: 0.3; }
.block-card.drag-over { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(108,142,245,0.2); }

.block-header {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px 10px 12px;
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    cursor: grab; user-select: none;
}
.block-header:active { cursor: grabbing; }

.block-type-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.dot-text    { background: var(--c-text); }
.dot-image   { background: var(--c-image); }
.dot-video   { background: var(--c-video); }
.dot-audio   { background: var(--c-audio); }
.dot-diagram { background: var(--c-diagram); }
.dot-pdf     { background: var(--c-pdf); }
.dot-ppt     { background: var(--c-ppt); }
.dot-quiz    { background: var(--c-quiz); }
.dot-cat     { background: var(--c-cat); }

.block-type-label {
    font-family: 'Syne', sans-serif; font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
}
.label-text    { color: var(--c-text); }
.label-image   { color: var(--c-image); }
.label-video   { color: var(--c-video); }
.label-audio   { color: var(--c-audio); }
.label-diagram { color: var(--c-diagram); }
.label-pdf     { color: var(--c-pdf); }
.label-ppt     { color: var(--c-ppt); }
.label-quiz    { color: var(--c-quiz); }
.label-cat     { color: var(--c-cat); }

.block-drag-handle { display: flex; gap: 3px; margin-left: auto; opacity: 0.4; }
.block-drag-handle span { display: block; width: 3px; height: 3px; background: var(--text-muted); border-radius: 50%; }
.block-card:hover .block-drag-handle { opacity: 0.8; }

.block-actions { display: flex; gap: 4px; }

.btn-icon {
    background: none; border: 1px solid transparent; padding: 4px 7px;
    border-radius: var(--radius-sm); cursor: pointer; font-size: 0.78rem;
    color: var(--text-dim); transition: var(--tr);
}
.btn-icon:hover { background: var(--surface3); color: var(--text); border-color: var(--border); }
.btn-icon.danger:hover { color: var(--danger); border-color: rgba(245,101,101,0.3); }
.btn-icon.save:hover   { color: var(--accent2); border-color: rgba(62,207,142,0.3); }

/* ── BLOCK BODY ── */
.block-body { padding: 16px 18px; }

/* Text block */
.text-editor {
    width: 100%; background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); padding: 12px 14px; border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem; line-height: 1.65;
    resize: vertical; min-height: 120px; outline: none; transition: border-color var(--tr);
}
.text-editor:focus { border-color: var(--c-text); }

/* Rich text toolbar */
.rich-toolbar {
    display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px;
    padding: 6px 8px; background: var(--surface3);
    border: 1px solid var(--border); border-radius: var(--radius-sm);
}
.rich-btn {
    background: none; border: none; color: var(--text-muted);
    padding: 3px 7px; border-radius: 4px; cursor: pointer;
    font-size: 0.8rem; transition: var(--tr);
}
.rich-btn:hover { background: var(--surface2); color: var(--text); }
.rich-content {
    min-height: 100px; background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); padding: 12px 14px; border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem; line-height: 1.65;
    outline: none; transition: border-color var(--tr);
}
.rich-content:focus { border-color: var(--c-text); }
.rich-content h1,.rich-content h2,.rich-content h3 { color: var(--text); font-family: 'Syne', sans-serif; margin: 8px 0 4px; }
.rich-content ul,.rich-content ol { padding-left: 20px; }
.rich-content code { background: var(--surface3); padding: 1px 5px; border-radius: 3px; font-family: 'JetBrains Mono', monospace; font-size: 0.85em; color: var(--accent3); }
.rich-content blockquote { border-left: 3px solid var(--accent); padding-left: 12px; color: var(--text-muted); font-style: italic; }

/* Image block */
.image-upload-area {
    border: 2px dashed var(--border); border-radius: var(--radius-sm);
    padding: 24px; text-align: center; cursor: pointer;
    transition: var(--tr); color: var(--text-muted);
}
.image-upload-area:hover { border-color: var(--c-image); color: var(--c-image); background: rgba(62,207,142,0.04); }
.image-upload-area input { display: none; }
.image-upload-area i { font-size: 1.5rem; margin-bottom: 8px; display: block; }
.image-preview { max-width: 100%; max-height: 320px; border-radius: var(--radius-sm); display: block; margin: 0 auto; }
.image-caption { width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text-muted); padding: 7px 12px; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.82rem; outline: none; margin-top: 8px; }
.image-caption:focus { border-color: var(--c-image); }

/* Video block */
.url-input-wrap { display: flex; gap: 8px; align-items: flex-start; flex-direction: column; }
.url-input { width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text); padding: 10px 14px; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.87rem; outline: none; transition: border-color var(--tr); }
.url-input:focus { border-color: var(--c-video); }
.video-preview { margin-top: 12px; border-radius: var(--radius-sm); overflow: hidden; background: #000; aspect-ratio: 16/9; display: none; }
.video-preview iframe { width: 100%; height: 100%; border: none; }

/* Audio block */
.audio-upload-area { border: 2px dashed var(--border); border-radius: var(--radius-sm); padding: 20px; text-align: center; cursor: pointer; transition: var(--tr); color: var(--text-muted); }
.audio-upload-area:hover { border-color: var(--c-audio); color: var(--c-audio); background: rgba(245,166,35,0.04); }
.audio-upload-area input { display: none; }
.audio-upload-area i { font-size: 1.4rem; margin-bottom: 6px; display: block; }
.audio-player { width: 100%; margin-top: 10px; accent-color: var(--c-audio); }

/* Diagram block */
.diagram-upload-area { border: 2px dashed var(--border); border-radius: var(--radius-sm); padding: 24px; text-align: center; cursor: pointer; transition: var(--tr); color: var(--text-muted); }
.diagram-upload-area:hover { border-color: var(--c-diagram); color: var(--c-diagram); background: rgba(232,121,249,0.04); }
.diagram-upload-area input { display: none; }
.diagram-upload-area i { font-size: 1.5rem; margin-bottom: 8px; display: block; }
.diagram-preview { max-width: 100%; max-height: 360px; border-radius: var(--radius-sm); display: block; margin: 0 auto; }
.diagram-caption { width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text-muted); padding: 7px 12px; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.82rem; outline: none; margin-top: 8px; }

/* Save indicator */
.save-status { font-size: 0.75rem; padding: 3px 9px; border-radius: 999px; font-weight: 500; }
.save-status.saved   { background: rgba(62,207,142,0.12); color: var(--accent2); border: 1px solid rgba(62,207,142,0.25); }
.save-status.saving  { background: rgba(108,142,245,0.12); color: var(--accent);  border: 1px solid rgba(108,142,245,0.25); }
.save-status.unsaved { background: rgba(245,166,35,0.12);  color: var(--accent3); border: 1px solid rgba(245,166,35,0.25); }

/* Empty / placeholder */
.editor-placeholder {
    flex: 1; display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 12px; color: var(--text-dim);
}
.editor-placeholder i  { font-size: 2.8rem; opacity: 0.3; }
.editor-placeholder h3 { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; color: var(--text-muted); }
.editor-placeholder p  { font-size: 0.83rem; max-width: 280px; text-align: center; }

/* Lesson title bar */
.lesson-titlebar {
    background: var(--surface2); border-bottom: 1px solid var(--border);
    padding: 12px 24px; display: flex; align-items: center; gap: 14px; flex-shrink: 0;
}
.lesson-num-pill { font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; background: rgba(108,142,245,0.12); color: var(--accent); border: 1px solid rgba(108,142,245,0.25); padding: 3px 9px; border-radius: 999px; white-space: nowrap; }
.lesson-title-text { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; color: var(--text); flex: 1; }
.lesson-module-tag { font-size: 0.75rem; color: var(--text-dim); background: var(--surface3); padding: 3px 9px; border-radius: 999px; }
.block-count-tag { font-size: 0.73rem; color: var(--text-dim); }

/* Animations */
@keyframes fadeIn { from { opacity:0; transform: translateY(6px); } to { opacity:1; transform: translateY(0); } }

/* Toast */
#toast { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast-item { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 16px; font-size: 0.83rem; color: var(--text); box-shadow: var(--shadow); display: flex; align-items: center; gap: 9px; max-width: 340px; pointer-events: all; cursor: pointer; }
.toast-item.success { border-left: 3px solid var(--accent2); animation: toastIn 0.22s ease, toastOut 0.22s ease 3.8s forwards; }
.toast-item.error   { border-left: 3px solid var(--danger);  animation: toastIn 0.22s ease, toastOut 0.22s ease 7s forwards; }
.toast-item.info    { border-left: 3px solid var(--accent);   animation: toastIn 0.22s ease, toastOut 0.22s ease 3.8s forwards; }
@keyframes toastIn  { from { opacity:0; transform:translateX(16px);} to {opacity:1;transform:translateX(0);} }
@keyframes toastOut { from { opacity:1; } to { opacity:0; transform:translateX(16px); } }

.spinner { width:14px; height:14px; border:2px solid var(--border); border-top-color:var(--accent); border-radius:50%; animation:spin 0.6s linear infinite; display:inline-block; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Scrollbar */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }
/* ---- Topics & subtopics modal ---- */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.62); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
.modal-overlay.open { display: flex; }
.modal { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); width: 640px; max-width: 100%; max-height: 90vh; overflow: auto; padding: 26px; box-shadow: var(--shadow); }
.modal h3 { margin: 0 0 16px; font-size: 1.05rem; color: var(--text); display: flex; align-items: center; gap: 8px; }
.modal .form-group { margin-bottom: 12px; }
.modal label { display: block; font-size: .76rem; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
.form-input { width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: var(--radius-sm); padding: 9px 11px; font-size: .88rem; font-family: inherit; }
.form-input:focus { border-color: var(--accent); outline: none; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
.btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface2); color: var(--text); cursor: pointer; font-size: .85rem; font-family: inherit; }
.btn:hover { border-color: var(--accent); }
.btn-primary { background: var(--accent); border-color: var(--accent); color: #0b0d11; font-weight: 600; }
.btn-sm { padding: 5px 9px; font-size: .78rem; }
.btn-icon { padding: 5px 8px; }
.topic-item { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 8px 12px; margin-bottom: 8px; background: var(--surface2); }
.topic-item .trow { display: flex; align-items: center; gap: 8px; }
.topic-item .ttl { flex: 1; font-weight: 600; color: var(--text); }
.subtopic-row { margin: 6px 0 0 20px; padding: 6px 10px; background: var(--surface3); border-radius: var(--radius-sm); display: flex; align-items: center; gap: 8px; }
.subtopic-row .ttl { flex: 1; color: var(--text); }
.modal-empty { color: var(--text-muted); font-size: .85rem; font-style: italic; padding: 6px 0; }
</style>
</head>
<body>

<header class="topbar">
    <div class="topbar-brand">UNILIS <span>Lesson Editor</span></div>
    <div class="topbar-right">
        <?php if ($mode === 'short_course' && $course_id): ?>
            <a href="course_builder.php?course_id=<?= $course_id ?>" class="btn-nav">
                <i class="fas fa-sitemap"></i> Course Builder
            </a>
        <?php elseif ($unit_id): ?>
            <a href="course_builder.php?unit_id=<?= $unit_id ?>" class="btn-nav">
                <i class="fas fa-sitemap"></i> Course Builder
            </a>
        <?php endif; ?>
        <?php if ($mode === 'short_course'): ?>
            <a href="catalogue.php" class="btn-nav"><i class="fas fa-globe"></i> Short Courses</a>
        <?php else: ?>
            <a href="assessment_builder.php<?= $unit_id ? "?unit_id=$unit_id" : '' ?>" class="btn-nav">
                <i class="fas fa-tasks"></i> Assessments
            </a>
        <?php endif; ?>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <!-- LEFT PANEL -->
    <aside class="left-panel">
        <div class="panel-header">
            <?php if ($mode === 'short_course' && $course_info): ?>
                <label><i class="fas fa-graduation-cap"></i> &nbsp;<?= htmlspecialchars($course_info['title']) ?></label>
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px">
                    Short Course · <?= count($modules_with_lessons) ?> module<?= count($modules_with_lessons) !== 1 ? 's' : '' ?>
                </div>
            <?php else: ?>
                <label><i class="fas fa-book"></i> &nbsp;Unit</label>
                <select class="styled-select" id="unit-select" onchange="switchUnit(this.value)">
                    <option value="">— select unit —</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="lesson-tree" id="lesson-tree">
            <?php if (empty($modules_with_lessons)): ?>
                <div style="padding:20px;color:var(--text-dim);font-size:0.82rem;text-align:center">
                    <?php if ($mode === 'short_course'): ?>
                        No modules yet. <a href="course_builder.php?course_id=<?= $course_id ?>" style="color:var(--accent)">Add modules first</a>.
                    <?php elseif ($unit_id): ?>
                        No modules yet. <a href="course_builder.php?unit_id=<?= $unit_id ?>" style="color:var(--accent)">Add modules first</a>.
                    <?php else: ?>
                        Select a unit to see lessons.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($modules_with_lessons as $mod): ?>
                    <div class="module-group">
                        <div class="module-label" onclick="toggleModuleGroup(this)">
                            <i class="fas fa-chevron-down"></i>
                            <?= htmlspecialchars($mod['title']) ?>
                        </div>
                        <div class="module-lessons">
                            <?php if (empty($mod['lessons'])): ?>
                                <div style="padding:4px 10px 6px 28px;font-size:0.77rem;color:var(--text-dim)">No lessons yet</div>
                            <?php else: ?>
                                <?php foreach ($mod['lessons'] as $lesson): ?>
                                    <?php
                                    $lessonLink = $mode === 'short_course'
                                        ? 'lesson_editor.php?course_id=' . $course_id . '&lesson_id=' . $lesson['id']
                                        : 'lesson_editor.php?unit_id=' . $unit_id . '&lesson_id=' . $lesson['id'];
                                    ?>
                                    <a class="lesson-link <?= $lesson_id == $lesson['id'] ? 'active' : '' ?>"
                                       href="<?= $lessonLink ?>">
                                        <span class="lesson-num-badge">L<?= $lesson['lesson_number'] ?? ($lesson['position'] + 1) ?></span>
                                        <?= htmlspecialchars($lesson['title']) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- EDITOR PANEL -->
    <div class="editor-panel" id="editor-panel">

        <?php if (!$current_lesson): ?>
            <div class="editor-placeholder">
                <i class="fas fa-pen-nib"></i>
                <h3>No Lesson Selected</h3>
                <p>Pick a lesson from the left panel, or go to Course Builder to create one.</p>
                <?php if ($unit_id): ?>
                    <a href="course_builder.php?unit_id=<?= $unit_id ?>" style="color:var(--accent);font-size:0.83rem;margin-top:8px">
                        <i class="fas fa-arrow-left"></i> Go to Course Builder
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- Lesson title bar -->
            <div class="lesson-titlebar">
                <span class="lesson-num-pill">Lesson <?= $current_lesson['lesson_number'] ?></span>
                <span class="lesson-title-text" id="lesson-title-display" ondblclick="enableTitleEdit()" style="cursor:pointer;"><?= htmlspecialchars($current_lesson['title']) ?></span>
                <span class="lesson-module-tag"><?= htmlspecialchars($current_lesson['module_title']) ?></span>
                <span class="block-count-tag" id="block-count"><?= count($content_blocks) ?> block<?= count($content_blocks) !== 1 ? 's' : '' ?></span>
                <?php if ($can_edit): ?>
                    <button onclick="enableTitleEdit()" style="margin-left:auto; background:none; border:none; color:var(--text-muted); cursor:pointer; padding:4px 8px; font-size:0.8rem;">
                        <i class="fas fa-pen"></i> Edit Title
                    </button>
                <?php endif; ?>
                <?php if ($can_edit && isset($current_lesson['id']) && (int)$current_lesson['id'] > 0): ?>
                    <a href="lesson_topics.php?lesson_id=<?= (int)$current_lesson['id'] ?>" style="background:var(--surface2); border:1px solid var(--border); color:var(--text-muted); cursor:pointer; padding:6px 10px; font-size:0.8rem; border-radius:var(--radius-sm); white-space:nowrap; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fas fa-list-ul"></i> Topics &amp; subtopics
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Title edit form (hidden by default) -->
            <div id="title-edit-form" style="display:none; padding:12px 24px; background:var(--surface2); border-bottom:1px solid var(--border);">
                <input type="text" id="lesson-title-input" value="<?= htmlspecialchars($current_lesson['title']) ?>" 
                       style="flex:1; padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); font-size:0.9rem; background:var(--surface); color:var(--text); outline:none;">
                <button onclick="saveLessonTitle()" style="padding:8px 16px; background:var(--accent); color:#fff; border:none; border-radius:var(--radius-sm); cursor:pointer; font-size:0.85rem; margin-left:8px;">
                    <i class="fas fa-save"></i> Save
                </button>
                <button onclick="cancelTitleEdit()" style="padding:8px 16px; background:var(--surface2); color:var(--text); border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; font-size:0.85rem; margin-left:8px;">
                    Cancel
                </button>
            </div>

            <!-- Add block toolbar -->
            <div class="editor-toolbar" id="editor-toolbar">
                <span class="toolbar-label">Add Block</span>
                <button class="block-btn" data-type="text"    onclick="addBlock('text')" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-align-left" style="color:var(--c-text)"></i> Text
                </button>
                <button class="block-btn" data-type="image"   onclick="addBlock('image')" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-image"      style="color:var(--c-image)"></i> Image
                </button>
                <button class="block-btn" data-type="video"   onclick="addBlock('video')" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-video"      style="color:var(--c-video)"></i> Video
                </button>
                <button class="block-btn" data-type="audio"   onclick="addBlock('audio')" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-music"      style="color:var(--c-audio)"></i> Audio
                </button>
                <button class="block-btn" data-type="diagram" onclick="addBlock('diagram')" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-diagram-project" style="color:var(--c-diagram)"></i> Diagram
                </button>
                <button class="block-btn" data-type="pdf" onclick="addBlock('pdf')" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-file-pdf" style="color:var(--c-pdf)"></i> PDF
                </button>
                <button class="block-btn" data-type="ppt" onclick="addBlock('ppt')" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-file-powerpoint" style="color:var(--c-ppt)"></i> PowerPoint
                </button>
                <?php if ($mode === 'short_course' && $lesson_id): ?>
                    <button class="block-btn" data-type="quiz" onclick="addQuizAssessment()" <?= !$can_edit ? 'disabled' : '' ?>>
                        <i class="fas fa-circle-question" style="color:var(--c-quiz)"></i> Quiz
                    </button>
                    <button class="block-btn" data-type="cat" onclick="addCatAssessment()" <?= !$can_edit ? 'disabled' : '' ?>>
                        <i class="fas fa-flag-checkered" style="color:var(--c-cat)"></i> CAT
                    </button>
                <?php endif; ?>
                <div class="toolbar-sep"></div>
                <button class="block-btn" onclick="saveAllBlocks()" style="border-color:rgba(62,207,142,0.4)" <?= !$can_edit ? 'disabled' : '' ?>>
                    <i class="fas fa-floppy-disk" style="color:var(--accent2)"></i> Save All
                </button>
                <?php if (!$can_edit): ?>
                    <div class="toolbar-sep"></div>
                    <span style="font-size:0.75rem;color:var(--danger);font-weight:600;padding:0 8px;">
                        <i class="fas fa-lock"></i> View Only
                    </span>
                <?php endif; ?>
            </div>

            <!-- Blocks canvas -->
            <div class="blocks-canvas">
                <div class="canvas-inner" id="blocks-container">

                    <?php if (empty($content_blocks)): ?>
                        <div id="empty-canvas" style="text-align:center;padding:48px 20px;color:var(--text-dim)">
                            <i class="fas fa-layer-group" style="font-size:2rem;margin-bottom:12px;display:block;opacity:0.3"></i>
                            <p style="font-size:0.85rem">No content yet. Click a block type above to start building.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($content_blocks as $block): ?>
                            <!-- PHP-rendered existing blocks (JS will also handle new ones) -->
                            <div id="php-block-<?= $block['id'] ?>"></div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Topics & Subtopics modal -->
<div class="modal-overlay" id="topics-modal">
    <div class="modal">
        <h3 id="topics-modal-title"><i class="fas fa-list-ul"></i> Topics &amp; subtopics</h3>
        <input type="hidden" id="topics-lesson-id">
        <div class="form-group">
            <label>Add a topic</label>
            <div style="display:flex;gap:8px">
                <input type="text" class="form-input" id="topics-title-input" placeholder="Topic title" style="flex:1">
                <button class="btn btn-primary" type="button" onclick="addTopic()"><i class="fas fa-plus"></i> Add</button>
            </div>
        </div>
        <div id="topics-list" style="max-height:400px;overflow:auto;margin-top:6px"></div>
        <div class="modal-actions">
            <button class="btn" onclick="closeTopicsModal()">Close</button>
        </div>
    </div>
</div>
<div id="toast"></div>

<script>
// ─────────────────────────────────────────────────────────
// CONFIG
// ─────────────────────────────────────────────────────────
const LESSON_ID = <?= $lesson_id ?: 'null' ?>;
const UNIT_ID   = <?= $unit_id   ?: 'null' ?>;
const MODE      = '<?= $mode ?>';
const COURSE_ID = <?= $course_id ?: 'null' ?>;
const MODULE_ID = <?= $current_lesson ? (int)$current_lesson['module_id'] : 'null' ?>;

// Existing blocks from PHP (to populate on load)
const EXISTING_BLOCKS = <?= json_encode($content_blocks) ?>;

// ─────────────────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────────────────
let blocks       = [];   // {localId, dbId, type, content, saved}
let dragSrc      = null;
let blockCounter = 0;

// ─────────────────────────────────────────────────────────
// INIT — render existing blocks from PHP
// ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (!LESSON_ID) return;

    const container = document.getElementById('blocks-container');
    container.innerHTML = ''; // clear PHP placeholders

    EXISTING_BLOCKS.forEach(b => {
        const localId = ++blockCounter;
        blocks.push({ localId, dbId: b.id, type: b.block_type, content: b.content, saved: true });
        container.appendChild(buildBlockCard(localId, b.block_type, b.content, b.id));
    });

    updateBlockCount();
});

// ─────────────────────────────────────────────────────────
// UNIT SWITCH
// ─────────────────────────────────────────────────────────
function switchUnit(uid) {
    if (uid) window.location.href = `lesson_editor.php?unit_id=${uid}`;
}

// ─────────────────────────────────────────────────────────
// LESSON TITLE EDIT
// ─────────────────────────────────────────────────────────
function enableTitleEdit() {
    const titleDisplay = document.getElementById('lesson-title-display');
    const titleForm = document.getElementById('title-edit-form');
    const titleInput = document.getElementById('lesson-title-input');
    
    if (titleDisplay && titleForm && titleInput) {
        titleDisplay.style.display = 'none';
        titleForm.style.display = 'flex';
        titleInput.focus();
    }
}

function cancelTitleEdit() {
    const titleDisplay = document.getElementById('lesson-title-display');
    const titleForm = document.getElementById('title-edit-form');
    const titleInput = document.getElementById('lesson-title-input');
    
    if (titleDisplay && titleForm && titleInput) {
        titleForm.style.display = 'none';
        titleDisplay.style.display = 'inline';
        // Reset to original value
        titleInput.value = titleDisplay.textContent;
    }
}

function saveLessonTitle() {
    const titleInput = document.getElementById('lesson-title-input');
    const newTitle = titleInput.value.trim();
    
    if (!newTitle) {
        showToast('Title cannot be empty', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('lesson_id', LESSON_ID);
    formData.append('title', newTitle);
    formData.append('module_id', MODULE_ID);
    
    if (MODE === 'short_course') {
        formData.append('course_id', COURSE_ID);
    } else {
        formData.append('unit_id', UNIT_ID);
    }
    
    fetch('ajax/short_course_save_lesson.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const titleDisplay = document.getElementById('lesson-title-display');
            const titleForm = document.getElementById('title-edit-form');
            
            titleDisplay.textContent = newTitle;
            titleForm.style.display = 'none';
            titleDisplay.style.display = 'inline';
            
            showToast('Title saved successfully', 'success');
        } else {
            showToast('Failed to save title: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        showToast('Error saving title: ' + err.message, 'error');
    });
}

function toggleModuleGroup(el) {
    el.classList.toggle('collapsed');
    const lessons = el.nextElementSibling;
    lessons.style.display = el.classList.contains('collapsed') ? 'none' : '';
}

// ─────────────────────────────────────────────────────────
// ADD BLOCK
// ─────────────────────────────────────────────────────────
function addBlock(type) {
    if (!LESSON_ID) { toast('Select a lesson first', 'error'); return; }

    const empty = document.getElementById('empty-canvas');
    if (empty) empty.remove();

    const localId = ++blockCounter;
    blocks.push({ localId, dbId: null, type, content: '', saved: false });

    const container = document.getElementById('blocks-container');
    const card = buildBlockCard(localId, type, '', null);
    container.appendChild(card);
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    updateBlockCount();
}

// ── Assessment flow helpers (short-course mode) ─────────────────────────────
function fetchAssessments() {
    return fetch('ajax/short_course_get_assessments.php?course_id=' + COURSE_ID)
        .then(r => r.json())
        .then(d => (d && d.success) ? d.assessments : [])
        .catch(() => []);
}

function createAssessment(payload) {
    const fd = new FormData();
    fd.append('course_id', COURSE_ID);
    fd.append('module_id', payload.module_id || 0);
    fd.append('lesson_id', payload.lesson_id || 0);
    fd.append('type', payload.type);
    fd.append('title', payload.title);
    fd.append('instructions', payload.type === 'quiz' ? 'Answer all the questions.' : 'Read the instructions carefully before starting.');
    fd.append('pass_mark', payload.pass_mark || 50);
    fd.append('max_attempts', payload.type === 'quiz' ? 3 : 1);
    fd.append('time_limit_minutes', payload.time_limit_minutes || 0);
    return fetch('ajax/short_course_save_assessment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toast(payload.type.toUpperCase() + ' created', 'success');
                window.location.href = 'short_course_assessment_questions.php?assessment_id=' + d.assessment_id;
            } else {
                toast(d.message, 'error');
            }
            return d.success;
        })
        .catch(() => { toast('Failed to create ' + payload.type, 'error'); return false; });
}

// A Quiz belongs to a single topic (short-course lesson).
function addQuizAssessment() {
    if (!LESSON_ID || !COURSE_ID || !MODULE_ID) { toast('Select a short-course lesson first', 'error'); return; }
    fetchAssessments().then(existing => {
        const quiz = (existing || []).find(a => a.type === 'quiz' && Number(a.lesson_id) === LESSON_ID);
        if (quiz) {
            toast('Quiz already exists — opening it', 'info');
            window.location.href = 'short_course_assessment_questions.php?assessment_id=' + quiz.id;
            return;
        }
        const lessonName = (document.getElementById('lesson-title-display')?.textContent || 'Lesson').trim();
        createAssessment({ type: 'quiz', title: lessonName + ' Quiz', module_id: MODULE_ID, lesson_id: LESSON_ID });
    });
}

// A CAT follows the whole module of lessons.
function addCatAssessment() {
    if (!LESSON_ID || !COURSE_ID || !MODULE_ID) { toast('Select a short-course lesson first', 'error'); return; }
    fetchAssessments().then(existing => {
        const cat = (existing || []).find(a => a.type === 'cat' && Number(a.lesson_id) === 0 && Number(a.module_id) === MODULE_ID);
        if (cat) {
            toast('CAT already exists — opening it', 'info');
            window.location.href = 'short_course_assessment_questions.php?assessment_id=' + cat.id;
            return;
        }
        const moduleName = (document.querySelector('.lesson-module-tag')?.textContent || 'Module').trim();
        createAssessment({ type: 'cat', title: moduleName + ' CAT', module_id: MODULE_ID, lesson_id: 0 });
    });
}

// ─────────────────────────────────────────────────────────
// BUILD BLOCK CARD
// ─────────────────────────────────────────────────────────
function buildBlockCard(localId, type, content, dbId) {
    const card = document.createElement('div');
    card.className   = 'block-card';
    card.dataset.lid = localId;
    card.draggable   = true;

    const icons  = { text:'fa-align-left', image:'fa-image', video:'fa-video', audio:'fa-music', diagram:'fa-diagram-project', pdf:'fa-file-pdf', ppt:'fa-file-powerpoint' };
    const labels = { text:'Text', image:'Image', video:'Video', audio:'Audio', diagram:'Diagram', pdf:'PDF', ppt:'PowerPoint' };

    card.innerHTML = `
        <div class="block-header">
            <div class="block-type-dot dot-${type}"></div>
            <span class="block-type-label label-${type}">
                <i class="fas ${icons[type]}"></i> ${labels[type]}
            </span>
            <div style="flex:1"></div>
            <span class="save-status ${dbId ? 'saved' : 'unsaved'}" id="status-${localId}">
                ${dbId ? '✓ Saved' : '● Unsaved'}
            </span>
            <div class="block-actions" style="margin-left:10px">
                <button class="btn-icon save" onclick="saveBlock(${localId})" title="Save block">
                    <i class="fas fa-floppy-disk"></i>
                </button>
                <button class="btn-icon danger" onclick="deleteBlock(${localId})" title="Delete block">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="block-drag-handle" style="margin-left:8px">
                <span></span><span></span><span></span>
                <span></span><span></span><span></span>
            </div>
        </div>
        <div class="block-body" id="body-${localId}">
            ${buildBlockBody(localId, type, content)}
        </div>
    `;

    // Drag events
    card.addEventListener('dragstart', e => { dragSrc = card; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
    card.addEventListener('dragend',   () => { card.classList.remove('dragging'); document.querySelectorAll('.block-card').forEach(c => c.classList.remove('drag-over')); dragSrc = null; });
    card.addEventListener('dragover',  e => { e.preventDefault(); if (card !== dragSrc) card.classList.add('drag-over'); });
    card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
    card.addEventListener('drop',      e => {
        e.preventDefault(); card.classList.remove('drag-over');
        if (!dragSrc || dragSrc === card) return;
        const container = document.getElementById('blocks-container');
        const cards = [...container.querySelectorAll('.block-card')];
        const si = cards.indexOf(dragSrc), di = cards.indexOf(card);
        if (si < di) container.insertBefore(dragSrc, card.nextSibling);
        else         container.insertBefore(dragSrc, card);
        saveBlockOrder();
    });

    return card;
}

// ─────────────────────────────────────────────────────────
// BUILD BLOCK BODY (by type)
// ─────────────────────────────────────────────────────────
function buildBlockBody(localId, type, content) {
    if (type === 'text')    return buildTextBody(localId, content);
    if (type === 'image')   return buildImageBody(localId, content);
    if (type === 'video')   return buildVideoBody(localId, content);
    if (type === 'audio')   return buildAudioBody(localId, content);
    if (type === 'diagram') return buildDiagramBody(localId, content);
    if (type === 'pdf')     return buildPdfBody(localId, content);
    if (type === 'ppt')     return buildPptBody(localId, content);
    return '';
}

// TEXT
function buildTextBody(localId, content) {
    const safe = escHtml(content || '');
    return `
        <div class="rich-toolbar">
            <button class="rich-btn" onclick="fmt('bold')"          title="Bold"><b>B</b></button>
            <button class="rich-btn" onclick="fmt('italic')"        title="Italic"><i>I</i></button>
            <button class="rich-btn" onclick="fmt('underline')"     title="Underline"><u>U</u></button>
            <button class="rich-btn" onclick="fmtBlock('h2')"       title="Heading">H2</button>
            <button class="rich-btn" onclick="fmtBlock('h3')"       title="Sub-heading">H3</button>
            <button class="rich-btn" onclick="fmt('insertUnorderedList')" title="Bullet list"><i class="fas fa-list-ul"></i></button>
            <button class="rich-btn" onclick="fmt('insertOrderedList')"   title="Numbered list"><i class="fas fa-list-ol"></i></button>
            <button class="rich-btn" onclick="fmtBlock('blockquote')" title="Quote"><i class="fas fa-quote-left"></i></button>
            <button class="rich-btn" onclick="insertCode(${localId})"   title="Code"><i class="fas fa-code"></i></button>
        </div>
        <div class="rich-content" id="rc-${localId}" contenteditable="true"
             oninput="markUnsaved(${localId})"
             onfocus="setActiveEditor(${localId})">${content || ''}</div>
    `;
}

// IMAGE
function buildImageBody(localId, content) {
    let imgSrc = '', caption = '';
    try { const d = JSON.parse(content||'{}'); imgSrc = d.src||''; caption = d.caption||''; } catch(e) {}
    return `
        <div class="image-upload-area" id="img-area-${localId}" onclick="document.getElementById('img-file-${localId}').click()">
            <input type="file" id="img-file-${localId}" accept="image/*"
                   onchange="handleImageUpload(event,${localId})">
            ${imgSrc
                ? `<img src="${escHtml(imgSrc)}" class="image-preview" id="img-preview-${localId}">`
                : `<i class="fas fa-image"></i><div>Click to upload image</div><small>JPG, PNG, GIF, WebP</small>`}
        </div>
        <input type="text" class="image-caption" id="img-caption-${localId}"
               value="${escAttr(caption)}"
               placeholder="Caption (optional)"
               oninput="markUnsaved(${localId})">
    `;
}

// VIDEO — supports direct upload AND YouTube/Vimeo URL
function buildVideoBody(localId, content) {
    let vidType = 'url', url = '', embed = '', src = '', name = '';
    try {
        const d = JSON.parse(content || '{}');
        vidType = d.type || (d.src ? 'upload' : 'url');
        url     = d.url   || '';
        embed   = d.embed || '';
        src     = d.src   || '';
        name    = d.name  || '';
    } catch(e) { url = content || ''; }

    const isUpload = vidType === 'upload';
    const isUrl    = !isUpload;

    return `
        <div class="vid-tabs">
            <button class="vid-tab ${isUrl ? 'active' : ''}"    onclick="switchVidTab(${localId},'url')">
                <i class="fas fa-link"></i> URL / Embed
            </button>
            <button class="vid-tab ${isUpload ? 'active' : ''}" onclick="switchVidTab(${localId},'upload')">
                <i class="fas fa-upload"></i> Upload Video
            </button>
        </div>

        <!-- URL TAB -->
        <div class="vid-tab-panel ${isUrl ? 'active' : ''}" id="vid-panel-url-${localId}">
            <label style="font-size:.78rem;color:var(--text-muted);display:block;margin-bottom:5px">
                YouTube, Vimeo, or direct video URL
            </label>
            <input type="url" class="url-input" id="vid-url-${localId}"
                   value="${escAttr(url)}"
                   placeholder="https://www.youtube.com/watch?v=..."
                   oninput="handleVideoUrl(${localId})">
            <div class="video-preview" id="vid-preview-${localId}"
                 style="${embed ? 'display:block' : 'display:none'}">
                ${embed ? `<iframe src="${escHtml(embed)}" allowfullscreen></iframe>` : ''}
            </div>
        </div>

        <!-- UPLOAD TAB -->
        <div class="vid-tab-panel ${isUpload ? 'active' : ''}" id="vid-panel-upload-${localId}">
            <div class="vid-upload-area" id="vid-drop-${localId}"
                 onclick="document.getElementById('vid-file-${localId}').click()"
                 ondragover="event.preventDefault();this.style.borderColor='var(--c-video)'"
                 ondragleave="this.style.borderColor=''"
                 ondrop="handleVideoDrop(event,${localId})">
                <input type="file" id="vid-file-${localId}" accept="video/mp4,video/webm,video/ogg"
                       onchange="handleVideoUpload(event,${localId})">
                <i class="fas fa-film" id="vid-upload-icon-${localId}"></i>
                <div id="vid-upload-label-${localId}">
                    ${src ? escHtml(name || 'Video loaded') : 'Click or drag to upload video'}
                </div>
                <small>MP4, WebM, OGG · up to 500MB</small>
            </div>
            <div class="vid-progress" id="vid-prog-${localId}">
                <div class="vid-progress-fill" id="vid-prog-fill-${localId}"></div>
            </div>
            <div class="vid-player" id="vid-player-${localId}"
                 style="${src ? 'display:block' : 'display:none'}">
                <video controls preload="metadata"
                       src="${src ? escHtml('../' + src) : ''}"
                       id="vid-el-${localId}">
                </video>
            </div>
        </div>
    `;
}

// AUDIO
function buildAudioBody(localId, content) {
    let src = '', name = '';
    try { const d = JSON.parse(content||'{}'); src = d.src||''; name = d.name||''; } catch(e) {}
    return `
        <div class="audio-upload-area" onclick="document.getElementById('aud-file-${localId}').click()">
            <input type="file" id="aud-file-${localId}" accept="audio/*"
                   onchange="handleAudioUpload(event,${localId})">
            <i class="fas fa-music"></i>
            <div id="aud-label-${localId}">${src ? escHtml(name||'Audio file loaded') : 'Click to upload audio file'}</div>
            <small>MP3, WAV, OGG, M4A</small>
        </div>
        ${src ? `<audio class="audio-player" id="aud-player-${localId}" controls src="${escHtml(src)}"></audio>` : `<audio class="audio-player" id="aud-player-${localId}" controls style="display:none"></audio>`}
    `;
}

// DIAGRAM
function buildDiagramBody(localId, content) {
    let imgSrc = '', caption = '';
    try { const d = JSON.parse(content||'{}'); imgSrc = d.src||''; caption = d.caption||''; } catch(e) {}
    return `
        <div class="diagram-upload-area" id="diag-area-${localId}" onclick="document.getElementById('diag-file-${localId}').click()">
            <input type="file" id="diag-file-${localId}" accept="image/*,application/pdf"
                   onchange="handleDiagramUpload(event,${localId})">
            ${imgSrc
                ? `<img src="${escHtml(imgSrc)}" class="diagram-preview">`
                : `<i class="fas fa-diagram-project"></i><div>Click to upload diagram</div><small>PNG, SVG, JPG, PDF</small>`}
        </div>
        <input type="text" class="diagram-caption" id="diag-caption-${localId}"
               value="${escAttr(caption)}"
               placeholder="Diagram description (optional)"
               oninput="markUnsaved(${localId})">
    `;
}

// PDF BLOCK
function buildPdfBody(localId, content) {
    let src = '', name = '', caption = '';
    try { const d = JSON.parse(content || '{}'); src = d.src || ''; name = d.name || ''; caption = d.caption || ''; } catch(e) {}
    const loaded = !!src;
    const previewUrl = loaded ? `pdf_preview.php?file=${encodeURIComponent(src)}&embed=1` : '';
    return `
        <div class="pdf-upload-area" id="pdf-drop-${localId}"
             onclick="document.getElementById('pdf-file-${localId}').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--c-pdf)'"
             ondragleave="this.style.borderColor=''"
             ondrop="handlePdfDrop(event,${localId})"
             style="${loaded ? 'display:none' : ''}">
            <input type="file" id="pdf-file-${localId}" accept="application/pdf"
                   onchange="handlePdfUpload(event,${localId})">
            <i class="fas fa-file-pdf"></i>
            <div>Click or drag to upload PDF</div>
            <small>PDF files only · up to 50MB</small>
        </div>

        <div class="pdf-preview-pane" id="pdf-pane-${localId}"
             style="${loaded ? 'display:block' : 'display:none'}">
            <div class="pdf-preview-bar">
                <i class="fas fa-file-pdf" style="color:var(--c-pdf);flex-shrink:0"></i>
                <span class="pdf-name" id="pdf-name-${localId}">${escHtml(name || 'document.pdf')}</span>
                <a href="${loaded ? escHtml('../' + src) : '#'}" target="_blank" id="pdf-open-${localId}">
                    <i class="fas fa-external-link-alt"></i> Download
                </a>
                <button onclick="replacePdf(${localId})"
                        style="font-size:.78rem;padding:4px 10px;background:transparent;border:1px solid var(--border);
                               color:var(--text-muted);border-radius:var(--radius-sm);cursor:pointer;
                               font-family:'DM Sans',sans-serif;transition:var(--tr)"
                        onmouseover="this.style.borderColor='var(--c-pdf)';this.style.color='var(--c-pdf)'"
                        onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">
                    <i class="fas fa-sync-alt"></i> Replace
                </button>
            </div>
            <iframe id="pdf-frame-${localId}"
                    src="${previewUrl}"
                    title="PDF Preview"></iframe>
        </div>

        <input type="text" style="width:100%;background:var(--surface2);border:1px solid var(--border);
               color:var(--text-muted);padding:7px 12px;border-radius:var(--radius-sm);
               font-family:'DM Sans',sans-serif;font-size:.82rem;outline:none;margin-top:8px"
               id="pdf-caption-${localId}"
               value="${escAttr(caption)}"
               placeholder="PDF title / description (optional)"
               oninput="markUnsaved(${localId})">
    `;
}

// POWERPOINT BLOCK (.ppt / .pptx)
function buildPptBody(localId, content) {
    let src = '', name = '', caption = '';
    try { const d = JSON.parse(content || '{}'); src = d.src || ''; name = d.name || ''; caption = d.caption || ''; } catch(e) {}
    const loaded = !!src;
    const previewUrl = loaded ? `ppt_preview.php?file=${encodeURIComponent(src)}&embed=1` : '';
    const fullPreviewUrl = loaded ? `ppt_preview.php?file=${encodeURIComponent(src)}` : '#';
    return `
        <div class="pdf-upload-area" id="ppt-drop-${localId}"
             onclick="document.getElementById('ppt-file-${localId}').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--c-ppt)'"
             ondragleave="this.style.borderColor=''"
             ondrop="handlePptDrop(event,${localId})"
             style="${loaded ? 'display:none' : ''}">
            <input type="file" id="ppt-file-${localId}" accept=".ppt,.pptx,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation"
                   onchange="handlePptUpload(event,${localId})">
            <i class="fas fa-file-powerpoint" style="color:var(--c-ppt)"></i>
            <div>Click or drag to upload PowerPoint</div>
            <small>PPT or PPTX · up to 50MB</small>
        </div>

        <div class="ppt-preview-pane" id="ppt-pane-${localId}" style="${loaded ? 'display:block' : 'display:none'}">
            <div class="pdf-preview-bar">
                <i class="fas fa-file-powerpoint" style="color:var(--c-ppt);flex-shrink:0"></i>
                <span class="pdf-name" id="ppt-name-${localId}">${escHtml(name || 'presentation.pptx')}</span>
                <a href="${fullPreviewUrl}" target="_blank" rel="noopener" id="ppt-full-${localId}">
                    <i class="fas fa-expand"></i> Full-page preview
                </a>
                <a href="${loaded ? escHtml('../' + src) : '#'}" target="_blank" rel="noopener" id="ppt-download-${localId}">
                    <i class="fas fa-download"></i> Download
                </a>
                <button onclick="replacePpt(${localId})" style="font-size:.78rem;padding:4px 10px;background:transparent;border:1px solid var(--border);color:var(--text-muted);border-radius:var(--radius-sm);cursor:pointer;font-family:'DM Sans',sans-serif">
                    <i class="fas fa-sync-alt"></i> Replace
                </button>
            </div>
            <iframe id="ppt-frame-${localId}" src="${previewUrl}" title="PowerPoint Preview"></iframe>
        </div>

        <input type="text" style="width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);padding:7px 12px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:.82rem;outline:none;margin-top:8px"
               id="ppt-caption-${localId}" value="${escAttr(caption)}" placeholder="Presentation title / description (optional)" oninput="markUnsaved(${localId})">
    `;
}

// ─────────────────────────────────────────────────────────
// VIDEO TAB SWITCHER
// ─────────────────────────────────────────────────────────
function switchVidTab(localId, tab) {
    ['url','upload'].forEach(t => {
        document.getElementById(`vid-panel-${t}-${localId}`)?.classList.toggle('active', t === tab);
        document.querySelectorAll(`.vid-tab`).forEach(btn => {
            if (btn.closest(`[id]`) === document.getElementById(`body-${localId}`)) {
                btn.classList.toggle('active', btn.textContent.toLowerCase().includes(tab === 'url' ? 'url' : 'upload'));
            }
        });
    });
    // Easier: just toggle both panels by finding them
    const urlPanel    = document.getElementById(`vid-panel-url-${localId}`);
    const uploadPanel = document.getElementById(`vid-panel-upload-${localId}`);
    if (urlPanel)    urlPanel.classList.toggle('active',    tab === 'url');
    if (uploadPanel) uploadPanel.classList.toggle('active', tab === 'upload');
    // Tab buttons
    const body = document.getElementById(`body-${localId}`);
    body?.querySelectorAll('.vid-tab').forEach((btn, i) => {
        btn.classList.toggle('active', (i === 0 && tab === 'url') || (i === 1 && tab === 'upload'));
    });
    const b = blocks.find(b => b.localId === localId);
    if (b) b._vidTab = tab;
    markUnsaved(localId);
}

// ─────────────────────────────────────────────────────────
// VIDEO UPLOAD HANDLER
// ─────────────────────────────────────────────────────────
function handleVideoUpload(event, localId) {
    const file = event.target.files[0];
    if (!file) return;
    _doVideoUpload(file, localId);
}
function handleVideoDrop(event, localId) {
    event.preventDefault();
    document.getElementById(`vid-drop-${localId}`).style.borderColor = '';
    const file = event.dataTransfer.files[0];
    if (file && file.type.startsWith('video/')) _doVideoUpload(file, localId);
    else toast('Please drop a video file', 'error');
}
function _doVideoUpload(file, localId) {
    const maxMb = 500;
    if (file.size > maxMb * 1024 * 1024) { toast(`Video must be under ${maxMb}MB`, 'error'); return; }

    const label  = document.getElementById(`vid-upload-label-${localId}`);
    const prog   = document.getElementById(`vid-prog-${localId}`);
    const fill   = document.getElementById(`vid-prog-fill-${localId}`);
    const player = document.getElementById(`vid-player-${localId}`);
    const icon   = document.getElementById(`vid-upload-icon-${localId}`);
    const area   = document.getElementById(`vid-drop-${localId}`);

    if (label) label.textContent = `Uploading ${file.name}…`;
    if (icon)  icon.className    = 'fas fa-spinner fa-spin';
    if (prog)  prog.style.display = 'block';
    if (fill)  fill.style.width   = '0%';

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/upload_block_file.php');
    xhr.upload.onprogress = e => {
        if (e.lengthComputable && fill) {
            fill.style.width = Math.round((e.loaded / e.total) * 100) + '%';
        }
    };
    xhr.onload = () => {
        if (prog) prog.style.display = 'none';
        try {
            const d = JSON.parse(xhr.responseText);
            if (d.success) {
                const b = blocks.find(b => b.localId === localId);
                if (b) { b._uploadedVideoPath = d.path; b._videoName = file.name; b._vidTab = 'upload'; }
                if (label)  label.textContent = file.name;
                if (icon)   icon.className    = 'fas fa-check-circle';
                if (player) {
                    player.style.display = 'block';
                    const vid = document.getElementById(`vid-el-${localId}`);
                    if (vid) vid.src = '../' + d.path;
                }
                markUnsaved(localId);
                toast('Video uploaded!', 'success');
            } else {
                // Reset upload area so lecturer can try again
                if (icon)  icon.className    = 'fas fa-film';
                if (label) label.textContent = 'Click or drag to upload video';
                toast(`Upload failed: ${d.message || 'Unknown error'}`, 'error');
            }
        } catch(e) {
            if (icon)  icon.className    = 'fas fa-film';
            if (label) label.textContent = 'Click or drag to upload video';
            toast('Upload error — check server response', 'error');
        }
    };
    xhr.onerror = () => {
        if (prog)  prog.style.display = 'none';
        if (icon)  icon.className    = 'fas fa-film';
        if (label) label.textContent = 'Click or drag to upload video';
        toast('Network error during upload', 'error');
    };

    const fd = new FormData();
    fd.append('file',      file);
    fd.append('folder',    'course_videos');
    fd.append('lesson_id', LESSON_ID);
    xhr.send(fd);
}

// ─────────────────────────────────────────────────────────
// PDF UPLOAD HANDLERS
// ─────────────────────────────────────────────────────────
function handlePdfUpload(event, localId) {
    const file = event.target.files[0];
    if (file) _doPdfUpload(file, localId);
}
function handlePdfDrop(event, localId) {
    event.preventDefault();
    document.getElementById(`pdf-drop-${localId}`).style.borderColor = '';
    const file = event.dataTransfer.files[0];
    if (file && file.type === 'application/pdf') _doPdfUpload(file, localId);
    else toast('Please drop a PDF file', 'error');
}
function replacePdf(localId) {
    // Show upload area again
    document.getElementById(`pdf-drop-${localId}`).style.display = '';
    document.getElementById(`pdf-pane-${localId}`).style.display = 'none';
    document.getElementById(`pdf-file-${localId}`)?.click();
}
function _doPdfUpload(file, localId) {
    if (file.size > 50 * 1024 * 1024) { toast('PDF must be under 50MB', 'error'); return; }

    const drop   = document.getElementById(`pdf-drop-${localId}`);
    const pane   = document.getElementById(`pdf-pane-${localId}`);
    const frame  = document.getElementById(`pdf-frame-${localId}`);
    const link   = document.getElementById(`pdf-open-${localId}`);
    const nameEl = document.getElementById(`pdf-name-${localId}`);

    if (drop) drop.innerHTML = `<i class="fas fa-spinner fa-spin"></i><div>Uploading ${escHtml(file.name)}…</div>`;

    uploadFile(file, 'course_pdfs', localId, (path) => {
        const b = blocks.find(b => b.localId === localId);
        if (b) { b._uploadedPdfPath = path; b._pdfName = file.name; }
        if (nameEl) nameEl.textContent = file.name;
        if (link)   { link.href = '../' + path; }
        const previewUrl = `pdf_preview.php?file=${encodeURIComponent(path)}&embed=1`;
        if (frame)  frame.src  = previewUrl;
        if (drop)   drop.style.display = 'none';
        if (pane)   pane.style.display = 'block';
        markUnsaved(localId);
        toast('PDF uploaded', 'success');
    });
}

// ─────────────────────────────────────────────────────────
// POWERPOINT UPLOAD HANDLERS
// ─────────────────────────────────────────────────────────
function handlePptUpload(event, localId) {
    const file = event.target.files[0];
    if (file) _doPptUpload(file, localId);
}
function handlePptDrop(event, localId) {
    event.preventDefault();
    document.getElementById(`ppt-drop-${localId}`).style.borderColor = '';
    const file = event.dataTransfer.files[0];
    if (file && /\.(ppt|pptx)$/i.test(file.name)) _doPptUpload(file, localId);
    else toast('Please drop a PPT or PPTX file', 'error');
}
function replacePpt(localId) {
    document.getElementById(`ppt-drop-${localId}`).style.display = '';
    document.getElementById(`ppt-pane-${localId}`).style.display = 'none';
    document.getElementById(`ppt-file-${localId}`)?.click();
}
function _doPptUpload(file, localId) {
    if (!/\.(ppt|pptx)$/i.test(file.name)) { toast('Select a PPT or PPTX file', 'error'); return; }
    if (file.size > 50 * 1024 * 1024) { toast('PowerPoint must be under 50MB', 'error'); return; }

    const drop = document.getElementById(`ppt-drop-${localId}`);
    const pane = document.getElementById(`ppt-pane-${localId}`);
    const frame = document.getElementById(`ppt-frame-${localId}`);
    const nameEl = document.getElementById(`ppt-name-${localId}`);
    const fullLink = document.getElementById(`ppt-full-${localId}`);
    const downloadLink = document.getElementById(`ppt-download-${localId}`);
    if (drop) drop.innerHTML = `<i class="fas fa-spinner fa-spin"></i><div>Uploading ${escHtml(file.name)}…</div>`;

    uploadFile(file, 'course_presentations', localId, (path) => {
        const b = blocks.find(b => b.localId === localId);
        if (b) { b._uploadedPptPath = path; b._pptName = file.name; }
        const previewUrl = `ppt_preview.php?file=${encodeURIComponent(path)}&embed=1`;
        const fullPreviewUrl = `ppt_preview.php?file=${encodeURIComponent(path)}`;
        if (nameEl) nameEl.textContent = file.name;
        if (frame) frame.src = previewUrl;
        if (fullLink) fullLink.href = fullPreviewUrl;
        if (downloadLink) downloadLink.href = '../' + path;
        if (drop) drop.style.display = 'none';
        if (pane) pane.style.display = 'block';
        markUnsaved(localId);
        toast('PowerPoint uploaded', 'success');
    });
}

// ─────────────────────────────────────────────────────────
// RICH TEXT HELPERS
// ─────────────────────────────────────────────────────────
let activeEditorId = null;
function setActiveEditor(lid) { activeEditorId = lid; }
function fmt(cmd) { document.execCommand(cmd, false, null); if (activeEditorId) markUnsaved(activeEditorId); }
function fmtBlock(tag) {
    document.execCommand('formatBlock', false, tag);
    if (activeEditorId) markUnsaved(activeEditorId);
}
function insertCode(lid) {
    const sel = window.getSelection();
    if (sel && sel.toString()) {
        document.execCommand('insertHTML', false, `<code>${sel.toString()}</code>`);
    }
    markUnsaved(lid);
}

// ─────────────────────────────────────────────────────────
// VIDEO URL EMBED
// ─────────────────────────────────────────────────────────
function handleVideoUrl(localId) {
    const input   = document.getElementById(`vid-url-${localId}`);
    const preview = document.getElementById(`vid-preview-${localId}`);
    const url     = input.value.trim();
    markUnsaved(localId);

    let embed = '';
    // YouTube
    const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (ytMatch) embed = `https://www.youtube.com/embed/${ytMatch[1]}?rel=0`;

    // Vimeo
    const vmMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vmMatch) embed = `https://player.vimeo.com/video/${vmMatch[1]}`;

    if (embed) {
        preview.style.display = 'block';
        preview.innerHTML = `<iframe src="${embed}" allowfullscreen></iframe>`;
        const b = blocks.find(b => b.localId === localId);
        if (b) b._embedUrl = embed;
    } else {
        preview.style.display = 'none';
    }
}

// ─────────────────────────────────────────────────────────
// FILE UPLOADS
// ─────────────────────────────────────────────────────────
function handleImageUpload(event, localId) {
    const file = event.target.files[0];
    if (!file) return;
    const area = document.getElementById(`img-area-${localId}`);
    area.innerHTML = `<div class="spinner"></div><div style="margin-top:8px;font-size:0.8rem">Uploading...</div>`;

    uploadFile(file, 'course_images', localId, (path) => {
        area.innerHTML = `
            <img src="../${path}" class="image-preview" id="img-preview-${localId}">
            <input type="file" id="img-file-${localId}" accept="image/*" onchange="handleImageUpload(event,${localId})" style="display:none">
        `;
        area.onclick = () => document.getElementById(`img-file-${localId}`).click();
        const b = blocks.find(b => b.localId === localId);
        if (b) b._uploadedPath = path;
        markUnsaved(localId);
    });
}

function handleAudioUpload(event, localId) {
    const file = event.target.files[0];
    if (!file) return;
    const label  = document.getElementById(`aud-label-${localId}`);
    const player = document.getElementById(`aud-player-${localId}`);
    label.textContent = 'Uploading...';

    uploadFile(file, 'course_audio', localId, (path) => {
        label.textContent = file.name;
        player.src = `../${path}`;
        player.style.display = 'block';
        const b = blocks.find(b => b.localId === localId);
        if (b) b._uploadedPath = path;
        markUnsaved(localId);
    });
}

function handleDiagramUpload(event, localId) {
    const file = event.target.files[0];
    if (!file) return;
    const area = document.getElementById(`diag-area-${localId}`);
    area.innerHTML = `<div class="spinner"></div><div style="margin-top:8px;font-size:0.8rem">Uploading...</div>`;

    uploadFile(file, 'course_diagrams', localId, (path) => {
        area.innerHTML = `
            <img src="../${path}" class="diagram-preview">
            <input type="file" id="diag-file-${localId}" accept="image/*,application/pdf" onchange="handleDiagramUpload(event,${localId})" style="display:none">
        `;
        area.onclick = () => document.getElementById(`diag-file-${localId}`).click();
        const b = blocks.find(b => b.localId === localId);
        if (b) b._uploadedPath = path;
        markUnsaved(localId);
    });
}

function uploadFile(file, folder, localId, callback) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('folder', folder);
    fd.append('lesson_id', LESSON_ID);

    fetch('ajax/upload_block_file.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) callback(d.path);
            else toast(d.message || 'Upload failed', 'error');
        })
        .catch(() => toast('Upload error', 'error'));
}

// ─────────────────────────────────────────────────────────
// SAVE BLOCK
// ─────────────────────────────────────────────────────────
function saveBlock(localId) {
    const b = blocks.find(b => b.localId === localId);
    if (!b) return;

    const content = extractBlockContent(localId, b.type, b);
    setStatus(localId, 'saving');

    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('block_type', b.type);
    fd.append('content',    content);
    if (b.dbId) fd.append('block_id', b.dbId);

    // Position = current index in DOM
    const cards = [...document.querySelectorAll('#blocks-container .block-card')];
    fd.append('position', cards.findIndex(c => parseInt(c.dataset.lid) === localId));

    const endpoint = MODE === 'short_course' ? 'ajax/save_short_course_content.php' : 'ajax/save_content_block.php';
    fetch(endpoint, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                b.dbId    = d.block_id || 0;
                b.content = content;
                b.saved   = true;
                setStatus(localId, 'saved');
                toast('Block saved', 'success');
            } else {
                setStatus(localId, 'unsaved');
                toast(d.message, 'error');
            }
        })
        .catch(() => { setStatus(localId, 'unsaved'); toast('Save failed', 'error'); });
}

function extractBlockContent(localId, type, block) {
    if (type === 'text') {
        const el = document.getElementById(`rc-${localId}`);
        return el ? el.innerHTML : '';
    }
    if (type === 'image') {
        const caption = document.getElementById(`img-caption-${localId}`)?.value || '';
        const src = block._uploadedPath ? `../${block._uploadedPath}` : (block.content ? JSON.parse(block.content||'{}').src || '' : '');
        return JSON.stringify({ src, caption });
    }
    if (type === 'video') {
        const b       = blocks.find(b => b.localId === localId);
        const tab     = b?._vidTab || (b?._uploadedVideoPath ? 'upload' : 'url');
        if (tab === 'upload') {
            const src  = b?._uploadedVideoPath || (b?.content ? (JSON.parse(b.content || '{}').src || '') : '');
            const name = b?._videoName || (b?.content ? (JSON.parse(b.content || '{}').name || '') : '');
            return JSON.stringify({ type: 'upload', src, name });
        } else {
            const url   = document.getElementById(`vid-url-${localId}`)?.value || '';
            const embed = b?._embedUrl || (b?.content ? (JSON.parse(b.content || '{}').embed || '') : '');
            return JSON.stringify({ type: 'url', url, embed });
        }
    }
    if (type === 'pdf') {
        const b       = blocks.find(b => b.localId === localId);
        const src     = b?._uploadedPdfPath || (b?.content ? (JSON.parse(b.content || '{}').src || '') : '');
        const name    = b?._pdfName        || (b?.content ? (JSON.parse(b.content || '{}').name || '') : '');
        const caption = document.getElementById(`pdf-caption-${localId}`)?.value || '';
        return JSON.stringify({ src, name, caption });
    }
    if (type === 'ppt') {
        const b       = blocks.find(b => b.localId === localId);
        const src     = b?._uploadedPptPath || (b?.content ? (JSON.parse(b.content || '{}').src || '') : '');
        const name    = b?._pptName || (b?.content ? (JSON.parse(b.content || '{}').name || '') : '');
        const caption = document.getElementById(`ppt-caption-${localId}`)?.value || '';
        return JSON.stringify({ src, name, caption });
    }
    if (type === 'audio') {
        const src  = block._uploadedPath ? `../${block._uploadedPath}` : (block.content ? JSON.parse(block.content||'{}').src || '' : '');
        const name = document.getElementById(`aud-label-${localId}`)?.textContent || '';
        return JSON.stringify({ src, name });
    }
    if (type === 'diagram') {
        const caption = document.getElementById(`diag-caption-${localId}`)?.value || '';
        const src = block._uploadedPath ? `../${block._uploadedPath}` : (block.content ? JSON.parse(block.content||'{}').src || '' : '');
        return JSON.stringify({ src, caption });
    }
    return '';
}

// ─────────────────────────────────────────────────────────
// SAVE ALL
// ─────────────────────────────────────────────────────────
function saveAllBlocks() {
    const unsaved = blocks.filter(b => !b.saved);
    if (unsaved.length === 0) { toast('All blocks already saved', 'info'); return; }
    unsaved.forEach(b => saveBlock(b.localId));
}

// ─────────────────────────────────────────────────────────
// DELETE BLOCK
// ─────────────────────────────────────────────────────────
function deleteBlock(localId) {
    const b = blocks.find(b => b.localId === localId);
    if (!b) return;
    if (!confirm('Delete this block?')) return;

    const card = document.querySelector(`.block-card[data-lid="${localId}"]`);

    if (!b.dbId) {
        blocks = blocks.filter(b => b.localId !== localId);
        card?.remove();
        updateBlockCount();
        return;
    }

    if (MODE === 'short_course') {
        // For short courses, clearing content means setting content_html to empty
        const fd = new FormData();
        fd.append('lesson_id', LESSON_ID);
        fd.append('block_type', 'text');
        fd.append('content', '');
        fetch('ajax/save_short_course_content.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    blocks = blocks.filter(b => b.localId !== localId);
                    card?.remove();
                    updateBlockCount();
                    toast('Content cleared', 'success');
                } else toast(d.message, 'error');
            })
            .catch(() => toast('Delete failed', 'error'));
        return;
    }

    const fd = new FormData();
    fd.append('block_id', b.dbId);
    fd.append('lesson_id', LESSON_ID);

    fetch('ajax/delete_content_block.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                blocks = blocks.filter(b => b.localId !== localId);
                card?.remove();
                updateBlockCount();
                toast('Block deleted', 'success');
            } else toast(d.message, 'error');
        })
        .catch(() => toast('Delete failed', 'error'));
}

// ─────────────────────────────────────────────────────────
// REORDER (drag-drop saves positions)
// ─────────────────────────────────────────────────────────
function saveBlockOrder() {
    // Short courses store content as a single content_html field, so reordering is not applicable
    if (MODE === 'short_course') return;

    const ids = [...document.querySelectorAll('#blocks-container .block-card')]
                    .map(c => {
                        const lid = parseInt(c.dataset.lid);
                        const b   = blocks.find(b => b.localId === lid);
                        return b?.dbId || null;
                    })
                    .filter(id => id !== null);

    if (ids.length === 0) return;

    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('order', JSON.stringify(ids));
    fetch('ajax/reorder_blocks.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .catch(() => {});
}

// ─────────────────────────────────────────────────────────
// UI HELPERS
// ─────────────────────────────────────────────────────────
function markUnsaved(localId) {
    const b = blocks.find(b => b.localId === localId);
    if (b) b.saved = false;
    setStatus(localId, 'unsaved');
}

function setStatus(localId, state) {
    const el = document.getElementById(`status-${localId}`);
    if (!el) return;
    el.className = `save-status ${state}`;
    el.textContent = state === 'saved' ? '✓ Saved' : state === 'saving' ? '⟳ Saving...' : '● Unsaved';
}

function updateBlockCount() {
    const el = document.getElementById('block-count');
    if (el) el.textContent = `${blocks.length} block${blocks.length !== 1 ? 's' : ''}`;
}

// ─────────────────────────────────────────────────────────
// TOAST
// ─────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const c = document.getElementById('toast');
    const e = document.createElement('div');
    e.className = `toast-item ${type}`;
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info' };
    e.innerHTML = `<i class="fas ${icons[type]||'fa-circle-info'}"></i><span style="flex:1">${escHtml(msg)}</span>${type==='error' ? '<i class="fas fa-times" style="opacity:.5;font-size:.75rem;flex-shrink:0"></i>' : ''}`;
    e.addEventListener('click', () => e.remove());
    e.title = 'Click to dismiss';
    c.appendChild(e);
    const delay = type === 'error' ? 7200 : 4000;
    setTimeout(() => e.remove(), delay);
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
    return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// TOPICS & SUBTOPICS
let topicsLessonId = null;
function openTopicsModal(lessonId) {
    topicsLessonId = lessonId;
    document.getElementById('topics-lesson-id').value = lessonId;
    document.getElementById('topics-modal-title').innerHTML = '<i class="fas fa-list-ul"></i> Topics &amp; subtopics';
    document.getElementById('topics-title-input').value = '';
    document.getElementById('topics-modal').classList.add('open');
    loadTopics();
}
function closeTopicsModal() { document.getElementById('topics-modal').classList.remove('open'); }
function loadTopics() {
    const list = document.getElementById('topics-list');
    list.innerHTML = '<p class="modal-empty">Loading&hellip;</p>';
    fetch(`ajax/short_course_topics.php?lesson_id=${topicsLessonId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success) { list.innerHTML = `<p class="modal-empty">${escHtml(d.message || 'Error')}</p>`; return; }
            if (!d.topics.length) { list.innerHTML = '<p class="modal-empty">No topics yet. Add one above.</p>'; return; }
            list.innerHTML = d.topics.map(buildTopicBlock).join('');
        })
        .catch(() => list.innerHTML = '<p class="modal-empty">Failed to load topics.</p>');
}
function buildTopicBlock(t) {
    const subs = (t.subs || []).map(s => `
        <div class="subtopic-row">
            <i class="fas fa-angle-right" style="color:var(--text-dim)"></i>
            <span class="ttl">${escHtml(s.title)}</span>
            <button class="btn btn-sm btn-icon" onclick="deleteTopic(${s.id})" title="Delete subtopic"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
        </div>`).join('');
    return `
        <div class="topic-item">
            <div class="trow">
                <i class="fas fa-folder-open" style="color:var(--accent2)"></i>
                <span class="ttl">${escHtml(t.title)}</span>
                <button class="btn btn-sm btn-icon" onclick="addSubTopic(${t.id})" title="Add subtopic"><i class="fas fa-plus" style="color:var(--accent3)"></i></button>
                <button class="btn btn-sm btn-icon" onclick="deleteTopic(${t.id})" title="Delete topic"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
            </div>
            ${subs}
        </div>`;
}
function addTopic() {
    const title = document.getElementById('topics-title-input').value.trim();
    if (!title) { toast('Topic title is required', 'error'); return; }
    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('lesson_id', topicsLessonId);
    fd.append('title', title);
    fd.append('parent_id', '0');
    fetch('ajax/short_course_topics.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.success) { toast('Topic added', 'success'); document.getElementById('topics-title-input').value=''; loadTopics(); }
            else toast(d.message, 'error');
        }).catch(() => toast('Network error', 'error'));
}
function addSubTopic(parentId) {
    const title = prompt('Subtopic title:');
    if (!title || !title.trim()) return;
    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('lesson_id', topicsLessonId);
    fd.append('title', title.trim());
    fd.append('parent_id', parentId);
    fetch('ajax/short_course_topics.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.success) { toast('Subtopic added', 'success'); loadTopics(); }
            else toast(d.message, 'error');
        }).catch(() => toast('Network error', 'error'));
}
function deleteTopic(id) {
    if (!confirm('Delete this topic and any subtopics under it?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('lesson_id', topicsLessonId);
    fd.append('topic_id', id);
    fetch('ajax/short_course_topics.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.success) { toast('Deleted', 'success'); loadTopics(); }
            else toast(d.message, 'error');
        }).catch(() => toast('Network error', 'error'));
}
document.addEventListener('click', function(e) {
    if (e.target && e.target.classList && e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
});

</script>
</body>
</html>
