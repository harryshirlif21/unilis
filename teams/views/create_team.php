<?php
ob_start();
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create & Manage Teams – UniLIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --orange:      #f97316;
            --orange-dark: #ea580c;
            --green:       #16a34a;
            --amber:       #f59e0b;
            --gray-100:    #f3f4f6;
            --gray-200:    #e5e7eb;
            --gray-400:    #9ca3af;
            --gray-600:    #4b5563;
            --gray-900:    #111827;
            --white:       #ffffff;
            --shadow-sm:   0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.05);
            --shadow-md:   0 4px 12px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.04);
            --radius:      14px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--gray-100); margin: 0; color: var(--gray-900); min-height: 100vh; }

        header { background: var(--white); border-bottom: 1px solid var(--gray-200); position: sticky; top: 0; z-index: 100; }
        .header-inner { max-width: 1120px; margin: 0 auto; padding: 0 1.5rem; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.4rem; color: var(--orange); letter-spacing: -.5px; display: flex; align-items: center; gap: .5rem; }
        .user-pill { display: flex; align-items: center; gap: .75rem; font-size: .875rem; color: var(--gray-600); }
        .user-pill .avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--orange), var(--amber)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: .8rem; }
        .logout-btn { color: var(--gray-600); text-decoration: none; padding: .35rem .75rem; border-radius: 6px; border: 1px solid var(--gray-200); font-size: .8rem; transition: all .15s; }
        .logout-btn:hover { background: var(--gray-100); color: var(--gray-900); }

        .page { max-width: 1120px; margin: 0 auto; padding: 2rem 1.5rem; display: grid; grid-template-columns: 380px 1fr; gap: 1.75rem; align-items: start; }

        .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); overflow: hidden; }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; gap: .75rem; }
        .card-header .icon-wrap { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: .95rem; flex-shrink: 0; }
        .icon-green  { background: #dcfce7; color: var(--green); }
        .icon-orange { background: #fff7ed; color: var(--orange); }
        .card-header h2 { margin: 0; font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 700; color: var(--gray-900); }
        .card-body { padding: 1.5rem; }

        #message { display: none; padding: .85rem 1rem; border-radius: 9px; margin-bottom: 1.25rem; font-size: .875rem; font-weight: 500; align-items: center; gap: .6rem; }
        #message.success { display: flex; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        #message.error   { display: flex; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .form-stack { display: flex; flex-direction: column; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: .4rem; }
        label { font-size: .8rem; font-weight: 600; color: var(--gray-600); text-transform: uppercase; letter-spacing: .04em; }

        input[type="text"], select {
            padding: .7rem .95rem; border: 1.5px solid var(--gray-200); border-radius: 9px;
            font-size: .95rem; font-family: 'DM Sans', sans-serif; color: var(--gray-900);
            background: var(--white); transition: border-color .15s, box-shadow .15s;
            appearance: none; -webkit-appearance: none; width: 100%;
        }
        .select-wrap { position: relative; }
        .select-wrap::after { content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: .95rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); pointer-events: none; font-size: .85rem; }
        input[type="text"]:focus, select:focus { outline: none; border-color: var(--orange); box-shadow: 0 0 0 3px rgba(249,115,22,.12); }

        .submit-btn { width: 100%; padding: .8rem; border: none; border-radius: 9px; background: var(--orange); color: white; font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 600; cursor: pointer; transition: background .15s, transform .1s, box-shadow .15s; display: flex; align-items: center; justify-content: center; gap: .5rem; margin-top: .25rem; }
        .submit-btn:hover    { background: var(--orange-dark); box-shadow: 0 4px 12px rgba(249,115,22,.3); }
        .submit-btn:active   { transform: scale(.98); }
        .submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        /* ── Table ── */
        .teams-table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        thead tr { background: var(--gray-100); border-bottom: 2px solid var(--gray-200); }
        thead th { padding: .75rem 1rem; text-align: left; font-size: .75rem; font-weight: 700; color: var(--gray-600); text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--gray-200); transition: background .12s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fff7ed; }
        tbody td { padding: .85rem 1rem; color: var(--gray-900); vertical-align: middle; }
        .td-title { font-weight: 600; }

        .tc-badge { display: inline-flex; align-items: center; font-size: .7rem; font-weight: 700; padding: .2rem .6rem; border-radius: 20px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        .badge-assignment { background: #dbeafe; color: #1e40af; }
        .badge-project    { background: #dcfce7; color: #15803d; }
        .badge-cat        { background: #fef9c3; color: #854d0e; }
        .badge-practical  { background: #f3e8ff; color: #7e22ce; }
        .badge-default    { background: var(--gray-100); color: var(--gray-600); }

        .status-pill { display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; font-weight: 600; padding: .2rem .6rem; border-radius: 20px; background: #dcfce7; color: #15803d; }
        .status-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #16a34a; display: inline-block; }

        .td-actions { display: flex; gap: .4rem; justify-content: flex-end; }
        .td-actions a { font-size: .78rem; font-weight: 600; padding: .35rem .75rem; border-radius: 6px; text-decoration: none; transition: background .12s; white-space: nowrap; }
        .btn-manage        { background: var(--orange); color: white; }
        .btn-manage:hover  { background: var(--orange-dark); }
        .btn-workspace     { background: #ecfdf5; color: var(--green); border: 1px solid #a7f3d0; }
        .btn-workspace:hover { background: #dcfce7; }

        .empty-state { padding: 3rem 1.5rem; text-align: center; color: var(--gray-400); }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; display: block; opacity: .4; }
        .empty-state p { margin: 0; font-size: .95rem; }

        .loading-dots { display: flex; justify-content: center; align-items: center; gap: .4rem; padding: 2.5rem 0; }
        .loading-dots span { width: 8px; height: 8px; background: var(--orange); border-radius: 50%; animation: bounce 1.2s infinite ease-in-out; opacity: .6; }
        .loading-dots span:nth-child(2) { animation-delay: .2s; }
        .loading-dots span:nth-child(3) { animation-delay: .4s; }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(.8); opacity: .4; } 40% { transform: scale(1.2); opacity: 1; } }

        @media (max-width: 820px) { .page { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header>
    <div class="header-inner">
        <div class="logo"><i class="fas fa-users-rectangle"></i> UniLIS Teams</div>
        <div class="user-pill">
            <div class="avatar"><?php echo strtoupper(substr((string)$_SESSION['user_id'], 0, 1)); ?></div>
            <span>Student #<?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
            <a href="/logout.php" class="logout-btn"><i class="fas fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </div>
</header>

<div class="page">

    <!-- ── Create Team ── -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="icon-wrap icon-green"><i class="fas fa-plus"></i></div>
                <h2>Create a New Team</h2>
            </div>
            <div class="card-body">
                <div id="message"></div>
                <form id="createTeamForm" class="form-stack" novalidate>

                    <div class="form-group">
                        <label for="title">Team Title *</label>
                        <input type="text" id="title" placeholder="e.g. Quantum Coders" required>
                    </div>

                    <div class="form-group">
                        <label for="unit_id">Unit *</label>
                        <div class="select-wrap">
                            <select id="unit_id" required>
                                <option value="">Loading units…</option>
                            </select>
                        </div>
                    </div>

                    <!-- course_id resolved server-side from session — no dropdown needed -->

                    <div class="form-group">
                        <label for="assessment_type">Assessment Type *</label>
                        <div class="select-wrap">
                            <select id="assessment_type" required>
                                <option value="">Select type…</option>
                                <option value="assignment">Assignment</option>
                                <option value="cat">CAT</option>
                                <option value="project">Project</option>
                                <option value="practical">Practical</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="createBtn">
                        <i class="fas fa-plus-circle"></i> Create Team
                    </button>

                </form>
            </div>
        </div>
    </div>

    <!-- ── Teams Table ── -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="icon-wrap icon-orange"><i class="fas fa-layer-group"></i></div>
                <h2>Your Teams</h2>
            </div>
            <div class="card-body" style="padding:0">
                <div id="teams-list">
                    <div class="loading-dots"><span></span><span></span><span></span></div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
const csrf       = <?php echo json_encode($_SESSION['csrf_token']); ?>;
const messageDiv = document.getElementById('message');

/* ── Helpers ── */
function showMessage(text, type = 'error') {
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    messageDiv.innerHTML = `<i class="fas ${icon}"></i> ${text}`;
    messageDiv.className = type;
}
function clearMessage() {
    messageDiv.className = '';
    messageDiv.innerHTML = '';
    messageDiv.style.display = '';
}
function badgeClass(type) {
    const map = { assignment:'badge-assignment', project:'badge-project',
                  cat:'badge-cat', practical:'badge-practical' };
    return map[type] || 'badge-default';
}
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

/* ── Safe JSON fetch ── */
async function safeFetch(url, options = {}) {
    const res  = await fetch(url, { credentials: 'same-origin', ...options });
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch {
        const plain = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        console.error(`RAW SERVER RESPONSE from ${url}:\n`, text);
        throw new Error(plain.substring(0, 300));
    }
}

/* ── Load Units from get_enrolled_units.php ── */
async function loadUnits() {
    const sel = document.getElementById('unit_id');
    try {
        const data = await safeFetch('/teams/api/get_enrolled_units.php');
        if (!data.success) throw new Error(data.message || 'Failed to load units');

        sel.innerHTML = '<option value="">Select unit…</option>';
        data.units.forEach(u => {
            const opt       = document.createElement('option');
            opt.value       = u.id;
            // show code + name if code exists, else just name
            opt.textContent = u.code ? `${u.code} – ${u.name}` : u.name;
            sel.appendChild(opt);
        });
    } catch (err) {
        sel.innerHTML = '<option value="">Could not load units</option>';
        console.error('loadUnits:', err);
    }
}

/* ── Load Teams Table ── */
async function loadTeams() {
    const listDiv = document.getElementById('teams-list');
    listDiv.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div>';
    try {
        const data = await safeFetch('/teams/api/get_user_teams.php');
        if (!data.success) throw new Error(data.error || 'Failed to load teams');

        if (!data.teams || !data.teams.length) {
            listDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <p>You are not in any teams yet.<br>Create one to get started!</p>
                </div>`;
            return;
        }

        const rows = data.teams.map(team => {
            const aType   = (team.assessment_type || '').toLowerCase();
            const label   = aType ? aType.charAt(0).toUpperCase() + aType.slice(1) : 'Unknown';
            const members = Array.isArray(team.members) ? team.members.length : (team.member_count || 0);
            return `
                <tr>
                    <td class="td-title">${escHtml(team.title)}</td>
                    <td>${escHtml(team.unit_name || '—')}</td>
                    <td><span class="tc-badge ${badgeClass(aType)}">${escHtml(label)}</span></td>
                    <td><span class="status-pill">${escHtml(team.status || 'Active')}</span></td>
                    <td style="text-align:center">${members}</td>
                    <td>
                        <div class="td-actions">
                            <a href="/teams/views/manage_team.php?team_id=${team.id}" class="btn-manage">
                                <i class="fas fa-sliders"></i> Manage
                            </a>
                            <a href="/teams/views/workspace.php?team_id=${team.id}" class="btn-workspace">
                                <i class="fas fa-arrow-up-right-from-square"></i> Workspace
                            </a>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        listDiv.innerHTML = `
            <div class="teams-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Team</th>
                            <th>Unit</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th style="text-align:center">Members</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;

    } catch (err) {
        listDiv.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-triangle-exclamation"></i>
                <p>Error: ${escHtml(err.message)}</p>
            </div>`;
        console.error('loadTeams:', err);
    }
}

/* ── Create Team ── */
document.getElementById('createTeamForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMessage();

    const title           = document.getElementById('title').value.trim();
    const unit_id         = parseInt(document.getElementById('unit_id').value, 10);
    const assessment_type = document.getElementById('assessment_type').value;

    if (!title || !unit_id || !assessment_type) {
        showMessage('Please fill in all fields.', 'error');
        return;
    }

    const btn = document.getElementById('createBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating…';

    try {
        // course_id is NOT sent — create.php resolves it securely from the session
        const data = await safeFetch('/teams/api/create.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ title, unit_id, assessment_type, csrf_token: csrf })
        });

        if (!data.success) throw new Error(data.message || 'Failed to create team');

        showMessage('Team created successfully!', 'success');
        document.getElementById('createTeamForm').reset();
        await loadUnits();
        loadTeams();

    } catch (err) {
        showMessage('Error: ' + err.message, 'error');
        console.error('createTeam:', err);
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-plus-circle"></i> Create Team';
    }
});

/* ── Boot ── */
loadUnits();
loadTeams();
</script>
</body>
</html>