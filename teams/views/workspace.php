<?php
session_start();

// Basic auth: only logged-in students (for now) can access workspace
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header('Location: /login.php');
    exit;
}

// Ensure CSRF token exists for any POST/fetch actions coming from this page
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentUserId = (int) $_SESSION['user_id'];
$csrfToken     = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Workspace – UniLIS</title>
    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --bg-light: #f3f4f6;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --tab-bg: #f9fafb;
            --tab-active: #ffffff;
            --tab-border-active: #f97316;
            --danger: #dc2626;
            --success: #16a34a;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
        }

        .workspace-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.25rem;
        }

        header.workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        header.workspace-header h1 {
            font-size: 1.5rem;
            margin: 0;
            color: var(--primary);
        }

        .header-meta {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-align: right;
        }

        .header-meta a {
            color: var(--primary);
            text-decoration: none;
        }

        .header-meta a:hover {
            text-decoration: underline;
        }

        .team-summary {
            background: var(--surface);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        .team-summary h2 {
            margin: 0 0 0.25rem 0;
            font-size: 1.2rem;
        }

        .team-summary p {
            margin: 0.15rem 0;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .tabs {
            margin-top: 1rem;
        }

        .tab-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            border-bottom: 1px solid var(--border);
            background: var(--tab-bg);
            border-radius: 10px 10px 0 0;
            padding: 0.25rem;
        }

        .tab-button {
            border: none;
            background: transparent;
            padding: 0.55rem 0.9rem;
            font-size: 0.92rem;
            cursor: pointer;
            border-radius: 999px;
            color: var(--text-muted);
            transition: background 0.15s, color 0.15s, box-shadow 0.15s;
        }

        .tab-button.active {
            background: var(--tab-active);
            color: var(--primary-dark);
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border: 1px solid rgba(249, 115, 22, 0.22);
        }

        .tab-panels {
            background: var(--surface);
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            border-top: none;
            padding: 1rem;
            min-height: 250px;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .muted {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .pill {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            color: var(--text-muted);
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        .loading {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .error {
            color: var(--danger);
            font-size: 0.9rem;
        }

        .chips-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin: 0.25rem 0;
        }

        .chip {
            padding: 0.15rem 0.4rem;
            border-radius: 999px;
            font-size: 0.75rem;
            border: 1px solid var(--border);
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            header.workspace-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .header-meta {
                text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="workspace-shell">
    <header class="workspace-header">
        <h1>Team Workspace</h1>
        <div class="header-meta">
            Student ID: <?php echo htmlspecialchars($currentUserId); ?><br>
            <a href="/teams/views/create_team.php">&larr; Back to Teams</a>
        </div>
    </header>

    <section class="team-summary" id="teamSummary">
        <h2 id="teamTitle">Loading team...</h2>
        <p id="teamMeta" class="muted">Fetching team details, please wait.</p>
    </section>

    <section class="tabs">
        <div class="tab-list" id="tabList">
            <button class="tab-button active" data-tab="files">Files</button>
            <button class="tab-button" data-tab="tasks">Tasks / Kanban</button>
            <button class="tab-button" data-tab="checklist">Submission Checklist</button>
            <button class="tab-button" data-tab="activity">Activity Log</button>
            <button class="tab-button" data-tab="health">Health Score</button>
            <button class="tab-button" data-tab="standups">Stand-ups</button>
        </div>

        <div class="tab-panels">
            <div class="tab-panel active" id="tab-files">
                <p class="muted">
                    Latest team files and versions from <span class="pill">team_files</span>.
                </p>
                <p id="filesStatus" class="muted">Loading files...</p>
                <div id="filesList" class="muted" style="font-size:0.9rem;"></div>
            </div>

            <div class="tab-panel" id="tab-tasks">
                <p class="muted">
                    Kanban board for team tasks (To Do / In Progress / Done),
                    backed by <span class="pill">team_tasks</span>.
                </p>
                <p id="tasksStatus" class="muted">Loading tasks...</p>
                <div id="tasksBoard" style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:0.5rem;">
                    <!-- Columns will be injected here -->
                </div>
            </div>

            <div class="tab-panel" id="tab-checklist">
                <p class="muted">
                    Submission checklist for this assessment, auto-populated from
                    <span class="pill">submission_checklist</span> and
                    <span class="pill">submission_signoffs</span>.
                </p>
                <p id="checklistStatus" class="muted">Loading checklist...</p>
                <ul id="checklistList" class="activity-list"></ul>
            </div>

            <div class="tab-panel" id="tab-activity">
                <p class="muted" id="activityStatus">Loading recent activity...</p>
                <ul class="activity-list" id="activityList"></ul>
            </div>

            <div class="tab-panel" id="tab-health">
                <p class="muted">
                    Team health score and heatmap, based on
                    <span class="pill">HEALTH_SCORE_WEIGHTS</span>,
                    recent tasks and activity.
                </p>
                <p id="healthStatus" class="muted">Loading health score...</p>
                <div id="healthBody" class="muted" style="font-size:0.9rem;"></div>
            </div>

            <div class="tab-panel" id="tab-standups">
                <p class="muted">
                    Lightweight daily stand-up entries stored in
                    <span class="pill">standup_entries</span>.
                </p>
                <p id="standupsStatus" class="muted">Loading recent stand-ups...</p>
                <ul id="standupsList" class="activity-list"></ul>
            </div>
        </div>
    </section>
</div>

<script>
// --- Basic state ---
const urlParams = new URLSearchParams(window.location.search);
const teamId = urlParams.get('team_id');
const currentUserId = <?php echo json_encode($currentUserId); ?>;
const csrfToken = <?php echo json_encode($csrfToken); ?>;

if (!teamId) {
    document.body.innerHTML = '<div class="workspace-shell"><p class="error">Missing team_id in URL.</p></div>';
}

// --- Tab switching ---
const tabButtons = document.querySelectorAll('.tab-button');
const panels = {
    files: document.getElementById('tab-files'),
    tasks: document.getElementById('tab-tasks'),
    checklist: document.getElementById('tab-checklist'),
    activity: document.getElementById('tab-activity'),
    health: document.getElementById('tab-health'),
    standups: document.getElementById('tab-standups')
};

tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-tab');

        tabButtons.forEach(b => b.classList.remove('active'));
        Object.values(panels).forEach(panel => panel.classList.remove('active'));

        btn.classList.add('active');
        if (panels[target]) {
            panels[target].classList.add('active');
        }

        // Lazy-load data when specific tabs are first opened
        if (target === 'activity' && !panels.activity.dataset.loaded) {
            loadActivity();
        } else if (target === 'files' && !panels.files.dataset.loaded) {
            loadFiles();
        } else if (target === 'tasks' && !panels.tasks.dataset.loaded) {
            loadTasks();
        } else if (target === 'checklist' && !panels.checklist.dataset.loaded) {
            loadChecklist();
        } else if (target === 'health' && !panels.health.dataset.loaded) {
            loadHealth();
        } else if (target === 'standups' && !panels.standups.dataset.loaded) {
            loadStandups();
        }
    });
});

// --- Team header: reuse /teams/api/get_team.php ---
async function loadTeamHeader() {
    const titleEl = document.getElementById('teamTitle');
    const metaEl = document.getElementById('teamMeta');

    try {
        const res = await fetch(`/teams/api/get_team.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Unable to load team');
        }

        const team = data.team || {};
        titleEl.textContent = team.title || 'Team';

        const pieces = [];
        if (team.unit_name) pieces.push('Unit: ' + team.unit_name);
        if (team.assessment_title) pieces.push('Assessment: ' + team.assessment_title);
        if (team.status) pieces.push('Status: ' + team.status);

        metaEl.textContent = pieces.join(' • ') || 'Team details';
    } catch (err) {
        titleEl.textContent = 'Error loading team';
        metaEl.textContent = err.message;
        metaEl.classList.add('error');
    }
}

// --- Files tab: read-only list via workspace_files.php ---
async function loadFiles() {
    const statusEl = document.getElementById('filesStatus');
    const listEl = document.getElementById('filesList');

    statusEl.textContent = 'Loading files...';
    listEl.innerHTML = '';

    try {
        const res = await fetch(`/teams/api/workspace_files.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not load files');
        }

        const files = data.files || [];
        if (files.length === 0) {
            statusEl.textContent = 'No files yet. Use the existing submission flow to upload.';
            return;
        }

        statusEl.textContent = '';

        const rows = files.map(f => {
            const when = f.uploaded_at ? new Date(f.uploaded_at).toLocaleString() : '';
            const v = f.version ? `v${f.version}` : '';
            return `<div>
                <strong>${f.file_name || 'File'}</strong>
                ${v ? `<span class="pill">${v}</span>` : ''}
                <div class="activity-meta">Uploaded ${when}</div>
            </div>`;
        });

        listEl.innerHTML = rows.join('');
        panels.files.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading files: ' + err.message;
        statusEl.classList.add('error');
    }
}

// --- Activity log ---
async function loadActivity() {
    const statusEl = document.getElementById('activityStatus');
    const listEl = document.getElementById('activityList');

    statusEl.textContent = 'Loading recent activity...';
    listEl.innerHTML = '';

    try {
        const res = await fetch(`/teams/api/get_activity_log.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not load activity log');
        }

        const items = data.activities || [];
        if (items.length === 0) {
            statusEl.textContent = 'No activity logged yet. Invite members, upload files, or manage tasks to see updates here.';
            return;
        }

        statusEl.textContent = '';

        items.forEach(row => {
            const li = document.createElement('li');
            li.className = 'activity-item';

            const detail = row.detail || '';
            const action = row.action_type || 'activity';
            const created = row.created_at ? new Date(row.created_at) : null;

            li.innerHTML = `
                <div><strong>${action}</strong> – ${detail || 'No detail provided.'}</div>
                <div class="activity-meta">
                    ${created ? created.toLocaleString() : ''}
                    ${row.user_name ? ' • by ' + row.user_name : ''}
                </div>
            `;
            listEl.appendChild(li);
        });

        panels.activity.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading activity: ' + err.message;
        statusEl.classList.add('error');
    }
}

// --- Tasks / Kanban: read-only from kanban_tasks.php ---
async function loadTasks() {
    const statusEl = document.getElementById('tasksStatus');
    const boardEl = document.getElementById('tasksBoard');

    statusEl.textContent = 'Loading tasks...';
    boardEl.innerHTML = '';

    try {
        const res = await fetch(`/teams/api/kanban_tasks.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not load tasks');
        }

        const tasks = data.tasks || [];
        if (tasks.length === 0) {
            statusEl.textContent = 'No tasks yet. Add tasks in a later phase or via lecturer tools.';
            return;
        }

        statusEl.textContent = '';

        const columns = {
            todo: [],
            in_progress: [],
            done: []
        };

        tasks.forEach(t => {
            const key = (t.status || 'todo').toLowerCase();
            if (!columns[key]) {
                columns[key] = [];
            }
            columns[key].push(t);
        });

        const order = [
            { key: 'todo', label: 'To Do' },
            { key: 'in_progress', label: 'In Progress' },
            { key: 'done', label: 'Done' }
        ];

        order.forEach(col => {
            const colTasks = columns[col.key] || [];
            const colDiv = document.createElement('div');
            colDiv.style.flex = '1 1 220px';
            colDiv.style.minWidth = '220px';
            colDiv.style.border = '1px solid var(--border)';
            colDiv.style.borderRadius = '8px';
            colDiv.style.background = '#f9fafb';
            colDiv.style.padding = '0.5rem 0.6rem';

            const h = document.createElement('h4');
            h.textContent = `${col.label} (${colTasks.length})`;
            h.style.margin = '0 0 0.4rem 0';
            h.style.fontSize = '0.95rem';
            h.style.color = '#374151';
            colDiv.appendChild(h);

            if (colTasks.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'muted';
                empty.style.fontSize = '0.8rem';
                empty.textContent = 'No tasks in this column.';
                colDiv.appendChild(empty);
            } else {
                colTasks.forEach(t => {
                    const card = document.createElement('div');
                    card.style.background = '#ffffff';
                    card.style.borderRadius = '6px';
                    card.style.border = '1px solid #e5e7eb';
                    card.style.padding = '0.4rem 0.5rem';
                    card.style.marginBottom = '0.4rem';
                    card.style.fontSize = '0.85rem';

                    const title = document.createElement('div');
                    title.style.fontWeight = '600';
                    title.textContent = t.title || 'Untitled task';
                    card.appendChild(title);

                    if (t.description) {
                        const desc = document.createElement('div');
                        desc.className = 'muted';
                        desc.style.fontSize = '0.8rem';
                        desc.textContent = t.description;
                        card.appendChild(desc);
                    }

                    const meta = document.createElement('div');
                    meta.className = 'activity-meta';
                    const bits = [];
                    if (t.due_date) {
                        bits.push('Due ' + new Date(t.due_date).toLocaleDateString());
                    }
                    if (t.assignee_id) {
                        bits.push('Assigned #' + t.assignee_id);
                    }
                    meta.textContent = bits.join(' • ');
                    card.appendChild(meta);

                    colDiv.appendChild(card);
                });
            }

            boardEl.appendChild(colDiv);
        });

        panels.tasks.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading tasks: ' + err.message;
        statusEl.classList.add('error');
    }
}

// --- Checklist: read-only from checklist.php ---
async function loadChecklist() {
    const statusEl = document.getElementById('checklistStatus');
    const listEl = document.getElementById('checklistList');

    statusEl.textContent = 'Loading checklist...';
    listEl.innerHTML = '';

    try {
        const res = await fetch(`/teams/api/checklist.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not load checklist');
        }

        const items = data.checklist || [];
        if (items.length === 0) {
            statusEl.textContent = 'No checklist items yet. They will appear here when configured for this assessment.';
            return;
        }

        statusEl.textContent = '';

        items.forEach(it => {
            const li = document.createElement('li');
            li.className = 'activity-item';

            const label = it.item_label || it.label || 'Checklist item';
            const checked = String(it.is_checked) === '1' || it.is_checked === true;
            const updated = it.updated_at ? new Date(it.updated_at).toLocaleString() : '';

            li.innerHTML = `
                <div>
                    <input type="checkbox" disabled ${checked ? 'checked' : ''}>
                    <span>${label}</span>
                </div>
                <div class="activity-meta">
                    ${checked ? 'Completed' : 'Pending'}
                    ${updated ? ' • last updated ' + updated : ''}
                </div>
            `;
            listEl.appendChild(li);
        });

        panels.checklist.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading checklist: ' + err.message;
        statusEl.classList.add('error');
    }
}

// --- Health: aggregate from tasks + activity via health.php ---
async function loadHealth() {
    const statusEl = document.getElementById('healthStatus');
    const bodyEl = document.getElementById('healthBody');

    statusEl.textContent = 'Calculating health score...';
    bodyEl.innerHTML = '';

    try {
        const res = await fetch(`/teams/api/health.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not calculate health score');
        }

        statusEl.textContent = '';
        const score = data.score ?? 0;
        const c = data.components || {};

        bodyEl.innerHTML = `
            <div style="margin-bottom:0.5rem;">
                <strong>Overall health:</strong> ${score}/100
            </div>
            <div class="activity-meta">
                Tasks done score: ${(c.tasks_done?.score ?? 0)} (raw: ${(c.tasks_done?.raw ?? 0)})
                <br>
                Activity score: ${(c.activity?.score ?? 0)} (events last 7 days: ${(c.activity?.raw ?? 0)})
                <br>
                Deadline factor: ${(c.deadline?.score ?? 0)}
            </div>
        `;

        panels.health.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading health: ' + err.message;
        statusEl.classList.add('error');
    }
}

// --- Stand-ups: list from standups.php ---
async function loadStandups() {
    const statusEl = document.getElementById('standupsStatus');
    const listEl = document.getElementById('standupsList');

    statusEl.textContent = 'Loading recent stand-ups...';
    listEl.innerHTML = '';

    try {
        const res = await fetch(`/teams/api/standups.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Could not load stand-ups');
        }

        const items = data.standups || [];
        if (items.length === 0) {
            statusEl.textContent = 'No stand-up entries yet.';
            return;
        }

        statusEl.textContent = '';

        items.forEach(it => {
            const li = document.createElement('li');
            li.className = 'activity-item';

            const created = it.created_at ? new Date(it.created_at).toLocaleString() : '';

            li.innerHTML = `
                <div><strong>${created}</strong></div>
                <div class="activity-meta">
                    <div><strong>Yesterday:</strong> ${it.yesterday || '-'}</div>
                    <div><strong>Today:</strong> ${it.today || '-'}</div>
                    <div><strong>Blockers:</strong> ${it.blockers || '-'}</div>
                </div>
            `;
            listEl.appendChild(li);
        });

        panels.standups.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading stand-ups: ' + err.message;
        statusEl.classList.add('error');
    }
}
// Initial load
loadTeamHeader();
loadFiles(); // files tab is the default visible tab
</script>

</body>
</html>

