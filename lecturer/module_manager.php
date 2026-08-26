<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id   = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

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
    error_log("module_manager unit fetch: " . $e->getMessage());
}

$selected_unit_id = intval($_GET['unit_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Module Manager — UNILIS</title>
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
    --accent3:    #f7934f;
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
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

.topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 32px; height: 60px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
}
.topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.1rem; letter-spacing: 0.04em; color: var(--accent); }
.topbar-brand span { color: var(--text-muted); font-weight: 400; margin-left: 8px; font-size: 0.85rem; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.btn-nav {
    background: var(--surface3); border: 1px solid var(--border); color: var(--text-muted);
    padding: 6px 14px; border-radius: var(--radius-sm); font-size: 0.8rem; cursor: pointer;
    text-decoration: none; transition: var(--transition); font-family: 'DM Sans', sans-serif;
}
.btn-nav:hover { background: var(--surface2); color: var(--text); }

.layout { max-width: 800px; margin: 0 auto; padding: 36px 24px; display: flex; flex-direction: column; gap: 24px; }

.page-header h1 { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
.page-header p  { font-size: 0.88rem; color: var(--text-muted); }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px 28px; }

.form-group { margin-bottom: 0; }
.form-group label {
    display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted);
    margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.09em;
}
.styled-select, .form-input {
    width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text);
    padding: 11px 14px; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem; outline: none; transition: border-color var(--transition);
}
.styled-select:focus, .form-input:focus { border-color: var(--accent); }
.styled-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%237a82a0' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; cursor: pointer;
}

.btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
    border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.85rem;
    font-weight: 500; cursor: pointer; border: none; transition: var(--transition); text-decoration: none;
}
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #3a7ce8; transform: translateY(-1px); }
.btn-danger  { background: var(--danger); color: #fff; }
.btn-danger:hover  { background: #e04040; }
.btn-ghost {
    background: transparent; border: 1px solid var(--border); color: var(--text-muted);
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm { padding: 5px 10px; font-size: 0.78rem; }
.btn-icon { padding: 6px 8px; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }

/* MODULE LIST */
.modules-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
}
.modules-header h3 {
    font-family: 'Syne', sans-serif; font-size: 0.9rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted);
}

#modules-list { display: flex; flex-direction: column; gap: 8px; }

.module-row {
    background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm);
    padding: 12px 16px; display: flex; align-items: center; gap: 12px;
    cursor: grab; transition: border-color var(--transition), background var(--transition);
    animation: fadeIn 0.2s ease;
}
.module-row:hover  { border-color: var(--surface3); }
.module-row.drag-over { border-color: var(--accent); background: rgba(79,142,247,0.06); }
.module-row.dragging  { opacity: 0.35; }
.module-row:active { cursor: grabbing; }

.drag-dots {
    display: flex; flex-direction: column; gap: 3px; padding: 2px 4px; color: var(--text-dim);
}
.drag-dots span { display: block; width: 16px; height: 2px; background: currentColor; border-radius: 2px; }
.module-row:hover .drag-dots { color: var(--accent); }

.mod-pos {
    font-family: 'Syne', sans-serif; font-size: 0.68rem; font-weight: 700; color: var(--accent);
    background: rgba(79,142,247,0.12); border: 1px solid rgba(79,142,247,0.25);
    padding: 2px 7px; border-radius: 999px; min-width: 28px; text-align: center;
}
.mod-title { flex: 1; font-size: 0.9rem; font-weight: 500; color: var(--text); }
.mod-lessons { font-size: 0.78rem; color: var(--text-dim); white-space: nowrap; }
.mod-actions { display: flex; gap: 6px; }

.inline-input {
    flex: 1; background: var(--surface3); border: 1px solid var(--accent2);
    color: var(--text); padding: 5px 10px; border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif; font-size: 0.88rem; outline: none;
}

/* ADD FORM */
.add-row {
    background: var(--surface2); border: 1px dashed var(--border); border-radius: var(--radius-sm);
    padding: 12px 16px; display: flex; align-items: center; gap: 10px;
}

/* EMPTY */
.empty-state {
    text-align: center; padding: 48px 24px; color: var(--text-dim);
}
.empty-state i { font-size: 2rem; margin-bottom: 12px; opacity: 0.4; display: block; }
.empty-state p { font-size: 0.85rem; }

/* PLACEHOLDER */
.placeholder-card {
    background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius);
    padding: 60px; text-align: center; color: var(--text-dim);
    display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.placeholder-card i { font-size: 2.5rem; opacity: 0.4; }

