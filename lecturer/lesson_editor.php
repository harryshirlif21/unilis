<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

$unit_id   = intval($_GET['unit_id']   ?? 0);
$lesson_id = intval($_GET['lesson_id'] ?? 0);

$units = [];
try {
    $stmt = $conn->prepare("SELECT u.id, u.name FROM units u JOIN lecturer_units lu ON u.id = lu.unit_id WHERE lu.lecturer_id = ? ORDER BY u.name ASC");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $units[] = $row;
    $stmt->close();
} catch (mysqli_sql_exception $e) { error_log("lesson_editor units: " . $e->getMessage()); }

$modules_with_lessons = [];
if ($unit_id) {
    try {
        $stmt = $conn->prepare("SELECT id, title, position FROM course_modules WHERE unit_id = ? AND lecturer_id = ? ORDER BY position ASC, id ASC");
        $stmt->bind_param("ii", $unit_id, $lecturer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $row['lessons'] = []; $modules_with_lessons[$row['id']] = $row; }
        $stmt->close();

        if (!empty($modules_with_lessons)) {
            $mids = array_keys($modules_with_lessons);
            $ph = implode(',', array_fill(0, count($mids), '?'));
            $types = str_repeat('i', count($mids));
            $params = array_merge([$unit_id], $mids);
            $stmt = $conn->prepare("SELECT id, module_id, title, lesson_number, position FROM course_lessons WHERE unit_id = ? AND module_id IN ($ph) ORDER BY module_id ASC, position ASC");
            $stmt->bind_param('i'.$types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (isset($modules_with_lessons[$row['module_id']])) $modules_with_lessons[$row['module_id']]['lessons'][] = $row;
            }
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) { error_log("lesson_editor modules: " . $e->getMessage()); }
}

$current_lesson = null;
$content_blocks = [];
if ($lesson_id) {
    try {
        $stmt = $conn->prepare("SELECT cl.id, cl.title, cl.lesson_number, cl.module_id, cl.unit_id, cm.title AS module_title FROM course_lessons cl JOIN course_modules cm ON cl.module_id = cm.id WHERE cl.id = ? AND cl.unit_id = ? AND cm.lecturer_id = ?");
        $stmt->bind_param("iii", $lesson_id, $unit_id, $lecturer_id);
        $stmt->execute();
        $current_lesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current_lesson) {
            $stmt = $conn->prepare("SELECT id, block_type, content, position FROM lesson_content_blocks WHERE lesson_id = ? ORDER BY position ASC, id ASC");
            $stmt->bind_param("i", $lesson_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $content_blocks[] = $row;
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) { error_log("lesson_editor blocks: " . $e->getMessage()); }
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
    --bg:#0b0d11;--surface:#13161e;--surface2:#1a1f2e;--surface3:#222840;
    --border:#282e45;--accent:#6c8ef5;--accent2:#3ecf8e;--accent3:#f5a623;
    --accent4:#e879f9;--danger:#f56565;--text:#e2e8f0;--text-muted:#718096;
    --text-dim:#404968;--shadow:0 8px 32px rgba(0,0,0,0.5);--radius:12px;
    --radius-sm:7px;--tr:0.15s ease;
    --c-text:#6c8ef5;--c-image:#3ecf8e;--c-video:#f56565;--c-audio:#f5a623;--c-diagram:#e879f9;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.topbar-brand{font-family:'Syne',sans-serif;font-weight:800;font-size:1rem;color:var(--accent);letter-spacing:0.04em;}
.topbar-brand span{color:var(--text-muted);font-weight:400;font-size:0.8rem;margin-left:8px;}
.topbar-right{display:flex;align-items:center;gap:10px;}
.btn-nav{background:var(--surface3);border:1px solid var(--border);color:var(--text-muted);padding:6px 13px;border-radius:var(--radius-sm);font-size:0.79rem;cursor:pointer;text-decoration:none;transition:var(--tr);font-family:'DM Sans',sans-serif;}
.btn-nav:hover{color:var(--text);background:var(--surface2);}
.layout{display:flex;height:calc(100vh - 58px);}
.left-panel{width:280px;min-width:280px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
.panel-header{padding:16px 18px 12px;border-bottom:1px solid var(--border);flex-shrink:0;}
.panel-header label{font-family:'Syne',sans-serif;font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-dim);display:block;margin-bottom:8px;}
.styled-select{width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.84rem;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%23718096' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:28px;}
.styled-select:focus{border-color:var(--accent);}
.lesson-tree{flex:1;overflow-y:auto;padding:10px 10px 20px;}
.module-group{margin-bottom:6px;}
.module-label{font-family:'Syne',sans-serif;font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-dim);padding:8px 10px 4px;display:flex;align-items:center;gap:6px;cursor:pointer;}
.module-label i{transition:transform var(--tr);}
.module-label.collapsed i{transform:rotate(-90deg);}
.module-label:hover{color:var(--text-muted);}
.lesson-link{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:var(--radius-sm);text-decoration:none;color:var(--text-muted);font-size:0.83rem;transition:var(--tr);border-left:2px solid transparent;margin:1px 0;cursor:pointer;}
.lesson-link:hover{background:var(--surface2);color:var(--text);border-left-color:var(--border);}
.lesson-link.active{background:rgba(108,142,245,0.12);color:var(--accent);border-left-color:var(--accent);font-weight:500;}
.lesson-num-badge{font-family:'JetBrains Mono',monospace;font-size:0.65rem;color:var(--text-dim);min-width:22px;}
.editor-panel{flex:1;display:flex;flex-direction:column;overflow:hidden;}
.editor-toolbar{background:var(--surface);border-bottom:1px solid var(--border);padding:10px 24px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex-shrink:0;}
.toolbar-label{font-family:'Syne',sans-serif;font-size:0.7rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-dim);margin-right:4px;}
.block-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.8rem;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface2);color:var(--text-muted);transition:var(--tr);}
.block-btn:hover{transform:translateY(-1px);}
.block-btn[data-type="text"]{border-color:rgba(108,142,245,0.4);}.block-btn[data-type="text"]:hover{background:rgba(108,142,245,0.12);color:var(--c-text);border-color:var(--c-text);}
.block-btn[data-type="image"]{border-color:rgba(62,207,142,0.4);}.block-btn[data-type="image"]:hover{background:rgba(62,207,142,0.12);color:var(--c-image);border-color:var(--c-image);}
.block-btn[data-type="video"]{border-color:rgba(245,101,101,0.4);}.block-btn[data-type="video"]:hover{background:rgba(245,101,101,0.12);color:var(--c-video);border-color:var(--c-video);}
.block-btn[data-type="audio"]{border-color:rgba(245,166,35,0.4);}.block-btn[data-type="audio"]:hover{background:rgba(245,166,35,0.12);color:var(--c-audio);border-color:var(--c-audio);}
.block-btn[data-type="diagram"]{border-color:rgba(232,121,249,0.4);}.block-btn[data-type="diagram"]:hover{background:rgba(232,121,249,0.12);color:var(--c-diagram);border-color:var(--c-diagram);}
.toolbar-sep{width:1px;height:24px;background:var(--border);margin:0 4px;}
.blocks-canvas{flex:1;overflow-y:auto;padding:24px 28px;}
.canvas-inner{max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:14px;}
.block-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:border-color var(--tr),box-shadow var(--tr);animation:fadeIn 0.2s ease;position:relative;}
.block-card:hover{border-color:var(--surface3);}
.block-card.dragging{opacity:0.3;}
.block-card.drag-over{border-color:var(--accent);box-shadow:0 0 0 2px rgba(108,142,245,0.2);}
.block-header{display:flex;align-items:center;gap:10px;padding:10px 14px 10px 12px;background:var(--surface2);border-bottom:1px solid var(--border);cursor:grab;user-select:none;}
.block-header:active{cursor:grabbing;}
.block-type-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.dot-text{background:var(--c-text);}.dot-image{background:var(--c-image);}.dot-video{background:var(--c-video);}.dot-audio{background:var(--c-audio);}.dot-diagram{background:var(--c-diagram);}
.block-type-label{font-family:'Syne',sans-serif;font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;}
.label-text{color:var(--c-text);}.label-image{color:var(--c-image);}.label-video{color:var(--c-video);}.label-audio{color:var(--c-audio);}.label-diagram{color:var(--c-diagram);}
.block-drag-handle{display:flex;gap:3px;margin-left:auto;opacity:0.4;}
.block-drag-handle span{display:block;width:3px;height:3px;background:var(--text-muted);border-radius:50%;}
.block-card:hover .block-drag-handle{opacity:0.8;}
.block-actions{display:flex;gap:4px;}
.btn-icon{background:none;border:1px solid transparent;padding:4px 7px;border-radius:var(--radius-sm);cursor:pointer;font-size:0.78rem;color:var(--text-dim);transition:var(--tr);}
.btn-icon:hover{background:var(--surface3);color:var(--text);border-color:var(--border);}
.btn-icon.danger:hover{color:var(--danger);border-color:rgba(245,101,101,0.3);}
.btn-icon.save:hover{color:var(--accent2);border-color:rgba(62,207,142,0.3);}
.block-body{padding:16px 18px;}
.rich-toolbar{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px;padding:6px 8px;background:var(--surface3);border:1px solid var(--border);border-radius:var(--radius-sm);}
.rich-btn{background:none;border:none;color:var(--text-muted);padding:3px 7px;border-radius:4px;cursor:pointer;font-size:0.8rem;transition:var(--tr);}
.rich-btn:hover{background:var(--surface2);color:var(--text);}
.rich-content{min-height:100px;background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:12px 14px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.9rem;line-height:1.65;outline:none;transition:border-color var(--tr);}
.rich-content:focus{border-color:var(--c-text);}
.rich-content h1,.rich-content h2,.rich-content h3{color:var(--text);font-family:'Syne',sans-serif;margin:8px 0 4px;}
.rich-content ul,.rich-content ol{padding-left:20px;}
.rich-content code{background:var(--surface3);padding:1px 5px;border-radius:3px;font-family:'JetBrains Mono',monospace;font-size:0.85em;color:var(--accent3);}
.rich-content blockquote{border-left:3px solid var(--accent);padding-left:12px;color:var(--text-muted);font-style:italic;}
.image-upload-area{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:24px;text-align:center;cursor:pointer;transition:var(--tr);color:var(--text-muted);}
.image-upload-area:hover{border-color:var(--c-image);color:var(--c-image);background:rgba(62,207,142,0.04);}
.image-upload-area input{display:none;}
.image-upload-area i{font-size:1.5rem;margin-bottom:8px;display:block;}
.image-preview{max-width:100%;max-height:320px;border-radius:var(--radius-sm);display:block;margin:0 auto;}
.image-caption{width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);padding:7px 12px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.82rem;outline:none;margin-top:8px;}
.url-input-wrap{display:flex;gap:8px;align-items:flex-start;flex-direction:column;}
.url-input{width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.87rem;outline:none;transition:border-color var(--tr);}
.url-input:focus{border-color:var(--c-video);}
.video-preview{margin-top:12px;border-radius:var(--radius-sm);overflow:hidden;background:#000;aspect-ratio:16/9;display:none;}
.video-preview iframe{width:100%;height:100%;border:none;}
.audio-upload-area{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:20px;text-align:center;cursor:pointer;transition:var(--tr);color:var(--text-muted);}
.audio-upload-area:hover{border-color:var(--c-audio);color:var(--c-audio);background:rgba(245,166,35,0.04);}
.audio-upload-area input{display:none;}
.audio-upload-area i{font-size:1.4rem;margin-bottom:6px;display:block;}
.audio-player{width:100%;margin-top:10px;accent-color:var(--c-audio);}
.diagram-upload-area{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:24px;text-align:center;cursor:pointer;transition:var(--tr);color:var(--text-muted);}
.diagram-upload-area:hover{border-color:var(--c-diagram);color:var(--c-diagram);background:rgba(232,121,249,0.04);}
.diagram-upload-area input{display:none;}
.diagram-upload-area i{font-size:1.5rem;margin-bottom:8px;display:block;}
.diagram-preview{max-width:100%;max-height:360px;border-radius:var(--radius-sm);display:block;margin:0 auto;}
.diagram-caption{width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);padding:7px 12px;border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;font-size:0.82rem;outline:none;margin-top:8px;}
.save-status{font-size:0.75rem;padding:3px 9px;border-radius:999px;font-weight:500;}
.save-status.saved{background:rgba(62,207,142,0.12);color:var(--accent2);border:1px solid rgba(62,207,142,0.25);}
.save-status.saving{background:rgba(108,142,245,0.12);color:var(--accent);border:1px solid rgba(108,142,245,0.25);}
.save-status.unsaved{background:rgba(245,166,35,0.12);color:var(--accent3);border:1px solid rgba(245,166,35,0.25);}
.editor-placeholder{flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;color:var(--text-dim);}
.editor-placeholder i{font-size:2.8rem;opacity:0.3;}
.editor-placeholder h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--text-muted);}
.editor-placeholder p{font-size:0.83rem;max-width:280px;text-align:center;}
.lesson-titlebar{background:var(--surface2);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;gap:14px;flex-shrink:0;}
.lesson-num-pill{font-family:'JetBrains Mono',monospace;font-size:0.7rem;background:rgba(108,142,245,0.12);color:var(--accent);border:1px solid rgba(108,142,245,0.25);padding:3px 9px;border-radius:999px;white-space:nowrap;}
.lesson-title-text{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--text);flex:1;}
.lesson-module-tag{font-size:0.75rem;color:var(--text-dim);background:var(--surface3);padding:3px 9px;border-radius:999px;}
.block-count-tag{font-size:0.73rem;color:var(--text-dim);}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}
#toast{position:fixed;bottom:24px;right:24px;z-index:999;display:flex;flex-direction:column;gap:8px;pointer-events:none;}
.toast-item{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 16px;font-size:0.83rem;color:var(--text);box-shadow:var(--shadow);display:flex;align-items:center;gap:9px;animation:toastIn 0.22s ease,toastOut 0.22s ease 2.6s forwards;max-width:300px;}
.toast-item.success{border-left:3px solid var(--accent2);}.toast-item.error{border-left:3px solid var(--danger);}.toast-item.info{border-left:3px solid var(--accent);}
@keyframes toastIn{from{opacity:0;transform:translateX(16px);}to{opacity:1;transform:translateX(0);}}
@keyframes toastOut{from{opacity:1;}to{opacity:0;transform:translateX(16px);}}
.spinner{width:14px;height:14px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 0.6s linear infinite;display:inline-block;}
@keyframes spin{to{transform:rotate(360deg);}}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-track{background:transparent;}::-webkit-scrollbar-thumb{background:var(--surface3);border-radius:3px;}
</style>
</head>
<body>

