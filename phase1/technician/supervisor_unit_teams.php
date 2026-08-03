<?php
/**
 * Supervisor Unit Teams View
 * Shows all teams in a unit for a supervisor (lecturer or technician)
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_extended.php';

// Only technician or lecturer can access
if (!in_array($_SESSION['user_role'], ['technician', 'lecturer'])) {
    header('Location: ../../login.php');
    exit;
}

$supervisor_id = $_SESSION['user_id'];
$supervisor_type = $_SESSION['user_role'] === 'technician' ? 'technician' : 'lecturer';
$unit_id = (int)($_GET['unit_id'] ?? 0);

if ($unit_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Verify supervisor is assigned to this unit
$checkStmt = $conn->prepare("
    SELECT 1 
    FROM team_supervisors ts
    JOIN teams t ON ts.team_id = t.id
    WHERE ts.lecturer_id = ? 
      AND ts.supervisor_type = ?
      AND ts.status = 'approved'
      AND t.unit_id = ?
    LIMIT 1
");
$checkStmt->bind_param("isi", $supervisor_id, $supervisor_type, $unit_id);
$checkStmt->execute();
$isSupervisor = $checkStmt->get_result()->num_rows > 0;
$checkStmt->close();

if (!$isSupervisor) {
    header('Location: dashboard.php');
    exit;
}

// Get unit info
$unitStmt = $conn->prepare("SELECT code, name FROM units WHERE id = ?");
$unitStmt->bind_param("i", $unit_id);
$unitStmt->execute();
$unit = $unitStmt->get_result()->fetch_assoc();
$unitStmt->close();

if (!$unit) {
    header('Location: dashboard.php');
    exit;
}

// Get all teams in this unit that the supervisor oversees
$teams = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            t.id as team_id,
            t.title as team_title,
            t.status,
            t.assessment_type,
            t.description,
            t.created_at,
            COUNT(DISTINCT tm.student_id) as member_count
        FROM team_supervisors ts
        JOIN teams t ON ts.team_id = t.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE ts.lecturer_id = ? 
          AND ts.supervisor_type = ?
          AND ts.status = 'approved'
          AND t.unit_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("isi", $supervisor_id, $supervisor_type, $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching teams: " . $e->getMessage());
}

// Get team members for each team
foreach ($teams as &$team) {
    $team['members'] = [];
    try {
        $memberStmt = $conn->prepare("
            SELECT 
                s.name,
                s.reg_no,
                s.email,
                tm.role,
                tm.joined_at
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
            WHERE tm.team_id = ?
            ORDER BY tm.role DESC, s.name ASC
        ");
        $memberStmt->bind_param("i", $team['team_id']);
        $memberStmt->execute();
        $memberResult = $memberStmt->get_result();
        while ($memberRow = $memberResult->fetch_assoc()) {
            $team['members'][] = $memberRow;
        }
        $memberStmt->close();
    } catch (Exception $e) {
        error_log("Error fetching team members: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams - <?= htmlspecialchars($unit['code']) ?> - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #333; }
        .header { background: linear-gradient(135deg, #0369a1, #0284c7); color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; }
        .header .user-info { font-size: 13px; opacity: .8; }
        .header a { color: #fff; text-decoration: none; opacity: .8; margin-left: 16px; }
        .header a:hover { opacity: 1; }
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .breadcrumb { margin-bottom: 24px; }
        .breadcrumb a { color: #0369a1; text-decoration: none; }
        .breadcrumb span { color: #6b7280; }
        .page-title { margin-bottom: 24px; }
        .page-title h2 { font-size: 24px; color: #374151; }
        .page-title p { color: #6b7280; font-size: 14px; }
        .teams-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }
        .team-card { background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .team-card .header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px; }
        .team-card .title { font-size: 18px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        .team-card .meta { font-size: 13px; color: #6b7280; }
        .team-card .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; color: white; }
        .badge.active { background: #10b981; }
        .badge.locked { background: #f59e0b; }
        .badge.archived { background: #6b7280; }
        .team-card .members { margin-top: 16px; }
        .team-card .members h4 { font-size: 14px; color: #374151; margin-bottom: 8px; }
        .team-card .member-list { list-style: none; }
        .team-card .member-list li { padding: 6px 0; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
        .team-card .member-list li:last-child { border-bottom: none; }
        .team-card .member-list .role { color: #0369a1; font-weight: 600; margin-right: 8px; }
        .no-teams { background: #fff; border-radius: 10px; padding: 40px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .no-teams p { color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-users"></i> Team Supervision</h1>
        <div>
            <span class="user-info"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div class="container">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> <span>/</span> 
            <a href="dashboard.php">Supervised Units</a> <span>/</span>
            <span><?= htmlspecialchars($unit['code']) ?> - <?= htmlspecialchars($unit['name']) ?></span>
        </div>

        <div class="page-title">
            <h2><?= htmlspecialchars($unit['code']) ?> - <?= htmlspecialchars($unit['name']) ?></h2>
            <p>Teams you are supervising in this unit</p>
        </div>

        <?php if (empty($teams)): ?>
            <div class="no-teams">
                <p>You are not supervising any teams in this unit.</p>
            </div>
        <?php else: ?>
            <div class="teams-grid">
                <?php foreach ($teams as $team): ?>
                    <div class="team-card">
                        <div class="header">
                            <div>
                                <div class="title"><?= htmlspecialchars($team['team_title']) ?></div>
                                <div class="meta">
                                    <span class="badge <?= htmlspecialchars($team['status']) ?>"><?= ucfirst($team['status']) ?></span>
                                    <span style="margin-left: 8px;"><?= $team['member_count'] ?> member<?= $team['member_count'] == 1 ? '' : 's' ?></span>
                                </div>
                            </div>
                        </div>
                        <?php if ($team['description']): ?>
                            <p style="font-size: 13px; color: #6b7280; margin-bottom: 12px;"><?= htmlspecialchars($team['description']) ?></p>
                        <?php endif; ?>
                        <div class="members">
                            <h4>Team Members</h4>
                            <?php if (empty($team['members'])): ?>
                                <p style="font-size: 13px; color: #6b7280;">No members yet</p>
                            <?php else: ?>
                                <ul class="member-list">
                                    <?php foreach ($team['members'] as $member): ?>
                                        <li>
                                            <span class="role"><?= ucfirst($member['role']) ?>:</span>
                                            <?= htmlspecialchars($member['name']) ?> 
                                            <span style="color: #9ca3af; margin-left: 4px;">(<?= htmlspecialchars($member['reg_no']) ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
