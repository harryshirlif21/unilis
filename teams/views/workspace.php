<?php
session_start();

require_once '../config.php';
require_once '../includes/team_access.php';

// Get team_id from URL
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Basic auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: /login.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$userRole = strtolower((string) $_SESSION['user_role']);

// Check if user can access this team (students must be members, supervisors must be approved)
$canAccess = false;
if ($userRole === 'student') {
    // Students must be team members - check via team_members table
    $memberCheck = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1");
    if ($memberCheck) {
        $memberCheck->bind_param('ii', $teamId, $currentUserId);
        $memberCheck->execute();
        $canAccess = $memberCheck->get_result()->num_rows > 0;
        $memberCheck->close();
    }
} elseif (in_array($userRole, ['lecturer', 'admin', 'technician'])) {
    // Supervisors/lecturers/admins use team_access function
    $canAccess = team_user_can_access_team($conn, $teamId, $currentUserId, $userRole);
}

if (!$canAccess) {
    header('Location: /login.php');
    exit;
}

// Ensure CSRF token exists for any POST/fetch actions coming from this page
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
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

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.75rem;
        }

        .file-card {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.6rem;
            background: #fff;
        }

        .file-thumb {
            height: 120px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            margin-bottom: 0.5rem;
            overflow: hidden;
            font-size: 0.85rem;
            color: #6b7280;
            text-align: center;
            padding: 0.5rem;
        }

        .file-thumb img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .read-btn {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.35rem 0.65rem;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .file-viewer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .file-viewer-overlay.active { display: flex; }

        .file-viewer {
            width: min(96vw, 1100px);
            height: min(92vh, 820px);
            background: #fff;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .file-viewer-header {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-viewer-content {
            flex: 1;
            background: #fff;
        }

        .file-viewer-content iframe {
            width: 100%;
            height: 100%;
            border: none;
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
            <?php echo ucfirst($userRole); ?> ID: <?php echo htmlspecialchars($currentUserId); ?><br>
            <?php if ($userRole === 'student'): ?>
                <a href="/teams/views/create_team.php">&larr; Back to Teams</a>
            <?php elseif (in_array($userRole, ['lecturer', 'admin', 'technician'])): ?>
                <a href="/teams/views/lecturer_teams.php">&larr; Back to Lecturer Teams</a>
            <?php endif; ?>
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
            <button class="tab-button" data-tab="peer">Peer Evaluation</button>
            <button class="tab-button supervisor-only" data-tab="supervisor" style="display: none;">Supervisor Tools</button>
            <button class="tab-button" data-tab="activity">Activity Log</button>
            <button class="tab-button" data-tab="health">Health Score</button>
            <button class="tab-button" data-tab="standups">Stand-ups</button>
        </div>

        <div class="tab-panels">
            <div class="tab-panel active" id="tab-files">
                <p class="muted">
                    Latest team files and versions from <span class="pill">team_files</span>.
                </p>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;margin:0.5rem 0 0.75rem 0;">
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Submission title</label>
                        <input id="workspaceSubmissionTitle" type="text" placeholder="e.g. Sprint 2 Draft" style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Description (optional)</label>
                        <input id="workspaceSubmissionDescription" type="text" placeholder="What files are included" style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Type</label>
                        <select id="workspaceSubmissionType" style="padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                            <option value="team">Team</option>
                            <option value="individual">Individual</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Files</label>
                        <input id="workspaceFilesInput" type="file" multiple style="width:100%;padding:0.45rem;border:1px solid #d1d5db;border-radius:8px;background:#fff;">
                    </div>
                    <button id="workspaceUploadBtn" type="button" style="background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0.6rem 0.9rem;cursor:pointer;">
                        Upload files
                    </button>
                    <span id="workspaceUploadStatus" class="muted"></span>
                </div>
                <p id="filesStatus" class="muted">Loading files...</p>
                <div id="filesList" class="muted" style="font-size:0.9rem;"></div>
            </div>

            <div class="tab-panel" id="tab-tasks">
                <p class="muted">
                    Kanban board for team tasks (To Do / In Progress / Done),
                    backed by <span class="pill">team_tasks</span>.
                </p>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;margin:0.5rem 0;">
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Task title</label>
                        <input id="newTaskTitle" type="text" style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Description (optional)</label>
                        <input id="newTaskDesc" type="text" style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Due</label>
                        <input id="newTaskDue" type="date" style="padding:0.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Priority</label>
                        <select id="newTaskPriority" style="padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                            <option>Low</option>
                            <option selected>Medium</option>
                            <option>High</option>
                        </select>
                    </div>
                    <button id="createTaskBtn" type="button" style="background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0.6rem 0.9rem;cursor:pointer;">
                        Add task
                    </button>
                    <span id="createTaskStatus" class="muted"></span>
                </div>
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
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;margin:0.5rem 0;">
                    <button id="signoffBtn" type="button" style="background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0.5rem 0.8rem;cursor:pointer;">
                        Sign off checklist
                    </button>
                    <span id="signoffStatus" class="muted"></span>
                </div>
                <p id="checklistStatus" class="muted">Loading checklist...</p>
                <ul id="checklistList" class="activity-list"></ul>
                <div style="margin-top:0.75rem;">
                    <div class="muted" style="font-weight:600;margin-bottom:0.25rem;">Sign-offs</div>
                    <ul id="signoffsList" class="activity-list"></ul>
                </div>
            </div>

            <div class="tab-panel" id="tab-peer">
                <p class="muted">Submit peer evaluation and review team averages.</p>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;margin:0.5rem 0;">
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Member</label>
                        <select id="peerMember" style="padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;min-width:180px;"></select>
                    </div>
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Contribution</label>
                        <input id="peerContribution" type="number" min="1" max="5" value="4" style="width:72px;padding:0.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Communication</label>
                        <input id="peerCommunication" type="number" min="1" max="5" value="4" style="width:72px;padding:0.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Quality</label>
                        <input id="peerQuality" type="number" min="1" max="5" value="4" style="width:72px;padding:0.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div>
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Reliability</label>
                        <input id="peerReliability" type="number" min="1" max="5" value="4" style="width:72px;padding:0.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <button id="peerSubmitBtn" type="button" style="background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0.6rem 0.9rem;cursor:pointer;">
                        Submit evaluation
                    </button>
                    <span id="peerStatus" class="muted"></span>
                </div>
                <p id="peerSummaryStatus" class="muted">Loading peer evaluation summary...</p>
                <div id="peerSummaryTable" class="muted" style="font-size:0.9rem;"></div>
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
                <div style="margin-top:0.75rem;">
                    <div class="muted" style="font-weight:600;margin-bottom:0.25rem;">Ghost detection</div>
                    <p id="ghostStatus" class="muted">Loading inactive members...</p>
                    <ul id="ghostList" class="activity-list"></ul>
                </div>
            </div>

            <div class="tab-panel" id="tab-standups">
                <p class="muted">
                    Lightweight daily stand-up entries stored in
                    <span class="pill">standup_entries</span>.
                </p>
                <p id="standupsStatus" class="muted">Loading recent stand-ups...</p>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;margin:0.5rem 0;">
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">What did you do today?</label>
                        <input id="didTodayInput" type="text" style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">What will you do next?</label>
                        <input id="willDoNextInput" type="text" style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label class="muted" style="display:block;margin-bottom:0.25rem;">Blockers (optional)</label>
                        <input id="blockersInput" type="text" style="width:100%;padding:0.55rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                    <button id="submitStandupBtn" type="button" style="background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0.6rem 0.9rem;cursor:pointer;">
                        Submit stand-up
                    </button>
                    <span id="submitStandupStatus" class="muted"></span>
                </div>
                <ul id="standupsList" class="activity-list"></ul>
            </div>

            <div class="tab-panel" id="tab-supervisor">
                <p class="muted">
                    Supervisor tools for managing team progress, marks, and membership requests.
                </p>
                
                <div style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 0.75rem 0; color: #1e293b;">📊 Team Insights</h4>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: #64748b;">Load comprehensive team intelligence including files, kanban, checklist, standups, heatmap, and peer evaluation.</p>
                    <button id="loadInsightsBtn" type="button" style="background: #3b82f6; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer;">
                        Load Full Insights
                    </button>
                    <span id="loadInsightsStatus" class="muted" style="margin-left: 0.5rem;"></span>
                </div>

                <div style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 0.75rem 0; color: #1e293b;">👥 Manage Supervisors</h4>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: #64748b;">Add or remove supervisors for this team.</p>
                    <button id="manageSupervisorsBtn" type="button" style="background: #8b5cf6; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer;">
                        Manage Supervisors
                    </button>
                </div>

                <div style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 0.75rem 0; color: #1e293b;">🏆 Award Marks</h4>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: #64748b;">Award group or individual marks for team members.</p>
                    <button id="awardMarksBtn" type="button" style="background: #10b981; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer;">
                        Award Marks
                    </button>
                </div>

                <div style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 0.75rem 0; color: #1e293b;">📋 Membership Requests</h4>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: #64748b;">Review and approve/reject team membership leave and removal requests.</p>
                    <button id="membershipRequestsBtn" type="button" style="background: #f59e0b; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer;">
                        View Membership Requests
                    </button>
                </div>

                <div style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 0.75rem 0; color: #1e293b;">📥 Export Data</h4>
                    <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: #64748b;">Export team data and peer evaluation reports.</p>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button id="exportPdfBtn" type="button" style="background: #dc2626; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer;">
                            Export PDF
                        </button>
                        <button id="exportExcelBtn" type="button" style="background: #059669; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer;">
                            Export Excel
                        </button>
                        <button id="exportPeerCsvBtn" type="button" style="background: #6366f1; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer;">
                            Peer Eval CSV
                        </button>
                    </div>
                </div>

                <div id="supervisorInsightsBody" style="margin-top: 1rem;"></div>
            </div>
        </div>
    </section>
</div>

<script>
// --- Basic state ---
const urlParams = new URLSearchParams(window.location.search);
const teamId = urlParams.get('team_id');
const currentUserId = <?php echo json_encode($currentUserId); ?>;
const userRole = <?php echo json_encode($userRole); ?>;
const csrfToken = <?php echo json_encode($csrfToken); ?>;

if (!teamId) {
    document.body.innerHTML = '<div class="workspace-shell"><p class="error">Missing team_id in URL.</p></div>';
}

// Check if user is supervisor (lecturer, admin, or technician)
const isSupervisor = ['lecturer', 'admin', 'technician'].includes(userRole);

// --- Tab switching ---
const tabButtons = document.querySelectorAll('.tab-button');
const panels = {
    files: document.getElementById('tab-files'),
    tasks: document.getElementById('tab-tasks'),
    checklist: document.getElementById('tab-checklist'),
    peer: document.getElementById('tab-peer'),
    supervisor: document.getElementById('tab-supervisor'),
    activity: document.getElementById('tab-activity'),
    health: document.getElementById('tab-health'),
    standups: document.getElementById('tab-standups')
};

// Show supervisor tab only for supervisors
if (isSupervisor) {
    document.querySelectorAll('.supervisor-only').forEach(btn => {
        btn.style.display = 'inline-block';
    });
}

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
        } else if (target === 'peer' && !panels.peer.dataset.loaded) {
            loadPeerSummary();
        } else if (target === 'health' && !panels.health.dataset.loaded) {
            loadHealth();
        } else if (target === 'standups' && !panels.standups.dataset.loaded) {
            loadStandups();
        } else if (target === 'supervisor' && !panels.supervisor.dataset.loaded) {
            panels.supervisor.dataset.loaded = '1';
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

// --- Peer evaluations ---
async function loadPeerSummary() {
    const statusEl = document.getElementById('peerSummaryStatus');
    const tableEl = document.getElementById('peerSummaryTable');
    const memberSel = document.getElementById('peerMember');

    statusEl.textContent = 'Loading peer evaluation summary...';
    tableEl.innerHTML = '';
    if (memberSel) memberSel.innerHTML = '';

    try {
        const res = await fetch(`/teams/api/peer_evaluation_summary.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Could not load peer evaluations');

        const members = data.members || [];
        const summary = data.summary || [];

        if (memberSel) {
            const options = members
                .filter(m => Number(m.student_id) !== Number(currentUserId))
                .map(m => `<option value="${m.student_id}">${m.name}</option>`)
                .join('');
            memberSel.innerHTML = options || '<option value="">No teammates available</option>';
        }

        if (summary.length === 0) {
            statusEl.textContent = 'No evaluations yet.';
            panels.peer.dataset.loaded = '1';
            return;
        }

        statusEl.textContent = '';
        let html = '<table style="width:100%;border-collapse:collapse;">';
        html += '<tr><th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Member</th><th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Responses</th><th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Overall</th></tr>';
        summary.forEach(r => {
            html += `<tr>
                <td style="padding:6px;border-bottom:1px solid #f1f1f1;">${r.evaluatee_name || ('User #' + r.evaluatee_id)}</td>
                <td style="padding:6px;border-bottom:1px solid #f1f1f1;">${r.responses}</td>
                <td style="padding:6px;border-bottom:1px solid #f1f1f1;">${Number(r.avg_overall || 0).toFixed(2)} / 5</td>
            </tr>`;
        });
        html += '</table>';
        tableEl.innerHTML = html;

        panels.peer.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading peer evaluations: ' + err.message;
        statusEl.classList.add('error');
    }
}

const peerSubmitBtn = document.getElementById('peerSubmitBtn');
if (peerSubmitBtn) {
    peerSubmitBtn.addEventListener('click', async () => {
        const statusEl = document.getElementById('peerStatus');
        const evaluatee_id = Number(document.getElementById('peerMember')?.value || 0);
        const contribution = Number(document.getElementById('peerContribution')?.value || 0);
        const communication = Number(document.getElementById('peerCommunication')?.value || 0);
        const quality = Number(document.getElementById('peerQuality')?.value || 0);
        const reliability = Number(document.getElementById('peerReliability')?.value || 0);

        if (!evaluatee_id) {
            if (statusEl) statusEl.textContent = 'Select a teammate first';
            return;
        }
        if (statusEl) statusEl.textContent = 'Submitting...';

        try {
            const res = await fetch('/teams/api/peer_evaluation_submit.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    team_id: teamId,
                    evaluatee_id,
                    contribution,
                    communication,
                    quality,
                    reliability,
                    csrf_token: csrfToken
                })
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error(data?.error || ('HTTP ' + res.status));
            }
            if (statusEl) statusEl.textContent = data.message || 'Submitted';
            loadPeerSummary();
            if (panels.activity && panels.activity.dataset.loaded) {
                loadActivity();
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Submit failed: ' + err.message;
        }
    });
}

// --- Files tab: list + upload for all team members ---
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
            statusEl.textContent = 'No files yet. Any team member can upload using the form above.';
            return;
        }

        statusEl.textContent = '';

        const rows = files.map(f => {
            const when = f.uploaded_at ? new Date(f.uploaded_at).toLocaleString() : '';
            const v = f.version ? `v${f.version}` : '';
            const mime = (f.mime_type || '').toLowerCase();
            const isImage = mime.startsWith('image/');
            const isPdf = mime.includes('pdf');
            const thumb = isImage
                ? `<img src="/teams/api/view_team_file.php?file_id=${encodeURIComponent(f.id)}" alt="thumbnail">`
                : (isPdf ? `<span>PDF preview available</span>` : `<span>No preview</span>`);
            return `<div class="file-card">
                <div class="file-thumb">${thumb}</div>
                <strong>${f.file_name || 'File'}</strong>
                ${v ? `<span class="pill">${v}</span>` : ''}
                <div class="activity-meta">Uploaded ${when}</div>
                <div class="activity-meta">By: ${f.uploader_name || ('User #' + (f.uploaded_by || ''))}</div>
                <div style="margin-top:0.4rem;">
                    <button class="read-btn" onclick="openTeamFileViewer(${Number(f.id) || 0}, '${String((f.file_name || 'File').replace(/'/g, "\\'"))}')">Read</button>
                    <button class="read-btn" style="background:#dc2626;margin-left:0.35rem;" onclick="deleteTeamFile(${Number(f.id) || 0}, '${String((f.file_name || 'File').replace(/'/g, "\\'"))}')">Delete</button>
                </div>
            </div>`;
        });

        listEl.innerHTML = `<div class="files-grid">${rows.join('')}</div>`;
        panels.files.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading files: ' + err.message;
        statusEl.classList.add('error');
    }
}

async function deleteTeamFile(fileId, fileName) {
    if (!fileId) return;
    const ok = confirm(`Delete file "${fileName || 'this file'}"? This action cannot be undone.`);
    if (!ok) return;

    const statusEl = document.getElementById('filesStatus');
    if (statusEl) statusEl.textContent = 'Deleting file...';

    try {
        const res = await fetch('/teams/api/delete_team_file.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file_id: fileId,
                csrf_token: csrfToken
            })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || ('HTTP ' + res.status));
        }

        if (statusEl) statusEl.textContent = data.message || 'File deleted';
        loadFiles();
        if (panels.activity && panels.activity.dataset.loaded) {
            loadActivity();
        }
    } catch (err) {
        if (statusEl) statusEl.textContent = 'Delete failed: ' + err.message;
    }
}

const workspaceUploadBtn = document.getElementById('workspaceUploadBtn');
if (workspaceUploadBtn) {
    workspaceUploadBtn.addEventListener('click', async () => {
        const statusEl = document.getElementById('workspaceUploadStatus');
        const titleEl = document.getElementById('workspaceSubmissionTitle');
        const descEl = document.getElementById('workspaceSubmissionDescription');
        const typeEl = document.getElementById('workspaceSubmissionType');
        const filesEl = document.getElementById('workspaceFilesInput');

        const title = (titleEl?.value || '').trim();
        const description = (descEl?.value || '').trim();
        const submissionType = (typeEl?.value || 'team').trim();
        const fileList = filesEl?.files ? Array.from(filesEl.files) : [];

        if (!title) {
            if (statusEl) statusEl.textContent = 'Submission title is required';
            return;
        }
        if (!fileList.length) {
            if (statusEl) statusEl.textContent = 'Please select at least one file';
            return;
        }

        if (statusEl) statusEl.textContent = 'Uploading...';
        workspaceUploadBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('team_id', teamId);
            formData.append('submission_title', title);
            formData.append('submission_description', description);
            formData.append('submission_type', submissionType === 'individual' ? 'individual' : 'team');
            formData.append('csrf_token', csrfToken);
            fileList.forEach(file => formData.append('files[]', file));

            const res = await fetch('/teams/api/submit.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error(data?.error || ('HTTP ' + res.status));
            }

            if (statusEl) statusEl.textContent = data.message || 'Files uploaded';
            if (titleEl) titleEl.value = '';
            if (descEl) descEl.value = '';
            if (typeEl) typeEl.value = 'team';
            if (filesEl) filesEl.value = '';

            loadFiles();
            if (panels.activity && panels.activity.dataset.loaded) {
                loadActivity();
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Upload failed: ' + err.message;
        } finally {
            workspaceUploadBtn.disabled = false;
        }
    });
}

function openTeamFileViewer(fileId, fileName) {
    const overlay = document.getElementById('fileViewerOverlay');
    const title = document.getElementById('fileViewerTitle');
    const content = document.getElementById('fileViewerContent');
    if (!overlay || !title || !content) return;

    title.textContent = fileName || 'File Viewer';
    content.innerHTML = `<iframe src="/teams/api/view_team_file.php?file_id=${encodeURIComponent(fileId)}"></iframe>`;
    overlay.classList.add('active');
}

function closeTeamFileViewer() {
    const overlay = document.getElementById('fileViewerOverlay');
    const content = document.getElementById('fileViewerContent');
    if (overlay) overlay.classList.remove('active');
    if (content) content.innerHTML = '';
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

// --- Tasks / Kanban ---
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
            statusEl.textContent = 'No tasks yet. Any team member can create the first task above.';
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

        const statusMap = {
            todo: 'Backlog',
            in_progress: 'In Progress',
            done: 'Done'
        };

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

                    // Simple status changer
                    const controls = document.createElement('div');
                    controls.style.marginTop = '0.35rem';
                    const sel = document.createElement('select');
                    sel.style.width = '100%';
                    sel.style.padding = '0.35rem';
                    sel.style.border = '1px solid #e5e7eb';
                    sel.style.borderRadius = '6px';
                    ['Backlog', 'In Progress', 'In Review', 'Done'].forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s;
                        opt.textContent = s;
                        if ((t.raw_status || t.status_raw || '') === s || (t.status_full || '') === s || false) {
                            // no-op; older payloads
                        }
                        controls.appendChild(document.createTextNode(''));
                    });
                    // Build options and select current based on original_status if provided
                    sel.innerHTML = `
                        <option value="Backlog">Backlog</option>
                        <option value="In Progress">In Progress</option>
                        <option value="In Review">In Review</option>
                        <option value="Done">Done</option>
                    `;
                    const currentFull = t.status_full || t.original_status || null;
                    if (currentFull) {
                        sel.value = currentFull;
                    } else {
                        sel.value = statusMap[col.key] || 'Backlog';
                    }

                    sel.addEventListener('change', async (e) => {
                        const newStatus = e.target.value;
                        try {
                            const res2 = await fetch('/teams/api/task_update_status.php', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    team_id: teamId,
                                    task_id: t.id,
                                    status: newStatus,
                                    csrf_token: csrfToken
                                })
                            });
                            const payload = await res2.json().catch(() => null);
                            if (!res2.ok || !payload || !payload.success) {
                                throw new Error(payload?.error || ('HTTP ' + res2.status));
                            }
                            loadTasks();
                            if (panels.activity && panels.activity.dataset.loaded) {
                                loadActivity();
                            }
                        } catch (err) {
                            alert('Failed to update task: ' + err.message);
                            loadTasks();
                        }
                    });

                    controls.appendChild(sel);
                    card.appendChild(controls);

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

// Create task handler
const createTaskBtn = document.getElementById('createTaskBtn');
if (createTaskBtn) {
    createTaskBtn.addEventListener('click', async () => {
        const statusEl = document.getElementById('createTaskStatus');
        const titleEl = document.getElementById('newTaskTitle');
        const descEl = document.getElementById('newTaskDesc');
        const dueEl = document.getElementById('newTaskDue');
        const priEl = document.getElementById('newTaskPriority');

        const title = (titleEl?.value || '').trim();
        const description = (descEl?.value || '').trim();
        const due_date = dueEl?.value || '';
        const priority = priEl?.value || 'Medium';

        if (!title) {
            if (statusEl) statusEl.textContent = 'Title is required';
            return;
        }

        if (statusEl) statusEl.textContent = 'Creating...';

        try {
            const res = await fetch('/teams/api/task_create.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    team_id: teamId,
                    title,
                    description,
                    due_date,
                    priority,
                    csrf_token: csrfToken
                })
            });

            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error(data?.error || ('HTTP ' + res.status));
            }

            if (titleEl) titleEl.value = '';
            if (descEl) descEl.value = '';
            if (dueEl) dueEl.value = '';
            if (priEl) priEl.value = 'Medium';
            if (statusEl) statusEl.textContent = data.message || 'Task created';

            loadTasks();
            if (panels.activity && panels.activity.dataset.loaded) {
                loadActivity();
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Create failed: ' + err.message;
        }
    });
}

// --- Checklist from checklist.php ---
async function loadChecklist() {
    const statusEl = document.getElementById('checklistStatus');
    const listEl = document.getElementById('checklistList');
    const signoffsEl = document.getElementById('signoffsList');
    const signoffStatusEl = document.getElementById('signoffStatus');

    statusEl.textContent = 'Loading checklist...';
    listEl.innerHTML = '';
    if (signoffsEl) signoffsEl.innerHTML = '';
    if (signoffStatusEl) signoffStatusEl.textContent = '';

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
            const updated = it.checked_at ? new Date(it.checked_at).toLocaleString() : '';

            li.innerHTML = `
                <div>
                    <input type="checkbox" class="checklistBox" data-id="${it.id}" ${checked ? 'checked' : ''}>
                    <span>${label}</span>
                </div>
                <div class="activity-meta">
                    ${checked ? 'Completed' : 'Pending'}
                    ${updated ? ' • last updated ' + updated : ''}
                </div>
            `;
            listEl.appendChild(li);
        });

        // Wire toggle handlers (POST checklist_toggle.php)
        document.querySelectorAll('.checklistBox').forEach(box => {
            box.addEventListener('change', async (e) => {
                const id = e.target.dataset.id;
                const newVal = e.target.checked ? 1 : 0;

                try {
                    const resp = await fetch('/teams/api/checklist_toggle.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            team_id: teamId,
                            checklist_id: id,
                            is_checked: newVal,
                            csrf_token: csrfToken
                        })
                    });

                    const payload = await resp.json().catch(() => null);
                    if (!resp.ok || !payload || !payload.success) {
                        const msg = payload?.error || ('HTTP ' + resp.status);
                        throw new Error(msg);
                    }

                    // Refresh checklist + signoffs + activity (so UI stays consistent)
                    loadChecklist();
                    if (panels.activity && panels.activity.dataset.loaded) {
                        loadActivity();
                    }
                } catch (err) {
                    // Revert UI state on failure
                    e.target.checked = !e.target.checked;
                    alert('Failed to update checklist: ' + err.message);
                }
            });
        });

        // Render sign-offs (if provided)
        const signoffs = data.signoffs || [];
        if (signoffsEl) {
            if (signoffs.length === 0) {
                const li = document.createElement('li');
                li.className = 'activity-item';
                li.innerHTML = `<div class="muted">No sign-offs yet.</div>`;
                signoffsEl.appendChild(li);
            } else {
                signoffs.forEach(s => {
                    const li = document.createElement('li');
                    li.className = 'activity-item';
                    const when = s.signed_at ? new Date(s.signed_at).toLocaleString() : '';
                    li.innerHTML = `
                        <div><strong>${s.user_name || ('User #' + s.user_id)}</strong></div>
                        <div class="activity-meta">${when}</div>
                    `;
                    signoffsEl.appendChild(li);
                });
            }
        }

        panels.checklist.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading checklist: ' + err.message;
        statusEl.classList.add('error');
    }
}

// Sign-off button handler
const signoffBtn = document.getElementById('signoffBtn');
if (signoffBtn) {
    signoffBtn.addEventListener('click', async () => {
        const statusEl = document.getElementById('signoffStatus');
        if (statusEl) statusEl.textContent = 'Signing off...';

        try {
            const res = await fetch('/teams/api/checklist_signoff.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    team_id: teamId,
                    csrf_token: csrfToken
                })
            });

            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                const msg = data?.error || ('HTTP ' + res.status);
                throw new Error(msg);
            }

            if (statusEl) statusEl.textContent = data.message || 'Signed off';

            // Refresh sign-offs and activity
            if (panels.checklist && panels.checklist.dataset.loaded) {
                loadChecklist();
            }
            if (panels.activity && panels.activity.dataset.loaded) {
                loadActivity();
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Sign-off failed: ' + err.message;
        }
    });
}

// --- Health: aggregate from tasks + activity via health.php ---
async function loadHealth() {
    const statusEl = document.getElementById('healthStatus');
    const bodyEl = document.getElementById('healthBody');
    const ghostStatusEl = document.getElementById('ghostStatus');
    const ghostListEl = document.getElementById('ghostList');

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
        const heatmap = data.heatmap || {};

        const heatItems = Object.entries(heatmap);
        const maxCount = heatItems.reduce((m, [,v]) => Math.max(m, Number(v) || 0), 0) || 1;

        const heatCells = heatItems.map(([d, v]) => {
            const n = Number(v) || 0;
            const intensity = Math.round((n / maxCount) * 4); // 0..4
            const shades = ['#f3f4f6', '#ffedd5', '#fdba74', '#fb923c', '#f97316'];
            const bg = shades[intensity] || shades[0];
            return `<div title="${d}: ${n} activities"
                style="width:14px;height:14px;border-radius:3px;background:${bg};border:1px solid #e5e7eb;"></div>`;
        }).join('');

        bodyEl.innerHTML = `
            <div style="margin-bottom:0.5rem;">
                <strong>Overall health:</strong> ${score}/100
            </div>
            <div style="margin:0.5rem 0;">
                <div class="muted" style="font-weight:600;margin-bottom:0.25rem;">Activity heatmap (last 14 days)</div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;max-width:320px;">${heatCells}</div>
            </div>
            <div class="activity-meta">
                Tasks done score: ${(c.tasks_done?.score ?? 0)} (raw: ${(c.tasks_done?.raw ?? 0)})
                <br>
                Activity score: ${(c.activity?.score ?? 0)} (events last 7 days: ${(c.activity?.raw ?? 0)})
                <br>
                Deadline factor: ${(c.deadline?.score ?? 0)}
            </div>
        `;

        // Load ghost status in parallel section
        if (ghostStatusEl && ghostListEl) {
            ghostStatusEl.textContent = 'Loading inactive members...';
            ghostListEl.innerHTML = '';
            try {
                const gRes = await fetch(`/teams/api/ghost_status.php?team_id=${encodeURIComponent(teamId)}`, {
                    credentials: 'same-origin'
                });
                const gData = await gRes.json().catch(() => null);
                if (!gRes.ok || !gData || !gData.success) {
                    throw new Error(gData?.error || ('HTTP ' + gRes.status));
                }
                const ghosts = gData.ghosts || [];
                const threshold = gData.threshold_days || 3;
                if (ghosts.length === 0) {
                    ghostStatusEl.textContent = `No ghost members (threshold: ${threshold} days).`;
                } else {
                    ghostStatusEl.textContent = `Flagged inactive members (>= ${threshold} days):`;
                    ghosts.forEach(g => {
                        const li = document.createElement('li');
                        li.className = 'activity-item';
                        li.innerHTML = `
                            <div><strong>${g.user_name || ('User #' + g.user_id)}</strong> — ${g.inactive_days} inactive day(s)</div>
                            <div class="activity-meta">
                                Last activity: ${g.last_activity_at ? new Date(g.last_activity_at).toLocaleString() : 'none'}
                                <button class="nudgeBtn" data-user="${g.user_id}" style="margin-left:8px;background:#f97316;color:#fff;border:none;border-radius:6px;padding:0.2rem 0.5rem;cursor:pointer;">Nudge</button>
                            </div>
                        `;
                        ghostListEl.appendChild(li);
                    });

                    document.querySelectorAll('.nudgeBtn').forEach(btn => {
                        btn.addEventListener('click', async () => {
                            const targetUserId = btn.dataset.user;
                            btn.disabled = true;
                            try {
                                const nRes = await fetch('/teams/api/ghost_nudge.php', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        team_id: teamId,
                                        target_user_id: targetUserId,
                                        csrf_token: csrfToken
                                    })
                                });
                                const nData = await nRes.json().catch(() => null);
                                if (!nRes.ok || !nData || !nData.success) {
                                    throw new Error(nData?.error || ('HTTP ' + nRes.status));
                                }
                                btn.textContent = 'Nudged';
                                if (panels.activity && panels.activity.dataset.loaded) {
                                    loadActivity();
                                }
                            } catch (err) {
                                btn.disabled = false;
                                alert('Nudge failed: ' + err.message);
                            }
                        });
                    });
                }
            } catch (err) {
                ghostStatusEl.textContent = 'Ghost detection error: ' + err.message;
            }
        }

        panels.health.dataset.loaded = '1';
    } catch (err) {
        statusEl.textContent = 'Error loading health: ' + err.message;
        statusEl.classList.add('error');
    }
}

// --- Supervisor Tools Functions ---
document.getElementById('loadInsightsBtn')?.addEventListener('click', async () => {
    const statusEl = document.getElementById('loadInsightsStatus');
    const bodyEl = document.getElementById('supervisorInsightsBody');
    
    if (statusEl) statusEl.textContent = 'Loading insights...';
    if (bodyEl) bodyEl.innerHTML = '';
    
    try {
        const res = await fetch(`/teams/api/lecturer_team_insights.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        
        const data = await res.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load insights');
        }
        
        if (statusEl) statusEl.textContent = 'Insights loaded successfully';
        if (bodyEl) bodyEl.innerHTML = data.html || 'Insights data loaded';
        
    } catch (err) {
        if (statusEl) statusEl.textContent = 'Error: ' + err.message;
        if (statusEl) statusEl.classList.add('error');
    }
});