<header class="topbar">
    <div class="topbar-brand">UNILIS <span>Lesson Editor</span></div>
    <div class="topbar-right">
        <?php if ($unit_id): ?>
            <a href="course_builder.php?unit_id=<?= $unit_id ?>" class="btn-nav"><i class="fas fa-sitemap"></i> Course Builder</a>
        <?php endif; ?>
        <a href="assessment_builder.php<?= $unit_id ? "?unit_id=$unit_id" : '' ?>" class="btn-nav"><i class="fas fa-tasks"></i> Assessments</a>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">
    <aside class="left-panel">
        <div class="panel-header">
            <label><i class="fas fa-book"></i> &nbsp;Unit</label>
            <select class="styled-select" id="unit-select" onchange="switchUnit(this.value)">
                <option value="">— select unit —</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $unit_id == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lesson-tree" id="lesson-tree">
            <?php if (empty($modules_with_lessons)): ?>
                <div style="padding:20px;color:var(--text-dim);font-size:0.82rem;text-align:center">
                    <?= $unit_id ? 'No modules yet. <a href="course_builder.php?unit_id='.$unit_id.'" style="color:var(--accent)">Add modules first</a>.' : 'Select a unit to see lessons.' ?>
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
                                    <a class="lesson-link <?= $lesson_id == $lesson['id'] ? 'active' : '' ?>"
                                       href="lesson_editor.php?unit_id=<?= $unit_id ?>&lesson_id=<?= $lesson['id'] ?>">
                                        <span class="lesson-num-badge">L<?= $lesson['lesson_number'] ?></span>
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
            <div class="lesson-titlebar">
                <span class="lesson-num-pill">Lesson <?= $current_lesson['lesson_number'] ?></span>
                <span class="lesson-title-text"><?= htmlspecialchars($current_lesson['title']) ?></span>
                <span class="lesson-module-tag"><?= htmlspecialchars($current_lesson['module_title']) ?></span>
                <span class="block-count-tag" id="block-count"><?= count($content_blocks) ?> block<?= count($content_blocks) !== 1 ? 's' : '' ?></span>
            </div>
            <div class="editor-toolbar">
                <span class="toolbar-label">Add Block</span>
                <button class="block-btn" data-type="text"    onclick="addBlock('text')"><i class="fas fa-align-left" style="color:var(--c-text)"></i> Text</button>
                <button class="block-btn" data-type="image"   onclick="addBlock('image')"><i class="fas fa-image" style="color:var(--c-image)"></i> Image</button>
                <button class="block-btn" data-type="video"   onclick="addBlock('video')"><i class="fas fa-video" style="color:var(--c-video)"></i> Video</button>
                <button class="block-btn" data-type="audio"   onclick="addBlock('audio')"><i class="fas fa-music" style="color:var(--c-audio)"></i> Audio</button>
                <button class="block-btn" data-type="diagram" onclick="addBlock('diagram')"><i class="fas fa-diagram-project" style="color:var(--c-diagram)"></i> Diagram</button>
                <div class="toolbar-sep"></div>
                <button class="block-btn" onclick="saveAllBlocks()" style="border-color:rgba(62,207,142,0.4)"><i class="fas fa-floppy-disk" style="color:var(--accent2)"></i> Save All</button>
            </div>
            <div class="blocks-canvas">
                <div class="canvas-inner" id="blocks-container"></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="toast"></div>

