<?php
session_start();

require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../../login.php"); // or wherever your login page is
    exit;
}

// Check if team tables exist
$teamTablesExist = false;
try {
    $checkTables = $conn->query("SHOW TABLES LIKE 'team_members'");
    if ($checkTables && $checkTables->num_rows > 0) {
        $teamTablesExist = true;
    }
} catch (Exception $e) {
    // Tables don't exist
}

if (!$teamTablesExist) {
    die("<h2>Teams Module Not Available</h2><p>The teams system tables have not been created. Please ask your administrator to run the migrate_teams_system.php migration script.</p><p><a href='../../student/dashboard.php'>Return to Dashboard</a></p>");
}

// Get current user info
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Generate CSRF if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Team - UniLIS</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 900px; 
            margin: 2rem auto; 
            padding: 1rem; 
            background: #f8f9fa; 
        }
        h1 { color: #2c3e50; }
        .team-header { 
            border-bottom: 1px solid #ccc; 
            padding-bottom: 1rem; 
            margin-bottom: 1.5rem; 
        }
        .members-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 1rem; 
            margin: 1.5rem 0; 
        }
        .member-card { 
            border: 1px solid #ddd; 
            padding: 1rem; 
            border-radius: 6px; 
            background: #fff; 
            box-shadow: 0 1px 4px rgba(0,0,0,0.05); 
        }
        .member-name {
            display: block;
            margin-bottom: 0.35rem;
        }
        .member-meta {
            margin-top: 0.4rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .member-role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.18rem 0.55rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            margin-left: 0.35rem;
        }
        .member-role-badge.leader {
            background: #fef3c7;
            color: #92400e;
        }
        .member-role-badge.member {
            background: #dbeafe;
            color: #1e40af;
        }
        .leader { 
            background: #fff3cd; 
            border-color: #ffeeba; 
            font-weight: bold; 
        }
        .actions { 
            margin: 1.5rem 0; 
            display: flex; 
            gap: 1rem; 
            flex-wrap: wrap; 
        }
        .settings-panel {
            margin: 1.25rem 0;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
        }
        .settings-panel h3 {
            margin: 0 0 0.6rem 0;
            color: #2c3e50;
        }
        .danger-btn {
            background: #dc3545;
            color: white;
        }
        .danger-btn:hover {
            background: #c82333;
        }
        .role-selector {
            padding: 0.6rem;
            min-width: 220px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: #fff;
            color: #212529;
        }
        .settings-row {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            flex-wrap: wrap;
        }
        .settings-row input[type="range"] {
            width: 280px;
            accent-color: #fd7e14;
        }
        .limit-badge {
            display: inline-flex;
            min-width: 44px;
            justify-content: center;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: #fff3cd;
            color: #7f5c00;
            font-weight: 700;
        }
        input[type="text"] { 
            padding: 0.6rem; 
            width: 280px; 
            border: 1px solid #ced4da; 
            border-radius: 4px; 
        }
        button { 
            padding: 0.6rem 1.2rem; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: 500; 
        }
        #addMemberBtn { background: #28a745; }
        #addMemberBtn:hover { background: #218838; }
        .remove { background: #dc3545; }
        .remove:hover { background: #c82333; }
        #submitBtn { background: #007bff; }
        #submitBtn:hover { background: #0069d9; }
        #message { 
            margin: 1rem 0; 
            padding: 0.8rem; 
            border-radius: 4px; 
            font-weight: 500; 
        }
        .success { background: #d4edda; color: #155724; }
        .error   { background: #f8d7da; color: #721c24; }
        .loading { color: #6c757d; text-align: center; padding: 2rem; }
        .user-info { 
            font-size: 0.9rem; 
            color: #555; 
            margin-bottom: 1rem; 
            text-align: right; 
        }
        .count-pill {
            display: inline-flex;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            vertical-align: middle;
        }
        .count-normal { background: #dcfce7; color: #166534; }
        .count-yellow { background: #fef9c3; color: #854d0e; }
        .count-orange { background: #ffedd5; color: #9a3412; }
        .count-red { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="user-info">
    Logged in as: Student (ID: <?php echo htmlspecialchars($current_user_id); ?>) 
    | <a href="../../logout.php">Logout</a>
</div>

<h1>Manage Team</h1>

<div id="team-header" class="team-header">
    <div class="loading">Loading team information...</div>
</div>

<div class="actions">
    <select id="memberRole" class="role-selector" aria-label="Select team role">
        <option value="member">Member</option>
        <option value="leader">Team Lead</option>
        <option value="frontend_developer">Frontend Developer</option>
        <option value="backend_developer">Backend Developer</option>
        <option value="machine_learning">Machine Learning</option>
        <option value="ui_ux_designer">UI/UX Designer</option>
        <option value="data_analyst">Data Analyst</option>
        <option value="tester">Tester</option>
        <option value="researcher">Researcher</option>
        <option value="presenter">Presenter</option>
        <option value="other">Other</option>
    </select>
    <input type="text" id="identifier" placeholder="Enter reg number or email">
    <button id="addMemberBtn">Add Member</button>
    <button id="resendCodeBtn" style="background:#fd7e14;">Resend Code</button>
    <button id="submitBtn">Submit Files</button>
</div>
<div class="actions">
    <select id="confirmMemberRole" class="role-selector" aria-label="Select confirmed member role">
        <option value="member">Member</option>
        <option value="leader">Team Lead</option>
        <option value="frontend_developer">Frontend Developer</option>
        <option value="backend_developer">Backend Developer</option>
        <option value="machine_learning">Machine Learning</option>
        <option value="ui_ux_designer">UI/UX Designer</option>
        <option value="data_analyst">Data Analyst</option>
        <option value="tester">Tester</option>
        <option value="researcher">Researcher</option>
        <option value="presenter">Presenter</option>
        <option value="other">Other</option>
    </select>
    <input type="text" id="confirmIdentifier" placeholder="Confirm member reg/email">
    <input type="text" id="confirmCode" placeholder="Enter 6-digit code">
    <button id="confirmMemberBtn" style="background:#6f42c1;">Confirm Member</button>
</div>

<div class="settings-panel">
    <h3>Manage Supervisors</h3>
    <div id="supervisorSection">
        <div id="existingSupervisors" style="margin-bottom: 1rem;">
            <p>Loading supervisors...</p>
        </div>
        <div style="margin-top: 1rem;">
            <input type="email" id="supervisorEmail" placeholder="Enter supervisor email..." style="width: 280px;">
            <button id="searchSupervisorBtn" style="background:#17a2b8;">Search</button>
        </div>
        <div id="searchResults" style="margin-top: 0.5rem;"></div>
        <p style="font-size: 0.85rem; color: #6c757d; margin-top: 0.5rem;">Enter email to search for lecturers/technicians. The supervisor will receive an email to approve the request.</p>
    </div>
</div>

<div id="teamSettingsPanel" class="settings-panel" style="display:none;">
    <h3>Team Settings</h3>
    <p style="margin:0 0 0.75rem 0;color:#6c757d;">Team leader can set member limit (maximum 15).</p>
    <div class="settings-row">
        <input type="range" id="teamLimitSlider" min="2" max="15" step="1" value="15">
        <span id="teamLimitValue" class="limit-badge">15</span>
        <button id="saveTeamSettingsBtn" style="background:#0d6efd;">Save Limit</button>
    </div>
    <small id="teamSettingsHint" style="display:block;margin-top:0.55rem;color:#6c757d;"></small>
</div>

<div id="teamDangerPanel" class="settings-panel" style="display:none;">
    <h3>Delete Team</h3>
    <p style="margin:0 0 0.75rem 0;color:#6c757d;">Delete this group and remove its members, invitations, and related records.</p>
    <button id="deleteTeamBtn" class="danger-btn">Delete Team</button>
</div>

<h3>Members <span id="memberCountPill" class="count-pill count-normal"><span id="size">0</span>/<span id="max-size">15</span></span></h3>
<div id="members-grid" class="members-grid"></div>

<h3 style="margin-top:1.25rem;">Invitations</h3>
<div style="margin-bottom:0.5rem;">
    <button id="refreshInvitesBtn" style="background:#17a2b8;">Refresh Invitations</button>
</div>
<div id="invites-grid" class="members-grid"></div>

<div id="message"></div>

<script>
// Get team ID from URL
const urlParams = new URLSearchParams(window.location.search);
const teamId = urlParams.get('team_id');

if (!teamId) {
    document.body.innerHTML = '<h2 style="color:#dc3545">Error: No team selected</h2>';
    throw new Error('Missing team_id');
}

// Current logged-in user (from PHP)
const currentUserId = <?php echo json_encode($current_user_id); ?>;

// CSRF token
const csrf = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";
let maxTeamMembers = 15;
let currentTeamSize = 0;
let isCurrentUserLeader = false;

const teamRoleLabels = {
    leader: 'Team Lead',
    member: 'Member',
    frontend_developer: 'Frontend Developer',
    backend_developer: 'Backend Developer',
    machine_learning: 'Machine Learning',
    ui_ux_designer: 'UI/UX Designer',
    data_analyst: 'Data Analyst',
    tester: 'Tester',
    researcher: 'Researcher',
    presenter: 'Presenter',
    other: 'Other'
};

function normalizeTeamRole(role) {
    const value = String(role || 'member').trim().toLowerCase();
    return teamRoleLabels[value] ? value : 'other';
}

function formatTeamRole(role) {
    const normalized = normalizeTeamRole(role);
    return teamRoleLabels[normalized] || 'Other';
}

const messageDiv = document.getElementById('message');

// Helper: show message
function showMessage(text, type = 'error') {
    messageDiv.className = type;
    messageDiv.textContent = text;
    messageDiv.scrollIntoView({ behavior: 'smooth' });
}

function memberCountClass(count) {
    if (count > 10) return 'count-red';
    if (count > 7) return 'count-orange';
    if (count > 5) return 'count-yellow';
    return 'count-normal';
}

function syncTeamLimitUI() {
    const panel = document.getElementById('teamSettingsPanel');
    const dangerPanel = document.getElementById('teamDangerPanel');
    const slider = document.getElementById('teamLimitSlider');
    const value = document.getElementById('teamLimitValue');
    const hint = document.getElementById('teamSettingsHint');

    if (!panel || !slider || !value || !hint) return;

    if (!isCurrentUserLeader) {
        panel.style.display = 'none';
        if (dangerPanel) dangerPanel.style.display = 'none';
        return;
    }

    panel.style.display = 'block';
    if (dangerPanel) dangerPanel.style.display = 'block';
    const minAllowed = Math.max(2, currentTeamSize);
    slider.min = String(minAllowed);
    slider.max = '15';
    slider.value = String(Math.min(15, Math.max(minAllowed, maxTeamMembers)));
    value.textContent = slider.value;
    hint.textContent = `Current members: ${currentTeamSize}. Minimum allowed limit is ${minAllowed}.`;
}

// Load team data
async function loadTeam() {
    const header = document.getElementById('team-header');
    header.innerHTML = '<div class="loading">Loading team...</div>';

    try {
        const res = await fetch(`/teams/api/get_team.php?team_id=${teamId}`, {
            credentials: 'same-origin'
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load team');
        }

        const team = data.team || {};
        const unitLabel = team.unit_display
            || ((team.unit_code && team.unit_name) ? `${team.unit_code} – ${team.unit_name}` : (team.unit_name || team.unit_code || '—'));
        const assessmentLabel = team.assessment_title || team.assessment_type || '—';
        const latest = team.latest_activity;
        let latestHtml = '';
        if (latest && latest.created_at) {
            const latestText = `${latest.action_label || latest.action_type || 'Activity'}${latest.user_name ? ' by ' + latest.user_name : ''}${latest.action_detail ? ' — ' + latest.action_detail : ''}`;
            latestHtml = `<p><strong>Latest activity:</strong> ${latestText} <span style="color:#6c757d;">(${new Date(latest.created_at).toLocaleString()})</span></p>`;
        }

        header.innerHTML = `
            <h2>${team.title || 'Untitled Team'}</h2>
            <p><strong>Unit:</strong> ${unitLabel} | <strong>Assessment:</strong> ${assessmentLabel}</p>
            <p><strong>Status:</strong> ${team.status || 'active'} | <strong>Members:</strong> ${team.member_count || (data.members || []).length}/${team.max_members || 15} | <strong>Created:</strong> ${team.created_at ? new Date(team.created_at).toLocaleDateString() : '—'}</p>
            ${latestHtml}
        `;

        maxTeamMembers = Math.min(15, Number(data.team.max_members) || 15);
        document.getElementById('max-size').textContent = maxTeamMembers;

        const grid = document.getElementById('members-grid');
        grid.innerHTML = '';

        const members = data.members || [];

        // Mark current user and set leader role
        members.forEach(m => {
            m.isCurrentUser = (m.student_id == currentUserId);
            m.role = normalizeTeamRole(m.role);
        });

        isCurrentUserLeader = members.some(m => m.isCurrentUser && m.role === 'leader');

        // Sort: leader first, then others
        members.sort((a, b) => {
            if (a.role === 'leader') return -1;
            if (b.role === 'leader') return 1;
            return formatTeamRole(a.role).localeCompare(formatTeamRole(b.role));
        });

        members.forEach(m => {
            const card = document.createElement('div');
            card.className = `member-card ${m.role === 'leader' ? 'leader' : ''}`;
            
            let actionButtons = '';
            
            // For regular members (not current user's own card or leaders)
            if (m.role === 'leader' && !m.isCurrentUser) {
                // Leader viewing other members: can request removal or use remove button
                actionButtons = `
                    <div style="margin-top:0.45rem; display:flex; gap:0.3rem; flex-wrap:wrap;">
                        <button class="request-removal" data-sid="${m.student_id}" style="background:#ff6b6b; font-size:0.85rem; padding:0.4rem 0.8rem; flex:1;">Removal Request</button>
                        <button class="remove" data-sid="${m.student_id}" style="font-size:0.85rem; padding:0.4rem 0.8rem; flex:1;">Remove</button>
                    </div>
                `;
            } else if (m.role !== 'leader' && m.isCurrentUser) {
                // Current user is a regular member: can request to leave
                actionButtons = `
                    <div style="margin-top:0.45rem;">
                        <button class="request-leave" data-sid="${m.student_id}" style="background:#ffc107; font-size:0.85rem; padding:0.4rem 0.8rem; width:100%;">Request to Leave</button>
                    </div>
                `;
            } else if (m.role === 'leader' && m.isCurrentUser) {
                // Current user is the leader
                // Show buttons to manage other team members
                actionButtons = `
                    <div style="margin-top:0.45rem;">
                        <button class="view-team-requests" data-team-id="${teamId}" style="background:#6c757d; font-size:0.85rem; padding:0.4rem 0.8rem; width:100%;">View Requests</button>
                    </div>
                `;
            } else if (m.role !== 'leader' && !m.isCurrentUser) {
                // Other regular members viewed by current user (if current user is leader)
                // This will be handled by the leader section above
            }

            const roleLabel = normalizeTeamRole(m.role);
            const roleText = formatTeamRole(roleLabel);
            
            card.innerHTML = `
                <strong class="member-name">${m.name || 'Unknown'}</strong>
                <div>${m.reg_no || m.email || '—'}</div>
                <div class="member-meta">
                    <span class="member-role-badge ${roleLabel}">${roleText}</span>
                    ${m.isCurrentUser ? '<span style="margin-left:0.35rem;color:#6c757d;">You</span>' : ''}
                </div>
                ${actionButtons}
            `;
            grid.appendChild(card);
        });

        const teamSize = members.length;
        currentTeamSize = teamSize;
        document.getElementById('size').textContent = teamSize;
        const countPill = document.getElementById('memberCountPill');
        countPill.className = `count-pill ${memberCountClass(teamSize)}`;
        syncTeamLimitUI();

        const addMemberBtn = document.getElementById('addMemberBtn');
        if (teamSize >= maxTeamMembers) {
            addMemberBtn.disabled = true;
            addMemberBtn.style.opacity = '0.6';
            addMemberBtn.title = `Team is full (${teamSize}/${maxTeamMembers})`;
        } else {
            addMemberBtn.disabled = false;
            addMemberBtn.style.opacity = '1';
            addMemberBtn.title = '';
        }

        // Add event listeners for membership-related buttons
        document.querySelectorAll('.request-leave').forEach(btn => {
            btn.addEventListener('click', () => requestMembershipLeave(btn.dataset.sid));
        });
        
        document.querySelectorAll('.request-removal').forEach(btn => {
            btn.addEventListener('click', () => showRemovalReasonModal(btn.dataset.sid));
        });
        
        document.querySelectorAll('.view-team-requests').forEach(btn => {
            btn.addEventListener('click', () => viewTeamRequests(btn.dataset.teamId));
        });

        // Add remove listeners (old direct removal button)
        document.querySelectorAll('.remove').forEach(btn => {
            btn.addEventListener('click', () => removeMember(btn.dataset.sid));
        });

        // Refresh invite list alongside members
        loadInvitations();

    } catch (err) {
        header.innerHTML = '<p style="color:#dc3545">Error loading team</p>';
        showMessage("Error loading team: " + err.message);
        console.error("loadTeam error:", err);
    }
}

// Load team invitations for leader
async function loadInvitations() {
    const grid = document.getElementById('invites-grid');
    if (!grid) return;
    grid.innerHTML = '<div class="loading">Loading invitations...</div>';

    try {
        const cleanupRes = await fetch('/teams/api/cleanup_expired_invitations.php', {
            credentials: 'same-origin'
        });
        await cleanupRes.json().catch(() => null); // best effort

        const res = await fetch(`/teams/api/get_team_invitations.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(`HTTP ${res.status}: ${text}`);
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to load invitations');
        }

        const invites = data.invitations || [];
        grid.innerHTML = '';

        if (invites.length === 0) {
            grid.innerHTML = '<div class="member-card">No invitations yet.</div>';
            return;
        }

        invites.forEach(inv => {
            const card = document.createElement('div');
            card.className = 'member-card';

            const status = inv.status || 'pending';
            const invitedAt = inv.invited_at ? new Date(inv.invited_at).toLocaleString() : '—';
            const expiresAt = inv.code_expires_at ? new Date(inv.code_expires_at).toLocaleString() : '—';
            const respondedAt = inv.responded_at ? new Date(inv.responded_at).toLocaleString() : '—';

            card.innerHTML = `
                <strong>${inv.invited_name || 'Unknown'}</strong><br>
                ${inv.invited_reg_no || inv.invited_email || '—'}<br>
                <em>Status: ${status}</em><br>
                <small>Invited: ${invitedAt}</small><br>
                <small>Code expires: ${expiresAt}</small><br>
                ${status !== 'pending' ? `<small>Responded: ${respondedAt}</small>` : ''}
                ${status === 'pending' ? `
                    <div style="margin-top:0.45rem;">
                        <button class="resendInviteBtn" data-invite-id="${inv.id}" style="background:#fd7e14;">Resend</button>
                    </div>
                ` : ''}
            `;
            grid.appendChild(card);
        });

        document.querySelectorAll('.resendInviteBtn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const inviteId = btn.dataset.inviteId;
                btn.disabled = true;
                try {
                    const res = await fetch('/teams/api/resend_invitation_code.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            team_id: teamId,
                            invitation_id: inviteId,
                            csrf_token: csrf
                        })
                    });
                    const data = await res.json().catch(() => null);
                    if (!res.ok || !data || !data.success) {
                        throw new Error(data?.error || ('HTTP ' + res.status));
                    }
                    showMessage(data.message || 'Code resent', 'success');
                    loadInvitations();
                } catch (err) {
                    btn.disabled = false;
                    showMessage('Resend code error: ' + err.message);
                }
            });
        });
    } catch (err) {
        grid.innerHTML = `<div class="member-card" style="color:#b91c1c;">Failed to load invitations: ${err.message}</div>`;
    }
}

// Add member
async function addMember() {
    const identifier = document.getElementById('identifier').value.trim();
    const role = normalizeTeamRole(document.getElementById('memberRole')?.value);
    if (!identifier) {
        showMessage('Please enter registration number or email');
        return;
    }

    try {
        const res = await fetch('/teams/api/add_member.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                identifier,
                role,
                csrf_token: csrf
            })
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(`HTTP ${res.status}: ${text}`);
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || data.message || 'Failed to add member');
        }

        showMessage(data.message || 'Member added successfully', 'success');
        document.getElementById('identifier').value = '';
        const roleSelect = document.getElementById('memberRole');
        if (roleSelect) roleSelect.value = 'member';
        loadTeam();

    } catch (err) {
        showMessage("Error adding member: " + err.message);
        console.error("addMember error:", err);
    }
}

// Remove member
async function removeMember(studentId) {
    if (!confirm('Remove this member? This cannot be undone.')) return;

    try {
        const res = await fetch('/teams/api/remove_member.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                student_id: studentId,
                csrf_token: csrf
            })
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(`HTTP ${res.status}: ${text}`);
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || data.message || 'Failed to remove member');
        }

        showMessage(data.message || 'Member removed successfully', 'success');
        loadTeam();

    } catch (err) {
        showMessage("Error removing member: " + err.message);
        console.error("removeMember error:", err);
    }
}

// Request membership leave (student wants to leave)
async function requestMembershipLeave(studentId) {
    const reason = prompt('Why do you want to leave this team?', '');
    if (reason === null) return;

    try {
        const res = await fetch('/teams/api/request_membership_leave.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                reason: reason || null,
                csrf_token: csrf
            })
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(`HTTP ${res.status}: ${text}`);
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || data.message || 'Failed to submit leave request');
        }

        showMessage(data.message || 'Leave request submitted successfully', 'success');
        loadTeam();

    } catch (err) {
        showMessage("Error submitting leave request: " + err.message);
        console.error("requestMembershipLeave error:", err);
    }
}

// Request member removal (team lead wants member removed)
function showRemovalReasonModal(studentId) {
    const reason = prompt('Reason for requesting member removal:', '');
    if (reason === null) return;
    
    requestMemberRemoval(studentId, reason);
}

async function requestMemberRemoval(studentId, reason) {
    try {
        const res = await fetch('/teams/api/request_member_removal.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                student_id: studentId,
                reason: reason || null,
                csrf_token: csrf
            })
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(`HTTP ${res.status}: ${text}`);
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || data.message || 'Failed to submit removal request');
        }

        showMessage(data.message || 'Removal request submitted successfully. Awaiting lecturer approval.', 'success');
        loadTeam();

    } catch (err) {
        showMessage("Error submitting removal request: " + err.message);
        console.error("requestMemberRemoval error:", err);
    }
}

// View pending membership requests for team lead
async function viewTeamRequests(teamId) {
    try {
        const res = await fetch(`/teams/api/get_pending_membership_requests.php?team_id=${teamId}`, {
            credentials: 'same-origin'
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load requests');
        }

        // Create modal to display requests
        const requests = data.requests || [];
        
        if (requests.length === 0) {
            showMessage('No pending membership requests for this team', 'success');
            return;
        }

        let requestsHTML = '<h3>Pending Membership Requests</h3><div style="max-height:400px; overflow-y:auto;">';
        
        requests.forEach(req => {
            const status = req.status === 'pending' 
                ? 'Pending (awaiting approvals)' 
                : 'Approved (awaiting completion)';
            
            const approvalStatus = `
                <small style="display:block; margin-top:0.5rem;">
                    Lecturer: ${req.approved_by_lecturer ? '✓ Approved' : '⏳ Pending'}<br>
                    Team Lead: ${req.approved_by_team_lead ? '✓ Approved' : '⏳ Pending'}
                </small>
            `;
            
            requestsHTML += `
                <div style="border:1px solid #ddd; padding:0.8rem; margin:0.5rem 0; border-radius:4px; background:#f9f9f9;">
                    <strong>${req.request_type === 'leave' ? 'Leave Request' : 'Removal Request'}</strong><br>
                    Student: ${req.student_name} (${req.student_reg})<br>
                    Status: ${status}<br>
                    Reason: ${req.reason || '(none provided)'}<br>
                    ${approvalStatus}
                </div>
            `;
        });
        
        requestsHTML += '</div>';
        
        // Replace message div temporarily with requests
        const msgDiv = document.getElementById('message');
        const oldHTML = msgDiv.innerHTML;
        msgDiv.innerHTML = requestsHTML + '<button onclick="location.reload();">Close</button>';
        msgDiv.scrollIntoView({ behavior: 'smooth' });

    } catch (err) {
        showMessage("Error loading requests: " + err.message);
        console.error("viewTeamRequests error:", err);
    }
}

document.getElementById('deleteTeamBtn').addEventListener('click', async () => {
    const confirmed = confirm('Delete this team and remove all members and related records? This cannot be undone.');
    if (!confirmed) return;

    try {
        // Relative so the request also resolves under a subdirectory install.
        const res = await fetch('../api/delete_team.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                csrf_token: csrf
            })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || data?.message || ('HTTP ' + res.status));
        }

        showMessage(data.message || 'Team deleted successfully', 'success');
        window.location.href = '../../student/dashboard.php';
    } catch (err) {
        showMessage('Delete team error: ' + err.message);
    }
});

// Event listeners
document.getElementById('addMemberBtn').addEventListener('click', addMember);
document.getElementById('resendCodeBtn').addEventListener('click', async () => {
    const identifier = document.getElementById('identifier').value.trim();
    if (!identifier) {
        showMessage('Enter reg number or email first');
        return;
    }
    try {
        const res = await fetch('/teams/api/resend_invitation_code.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                identifier,
                csrf_token: csrf
            })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || ('HTTP ' + res.status));
        }
        showMessage(data.message || 'Code resent', 'success');
    } catch (err) {
        showMessage('Resend code error: ' + err.message);
    }
});
document.getElementById('confirmMemberBtn').addEventListener('click', async () => {
    const identifier = document.getElementById('confirmIdentifier').value.trim();
    const code = document.getElementById('confirmCode').value.trim();
    const role = normalizeTeamRole(document.getElementById('confirmMemberRole')?.value);
    if (!identifier || !code) {
        showMessage('Provide identifier and confirmation code');
        return;
    }
    try {
        const res = await fetch('/teams/api/confirm_member.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                identifier,
                code,
                role,
                csrf_token: csrf
            })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || ('HTTP ' + res.status));
        }
        showMessage(data.message || 'Member confirmed', 'success');
        document.getElementById('confirmIdentifier').value = '';
        document.getElementById('confirmCode').value = '';
        const confirmRoleSelect = document.getElementById('confirmMemberRole');
        if (confirmRoleSelect) confirmRoleSelect.value = 'member';
        loadTeam();
    } catch (err) {
        showMessage('Confirm member error: ' + err.message);
    }
});
document.getElementById('submitBtn').addEventListener('click', () => {
    window.location.href = `submit.php?team_id=${teamId}`;
});
document.getElementById('refreshInvitesBtn').addEventListener('click', loadInvitations);

// Supervisor management functions
function canManageSupervisors() {
    return true;
}

async function loadSupervisors() {
    const container = document.getElementById('existingSupervisors');
    container.innerHTML = '<p>Loading supervisors...</p>';
    
    try {
        const res = await fetch(`/teams/api/get_team_supervisors.php?team_id=${teamId}`, {
            credentials: 'same-origin'
        });
        
        if (!res.ok) throw new Error('HTTP ' + res.status);
        
        const data = await res.json();
        
        if (!data.success) {
            container.innerHTML = '<p style="color: #dc3545;">Failed to load supervisors</p>';
            return;
        }
        
        if (data.supervisors.length === 0) {
            container.innerHTML = '<p style="color: #6c757d;">No supervisors assigned yet.</p>';
            return;
        }
        
        let html = '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';
        data.supervisors.forEach(sup => {
            const statusClass = sup.status === 'approved' ? 'success' : 
                               sup.status === 'pending' ? 'warning' : 'error';
            const statusLabel = sup.status.charAt(0).toUpperCase() + sup.status.slice(1);
            const typeLabel = sup.supervisor_type === 'technician' ? 'Technician' : 'Lecturer';
            
            html += `
                <div style="padding: 0.5rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                    <strong>${sup.name}</strong> <span style="font-size: 0.8rem; color: #6c757d;">(${typeLabel})</span>
                    <span style="margin-left: 0.5rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; background: ${sup.status === 'approved' ? '#d4edda' : sup.status === 'pending' ? '#fff3cd' : '#f8d7da'}; color: ${sup.status === 'approved' ? '#155724' : sup.status === 'pending' ? '#856404' : '#721c24'};">${statusLabel}</span>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (err) {
        console.error('Error loading supervisors:', err);
        container.innerHTML = '<p style="color: #dc3545;">Error loading supervisors</p>';
    }
}

async function searchSupervisor() {
    const email = document.getElementById('supervisorEmail').value.trim();
    const resultsDiv = document.getElementById('searchResults');
    
    if (!email) {
        resultsDiv.innerHTML = '<p style="color: #6c757d; font-size: 0.85rem;">Please enter an email address</p>';
        return;
    }
    
    resultsDiv.innerHTML = '<p style="color: #6c757d; font-size: 0.85rem;">Searching...</p>';
    
    try {
        const res = await fetch(`/teams/api/search_supervisor.php?team_id=${teamId}&email=${encodeURIComponent(email)}`, {
            credentials: 'same-origin'
        });
        
        if (!res.ok) throw new Error('HTTP ' + res.status);
        
        const data = await res.json();
        
        if (!data.success) {
            resultsDiv.innerHTML = `<p style="color: #dc3545; font-size: 0.85rem;">${data.message}</p>`;
            return;
        }
        
        if (data.results.length === 0) {
            resultsDiv.innerHTML = '<p style="color: #6c757d; font-size: 0.85rem;">No supervisors found with this email</p>';
            return;
        }
        
        let html = '';
        data.results.forEach(person => {
            const typeLabel = person.supervisor_type === 'technician'
                ? 'Technician'
                : person.supervisor_type === 'admin'
                    ? 'Department Admin'
                    : 'Lecturer';
            html += `
                <div style="padding: 0.5rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 0.5rem; cursor: pointer;" onclick="nominateSupervisor(${person.id}, '${person.supervisor_type}', '${person.name.replace(/'/g, "\\'")}')">
                    <strong>${person.name}</strong> <span style="font-size: 0.8rem; color: #6c757d;">(${typeLabel})</span><br>
                    <span style="font-size: 0.85rem; color: #6c757d;">${person.email}</span><br>
                    <span style="font-size: 0.8rem; color: #6c757d;">${person.team_count} teams supervised</span>
                </div>
            `;
        });
        
        resultsDiv.innerHTML = html;
    } catch (err) {
        console.error('Error searching supervisor:', err);
        resultsDiv.innerHTML = '<p style="color: #dc3545; font-size: 0.85rem;">Error searching supervisor</p>';
    }
}

async function nominateSupervisor(personId, supervisorType, personName) {
    if (!canManageSupervisors()) {
        showMessage('Only the unit lecturer, admin, or approved supervisor can manage supervisors for this team', 'error');
        return;
    }

    if (!confirm(`Nominate ${personName} as supervisor for this team? They will receive an email to approve the request.`)) {
        return;
    }
    
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    try {
        const res = await fetch('/teams/api/request_supervisor.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                lecturer_id: personId,
                supervisor_type: supervisorType,
                csrf_token: csrf
            })
        });
        
        const data = await res.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            document.getElementById('supervisorEmail').value = '';
            document.getElementById('searchResults').innerHTML = '';
            loadSupervisors();
        } else {
            showMessage(data.message || 'Failed to nominate supervisor', 'error');
        }
    } catch (err) {
        console.error('Error nominating supervisor:', err);
        showMessage('Error nominating supervisor: ' + (err.message || 'Unknown error'), 'error');
    }
}

document.getElementById('searchSupervisorBtn').addEventListener('click', searchSupervisor);

// Load supervisors on page load
loadSupervisors();

const teamLimitSlider = document.getElementById('teamLimitSlider');
const teamLimitValue = document.getElementById('teamLimitValue');
if (teamLimitSlider && teamLimitValue) {
    teamLimitSlider.addEventListener('input', () => {
        teamLimitValue.textContent = teamLimitSlider.value;
    });
}

document.getElementById('saveTeamSettingsBtn').addEventListener('click', async () => {
    if (!isCurrentUserLeader) {
        showMessage('Only team leaders can update team settings');
        return;
    }

    const max_members = Number(teamLimitSlider?.value || 15);
    const minAllowed = Math.max(2, currentTeamSize);

    if (!Number.isFinite(max_members) || max_members < minAllowed || max_members > 15) {
        showMessage(`Member limit must be between ${minAllowed} and 15`);
        return;
    }

    const saveBtn = document.getElementById('saveTeamSettingsBtn');
    saveBtn.disabled = true;
    const oldLabel = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';

    try {
        const res = await fetch('/teams/api/update_team_settings.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                max_members,
                csrf_token: csrf
            })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || ('HTTP ' + res.status));
        }

        showMessage(data.message || 'Team settings updated', 'success');
        maxTeamMembers = Number(data.max_members) || max_members;
        loadTeam();
    } catch (err) {
        showMessage('Save settings error: ' + err.message);
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = oldLabel;
    }
});

// Load on page ready
loadTeam();
</script>

</body>
</html>