document.getElementById('manageSupervisorsBtn')?.addEventListener('click', () => {
    // Open supervisor management modal (reuse from lecturer_teams.php)
    if (typeof openSupervisorModal === 'function') {
        // Get team title from current page
        const teamTitle = document.getElementById('teamTitle')?.textContent || 'Team';
        openSupervisorModal(teamId, teamTitle);
    } else {
        // Fallback: navigate to lecturer_teams with team_id
        window.location.href = `/teams/views/lecturer_teams.php?team_id=${teamId}`;
    }
});

document.getElementById('awardMarksBtn')?.addEventListener('click', () => {
    // Navigate to lecturer_teams for marks management
    window.location.href = `/teams/views/lecturer_teams.php?team_id=${teamId}`;
});

document.getElementById('membershipRequestsBtn')?.addEventListener('click', () => {
    // Navigate to membership requests approval page
    window.location.href = `/teams/views/approve_membership_requests.php?team_id=${teamId}`;
});

document.getElementById('exportPdfBtn')?.addEventListener('click', () => {
    window.open(`/teams/api/export_group_members_pdf.php?team_id=${teamId}`, '_blank');
});

document.getElementById('exportExcelBtn')?.addEventListener('click', () => {
    window.open(`/teams/api/export_all_teams_excel.php`, '_blank');
});

