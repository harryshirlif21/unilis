<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';
require_once __DIR__ . '/teams/includes/team_display_helpers.php';

$filterTeamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;

$teamOptions = [];
$teamOptionsResult = $conn->query('SELECT id, title FROM teams ORDER BY title ASC');
if ($teamOptionsResult) {
    while ($row = $teamOptionsResult->fetch_assoc()) {
        $teamOptions[] = $row;
    }
}

$teams = [];
$sql = "
    SELECT
        t.id,
        t.title,
        t.unit_id,
        t.status,
        t.assessment_type,
        t.created_at,
        u.code AS unit_code,
        u.name AS unit_name,
        c.name AS course_name,
        (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) AS member_count,
        GROUP_CONCAT(DISTINCT l.name SEPARATOR ', ') AS supervisors,
        tl.name AS lead_name,
        tl_course.name AS lead_course_name,
        tl.year_of_study AS lead_year
    FROM teams t
    LEFT JOIN units u ON t.unit_id = u.id
    LEFT JOIN courses c ON t.course_id = c.id
    LEFT JOIN team_supervisors tsup ON tsup.team_id = t.id AND tsup.status = 'approved'
    LEFT JOIN lecturers l ON tsup.lecturer_id = l.id AND tsup.supervisor_type = 'lecturer'
    LEFT JOIN team_members tl_m ON tl_m.team_id = t.id AND tl_m.role = 'leader'
    LEFT JOIN students tl ON tl_m.student_id = tl.id
    LEFT JOIN courses tl_course ON tl.course_id = tl_course.id
    WHERE 1 = 1
";

if ($filterTeamId > 0) {
    $sql .= ' AND t.id = ' . $filterTeamId;
}

$sql .= "
    GROUP BY t.id, t.title, t.unit_id, t.status, t.assessment_type, t.created_at, u.code, u.name, c.name, tl.name, tl_course.name, tl.year_of_study
    ORDER BY u.code ASC, u.name ASC, t.created_at DESC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $hasUnit = trim((string) ($row['unit_name'] ?? '')) !== '' || trim((string) ($row['unit_code'] ?? '')) !== '';
        $row['unit_display'] = $hasUnit
            ? team_format_unit_display($row['unit_code'] ?? null, $row['unit_name'] ?? null)
            : (((int) ($row['unit_id'] ?? 0) > 0)
                ? 'Missing unit record (ID: ' . (int) $row['unit_id'] . ')'
                : 'No unit assigned');
        $row['unit_missing'] = !$hasUnit;
        $row['assessment_title'] = team_assessment_label($row['assessment_type'] ?? null);
        $teams[] = $row;
    }
} else {
    $query_error = $conn->error;
}

$membersByTeam = team_fetch_members_grouped(
    $conn,
    array_map(static fn(array $team): int => (int) $team['id'], $teams)
);

foreach ($teams as &$team) {
    $team['members'] = $membersByTeam[(int) $team['id']] ?? [];
}
unset($team);