<script>
const LESSON_ID = <?= $lesson_id ?: 'null' ?>;
const UNIT_ID   = <?= $unit_id   ?: 'null' ?>;
const EXISTING_BLOCKS = <?= json_encode($content_blocks) ?>;

let blocks = [], dragSrc = null, blockCounter = 0;

document.addEventListener('DOMContentLoaded', () => {
    // ── KEY FIX: guard against missing container (no lesson selected) ──
    const container = document.getElementById('blocks-container');
    if (!container || !LESSON_ID) return;

    if (EXISTING_BLOCKS.length === 0) {
        container.innerHTML = `
            <div id="empty-canvas" style="text-align:center;padding:48px 20px;color:var(--text-dim)">
                <i class="fas fa-layer-group" style="font-size:2rem;margin-bottom:12px;display:block;opacity:0.3"></i>
                <p style="font-size:0.85rem">No content yet. Click a block type above to start building.</p>
            </div>`;
    } else {
        EXISTING_BLOCKS.forEach(b => {
            const localId = ++blockCounter;
            blocks.push({ localId, dbId: b.id, type: b.block_type, content: b.content, saved: true });
            container.appendChild(buildBlockCard(localId, b.block_type, b.content, b.id));
        });
    }
    updateBlockCount();
});

function switchUnit(uid) { if (uid) window.location.href = `lesson_editor.php?unit_id=${uid}`; }

