<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../index.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];

// Get all interactive assignments created by this lecturer
try {
    $assignments_stmt = $conn->prepare("
        SELECT a.id, a.title, a.due_date, u.name AS unit_name, u.code AS unit_code,
               COUNT(s.id) as submission_count,
               AVG(s.score) as average_score,
               MAX(s.score) as highest_score,
               MIN(s.score) as lowest_score
        FROM interactive_assignments a 
        JOIN units u ON a.unit_id = u.id 
        LEFT JOIN interactive_submissions s ON a.id = s.assignment_id
        WHERE a.lecturer_id = ?
        GROUP BY a.id, a.title, a.due_date, u.name, u.code
        ORDER BY a.due_date DESC
    ");
    $assignments_stmt->bind_param("i", $lecturer_id);
    $assignments_stmt->execute();
    $assignments = $assignments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $assignments_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching assignments: " . $e->getMessage());
    $assignments = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Scores Overview - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/scores_overview.css">
</head>

<body>
    <header class="header">
        <h1>Student Scores Overview</h1>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </header>

    <div class="container">
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($assignments) ?></div>
                <div class="stat-label">Total Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= array_sum(array_column($assignments, 'submission_count')) ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $avgScores = array_filter(array_column($assignments, 'average_score'), function ($v) {
                        return $v !== null;
                    });
                    echo count($avgScores) > 0 ? round(array_sum($avgScores) / count($avgScores), 1) : '0';
                    ?>
                </div>
                <div class="stat-label">Overall Average</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $maxScores = array_filter(array_column($assignments, 'highest_score'), function ($v) {
                        return $v !== null;
                    });
                    echo count($maxScores) > 0 ? max($maxScores) : '0';
                    ?>
                </div>
                <div class="stat-label">Highest Score</div>
            </div>
        </div>

        <!-- Assignments Table -->
        <div class="assignments-table">
            <h3 style="margin-top: 0; color: var(--secondary-color);">Interactive Assignments & Student Performance</h3>

            <?php if (empty($assignments)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 3em; color: #ccc; margin-bottom: 15px;"></i>
                    <p>No interactive assignments found. <a href="create_questions.php">Create your first assignment</a></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Unit</th>
                            <th>Due Date</th>
                            <th>Submissions</th>
                            <th>Average Score</th>
                            <th>Highest Score</th>
                            <th>Lowest Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($assignment['title']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($assignment['unit_name']) ?><br>
                                    <small style="color: #666;"><?= htmlspecialchars($assignment['unit_code']) ?></small>
                                </td>
                                <td><?= date("d M Y", strtotime($assignment['due_date'])) ?></td>
                                <td class="score-cell"><?= $assignment['submission_count'] ?></td>
                                <td class="score-cell <?php
                                                        if ($assignment['average_score'] >= 80) echo 'score-excellent';
                                                        elseif ($assignment['average_score'] >= 60) echo 'score-good';
                                                        elseif ($assignment['average_score'] >= 40) echo 'score-average';
                                                        else echo 'score-poor';
                                                        ?>">
                                    <?= $assignment['average_score'] ? round($assignment['average_score'], 1) : 'N/A' ?>
                                </td>
                                <td class="score-cell score-excellent"><?= $assignment['highest_score'] ?: 'N/A' ?></td>
                                <td class="score-cell score-poor"><?= $assignment['lowest_score'] ?: 'N/A' ?></td>
                                <td>
                                    <a href="view_scores.php?id=<?= $assignment['id'] ?>" class="action-btn">
                                        <i class="fas fa-chart-line"></i> View Scores
                                    </a>
                                    <a href="export_scores.php?id=<?= $assignment['id'] ?>&format=pdf" class="action-btn export">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                    <a href="export_scores.php?id=<?= $assignment['id'] ?>&format=excel" class="action-btn export">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>