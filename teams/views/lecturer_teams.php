<?php
session_start();

/* =========================
   DATABASE CONNECTION
========================= */
require_once '../../config/db.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../../login.php");
    exit;
}

$lecturerId = $_SESSION['user_id'];

/* =========================
   CSRF TOKEN
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   FETCH TEAMS FOR LECTURER UNITS
========================= */
$sql = "
SELECT 
    t.id AS team_id,
    t.title AS team_title,
    t.status,
    t.created_at AS team_created,
    t.assessment_type,
    u.name AS unit_name,
    COUNT(tm.student_id) AS member_count
FROM teams t
JOIN units u ON t.unit_id = u.id
JOIN lecturer_units lu ON u.id = lu.unit_id
LEFT JOIN team_members tm ON t.id = tm.team_id
WHERE lu.lecturer_id = ?
GROUP BY t.id
ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param("i", $lecturerId);
$stmt->execute();
$result = $stmt->get_result();

$teams = [];
while ($row = $result->fetch_assoc()) {
    $teams[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Teams - UniLIS</title>
    <style>
        body { font-family: Arial; max-width: 1100px; margin:2rem auto; padding:1rem; background:#f4f6f9; }
        h1 { color: #2c3e50; }
        .team-card { background: white; border-radius:6px; padding:1rem; margin-bottom:1rem; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
        .team-header { display:flex; justify-content:space-between; align-items:center; }
        .badge { padding:0.3rem 0.6rem; border-radius:4px; font-size:0.85rem; color:white; }
        .active { background:#28a745; }
        .locked { background:#ffc107; }
        .archived { background:#6c757d; }
        .members { margin-top:0.8rem; padding-left:1rem; color:#555; }
        .meta { margin-top:6px; font-size:0.9rem; color:#666; }
        .empty { padding:2rem; text-align:center; color:#888; }
    </style>
</head>
<body>

<h1>Teams for Your Units</h1>

<?php if (empty($teams)): ?>
    <div class="empty">No teams found for your assigned units.</div>
<?php else: ?>
    <?php foreach ($teams as $team): ?>

        <div class="team-card">
            <div class="team-header">
                <div>
                    <strong><?= htmlspecialchars($team['team_title']); ?></strong><br>
                    <small>
                        Unit: <?= htmlspecialchars($team['unit_name']); ?> |
                        Type: <?= htmlspecialchars($team['assessment_type']); ?>
                    </small>
                </div>
                <div>
                    <span class="badge <?= htmlspecialchars($team['status']); ?>">
                        <?= ucfirst($team['status']); ?>
                    </span>
                </div>
            </div>

            <div class="meta">
                Created: <?= date('d M Y', strtotime($team['team_created'])); ?> |
                Members: <?= $team['member_count']; ?>
            </div>

            <?php
            // Fetch team members
            $memberSql = "
                SELECT s.name, s.reg_no
                FROM team_members tm
                JOIN students s ON tm.student_id = s.id
                WHERE tm.team_id = ?
            ";
            $memberStmt = $conn->prepare($memberSql);
            if ($memberStmt) {
                $memberStmt->bind_param("i", $team['team_id']);
                $memberStmt->execute();
                $membersResult = $memberStmt->get_result();
            }
            ?>

            <div class="members">
                <strong>Members:</strong>
                <ul>
                    <?php if (isset($membersResult) && $membersResult->num_rows > 0): ?>
                        <?php while ($member = $membersResult->fetch_assoc()): ?>
                            <li><?= htmlspecialchars($member['name']); ?> (<?= htmlspecialchars($member['reg_no']); ?>)</li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li>No members yet.</li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>

    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>