<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

$teams = [];
$sql = "
    SELECT
        t.id,
        t.title,
        t.status,
        t.created_at,
        u.code AS unit_code,
        u.name AS unit_name,
        (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) AS member_count,
        GROUP_CONCAT(DISTINCT l.name SEPARATOR ', ') AS supervisors,
        tl.name AS lead_name,
        tl_course.name AS lead_course_name,
        tl.year_of_study AS lead_year
    FROM teams t
    LEFT JOIN units u ON t.unit_id = u.id
    LEFT JOIN team_supervisors tsup ON tsup.team_id = t.id AND tsup.status = 'approved'
    LEFT JOIN lecturers l ON tsup.lecturer_id = l.id AND tsup.supervisor_type = 'lecturer'
    LEFT JOIN team_members tl_m ON tl_m.team_id = t.id AND tl_m.role = 'leader'
    LEFT JOIN students tl ON tl_m.student_id = tl.id
    LEFT JOIN courses tl_course ON tl.course_id = tl_course.id
    GROUP BY t.id, t.title, t.status, t.created_at, u.code, u.name, tl.name, tl_course.name, tl.year_of_study
    ORDER BY t.created_at DESC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
} else {
    $query_error = $conn->error;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>All Teams - UNILIS Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 24px; color: #333; }
        h2 { margin-bottom: 4px; }
        .count { color: #6b7280; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        th { background: #f2f2f2; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #fff; }
        .badge.active { background: #10b981; }
        .badge.locked { background: #f59e0b; }
        .badge.archived { background: #6b7280; }
        .error { color: #991b1b; background: #fee2e2; padding: 12px; border-radius: 6px; }
        .empty { color: #6b7280; padding: 20px; text-align: center; }
        a.back { display: inline-block; margin-bottom: 16px; color: #0369a1; text-decoration: none; }
    </style>
</head>
<body>
    <a class="back" href="admin/dashboard.php">&larr; Back to Dashboard</a>
    <h2>All Teams</h2>

    <?php if (isset($query_error)): ?>
        <p class="error">Failed to load teams: <?= htmlspecialchars($query_error) ?></p>
    <?php elseif (empty($teams)): ?>
        <p class="empty">No teams have been created yet.</p>
    <?php else: ?>
        <p class="count"><?= count($teams) ?> team(s) total</p>
        <table>
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Members</th>
                    <th>Supervisor(s)</th>
                    <th>Team Lead</th>
                    <th>Lead Course</th>
                    <th>Lead Year</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                    <tr>
                        <td><?= htmlspecialchars($team['title']) ?></td>
                        <td><?= htmlspecialchars($team['unit_code'] ?? '—') ?> - <?= htmlspecialchars($team['unit_name'] ?? '—') ?></td>
                        <td><span class="badge <?= htmlspecialchars($team['status']) ?>"><?= ucfirst(htmlspecialchars($team['status'])) ?></span></td>
                        <td><?= (int)$team['member_count'] ?></td>
                        <td><?= htmlspecialchars($team['supervisors'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($team['lead_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($team['lead_course_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($team['lead_year'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($team['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>