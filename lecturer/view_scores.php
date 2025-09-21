<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../index.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$assignment_id = intval($_GET['id'] ?? 0);

if ($assignment_id <= 0) {
    $_SESSION['error'] = "Invalid assignment ID.";
    header("Location: create_questions.php");
    exit;
}

// Get assignment details
try {
    $assignment_stmt = $conn->prepare("
        SELECT a.id, a.title, a.due_date, u.name AS unit_name, u.code AS unit_code
        FROM interactive_assignments a 
        JOIN units u ON a.unit_id = u.id 
        WHERE a.id = ? AND a.lecturer_id = ?
    ");
    $assignment_stmt->bind_param("ii", $assignment_id, $lecturer_id);
    $assignment_stmt->execute();
    $assignment = $assignment_stmt->get_result()->fetch_assoc();
    $assignment_stmt->close();
    
    if (!$assignment) {
        $_SESSION['error'] = "Assignment not found or you don't have permission to view it.";
        header("Location: create_questions.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching assignment: " . $e->getMessage());
    $_SESSION['error'] = "Error loading assignment.";
    header("Location: create_questions.php");
    exit;
}

// Get student scores
try {
    $scores_stmt = $conn->prepare("
        SELECT 
            s.id as submission_id,
            s.score,
            s.submitted_at,
            st.name as student_name,
            st.reg_no,
            st.email
        FROM interactive_submissions s
        JOIN students st ON s.student_id = st.id
        WHERE s.assignment_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $scores_stmt->bind_param("i", $assignment_id);
    $scores_stmt->execute();
    $scores = $scores_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $scores_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching scores: " . $e->getMessage());
    $scores = [];
}

// Calculate statistics
$total_students = count($scores);
$total_score = array_sum(array_column($scores, 'score'));
$average_score = $total_students > 0 ? round($total_score / $total_students, 2) : 0;
$max_score = $total_students > 0 ? max(array_column($scores, 'score')) : 0;
$min_score = $total_students > 0 ? min(array_column($scores, 'score')) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Scores - <?= htmlspecialchars($assignment['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/view_scores.css">
</head>
<body>
    <header class="header">
        <h1>Student Scores</h1>
        <a href="create_questions.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Assignments
        </a>
    </header>

    <div class="container">
        <!-- Assignment Header -->
        <div class="assignment-header">
            <h2 class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></h2>
            
            <div class="assignment-meta">
                <div class="meta-item">
                    <div class="meta-label">Unit</div>
                    <div class="meta-value"><?= htmlspecialchars($assignment['unit_name']) ?> (<?= htmlspecialchars($assignment['unit_code']) ?>)</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Due Date</div>
                    <div class="meta-value"><?= date("d M Y, h:i A", strtotime($assignment['due_date'])) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Total Submissions</div>
                    <div class="meta-value"><?= $total_students ?></div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_students ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $average_score ?></div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $max_score ?></div>
                <div class="stat-label">Highest Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $min_score ?></div>
                <div class="stat-label">Lowest Score</div>
            </div>
        </div>

        <!-- Export Section -->
        <div class="export-section">
            <h3 style="margin: 0; color: var(--secondary-color);">Export Results:</h3>
            <a href="export_scores.php?id=<?= $assignment_id ?>&format=pdf" class="export-btn pdf">
                <i class="fas fa-file-pdf"></i>
                Export as PDF
            </a>
            <a href="export_scores.php?id=<?= $assignment_id ?>&format=excel" class="export-btn">
                <i class="fas fa-file-excel"></i>
                Export as Excel
            </a>
        </div>

        <!-- Scores Table -->
        <div class="scores-table">
            <h3 style="margin-top: 0; color: var(--secondary-color);">Student Scores</h3>
            
            <?php if (empty($scores)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 3em; color: #ccc; margin-bottom: 15px;"></i>
                    <p>No students have submitted this assignment yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Registration Number</th>
                            <th>Email</th>
                            <th>Score</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scores as $score): ?>
                            <tr>
                                <td><?= htmlspecialchars($score['student_name']) ?></td>
                                <td><?= htmlspecialchars($score['reg_no']) ?></td>
                                <td><?= htmlspecialchars($score['email']) ?></td>
                                <td class="score-cell <?php
                                    if ($score['score'] >= 80) echo 'score-excellent';
                                    elseif ($score['score'] >= 60) echo 'score-good';
                                    elseif ($score['score'] >= 40) echo 'score-average';
                                    else echo 'score-poor';
                                ?>">
                                    <?= $score['score'] ?>
                                </td>
                                <td><?= date("d M Y, h:i A", strtotime($score['submitted_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