/* SPINNER / TOAST */
.spinner {
    width: 15px; height: 15px; border: 2px solid var(--border); border-top-color: var(--accent);
    border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block;
}
#toast {
    position: fixed; bottom: 28px; right: 28px; z-index: 999;
    display: flex; flex-direction: column; gap: 8px; pointer-events: none;
}
.toast-item {
    background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm);
    padding: 12px 18px; font-size: 0.85rem; color: var(--text); box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 10px;
    animation: toastIn 0.25s ease, toastOut 0.25s ease 2.5s forwards;
}
.toast-item.success { border-left: 3px solid var(--accent2); }
.toast-item.error   { border-left: 3px solid var(--danger); }

@keyframes fadeIn   { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
@keyframes toastIn  { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
@keyframes toastOut { from { opacity: 1; } to { opacity: 0; transform: translateX(20px); } }
@keyframes spin     { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<header class="topbar">
    <div class="topbar-brand">UNILIS <span>Module Manager</span></div>
    <div class="topbar-right">
        <span style="font-size:0.82rem;color:var(--text-muted)"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($lecturer_name) ?></span>
        <a href="course_builder.php" class="btn-nav"><i class="fas fa-layer-group"></i> Course Builder</a>
        <a href="lesson_editor.php" class="btn-nav"><i class="fas fa-pen-nib"></i> Lesson Editor</a>
        <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</header>

<div class="layout">

    <div class="page-header">
        <h1><i class="fas fa-cubes" style="color:var(--accent);margin-right:10px"></i>Module Manager</h1>
        <p>Create, rename, reorder and delete modules (chapters) for each unit. Modules contain lessons.</p>
    </div>

    <!-- Unit Selector -->
    <div class="card">
        <div class="form-group">
            <label><i class="fas fa-book"></i> &nbsp;Select Unit</label>
            <select class="styled-select" id="unit-select" onchange="loadModules(this.value)">
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

    <!-- Module List -->
    <div id="modules-panel" style="display:<?= $selected_unit_id ? 'block' : 'none' ?>">
        <div class="card">
            <div class="modules-header">
                <h3><i class="fas fa-cubes"></i> &nbsp;Modules</h3>
                <span id="mod-count" style="font-size:0.8rem;color:var(--text-dim)"></span>
            </div>

            <div id="modules-list"></div>

            <!-- Add new module row -->
            <div class="add-row" id="add-row" style="margin-top:12px">
                <i class="fas fa-plus-circle" style="color:var(--accent2);font-size:1rem"></i>
                <input type="text" class="form-input" id="new-module-title"
                       placeholder="New module title..." style="flex:1"
                       onkeydown="if(event.key==='Enter') addModule()">
                <button class="btn btn-primary btn-sm" id="add-btn" onclick="addModule()">
                    <i class="fas fa-plus"></i> Add Module
                </button>
            </div>
        </div>
    </div>

    <!-- Placeholder -->
    <div id="modules-placeholder" class="placeholder-card" style="display:<?= $selected_unit_id ? 'none' : 'flex' ?>">
        <i class="fas fa-cubes"></i>
        <p>Select a unit above to manage its modules.</p>
    </div>

</div>

<div id="toast"></div>

<script>
let currentUnitId = <?= $selected_unit_id ?: 'null' ?>;
let modules = [];
let dragSrc = null;

if (currentUnitId) loadModules(currentUnitId);

function loadModules(unitId) {
    currentUnitId = unitId || null;
    if (!currentUnitId) {
        document.getElementById('modules-panel').style.display       = 'none';
        document.getElementById('modules-placeholder').style.display = 'flex';
        return;
    }
    document.getElementById('modules-panel').style.display       = 'block';
    document.getElementById('modules-placeholder').style.display = 'none';
    document.getElementById('modules-list').innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-dim)"><span class="spinner"></span></div>';

    fetch(`ajax/get_course_tree.php?unit_id=${unitId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { toast(data.message, 'error'); return; }
            modules = data.modules || [];
            renderModules();
        })
        .catch(() => toast('Failed to load modules', 'error'));
}

function renderModules() {
    const list = document.getElementById('modules-list');
    document.getElementById('mod-count').textContent = `${modules.length} module${modules.length !== 1 ? 's' : ''}`;

    if (modules.length === 0) {
        list.innerHTML = `<div class="empty-state"><i class="fas fa-cubes"></i><p>No modules yet. Add one below.</p></div>`;
        return;
    }

    list.innerHTML = '';
    modules.forEach((mod, idx) => {
        const row = document.createElement('div');
        row.className   = 'module-row';
        row.draggable   = true;
        row.dataset.id  = mod.id;
        row.innerHTML = `
            <div class="drag-dots"><span></span><span></span><span></span></div>
            <span class="mod-pos">M${idx + 1}</span>
            <span class="mod-title" id="mt-${mod.id}" ondblclick="inlineRename(${mod.id})"
                  title="Double-click to rename">${escHtml(mod.title)}</span>
            <span class="mod-lessons">${(mod.lessons||[]).length} lesson${(mod.lessons||[]).length !== 1 ? 's' : ''}</span>
            <div class="mod-actions">
                <button class="btn btn-ghost btn-sm btn-icon" onclick="inlineRename(${mod.id})" title="Rename">
                    <i class="fas fa-pen" style="color:var(--accent)"></i>
                </button>
                <a href="lesson_editor.php?unit_id=${currentUnitId}" class="btn btn-ghost btn-sm btn-icon" title="Edit Lessons">
                    <i class="fas fa-pen-nib" style="color:var(--accent2)"></i>
                </a>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteModule(${mod.id},'${escAttr(mod.title)}')" title="Delete">
                    <i class="fas fa-trash" style="color:var(--danger)"></i>
                </button>
            </div>
        `;

        // Drag events
        row.addEventListener('dragstart', e => {
            dragSrc = row; row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('dragging');
            list.querySelectorAll('.module-row').forEach(r => r.classList.remove('drag-over'));
        });
        row.addEventListener('dragover', e => {
            e.preventDefault();
            if (row !== dragSrc) row.classList.add('drag-over');
        });
        row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
        row.addEventListener('drop', e => {
            e.preventDefault();
            row.classList.remove('drag-over');
            if (!dragSrc || dragSrc === row) return;
            const rows    = [...list.querySelectorAll('.module-row')];
            const srcIdx  = rows.indexOf(dragSrc);
            const destIdx = rows.indexOf(row);
            if (srcIdx < destIdx) list.insertBefore(dragSrc, row.nextSibling);
            else                  list.insertBefore(dragSrc, row);
            saveOrder();
        });

        list.appendChild(row);
    });
}

function inlineRename(moduleId) {
    const titleEl = document.getElementById(`mt-${moduleId}`);
    const current = titleEl.textContent.trim();
    titleEl.style.display = 'none';

    const input = document.createElement('input');
    input.type      = 'text';
    input.className = 'inline-input';
    input.value     = current;
    titleEl.parentNode.insertBefore(input, titleEl.nextSibling);
    input.focus(); input.select();

    const commit = () => {
        const val = input.value.trim();
        input.remove();
        titleEl.style.display = '';
        if (!val || val === current) return;
        titleEl.textContent = val;
        const mod = modules.find(m => m.id === moduleId);
        if (mod) mod.title = val;

        const body = new FormData();
        body.append('module_id',   moduleId);
        body.append('unit_id',     currentUnitId);
        body.append('lecturer_id', '<?= $lecturer_id ?>');
        body.append('title',       val);
        fetch('ajax/save_module.php', { method: 'POST', body })
            .then(r => r.json())
            .then(d => toast(d.success ? 'Module renamed' : d.message, d.success ? 'success' : 'error'))
            .catch(() => toast('Rename failed', 'error'));
    };

    input.addEventListener('blur', commit);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter')  commit();
        if (e.key === 'Escape') { input.remove(); titleEl.style.display = ''; }
    });
}

function addModule() {
    const input = document.getElementById('new-module-title');
    const title = input.value.trim();
    if (!title) { input.focus(); return; }

    const btn = document.getElementById('add-btn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span>';

    const body = new FormData();
    body.append('unit_id',     currentUnitId);
    body.append('lecturer_id', '<?= $lecturer_id ?>');
    body.append('title',       title);

    fetch('ajax/save_module.php', { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                input.value = '';
                toast('Module added', 'success');
                loadModules(currentUnitId);
            } else {
                toast(d.message, 'error');
            }
        })
        .catch(() => toast('Network error', 'error'))
        .finally(() => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Module';
        });
}

function deleteModule(moduleId, title) {
    if (!confirm(`Delete module "${title}" and all its lessons?\n\nThis cannot be undone.`)) return;

    const body = new FormData();
    body.append('module_id', moduleId);
    body.append('unit_id',   currentUnitId);

    fetch('ajax/delete_module.php', { method: 'POST', body })
        .then(r => r.json())
        .then(d => {
            if (d.success) { toast('Module deleted', 'success'); loadModules(currentUnitId); }
            else toast(d.message, 'error');
        })
        .catch(() => toast('Delete failed', 'error'));
}

function saveOrder() {
    const ids = [...document.querySelectorAll('#modules-list .module-row')]
                    .map(r => parseInt(r.dataset.id));
    const body = new FormData();
    body.append('unit_id', currentUnitId);
    body.append('order',   JSON.stringify(ids));
    fetch('ajax/reorder_modules.php', { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.success) loadModules(currentUnitId); })
        .catch(() => toast('Reorder failed', 'error'));
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
function escAttr(s) {
    return String(s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;');
}
</script>
</body>
</html>