$missingUnitCount = count(array_filter($teams, static fn(array $team): bool => !empty($team['unit_missing'])));
$totalMemberRows = array_sum(array_map(static fn(array $team): int => count($team['members']), $teams));
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
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; vertical-align: top; }
        th { background: #f2f2f2; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; color: #fff; }
        .badge.active { background: #10b981; }
        .badge.locked { background: #f59e0b; }
        .badge.archived { background: #6b7280; }
        .role-badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #e5e7eb; color: #374151; margin-left: 6px; }
        .role-badge.leader { background: #fef3c7; color: #92400e; }
        .error { color: #991b1b; background: #fee2e2; padding: 12px; border-radius: 6px; }
        .empty { color: #6b7280; padding: 20px; text-align: center; }
        a.back { display: inline-block; margin-bottom: 16px; color: #0369a1; text-decoration: none; }
        .unit-missing { background: #fff7ed; color: #9a3412; font-weight: 600; }
        .unit-id { display: block; font-size: 12px; color: #6b7280; font-weight: 400; margin-top: 2px; }
        .summary-warn { color: #9a3412; background: #fff7ed; border: 1px solid #fed7aa; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .filter-bar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-bar select, .filter-bar a { font-size: 14px; }
        .filter-bar a { color: #0369a1; text-decoration: none; }
        .member-list { list-style: none; margin: 0; padding: 0; }
        .member-list li { padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
        .member-list li:last-child { border-bottom: none; padding-bottom: 0; }
        .member-name { font-weight: 600; }
        .member-meta { display: block; font-size: 12px; color: #6b7280; margin-top: 2px; }
        .no-members { color: #9ca3af; font-style: italic; }
        .team-id { font-size: 12px; color: #6b7280; font-weight: 400; }
    </style>
</head>
<body>
    <a class="back" href="admin/dashboard.php">&larr; Back to Dashboard</a>
    <h2>All Teams</h2>

    <?php if (isset($query_error)): ?>
        <p class="error">Failed to load teams: <?= htmlspecialchars($query_error) ?></p>
    <?php elseif (empty($teams)): ?>
        <p class="empty"><?= $filterTeamId > 0 ? 'No team found for the selected filter.' : 'No teams have been created yet.' ?></p>
        <?php if ($filterTeamId > 0): ?>
            <p><a href="teams_list.php">Show all teams</a></p>
        <?php endif; ?>
    <?php else: ?>
        <div class="filter-bar">
            <form method="get">
                <label for="team_id">Filter by team:</label>
                <select name="team_id" id="team_id" onchange="this.form.submit()">
                    <option value="">All teams</option>
                    <?php foreach ($teamOptions as $teamOption): ?>
                        <option value="<?= (int) $teamOption['id'] ?>" <?= $filterTeamId === (int) $teamOption['id'] ? 'selected' : '' ?>>
                            #<?= (int) $teamOption['id'] ?> - <?= htmlspecialchars($teamOption['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($filterTeamId > 0): ?>
                <a href="teams_list.php">Clear filter</a>
            <?php endif; ?>
        </div>

        <p class="count">
            <?= count($teams) ?> team(s) total
            <?php if ($totalMemberRows > 0): ?>
                ù <?= (int) $totalMemberRows ?> member record(s) loaded
            <?php endif; ?>
        </p>
        <?php if ($missingUnitCount > 0): ?>
            <p class="summary-warn"><?= (int) $missingUnitCount ?> team(s) have a missing or invalid unit link. Check the Unit column below.</p>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Team</th>
                    <th>Unit</th>
                    <th>Assessment</th>
                    <th>Status</th>
                    <th>Members</th>
                    <th>Supervisor(s)</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                    <tr>
                        <td>
                            <span class="member-name"><?= htmlspecialchars($team['title']) ?></span>
                            <span class="team-id">Team ID: <?= (int) $team['id'] ?></span>
                        </td>
                        <td class="<?= !empty($team['unit_missing']) ? 'unit-missing' : '' ?>">
                            <?= htmlspecialchars($team['unit_display']) ?>
                            <?php if ((int) ($team['unit_id'] ?? 0) > 0): ?>
                                <span class="unit-id">Unit ID: <?= (int) $team['unit_id'] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($team['course_name'])): ?>
                                <span class="unit-id">Course: <?= htmlspecialchars($team['course_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($team['assessment_title']) ?></td>
                        <td><span class="badge <?= htmlspecialchars($team['status']) ?>"><?= ucfirst(htmlspecialchars($team['status'])) ?></span></td>
                        <td>
                            <?php if (empty($team['members'])): ?>
                                <span class="no-members">No members</span>
                            <?php else: ?>
                                <ul class="member-list">
                                    <?php foreach ($team['members'] as $member): ?>
                                        <li>
                                            <span class="member-name"><?= htmlspecialchars($member['name']) ?></span>
                                            <span class="role-badge <?= htmlspecialchars(strtolower((string) ($member['role'] ?? 'member'))) ?>">
                                                <?= htmlspecialchars($member['role_label']) ?>
                                            </span>
                                            <span class="member-meta">
                                                <?= htmlspecialchars($member['reg_no'] ?: 'No reg no') ?>
                                                <?php if (!empty($member['course_name'])): ?>
                                                    ù <?= htmlspecialchars($member['course_name']) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($member['year_of_study'])): ?>
                                                    ù Year <?= (int) $member['year_of_study'] ?>
                                                <?php endif; ?>
                                                <?php if (!empty($member['email'])): ?>
                                                    ù <?= htmlspecialchars($member['email']) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($member['joined_at'])): ?>
                                                    ù Joined <?= htmlspecialchars(date('d M Y', strtotime($member['joined_at']))) ?>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($team['supervisors'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($team['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
