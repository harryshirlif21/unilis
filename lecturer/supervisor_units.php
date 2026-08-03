<?php
/**
 * Supervisor Units View for Lecturers
 * Shows units where lecturer is supervising project teams
 */

session_start();
require_once '../config/db.php';

// Check if lecturer is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// Get units where lecturer is supervising teams
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
          AND tsup.supervisor_type = 'lecturer'
          AND tsup.status = 'approved'
        GROUP BY u.id, u.code, u.name
        ORDER BY u.code ASC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $supervisedUnits[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching supervised units: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervised Project Units - UNILIS</title>
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
        .breadcrumb { margin-bottom: 24px; }
        .breadcrumb a { color: #0369a1; text-decoration: none; }
        .breadcrumb span { color: #6b7280; }
        .page-title { margin-bottom: 24px; }
        .page-title h2 { font-size: 24px; color: #374151; }
        .page-title p { color: #6b7280; font-size: 14px; }
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
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-users"></i> Supervised Units</h1>
        <div>
            <span class="user-info"><?= htmlspecialchars($lecturer_name) ?></span>
            <a href="lecturer_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div class="container">
        <div class="breadcrumb">
            <a href="lecturer_dashboard.php">Dashboard</a> <span>/</span> 
            <span>Supervised Units</span>
        </div>

        <div class="page-title">
            <h2>Supervised Units</h2>
            <p>Units where you are supervising teams</p>
        </div>

        <?php if (empty($supervisedUnits)): ?>
            <div class="no-units">
                <p>You are not currently supervising any teams.</p>
            </div>
        <?php else: ?>
            <div class="units-grid">
                <?php foreach ($supervisedUnits as $unit): ?>
                    <div class="unit-tile" onclick="window.location.href='../phase1/technician/supervisor_unit_teams.php?unit_id=<?= $unit['unit_id'] ?>'">
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
</body>
</html>