function toggleModuleGroup(el) {
    el.classList.toggle('collapsed');
    const lessons = el.nextElementSibling;
    if (lessons) lessons.style.display = el.classList.contains('collapsed') ? 'none' : '';
}

function addBlock(type) {
    if (!LESSON_ID) { toast('Select a lesson first', 'error'); return; }
    const empty = document.getElementById('empty-canvas');
    if (empty) empty.remove();
    const localId = ++blockCounter;
    blocks.push({ localId, dbId: null, type, content: '', saved: false });
    const container = document.getElementById('blocks-container');
    if (!container) return;
    const card = buildBlockCard(localId, type, '', null);
    container.appendChild(card);
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    updateBlockCount();
}

function buildBlockCard(localId, type, content, dbId) {
    const card = document.createElement('div');
    card.className = 'block-card';
    card.dataset.lid = localId;
    card.draggable = true;
    const icons  = { text:'fa-align-left', image:'fa-image', video:'fa-video', audio:'fa-music', diagram:'fa-diagram-project' };
    const labels = { text:'Text', image:'Image', video:'Video', audio:'Audio', diagram:'Diagram' };
    card.innerHTML = `
        <div class="block-header">
            <div class="block-type-dot dot-${type}"></div>
            <span class="block-type-label label-${type}"><i class="fas ${icons[type]}"></i> ${labels[type]}</span>
            <div style="flex:1"></div>
            <span class="save-status ${dbId ? 'saved' : 'unsaved'}" id="status-${localId}">${dbId ? '✓ Saved' : '● Unsaved'}</span>
            <div class="block-actions" style="margin-left:10px">
                <button class="btn-icon save"   onclick="saveBlock(${localId})"   title="Save"><i class="fas fa-floppy-disk"></i></button>
                <button class="btn-icon danger" onclick="deleteBlock(${localId})" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
            <div class="block-drag-handle" style="margin-left:8px">
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
        </div>
        <div class="block-body" id="body-${localId}">${buildBlockBody(localId, type, content)}</div>
    `;
    card.addEventListener('dragstart', e => { dragSrc = card; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
    card.addEventListener('dragend',   () => { card.classList.remove('dragging'); document.querySelectorAll('.block-card').forEach(c => c.classList.remove('drag-over')); dragSrc = null; });
    card.addEventListener('dragover',  e => { e.preventDefault(); if (card !== dragSrc) card.classList.add('drag-over'); });
    card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
    card.addEventListener('drop', e => {
        e.preventDefault(); card.classList.remove('drag-over');
        if (!dragSrc || dragSrc === card) return;
        const c = document.getElementById('blocks-container');
        const cards = [...c.querySelectorAll('.block-card')];
        const si = cards.indexOf(dragSrc), di = cards.indexOf(card);
        if (si < di) c.insertBefore(dragSrc, card.nextSibling); else c.insertBefore(dragSrc, card);
        saveBlockOrder();
    });
    return card;
}

function buildBlockBody(localId, type, content) {
    if (type === 'text')    return buildTextBody(localId, content);
    if (type === 'image')   return buildImageBody(localId, content);
    if (type === 'video')   return buildVideoBody(localId, content);
    if (type === 'audio')   return buildAudioBody(localId, content);
    if (type === 'diagram') return buildDiagramBody(localId, content);
    return '';
}

function buildTextBody(localId, content) {
    return `
        <div class="rich-toolbar">
            <button class="rich-btn" onclick="fmt('bold')"                title="Bold"><b>B</b></button>
            <button class="rich-btn" onclick="fmt('italic')"              title="Italic"><i>I</i></button>
            <button class="rich-btn" onclick="fmt('underline')"           title="Underline"><u>U</u></button>
            <button class="rich-btn" onclick="fmtBlock('h2')"             title="Heading">H2</button>
            <button class="rich-btn" onclick="fmtBlock('h3')"             title="Sub-heading">H3</button>
            <button class="rich-btn" onclick="fmt('insertUnorderedList')" title="Bullets"><i class="fas fa-list-ul"></i></button>
            <button class="rich-btn" onclick="fmt('insertOrderedList')"   title="Numbers"><i class="fas fa-list-ol"></i></button>
            <button class="rich-btn" onclick="fmtBlock('blockquote')"     title="Quote"><i class="fas fa-quote-left"></i></button>
            <button class="rich-btn" onclick="insertCode(${localId})"     title="Code"><i class="fas fa-code"></i></button>
        </div>
        <div class="rich-content" id="rc-${localId}" contenteditable="true"
             oninput="markUnsaved(${localId})" onfocus="setActiveEditor(${localId})">${content || ''}</div>`;
}

function buildImageBody(localId, content) {
    let imgSrc = '', caption = '';
    try { const d = JSON.parse(content||'{}'); imgSrc = d.src||''; caption = d.caption||''; } catch(e) {}
    return `
        <div class="image-upload-area" id="img-area-${localId}" onclick="document.getElementById('img-file-${localId}').click()">
            <input type="file" id="img-file-${localId}" accept="image/*" onchange="handleImageUpload(event,${localId})">
            ${imgSrc ? `<img src="${escHtml(imgSrc)}" class="image-preview">` : `<i class="fas fa-image"></i><div>Click to upload image</div><small>JPG, PNG, GIF, WebP</small>`}
        </div>
        <input type="text" class="image-caption" id="img-caption-${localId}" value="${escAttr(caption)}" placeholder="Caption (optional)" oninput="markUnsaved(${localId})">`;
}

function buildVideoBody(localId, content) {
    let url = '', embed = '';
    try { const d = JSON.parse(content||'{}'); url = d.url||''; embed = d.embed||''; } catch(e) { url = content||''; }
    return `
        <div class="url-input-wrap">
            <label style="font-size:0.78rem;color:var(--text-muted)">YouTube or Vimeo URL</label>
            <input type="url" class="url-input" id="vid-url-${localId}" value="${escAttr(url)}" placeholder="https://www.youtube.com/watch?v=..." oninput="handleVideoUrl(${localId})">
        </div>
        <div class="video-preview" id="vid-preview-${localId}" style="${embed ? 'display:block' : 'display:none'}">
            ${embed ? `<iframe src="${escHtml(embed)}" allowfullscreen></iframe>` : ''}
        </div>`;
}

function buildAudioBody(localId, content) {
    let src = '', name = '';
    try { const d = JSON.parse(content||'{}'); src = d.src||''; name = d.name||''; } catch(e) {}
    return `
        <div class="audio-upload-area" onclick="document.getElementById('aud-file-${localId}').click()">
            <input type="file" id="aud-file-${localId}" accept="audio/*" onchange="handleAudioUpload(event,${localId})">
            <i class="fas fa-music"></i>
            <div id="aud-label-${localId}">${src ? escHtml(name||'Audio file loaded') : 'Click to upload audio file'}</div>
            <small>MP3, WAV, OGG, M4A</small>
        </div>
        ${src ? `<audio class="audio-player" id="aud-player-${localId}" controls src="${escHtml(src)}"></audio>` : `<audio class="audio-player" id="aud-player-${localId}" controls style="display:none"></audio>`}`;
}

function buildDiagramBody(localId, content) {
    let imgSrc = '', caption = '';
    try { const d = JSON.parse(content||'{}'); imgSrc = d.src||''; caption = d.caption||''; } catch(e) {}
    return `
        <div class="diagram-upload-area" id="diag-area-${localId}" onclick="document.getElementById('diag-file-${localId}').click()">
            <input type="file" id="diag-file-${localId}" accept="image/*,application/pdf" onchange="handleDiagramUpload(event,${localId})">
            ${imgSrc ? `<img src="${escHtml(imgSrc)}" class="diagram-preview">` : `<i class="fas fa-diagram-project"></i><div>Click to upload diagram</div><small>PNG, SVG, JPG, PDF</small>`}
        </div>
        <input type="text" class="diagram-caption" id="diag-caption-${localId}" value="${escAttr(caption)}" placeholder="Diagram description (optional)" oninput="markUnsaved(${localId})">`;
}

let activeEditorId = null;
function setActiveEditor(lid) { activeEditorId = lid; }
function fmt(cmd) { document.execCommand(cmd, false, null); if (activeEditorId) markUnsaved(activeEditorId); }
function fmtBlock(tag) { document.execCommand('formatBlock', false, tag); if (activeEditorId) markUnsaved(activeEditorId); }
function insertCode(lid) {
    const sel = window.getSelection();
    if (sel && sel.toString()) document.execCommand('insertHTML', false, `<code>${sel.toString()}</code>`);
    markUnsaved(lid);
}

function handleVideoUrl(localId) {
    const url = document.getElementById(`vid-url-${localId}`).value.trim();
    const preview = document.getElementById(`vid-preview-${localId}`);
    markUnsaved(localId);
    let embed = '';
    const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (yt) embed = `https://www.youtube.com/embed/${yt[1]}?rel=0`;
    const vm = url.match(/vimeo\.com\/(\d+)/);
    if (vm) embed = `https://player.vimeo.com/video/${vm[1]}`;
    if (embed) {
        preview.style.display = 'block';
        preview.innerHTML = `<iframe src="${embed}" allowfullscreen></iframe>`;
        const b = blocks.find(b => b.localId === localId);
        if (b) b._embedUrl = embed;
    } else { preview.style.display = 'none'; }
}

function handleImageUpload(event, localId) {
    const file = event.target.files[0]; if (!file) return;
    const area = document.getElementById(`img-area-${localId}`);
    area.innerHTML = `<div class="spinner"></div><div style="margin-top:8px;font-size:0.8rem">Uploading...</div>`;
    uploadFile(file, 'course_images', localId, path => {
        area.innerHTML = `<img src="../${path}" class="image-preview"><input type="file" id="img-file-${localId}" accept="image/*" onchange="handleImageUpload(event,${localId})" style="display:none">`;
        area.onclick = () => document.getElementById(`img-file-${localId}`).click();
        const b = blocks.find(b => b.localId === localId); if (b) b._uploadedPath = path;
        markUnsaved(localId);
    });
}

function handleAudioUpload(event, localId) {
    const file = event.target.files[0]; if (!file) return;
    const label = document.getElementById(`aud-label-${localId}`);
    const player = document.getElementById(`aud-player-${localId}`);
    label.textContent = 'Uploading...';
    uploadFile(file, 'course_audio', localId, path => {
        label.textContent = file.name; player.src = `../${path}`; player.style.display = 'block';
        const b = blocks.find(b => b.localId === localId); if (b) b._uploadedPath = path;
        markUnsaved(localId);
    });
}

function handleDiagramUpload(event, localId) {
    const file = event.target.files[0]; if (!file) return;
    const area = document.getElementById(`diag-area-${localId}`);
    area.innerHTML = `<div class="spinner"></div><div style="margin-top:8px;font-size:0.8rem">Uploading...</div>`;
    uploadFile(file, 'course_diagrams', localId, path => {
        area.innerHTML = `<img src="../${path}" class="diagram-preview"><input type="file" id="diag-file-${localId}" accept="image/*,application/pdf" onchange="handleDiagramUpload(event,${localId})" style="display:none">`;
        area.onclick = () => document.getElementById(`diag-file-${localId}`).click();
        const b = blocks.find(b => b.localId === localId); if (b) b._uploadedPath = path;
        markUnsaved(localId);
    });
}

function uploadFile(file, folder, localId, callback) {
    const fd = new FormData();
    fd.append('file', file); fd.append('folder', folder); fd.append('lesson_id', LESSON_ID);
    fetch('ajax/upload_block_file.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) callback(d.path); else toast(d.message || 'Upload failed', 'error'); })
        .catch(() => toast('Upload error', 'error'));
}

function saveBlock(localId) {
    const b = blocks.find(b => b.localId === localId); if (!b) return;
    const content = extractBlockContent(localId, b.type, b);
    setStatus(localId, 'saving');
    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID); fd.append('block_type', b.type); fd.append('content', content);
    if (b.dbId) fd.append('block_id', b.dbId);
    const cards = [...document.querySelectorAll('#blocks-container .block-card')];
    fd.append('position', cards.findIndex(c => parseInt(c.dataset.lid) === localId));
    fetch('ajax/save_content_block.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { b.dbId = d.block_id; b.content = content; b.saved = true; setStatus(localId, 'saved'); toast('Block saved', 'success'); }
            else { setStatus(localId, 'unsaved'); toast(d.message, 'error'); }
        })
        .catch(() => { setStatus(localId, 'unsaved'); toast('Save failed', 'error'); });
}

function extractBlockContent(localId, type, block) {
    if (type === 'text') { const el = document.getElementById(`rc-${localId}`); return el ? el.innerHTML : ''; }
    if (type === 'image') {
        const caption = document.getElementById(`img-caption-${localId}`)?.value || '';
        const src = block._uploadedPath ? `../${block._uploadedPath}` : (block.content ? JSON.parse(block.content||'{}').src||'' : '');
        return JSON.stringify({ src, caption });
    }
    if (type === 'video') {
        const url = document.getElementById(`vid-url-${localId}`)?.value || '';
        const embed = block._embedUrl || (block.content ? JSON.parse(block.content||'{}').embed||'' : '');
        return JSON.stringify({ url, embed });
    }
    if (type === 'audio') {
        const src = block._uploadedPath ? `../${block._uploadedPath}` : (block.content ? JSON.parse(block.content||'{}').src||'' : '');
        const name = document.getElementById(`aud-label-${localId}`)?.textContent || '';
        return JSON.stringify({ src, name });
    }
    if (type === 'diagram') {
        const caption = document.getElementById(`diag-caption-${localId}`)?.value || '';
        const src = block._uploadedPath ? `../${block._uploadedPath}` : (block.content ? JSON.parse(block.content||'{}').src||'' : '');
        return JSON.stringify({ src, caption });
    }
    return '';
}

function saveAllBlocks() {
    const unsaved = blocks.filter(b => !b.saved);
    if (!unsaved.length) { toast('All blocks already saved', 'info'); return; }
    unsaved.forEach(b => saveBlock(b.localId));
}

function deleteBlock(localId) {
    const b = blocks.find(b => b.localId === localId); if (!b) return;
    if (!confirm('Delete this block?')) return;
    const card = document.querySelector(`.block-card[data-lid="${localId}"]`);
    if (!b.dbId) { blocks = blocks.filter(b => b.localId !== localId); card?.remove(); updateBlockCount(); return; }
    const fd = new FormData();
    fd.append('block_id', b.dbId); fd.append('lesson_id', LESSON_ID);
    fetch('ajax/delete_content_block.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { blocks = blocks.filter(b => b.localId !== localId); card?.remove(); updateBlockCount(); toast('Block deleted', 'success'); }
            else toast(d.message, 'error');
        })
        .catch(() => toast('Delete failed', 'error'));
}

