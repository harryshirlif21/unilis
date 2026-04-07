<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../../login.php"); // or wherever your login page is
    exit;
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
    <input type="text" id="identifier" placeholder="Enter reg number or email">
    <button id="addMemberBtn">Add Member</button>
    <button id="resendCodeBtn" style="background:#fd7e14;">Resend Code</button>
    <button id="submitBtn">Submit Files</button>
</div>
<div class="actions">
    <input type="text" id="confirmIdentifier" placeholder="Confirm member reg/email">
    <input type="text" id="confirmCode" placeholder="Enter 6-digit code">
    <button id="confirmMemberBtn" style="background:#6f42c1;">Confirm Member</button>
</div>

<h3>Members (<span id="size">0</span>/5)</h3>
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

const messageDiv = document.getElementById('message');

// Helper: show message
function showMessage(text, type = 'error') {
    messageDiv.className = type;
    messageDiv.textContent = text;
    messageDiv.scrollIntoView({ behavior: 'smooth' });
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

        // Update header
        header.innerHTML = `
            <h2>${data.team.title || 'Untitled Team'}</h2>
            <p>Unit: ${data.team.unit_name || '—'} | Assessment: ${data.team.assessment_title || '—'}</p>
            <p>Status: ${data.team.status || 'active'} | Created: ${new Date(data.team.created_at).toLocaleDateString()}</p>
        `;

        const grid = document.getElementById('members-grid');
        grid.innerHTML = '';

        const members = data.members || [];

        // Mark current user and set leader role
        members.forEach(m => {
            m.isCurrentUser = (m.student_id == currentUserId);
            if (!m.role) m.role = 'member';
        });

        // Sort: leader first, then others
        members.sort((a, b) => {
            if (a.role === 'leader') return -1;
            if (b.role === 'leader') return 1;
            return 0;
        });

        members.forEach(m => {
            const card = document.createElement('div');
            card.className = `member-card ${m.role === 'leader' ? 'leader' : ''}`;
            card.innerHTML = `
                <strong>${m.name || 'Unknown'}</strong><br>
                ${m.reg_no || m.reg_number || m.email || '—'}<br>
                <em>${m.role}${m.isCurrentUser ? ' (You)' : ''}</em>
                ${m.role !== 'leader' ? `<button class="remove" data-sid="${m.student_id}">Remove</button>` : ''}
            `;
            grid.appendChild(card);
        });

        document.getElementById('size').textContent = members.length;

        // Add remove listeners
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
        loadTeam();
    } catch (err) {
        showMessage('Confirm member error: ' + err.message);
    }
});
document.getElementById('submitBtn').addEventListener('click', () => {
    window.location.href = `submit.php?team_id=${teamId}`;
});
document.getElementById('refreshInvitesBtn').addEventListener('click', loadInvitations);

// Load on page ready
loadTeam();
</script>

</body>
</html>