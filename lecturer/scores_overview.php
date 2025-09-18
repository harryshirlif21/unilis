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
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --text-color: #333;
            --light-bg: #ecf0f1;
            --white: #ffffff;
            --border-color: #ddd;
            --danger-color: #e74c3c;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .header {
            background-color: var(--secondary-color);
            color: var(--white);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 400;
        }
        
        .back-btn {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s ease;
        }
        
        .back-btn:hover {
            background-color: var(--accent-color);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 25px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .assignments-table {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            padding: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
            min-width: 800px;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        table th {
            background-color: var(--light-bg);
            color: var(--secondary-color);
            font-weight: bold;
            text-transform: uppercase;
        }
        
        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        table tbody tr:hover {
            background-color: #f0f8ff;
        }
        
        .action-btn {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            transition: background-color 0.2s ease;
            margin-right: 5px;
        }
        
        .action-btn:hover {
            background-color: var(--accent-color);
        }
        
        .action-btn.export {
            background-color: var(--success-color);
        }
        
        .action-btn.export:hover {
            background-color: #218838;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .score-cell {
            font-weight: bold;
            text-align: center;
        }
        
        .score-excellent { color: var(--success-color); }
        .score-good { color: var(--accent-color); }
        .score-average { color: var(--warning-color); }
        .score-poor { color: var(--danger-color); }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .assignments-table {
                padding: 20px;
            }
            
            table {
                min-width: 600px;
            }
        }
    </style>
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
                    $avgScores = array_filter(array_column($assignments, 'average_score'), function($v) { return $v !== null; });
                    echo count($avgScores) > 0 ? round(array_sum($avgScores) / count($avgScores), 1) : '0';
                    ?>
                </div>
                <div class="stat-label">Overall Average</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $maxScores = array_filter(array_column($assignments, 'highest_score'), function($v) { return $v !== null; });
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