function saveBlockOrder() {
    const ids = [...document.querySelectorAll('#blocks-container .block-card')]
        .map(c => { const b = blocks.find(b => b.localId === parseInt(c.dataset.lid)); return b?.dbId||null; })
        .filter(id => id !== null);
    if (!ids.length) return;
    const fd = new FormData();
    fd.append('lesson_id', LESSON_ID); fd.append('order', JSON.stringify(ids));
    fetch('ajax/reorder_blocks.php', { method: 'POST', body: fd }).catch(() => {});
}

function markUnsaved(localId) { const b = blocks.find(b => b.localId === localId); if (b) b.saved = false; setStatus(localId, 'unsaved'); }
function setStatus(localId, state) {
    const el = document.getElementById(`status-${localId}`); if (!el) return;
    el.className = `save-status ${state}`;
    el.textContent = state === 'saved' ? '✓ Saved' : state === 'saving' ? '⟳ Saving...' : '● Unsaved';
}
function updateBlockCount() { const el = document.getElementById('block-count'); if (el) el.textContent = `${blocks.length} block${blocks.length !== 1 ? 's' : ''}`; }

function toast(msg, type = 'info') {
    const c = document.getElementById('toast'); if (!c) return;
    const e = document.createElement('div');
    e.className = `toast-item ${type}`;
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', info:'fa-circle-info' };
    e.innerHTML = `<i class="fas ${icons[type]||'fa-circle-info'}"></i> ${escHtml(msg)}`;
    c.appendChild(e); setTimeout(() => e.remove(), 2900);
}
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(s) { return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
</script>
</body>
</html>