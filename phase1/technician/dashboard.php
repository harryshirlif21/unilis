<?php
/**
 * Technician Dashboard
 * UNILIS Academic Foundation Expansion
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_extended.php';

// Only technician can access
phase1_guard_role(ROLE_TECHNICIAN, '../../login.php');

$technician_id = $_SESSION['user_id'];
$staff_id = $_SESSION['staff_id'];
$department_name = $_SESSION['department_name'] ?? 'General';

// Get units where technician is a supervisor
$supervisedUnits = [];
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            u.id as unit_id,
            u.code as unit_code,
            u.name as unit_name,
            COUNT(DISTINCT ts.team_id) as team_count
        FROM team_supervisors tsup
        JOIN teams t ON tsup.team_id = t.id
        JOIN units u ON t.unit_id = u.id
        WHERE tsup.lecturer_id = ? 
          AND tsup.supervisor_type = 'technician'
          AND tsup.status = 'approved'
        GROUP BY u.id, u.code, u.name
        ORDER BY u.code ASC
    ");
    $stmt->bind_param("i", $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $supervisedUnits[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching supervised units: " . $e->getMessage());
}

$supervisedTeams = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            t.id as team_id,
            t.title as team_title,
            t.assessment_type,
            t.description,
            t.created_at as team_created,
            u.code as unit_code,
            u.name as unit_name,
            t.status,
            COUNT(DISTINCT tm.student_id) as member_count
        FROM team_supervisors tsup
        JOIN teams t ON tsup.team_id = t.id
        JOIN units u ON t.unit_id = u.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE tsup.lecturer_id = ?
          AND tsup.supervisor_type = 'technician'
          AND tsup.status = 'approved'
        GROUP BY t.id, t.title, t.assessment_type, t.description, t.created_at, u.code, u.name, t.status
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $supervisedTeams[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching supervised teams: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; }
        .header { background: linear-gradient(135deg, #0369a1, #0284c7); color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; }
        .header .user-info { font-size: 13px; opacity: .8; }
        .header a { color: #fff; text-decoration: none; opacity: .8; margin-left: 16px; }
        .header a:hover { opacity: 1; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .welcome { margin-bottom: 24px; }
        .welcome h2 { font-size: 22px; color: #0369a1; }
        .welcome p { color: #6b7280; font-size: 14px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .info-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .info-card .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .info-card .value { font-size: 18px; font-weight: 600; color: #0369a1; margin-top: 4px; }
        .placeholder { background: #fff; border-radius: 10px; padding: 40px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .placeholder .icon { font-size: 48px; color: #93c5fd; margin-bottom: 16px; }
        .placeholder h3 { color: #374151; margin-bottom: 8px; }
        .placeholder p { color: #6b7280; font-size: 14px; }
        .units-section { margin-top: 32px; }
        .units-section h3 { font-size: 18px; color: #374151; margin-bottom: 16px; }
        .units-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .unit-tile { 
            background: #fff; 
            border-radius: 10px; 
            padding: 24px; 
            box-shadow: 0 1px 3px rgba(0,0,0,.1); 
            cursor: pointer; 
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .unit-tile:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            border-color: #0369a1;
        }
        .unit-tile .code { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
        .unit-tile .name { font-size: 16px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .unit-tile .team-count { font-size: 14px; color: #0369a1; }
        .unit-tile .team-count i { margin-right: 4px; }
        .no-units { background: #fff; border-radius: 10px; padding: 32px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .no-units p { color: #6b7280; font-size: 14px; }
        .supervised-section { margin-top: 24px; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .supervised-section h3 { margin-bottom: 12px; color: #374151; }
        .supervised-list { display: grid; gap: 12px; }
        .supervised-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; background: #f9fafb; }
        .supervised-card a { color: #0369a1; text-decoration: none; font-weight: 600; }
        .supervised-meta { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #fff; margin-right: 8px; }
        .badge.active { background: #10b981; }
        .badge.locked { background: #f59e0b; }
        .badge.archived { background: #6b7280; }
        .supervisor-tools { margin-top: 24px; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .supervisor-tools h3 { margin-bottom: 16px; color: #374151; }
        .tools-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .tool-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: #f9fafb; text-align: center; transition: all 0.2s; }
        .tool-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); border-color: #0369a1; }
        .tool-card .icon { font-size: 24px; color: #0369a1; margin-bottom: 8px; }
        .tool-card .title { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        .tool-card .description { font-size: 12px; color: #6b7280; }
        .tool-card button { margin-top: 12px; background: #0369a1; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; cursor: pointer; font-size: 13px; transition: background 0.2s; }
        .tool-card button:hover { background: #0284c7; }
        .status-message { margin-top: 12px; padding: 8px 12px; border-radius: 6px; font-size: 13px; display: none; }
        .status-message.success { background: #d1fae5; color: #065f46; }
        .status-message.error { background: #fee2e2; color: #991b1b; }
        .insights-container { margin-top: 16px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
        
        /* Lecturer Teams Style */
        .teams-container { margin-top: 24px; }
        .team-card { background: #fff; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; }
        .team-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .team-title-section h3 { margin: 0 0 0.5rem 0; color: #1e293b; font-size: 1.25rem; }
        .team-meta { color: #64748b; font-size: 0.9rem; line-height: 1.5; }
        .mark-box { cursor: pointer; display: inline-block; width: 24px; height: 24px; border: 2px solid #3b82f6; border-radius: 4px; margin-right: 8px; vertical-align: middle; text-align: center; line-height: 20px; color: #3b82f6; font-weight: bold; }
        .mark-box.checked { background: #3b82f6; color: #fff; }
        .ellipsis-menu { position: relative; }
        .ellipsis-btn { background: none; border: none; font-size: 18px; cursor: pointer; padding: 4px; }
        .ellipsis-content { display: none; position: absolute; right: 0; top: 100%; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 200px; z-index: 100; }
        .ellipsis-content.show { display: block; }
        .ellipsis-content a { display: block; padding: 8px 12px; color: #475569; text-decoration: none; font-size: 0.9rem; }
        .ellipsis-content a:hover { background: #f1f5f9; color: #0369a1; }
        .team-leader { background: #fef3c7; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        .team-leader h4 { margin: 0 0 0.75rem 0; color: #92400e; }
        .leader-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; }
        .leader-contact-item { color: #78350f; font-size: 0.9rem; }
        .members-section { margin-top: 1.5rem; }
        .members-section h4 { margin: 0 0 1rem 0; color: #374151; }
        .members-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
        .member-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
        .member-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .member-name { font-weight: 600; color: #1e293b; }
        .member-role { font-size: 0.75rem; padding: 2px 8px; border-radius: 999px; background: #dbeafe; color: #1e40af; }
        .member-mark-field { display: flex; align-items: center; gap: 0.5rem; margin: 0.5rem 0; }
        .member-mark-field input { width: 80px; padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; }
        .member-mark-field button { background: #10b981; color: #fff; border: none; border-radius: 4px; padding: 4px 12px; cursor: pointer; font-size: 0.85rem; }
        .member-files { margin-top: 0.75rem; }
        .member-files h5 { margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #64748b; }
        .file-item { display: flex; align-items: center; gap: 0.5rem; padding: 4px 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 0.25rem; font-size: 0.85rem; }
        .file-item a { color: #0369a1; text-decoration: none; }
        .file-item a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-tools"></i> Technician Dashboard</h1>
        <div>
            <span class="user-info"><?= htmlspecialchars($_SESSION['user_name']) ?> (<?= htmlspecialchars($staff_id) ?>)</span>
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div class="container">
        <div class="welcome">
            <h2>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h2>
            <p>Department: <?= htmlspecialchars($department_name) ?></p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <div class="label">Staff ID</div>
                <div class="value"><?= htmlspecialchars($staff_id) ?></div>
            </div>
            <div class="info-card">
                <div class="label">Department</div>
                <div class="value"><?= htmlspecialchars($department_name) ?></div>
            </div>
            <div class="info-card">
                <div class="label">Email</div>
                <div class="value"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
            </div>
        </div>

        <div class="units-section">
            <h3><i class="fas fa-users"></i> Supervised Units</h3>
            <?php if (empty($supervisedUnits)): ?>
                <div class="no-units">
                    <p>You are not currently supervising any teams.</p>
                </div>
            <?php else: ?>
                <div class="units-grid">
                    <?php foreach ($supervisedUnits as $unit): ?>
                        <div class="unit-tile" onclick="window.location.href='supervisor_unit_teams.php?unit_id=<?= $unit['unit_id'] ?>'">
                            <div class="code"><?= htmlspecialchars($unit['unit_code']) ?></div>
                            <div class="name"><?= htmlspecialchars($unit['unit_name']) ?></div>
                            <div class="team-count">
                                <i class="fas fa-users"></i> <?= $unit['team_count'] ?> team<?= $unit['team_count'] == 1 ? '' : 's' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="teams-container">
            <h3><i class="fas fa-users"></i> Supervised Teams</h3>
            <?php if (empty($supervisedTeams)): ?>
                <p style="color:#6b7280;">You are not currently supervising any teams.</p>
            <?php else: ?>
                <?php 
                // Fetch detailed team data for each supervised team
                foreach ($supervisedTeams as $team): 
                    $team_id = (int)$team['team_id'];
                    
                    // Get team leader
                    $teamLeader = null;
                    $leaderStmt = $conn->prepare("
                        SELECT s.id as student_id, s.name as student_name, s.reg_no, s.email, s.year_of_study, tm.role
                        FROM team_members tm
                        JOIN students s ON tm.student_id = s.id
                        WHERE tm.team_id = ? AND tm.role = 'leader'
                        LIMIT 1
                    ");
                    if ($leaderStmt) {
                        $leaderStmt->bind_param("i", $team_id);
                        $leaderStmt->execute();
                        $teamLeader = $leaderStmt->get_result()->fetch_assoc();
                        $leaderStmt->close();
                    }
                    
                    // Get team members
                    $teamMembers = [];
                    $membersStmt = $conn->prepare("
                        SELECT s.id as student_id, s.name as student_name, s.reg_no, s.email, tm.role
                        FROM team_members tm
                        JOIN students s ON tm.student_id = s.id
                        WHERE tm.team_id = ?
                        ORDER BY tm.role = 'leader' DESC, s.name ASC
                    ");
                    if ($membersStmt) {
                        $membersStmt->bind_param("i", $team_id);
                        $membersStmt->execute();
                        $result = $membersStmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $teamMembers[] = $row;
                        }
                        $membersStmt->close();
                    }
                ?>
                    <div class="team-card">
                        <div class="team-header">
                            <div class="team-title-section">
                                <h3>
                                    <span class="mark-box" title="Award group mark" onclick="toggleMarkBox(this, <?= $team_id; ?>)">□</span>
                                    <?= htmlspecialchars($team['team_title']); ?>
                                </h3>
                                <div class="team-meta">
                                    <strong>Unit:</strong> <?= htmlspecialchars($team['unit_name']); ?> (<?= htmlspecialchars($team['unit_code']); ?>)<br>
                                    <strong>Type:</strong> <?= htmlspecialchars($team['assessment_type'] ?: 'General'); ?><br>
                                    <strong>Created:</strong> <?= $team['team_created'] ? date('d M Y', strtotime($team['team_created'])) : 'Not specified'; ?> |
                                    <strong>Members:</strong> <?= $team['member_count']; ?>
                                </div>
                                <div style="margin-top: 0.5rem;">
                                    <a href="../../teams/views/lecturer_teams.php?team_id=<?= $team_id; ?>" style="color: #3b82f6; text-decoration: none; font-size: 0.9rem; font-weight: 600;">
                                        🚀 Open Team Workspace
                                    </a>
                                </div>
                            </div>
                            <div>
                                <span class="badge <?= htmlspecialchars($team['status']); ?>">
                                    <?= ucfirst(htmlspecialchars($team['status'])); ?>
                                </span>
                                <div style="margin-top:0.5rem;">
                                    <div class="ellipsis-menu">
                                        <button class="ellipsis-btn" onclick="toggleMenu(<?= $team_id; ?>)">⚙️</button>
                                        <div id="menu-<?= $team_id; ?>" class="ellipsis-content">
                                            <a href="#" onclick="generatePDF(<?= $team_id; ?>); return false;">📄 Export PDF</a>
                                            <a href="#" onclick="generateExcel(<?= $team_id; ?>); return false;">📊 Export Excel</a>
                                            <a href="../../teams/api/peer_evaluation_report.php?team_id=<?= $team_id; ?>&format=json" target="_blank">🧾 Peer Eval (JSON)</a>
                                            <a href="../../teams/api/peer_evaluation_report.php?team_id=<?= $team_id; ?>&format=csv" target="_blank">📋 Peer Eval (CSV)</a>
                                            <a href="../../teams/api/export_group_members_pdf.php?team_id=<?= $team_id; ?>" target="_blank">👥 Download Group Members PDF</a>
                                            <hr style="margin: 8px 0; border: none; border-top: 1px solid #e2e8f0;">
                                            <a href="../../teams/views/lecturer_teams.php?team_id=<?= $team_id; ?>">👨‍🏫 Full Team Management</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Leader Section -->
                        <?php if ($teamLeader): ?>
                            <div class="team-leader">
                                <h4>👑 Team Leader</h4>
                                <div class="leader-info">
                                    <div class="leader-contact-item">
                                        <strong>Name:</strong> <?= htmlspecialchars($teamLeader['student_name']); ?>
                                    </div>
                                    <div class="leader-contact-item">
                                        <strong>Reg No:</strong> <?= htmlspecialchars($teamLeader['reg_no']); ?>
                                    </div>
                                    <div class="leader-contact-item">
                                        <strong>📧 Email:</strong> <?= htmlspecialchars($teamLeader['email']); ?>
                                    </div>
                                    <div class="leader-contact-item">
                                        <strong>📚 Year:</strong> <?= htmlspecialchars($teamLeader['year_of_study'] ?: 'Not specified'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Members Section -->
                        <div class="members-section">
                            <h4>👥 Team Members</h4>
                            <div class="members-grid">
                                <?php foreach ($teamMembers as $member): ?>
                                    <div class="member-card">
                                        <div class="member-header">
                                            <div class="member-name">
                                                <span class="mark-box" title="Award individual mark" onclick="toggleMarkBox(this, <?= $team_id; ?>, <?= $member['student_id']; ?>)">□</span>
                                                <?= htmlspecialchars($member['student_name']); ?>
                                            </div>
                                            <span class="member-role">
                                                <?= htmlspecialchars(ucfirst($member['role'] ?: 'member')); ?>
                                            </span>
                                        </div>
                                        <div style="font-size: 0.9rem; color: #6b7280; margin-bottom: 0.5rem;">
                                            <?= htmlspecialchars($member['reg_no']); ?>
                                        </div>
                                        
                                        <!-- Individual Mark Field -->
                                        <div class="member-mark-field" style="margin: 0.5rem 0;">
                                            <input type="number" id="mark-<?= $team_id; ?>-<?= $member['student_id']; ?>" placeholder="Mark" min="0" max="100">
                                            <button onclick="awardIndividualMark(<?= $team_id; ?>, <?= $member['student_id']; ?>)">Award</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="placeholder">
            <div class="icon"><i class="fas fa-microscope"></i></div>
            <h3>SmartLab Integration Coming Soon</h3>
            <p>Your technician dashboard will be fully integrated with SmartLab in a future update.<br>
            You will be able to manage lab sessions, equipment, and practicals from here.</p>
        </div>
    </div>

    <script>
        function getSelectedTeamId() {
            const selector = document.getElementById('teamSelector');
            return selector ? selector.value : null;
        }

        function showStatus(message, type = 'success') {
            const statusEl = document.getElementById('statusMessage');
            if (statusEl) {
                statusEl.textContent = message;
                statusEl.className = 'status-message ' + type;
                statusEl.style.display = 'block';
                setTimeout(() => {
                    statusEl.style.display = 'none';
                }, 5000);
            }
        }

        function loadTeamInsights() {
            const teamId = getSelectedTeamId();
            if (!teamId) {
                showStatus('Please select a team first', 'error');
                return;
            }

            const insightsContainer = document.getElementById('insightsContainer');
            if (insightsContainer) {
                insightsContainer.style.display = 'block';
                insightsContainer.innerHTML = '<p style="color: #6b7280;">Loading team insights...</p>';
                showStatus('Loading insights...', 'success');

                fetch(`../../teams/api/lecturer_team_insights.php?team_id=${teamId}`, {
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        insightsContainer.innerHTML = data.html || '<p style="color: #6b7280;">Insights loaded successfully</p>';
                        showStatus('Insights loaded successfully', 'success');
                    } else {
                        insightsContainer.innerHTML = `<p style="color: #dc2626;">Error: ${data.error || 'Failed to load insights'}</p>`;
                        showStatus('Failed to load insights', 'error');
                    }
                })
                .catch(error => {
                    insightsContainer.innerHTML = `<p style="color: #dc2626;">Error: ${error.message}</p>`;
                    showStatus('Error loading insights', 'error');
                });
            }
        }

        function openWorkspace() {
            const teamId = getSelectedTeamId();
            if (!teamId) {
                showStatus('Please select a team first', 'error');
                return;
            }
            window.location.href = `../../teams/views/workspace.php?team_id=${teamId}`;
        }

        function awardMarks() {
            const teamId = getSelectedTeamId();
            if (!teamId) {
                showStatus('Please select a team first', 'error');
                return;
            }
            window.location.href = `../../teams/views/lecturer_teams.php?team_id=${teamId}`;
        }

        function manageSupervisors() {
            const teamId = getSelectedTeamId();
            if (!teamId) {
                showStatus('Please select a team first', 'error');
                return;
            }
            window.location.href = `../../teams/views/lecturer_teams.php?team_id=${teamId}`;
        }

        function exportPdf() {
            const teamId = getSelectedTeamId();
            if (!teamId) {
                showStatus('Please select a team first', 'error');
                return;
            }
            window.open(`../../teams/api/export_group_members_pdf.php?team_id=${teamId}`, '_blank');
            showStatus('PDF export initiated', 'success');
        }

        function exportExcel() {
            const teamId = getSelectedTeamId();
            if (!teamId) {
                showStatus('Please select a team first', 'error');
                return;
            }
            window.open(`../../teams/api/export_all_teams_excel.php`, '_blank');
            showStatus('Excel export initiated', 'success');
        }

        // Lecturer Teams Functions
        function toggleMenu(teamId) {
            const menu = document.getElementById('menu-' + teamId);
            if (menu) {
                menu.classList.toggle('show');
            }
        }

        function toggleMarkBox(element, teamId, studentId = null) {
            element.classList.toggle('checked');
            if (element.classList.contains('checked')) {
                element.textContent = '✓';
            } else {
                element.textContent = '□';
            }
        }

        function generatePDF(teamId) {
            window.open(`../../teams/api/export_group_members_pdf.php?team_id=${teamId}`, '_blank');
        }

        function generateExcel(teamId) {
            window.open(`../../teams/api/export_all_teams_excel.php`, '_blank');
        }

        function awardIndividualMark(teamId, studentId) {
            const markInput = document.getElementById(`mark-${teamId}-${studentId}`);
            const mark = markInput ? markInput.value : null;
            
            if (!mark || mark === '') {
                alert('Please enter a mark');
                return;
            }
            
            if (mark < 0 || mark > 100) {
                alert('Mark must be between 0 and 100');
                return;
            }

            if (confirm(`Award mark ${mark} to this student?`)) {
                // Navigate to lecturer teams with mark parameters
                window.location.href = `../../teams/views/lecturer_teams.php?team_id=${teamId}&student_id=${studentId}&mark=${mark}`;
            }
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.ellipsis-menu')) {
                document.querySelectorAll('.ellipsis-content').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    </script>
</body>
</html>