document.getElementById('exportPeerCsvBtn')?.addEventListener('click', () => {
    window.open(`/teams/api/peer_evaluation_report.php?team_id=${teamId}&format=csv`, '_blank');
});

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

// Stand-up submit handler
const submitStandupBtn = document.getElementById('submitStandupBtn');
if (submitStandupBtn) {
    submitStandupBtn.addEventListener('click', async () => {
        const statusEl = document.getElementById('submitStandupStatus');
        const didEl = document.getElementById('didTodayInput');
        const nextEl = document.getElementById('willDoNextInput');
        const blockEl = document.getElementById('blockersInput');

        const did_today = (didEl?.value || '').trim();
        const will_do_next = (nextEl?.value || '').trim();
        const blockers = (blockEl?.value || '').trim();

        if (!did_today || !will_do_next) {
            if (statusEl) statusEl.textContent = 'Please fill today and next fields';
            return;
        }

        if (statusEl) statusEl.textContent = 'Submitting...';

        try {
            const res = await fetch('/teams/api/standup_create.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    team_id: teamId,
                    did_today,
                    will_do_next,
                    blockers,
                    csrf_token: csrfToken
                })
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error(data?.error || ('HTTP ' + res.status));
            }
            if (didEl) didEl.value = '';
            if (nextEl) nextEl.value = '';
            if (blockEl) blockEl.value = '';
            if (statusEl) statusEl.textContent = data.message || 'Stand-up submitted';
            loadStandups();
            if (panels.activity && panels.activity.dataset.loaded) {
                loadActivity();
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Submit failed: ' + err.message;
        }
    });
}
// Initial load
loadTeamHeader();
loadFiles(); // files tab is the default visible tab
</script>

<div id="fileViewerOverlay" class="file-viewer-overlay" onclick="if(event.target===this) closeTeamFileViewer();">
    <div class="file-viewer">
        <div class="file-viewer-header">
            <div id="fileViewerTitle">File Viewer</div>
            <button type="button" class="read-btn" style="background:#dc2626;" onclick="closeTeamFileViewer()">Close</button>
        </div>
        <div id="fileViewerContent" class="file-viewer-content"></div>
    </div>
</div>

</body>
</html>

