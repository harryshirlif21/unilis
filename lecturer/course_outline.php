<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Fetch units assigned to this lecturer
$units = [];
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
    error_log("course_outline unit fetch: " . $e->getMessage());
}

// Pre-select unit if passed via GET
$selected_unit_id = intval($_GET['unit_id'] ?? 0);
$outline_data     = null;

if ($selected_unit_id) {
    try {
        $stmt = $conn->prepare("
            SELECT description, outline, updated_at
            FROM course_outlines
            WHERE unit_id = ? AND lecturer_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $selected_unit_id, $lecturer_id);
        $stmt->execute();
        $outline_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("course_outline fetch: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Course Outline — UNILIS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg:         #0d0f14;
    --surface:    #161921;
    --surface2:   #1e2230;
    --surface3:   #262c3d;
    --border:     #2a3148;
    --accent:     #4f8ef7;
    --accent2:    #38d9a9;
    --danger:     #f75f5f;
    --text:       #e8eaf0;
    --text-muted: #7a82a0;
    --text-dim:   #4a5270;
    --shadow:     0 4px 24px rgba(0,0,0,0.4);
    --radius:     10px;
    --radius-sm:  6px;
    --transition: 0.18s ease;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* TOPBAR */
.topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 32px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
}
.topbar-brand {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: 0.04em;
    color: var(--accent);
}
.topbar-brand span { color: var(--text-muted); font-weight: 400; margin-left: 8px; font-size: 0.85rem; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.btn-nav {
    background: var(--surface3);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
    font-family: 'DM Sans', sans-serif;
}
.btn-nav:hover { background: var(--surface2); color: var(--text); }

/* LAYOUT */
.layout {
    max-width: 900px;
    margin: 0 auto;
    padding: 36px 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* PAGE HEADER */
.page-header h1 {
    font-family: 'Syne', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 6px;
}
.page-header p {
    font-size: 0.88rem;
    color: var(--text-muted);
}

/* CARD */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 28px 32px;
}

/* FORM */
.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.09em;
}
.styled-select, .form-input, .form-textarea {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 11px 14px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    outline: none;
    transition: border-color var(--transition);
}
.styled-select:focus, .form-input:focus, .form-textarea:focus { border-color: var(--accent); }
.styled-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%237a82a0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
    cursor: pointer;
}
.form-textarea { resize: vertical; min-height: 120px; line-height: 1.6; }
.char-count { font-size: 0.72rem; color: var(--text-dim); text-align: right; margin-top: 4px; }

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: var(--transition);
    text-decoration: none;
}
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #3a7ce8; transform: translateY(-1px); }
.btn-success { background: var(--accent2); color: #0d1a15; }
.btn-success:hover { background: #2ec99a; transform: translateY(-1px); }
.btn-ghost {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-muted);
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }

.form-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 8px;
}

/* STATUS BADGE */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.badge-set   { background: rgba(56,217,169,0.15); color: var(--accent2); border: 1px solid rgba(56,217,169,0.3); }
.badge-unset { background: rgba(247,95,95,0.12);  color: var(--danger);  border: 1px solid rgba(247,95,95,0.3); }
.badge-saved { background: rgba(79,142,247,0.12); color: var(--accent);  border: 1px solid rgba(79,142,247,0.3); }

/* LAST SAVED */
.last-saved {
    font-size: 0.78rem;
    color: var(--text-dim);
    margin-left: auto;
}

/* TOAST */
#toast {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
}
.toast-item {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 18px;
    font-size: 0.85rem;
    color: var(--text);
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 10px;
    animation: toastIn 0.25s ease, toastOut 0.25s ease 2.5s forwards;
}
.toast-item.success { border-left: 3px solid var(--accent2); }
.toast-item.error   { border-left: 3px solid var(--danger); }

.spinner {
    width: 16px; height: 16px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    display: inline-block;
}

