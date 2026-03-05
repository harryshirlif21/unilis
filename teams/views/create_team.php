<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// CSRF token
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
    <style>
        :root {
            --primary-orange: #f97316;
            --primary-orange-dark: #ea580c;
            --create-green: #16a34a;
            --create-green-dark: #15803d;
            --golden: #fbbf24;
            --golden-dark: #f59e0b;
            --gray: #6b7280;
            --light: #f3f4f6;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            margin: 0;
            padding: 0;
            color: #111827;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        header {
            background: var(--white);
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1 {
            margin: 0;
            color: var(--primary-orange);
            font-size: 1.8rem;
        }

        .user-info {
            font-size: 0.95rem;
            color: var(--gray);
        }

        .user-info a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: 500;
        }

        .user-info a:hover {
            text-decoration: underline;
        }

        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .create-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        label {
            font-weight: 500;
            color: #374151;
        }

        input[type="text"] {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        #createBtn {
            background: var(--golden);
            color: #1f2937;
        }

        #createBtn:hover {
            background: var(--golden-dark);
        }

        #message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .success { background: #ecfdf5; color: #065f46; }
        .error   { background: #fef2f2; color: #991b1b; }

        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .team-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s;
        }

        .team-card:hover {
            transform: translateY(-4px);
        }

        .team-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        }

        .team-header h3 {
            margin: 0;
            color: var(--primary-orange);
            font-size: 1.25rem;
        }

        .team-body {
            padding: 1.25rem 1.5rem;
        }

        .team-info {
            color: var(--gray);
            font-size: 0.95rem;
            margin: 0.5rem 0;
        }

        .team-actions {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            text-align: right;
        }

        .team-actions a {
            background: var(--primary-orange);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .team-actions a:hover {
            background: var(--primary-orange-dark);
        }

        .loading {
            text-align: center;
            color: var(--gray);
            padding: 3rem 0;
        }

        h2.create-heading {
            color: var(--create-green);
            margin-top: 0;
        }
    </style>
</head>
<body>

<header>
    <div class="container header-content">
        <h1>Create & Manage Teams</h1>
        <div class="user-info">
            Welcome, Student #<?php echo htmlspecialchars($_SESSION['user_id']); ?> 
            | <a href="/logout.php">Logout</a>
        </div>
    </div>
</header>

<div class="container">

    <!-- Create Team Card -->
    <div class="card">
        <h2 class="create-heading">Create a New Team</h2>
        <div id="message"></div>
        
        <form id="createTeamForm" class="create-form">
            <div class="form-group">
                <label for="title">Team Title *</label>
                <input type="text" id="title" placeholder="e.g. Quantum Coders" required>
            </div>
            <div class="form-group">
                <label for="unit_name">Unit Name *</label>
                <input type="text" id="unit_name" placeholder="e.g. Database Systems" required>
            </div>
            <div class="form-group">
                <label for="assessment_title">Assessment *</label>
                <input type="text" id="assessment_title" placeholder="e.g. Final Project" required>
            </div>
            <button type="submit" id="createBtn">Create Team</button>
        </form>
    </div>

    <!-- Your Teams Section -->
    <div class="card">
        <h2>Your Teams</h2>
        <div id="teams-list">
            <div class="loading">Loading your teams...</div>
        </div>
    </div>

</div>

<script>
const csrf = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";
const messageDiv = document.getElementById('message');

// Show message with icon
function showMessage(text, type = 'error') {
    messageDiv.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${text}
    `;
    messageDiv.className = type;
    messageDiv.style.display = 'flex';
    messageDiv.style.alignItems = 'center';
    messageDiv.style.gap = '0.75rem';
}

// Load user's teams
async function loadTeams() {
    const listDiv = document.getElementById('teams-list');
    listDiv.innerHTML = '<div class="loading">Loading your teams...</div>';

    try {
        const res = await fetch('/teams/api/get_user_teams.php', {
            credentials: 'same-origin'
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();

        if (!data.success) throw new Error(data.error || 'Failed to load teams');

        if (data.teams.length === 0) {
            listDiv.innerHTML = '<p style="color:#6b7280">You are not in any teams yet.</p>';
            return;
        }

        listDiv.innerHTML = '';
        data.teams.forEach(team => {
            const card = document.createElement('div');
            card.className = 'team-card';
            card.innerHTML = `
                <div class="team-header">
                    <h3>${team.title}</h3>
                </div>
                <div class="team-body">
                    <p class="team-info"><strong>Unit:</strong> ${team.unit_name || '—'}</p>
                    <p class="team-info"><strong>Assessment:</strong> ${team.assessment_title || '—'}</p>
                    <p class="team-info"><strong>Status:</strong> ${team.status || 'active'}</p>
                    <p class="team-info"><strong>Members:</strong> ${team.members?.length || 0}</p>
                </div>
                <div class="team-actions">
                    <a href="/teams/views/manage_team.php?team_id=${team.id}">Manage Team</a>
                    <a href="/teams/views/workspace.php?team_id=${team.id}" style="margin-left:0.5rem;background:#10b981;">Open Workspace</a>
                </div>
            `;
            listDiv.appendChild(card);
        });
    } catch (err) {
        listDiv.innerHTML = `<p class="error">Error loading teams: ${err.message}</p>`;
        console.error('loadTeams error:', err);
    }
}

// Create new team
document.getElementById('createTeamForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const title = document.getElementById('title').value.trim();
    const unit_name = document.getElementById('unit_name').value.trim();
    const assessment_title = document.getElementById('assessment_title').value.trim();

    if (!title || !unit_name || !assessment_title) {
        showMessage('Please fill in all fields', 'error');
        return;
    }

    try {
        const res = await fetch('/teams/api/create_team.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, unit_name, assessment_title, csrf_token: csrf })
        });

        const data = await res.json();

        if (!data.success) throw new Error(data.error || 'Failed to create team');

        showMessage('Team created successfully!', 'success');
        document.getElementById('createTeamForm').reset();
        loadTeams();

    } catch (err) {
        showMessage('Error creating team: ' + err.message, 'error');
        console.error('createTeam error:', err);
    }
});

// Initial load
loadTeams();
</script>

</body>
</html>