<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$assignment_id = $_GET['id'] ?? 0;

// Fetch assignment details
$stmt = $conn->prepare("
    SELECT a.*, u.name as unit_name,
           COUNT(DISTINCT s.student_id) as submission_count,
           COUNT(DISTINCT s.id) as total_submissions,
           AVG(s.grade) as average_grade
    FROM assignments a
    JOIN units u ON a.unit_id = u.id
    LEFT JOIN submissions s ON a.id = s.assignment_id
    WHERE a.id = ? AND a.lecturer_id = ?
    GROUP BY a.id
");
$stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    header("Location: dashboard.php");
    exit;
}

// Fetch submissions with student details
$stmt = $conn->prepare("
    SELECT DISTINCT s.student_id,
           st.name as student_name,
           MAX(s.submitted_at) as submission_time,
           SUM(s.grade) as total_grade,
           COUNT(s.id) as questions_answered
    FROM submissions s
    JOIN students st ON s.student_id = st.id
    WHERE s.assignment_id = ?
    GROUP BY s.student_id
    ORDER BY submission_time DESC
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submissions - <?= htmlspecialchars($assignment['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/review_assignments.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><?= htmlspecialchars($assignment['title']) ?></h1>
                <p><strong>Unit:</strong> <?= htmlspecialchars($assignment['unit_name']) ?></p>
            </div>
            <a href="export_grades.php?id=<?= $assignment_id ?>" class="btn btn-download">
                <i class="fas fa-download"></i> Export Grades
            </a>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <h3><?= $assignment['submission_count'] ?></h3>
                <p>Students Submitted</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($assignment['average_grade'], 1) ?></h3>
                <p>Average Grade</p>
            </div>
            <div class="stat-card">
                <h3><?= $assignment['total_submissions'] ?></h3>
                <p>Total Submissions</p>
            </div>
        </div>

        <div class="filters">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by student name...">
                <i class="fas fa-search"></i>
            </div>
            <div class="filter-group">
                <label for="sortBy">Sort by:</label>
                <select id="sortBy">
                    <option value="submission_time">Submission Time</option>
                    <option value="student_name">Student Name</option>
                    <option value="grade">Grade</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filterStatus">Status:</label>
                <select id="filterStatus">
                    <option value="all">All</option>
                    <option value="ontime">On Time</option>
                    <option value="late">Late</option>
                </select>
            </div>
        </div>

        <table class="submissions-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Submission Time</th>
                    <th>Status</th>
                    <th>Grade</th>
                    <th>Progress</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission): ?>
                    <?php 
                    $isLate = strtotime($submission['submission_time']) > strtotime($assignment['deadline']);
                    $progress = ($submission['questions_answered'] / $assignment['total_questions']) * 100;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($submission['student_name']) ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($submission['submission_time'])) ?></td>
                        <td>
                            <span class="status-badge <?= $isLate ? 'status-late' : 'status-ontime' ?>">
                                <?= $isLate ? 'Late' : 'On Time' ?>
                            </span>
                        </td>
                        <td><?= number_format($submission['total_grade'], 1) ?>/<?= $assignment['total_points'] ?></td>
                        <td><?= number_format($progress) ?>%</td>
                        <td class="action-buttons">
                            <a href="review_submission.php?assignment_id=<?= $assignment_id ?>&student_id=<?= $submission['student_id'] ?>" 
                               class="btn btn-review">
                                <i class="fas fa-eye"></i> Review
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchText = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.submissions-table tbody tr');

            rows.forEach(row => {
                const studentName = row.querySelector('td').textContent.toLowerCase();
                row.style.display = studentName.includes(searchText) ? '' : 'none';
            });
        });

        // Sorting functionality
        document.getElementById('sortBy').addEventListener('change', function(e) {
            const sortBy = e.target.value;
            const tbody = document.querySelector('.submissions-table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                let aValue, bValue;

                switch(sortBy) {
                    case 'submission_time':
                        aValue = new Date(a.children[1].textContent);
                        bValue = new Date(b.children[1].textContent);
                        break;
                    case 'student_name':
                        aValue = a.children[0].textContent;
                        bValue = b.children[0].textContent;
                        break;
                    case 'grade':
                        aValue = parseFloat(a.children[3].textContent);
                        bValue = parseFloat(b.children[3].textContent);
                        break;
                }

                if (aValue < bValue) return -1;
                if (aValue > bValue) return 1;
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
        });

        // Status filter functionality
        document.getElementById('filterStatus').addEventListener('change', function(e) {
            const status = e.target.value;
            const rows = document.querySelectorAll('.submissions-table tbody tr');

            rows.forEach(row => {
                const statusBadge = row.querySelector('.status-badge');
                if (status === 'all' || 
                    (status === 'ontime' && statusBadge.classList.contains('status-ontime')) ||
                    (status === 'late' && statusBadge.classList.contains('status-late'))) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