@keyframes toastIn  { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
@keyframes toastOut { from { opacity: 1; } to { opacity: 0; transform: translateX(20px); } }
@keyframes spin     { to   { transform: rotate(360deg); } }
@keyframes fadeIn   { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.card { animation: fadeIn 0.3s ease; }

/* PLACEHOLDER */
.placeholder-card {
    background: var(--surface);
    border: 1px dashed var(--border);
    border-radius: var(--radius);
    padding: 60px;
    text-align: center;
    color: var(--text-dim);
}
.placeholder-card i { font-size: 2.5rem; margin-bottom: 16px; opacity: 0.4; }
.placeholder-card p { font-size: 0.88rem; }
</style>
</head>
<body>

<header class="topbar">
    <div class="topbar-brand">UNILIS <span>Course Outline</span></div>
    <div class="topbar-right">
        <span style="font-size:0.82rem;color:var(--text-muted)"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($lecturer_name) ?></span>
        <a href="course_builder.php" class="btn-nav"><i class="fas fa-layer-group"></i> Course Builder</a>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <div class="page-header">
        <h1><i class="fas fa-align-left" style="color:var(--accent);margin-right:10px"></i>Course Outline Editor</h1>
        <p>Set the description and syllabus for each unit. This is displayed to students on their course view.</p>
    </div>

    <!-- Unit Selector -->
    <div class="card">
        <div class="form-group" style="margin-bottom:0">
            <label><i class="fas fa-book"></i> &nbsp;Select Unit</label>
            <select class="styled-select" id="unit-select" onchange="loadOutline(this.value)">
                <option value="">— choose a unit —</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>"
                        <?= $selected_unit_id === $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Outline Editor (shown after unit selected) -->
    <div id="outline-editor" style="display:<?= $selected_unit_id ? 'block' : 'none' ?>">
        <div class="card">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
                <h2 style="font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700">
                    <i class="fas fa-pen-to-square" style="color:var(--accent);margin-right:8px"></i>
                    Edit Outline
                </h2>
                <span class="status-badge" id="outline-status-badge">
                    <?php if ($outline_data): ?>
                        <i class="fas fa-circle-check"></i> Outline set
                    <?php else: ?>
                        <i class="fas fa-circle-xmark"></i> Not set
                    <?php endif; ?>
                </span>
                <?php if ($outline_data && $outline_data['updated_at']): ?>
                    <span class="last-saved" id="last-saved">
                        Last saved: <?= htmlspecialchars($outline_data['updated_at']) ?>
                    </span>
                <?php else: ?>
                    <span class="last-saved" id="last-saved"></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Course Description <span style="color:var(--text-dim);font-weight:400;text-transform:none;letter-spacing:0">(shown at top of student course view)</span></label>
                <textarea class="form-textarea" id="outline-description"
                          placeholder="Brief overview of what students will learn in this unit..."
                          oninput="charCount(this,'desc-count',600)"
                          style="min-height:90px"><?= htmlspecialchars($outline_data['description'] ?? '') ?></textarea>
                <div class="char-count"><span id="desc-count"><?= strlen($outline_data['description'] ?? '') ?></span>/600</div>
            </div>

            <div class="form-group">
                <label>Course Outline / Syllabus <span style="color:var(--text-dim);font-weight:400;text-transform:none;letter-spacing:0">(weekly breakdown, topics, objectives)</span></label>
                <textarea class="form-textarea" id="outline-content"
                          placeholder="Week 1: Introduction to the course&#10;Week 2: Core concepts&#10;Week 3: Practical applications&#10;..."
                          oninput="charCount(this,'outline-count',3000)"
                          style="min-height:200px"><?= htmlspecialchars($outline_data['outline'] ?? '') ?></textarea>
                <div class="char-count"><span id="outline-count"><?= strlen($outline_data['outline'] ?? '') ?></span>/3000</div>
            </div>

            <div class="form-actions">
                <button class="btn btn-success" id="save-btn" onclick="saveOutline()">
                    <i class="fas fa-save"></i> Save Outline
                </button>
                <button class="btn btn-ghost" onclick="clearForm()">
                    <i class="fas fa-rotate-left"></i> Reset
                </button>
                <a id="link-go-builder" href="#" class="btn btn-ghost" style="margin-left:auto">
                    <i class="fas fa-layer-group"></i> Go to Course Builder
                </a>
            </div>
        </div>
    </div>

    <!-- Placeholder when no unit selected -->
    <div id="outline-placeholder" class="placeholder-card" style="display:<?= $selected_unit_id ? 'none' : 'flex' ?>;flex-direction:column;align-items:center">
        <i class="fas fa-align-left"></i>
        <p>Select a unit above to edit its course outline.</p>
    </div>

</div>

<div id="toast"></div>

<script>
let currentUnitId = <?= $selected_unit_id ?: 'null' ?>;
let originalDesc    = <?= json_encode($outline_data['description'] ?? '') ?>;
let originalOutline = <?= json_encode($outline_data['outline'] ?? '') ?>;

// If unit pre-selected, update builder link
if (currentUnitId) updateBuilderLink(currentUnitId);

function loadOutline(unitId) {
    currentUnitId = unitId || null;
    if (!currentUnitId) {
        document.getElementById('outline-editor').style.display      = 'none';
        document.getElementById('outline-placeholder').style.display = 'flex';
        return;
    }
    updateBuilderLink(unitId);

    // Fetch from server
    fetch(`ajax/get_course_tree.php?unit_id=${unitId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { toast(data.message, 'error'); return; }

            const outline = data.outline || null;
            const desc    = outline ? (outline.description || '') : '';
            const content = outline ? (outline.outline     || '') : '';

            document.getElementById('outline-description').value = desc;
            document.getElementById('outline-content').value     = content;
            charCount(document.getElementById('outline-description'), 'desc-count',    600);
            charCount(document.getElementById('outline-content'),     'outline-count', 3000);

            originalDesc    = desc;
            originalOutline = content;

            updateStatusBadge(!!desc);
            document.getElementById('last-saved').textContent = '';

            document.getElementById('outline-editor').style.display      = 'block';
            document.getElementById('outline-placeholder').style.display = 'none';
        })
        .catch(() => toast('Failed to load outline', 'error'));
}

function saveOutline() {
    if (!currentUnitId) { toast('Select a unit first', 'error'); return; }

    const desc    = document.getElementById('outline-description').value.trim();
    const content = document.getElementById('outline-content').value.trim();

    const btn = document.getElementById('save-btn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> Saving...';

    const body = new FormData();
    body.append('unit_id',     currentUnitId);
    body.append('lecturer_id', '<?= $lecturer_id ?>');
    body.append('description', desc);
    body.append('outline',     content);

    fetch('ajax/save_course_outline.php', { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                toast('Outline saved successfully', 'success');
                originalDesc    = desc;
                originalOutline = content;
                updateStatusBadge(!!desc);
                const now = new Date().toLocaleString();
                document.getElementById('last-saved').textContent = 'Last saved: ' + now;
            } else {
                toast(d.message || 'Save failed', 'error');
            }
        })
        .catch(() => toast('Network error', 'error'))
        .finally(() => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Outline';
        });
}

function clearForm() {
    document.getElementById('outline-description').value = originalDesc;
    document.getElementById('outline-content').value     = originalOutline;
    charCount(document.getElementById('outline-description'), 'desc-count',    600);
    charCount(document.getElementById('outline-content'),     'outline-count', 3000);
    toast('Reset to last saved version', 'info');
}

function updateStatusBadge(hasContent) {
    const badge = document.getElementById('outline-status-badge');
    if (hasContent) {
        badge.className   = 'status-badge badge-set';
        badge.innerHTML   = '<i class="fas fa-circle-check"></i> Outline set';
    } else {
        badge.className   = 'status-badge badge-unset';
        badge.innerHTML   = '<i class="fas fa-circle-xmark"></i> Not set';
    }
}

function updateBuilderLink(unitId) {
    document.getElementById('link-go-builder').href = `course_builder.php?unit_id=${unitId}`;
}

function charCount(el, countId, max) {
    const len = el.value.length;
    const el2 = document.getElementById(countId);
    el2.textContent = len;
    el2.style.color = len > max * 0.9 ? 'var(--danger)' : '';
}

function toast(msg, type = 'info') {
    const container = document.getElementById('toast');
    const el = document.createElement('div');
    el.className = `toast-item ${type}`;
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    el.innerHTML = `<i class="fas ${icons[type] || 'fa-circle-info'}"></i> ${escHtml(msg)}`;
    container.appendChild(el);
    setTimeout(() => el.remove(), 2800);
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>