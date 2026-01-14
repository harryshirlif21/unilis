<?php
/*********************************
 * INITIALIZATION & SECURITY
 *********************************/
session_start();
require_once 'config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

/*********************************
 * STUDENT INFO
 *********************************/
$stmt = $conn->prepare("
    SELECT full_name, course, gpa
    FROM students
    WHERE id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

/*********************************
 * AVATAR INITIALS (FIXED)
 *********************************/
$initials = '';
foreach (explode(' ', $student['full_name']) as $n) {
    $initials .= strtoupper($n[0]);
}

/*********************************
 * QUICK STATS
 *********************************/
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM assignments WHERE student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($totalAssignments);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM assignments
    WHERE student_id = ?
    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($dueThisWeek);
$stmt->fetch();
$stmt->close();

/* If you don’t track study hours yet */
$studyHours = 0;

/*********************************
 * UPCOMING DEADLINES
 *********************************/
$stmt = $conn->prepare("
    SELECT title, due_date, progress, 'Assignment' AS type
    FROM assignments
    WHERE student_id = ?

    UNION ALL

    SELECT c.title, c.exam_date AS due_date, 0 AS progress, 'CAT' AS type
    FROM cats c
    WHERE c.course_id IN (
        SELECT course_id FROM enrollments WHERE student_id = ?
    )

    ORDER BY due_date ASC
    LIMIT 5
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$deadlines = $stmt->get_result();

/*********************************
 * NOTES
 *********************************/
$stmt = $conn->prepare("
    SELECT title, content, updated_at, tag, color
    FROM notes
    WHERE student_id = ?
    ORDER BY updated_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$notes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Modern Student Dashboard</title>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- 🔒 CSS KEPT EXACTLY AS try.html -->
<style>
/* === PASTE OF try.html CSS — UNCHANGED === */
<?php include 'try.css.inline.php'; ?>
</style>
</head>

<body>

<div class="container">

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">
        <div class="logo-icon">E</div>
        <div class="logo-text">EduDash</div>
    </div>

    <div class="user-info">
        <div class="user-avatar"><?= $initials ?></div>
        <div class="user-details">
            <h3><?= htmlspecialchars($student['full_name']) ?></h3>
            <p><?= htmlspecialchars($student['course']) ?></p>
        </div>
    </div>

    <div class="nav-links">
        <a class="nav-item active"><i class="fas fa-chart-bar"></i> Overview</a>
        <a class="nav-item"><i class="fas fa-book-open"></i> Courses</a>
        <a class="nav-item"><i class="fas fa-tasks"></i> Assignments</a>
        <a class="nav-item"><i class="fas fa-calendar-alt"></i> Schedule</a>
        <a class="nav-item"><i class="fas fa-sticky-note"></i> Notes</a>
        <a class="nav-item"><i class="fas fa-cog"></i> Settings</a>
    </div>

    <div class="user-info">
        <p>Current GPA</p>
        <h3><?= number_format($student['gpa'], 2) ?></h3>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

<!-- QUICK STATS -->
<div class="cards-grid">

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Quick Stats</h2>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-value"><?= $totalAssignments ?></div>
            <div class="stat-label">Assignments</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $dueThisWeek ?></div>
            <div class="stat-label">Due This Week</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $studyHours ?></div>
            <div class="stat-label">Study Hours</div>
        </div>
    </div>
</div>

<!-- UPCOMING DEADLINES -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Upcoming Deadlines</h2>
    </div>

    <?php if ($deadlines->num_rows > 0): ?>
        <?php while ($d = $deadlines->fetch_assoc()):
            $daysLeft = (strtotime($d['due_date']) - time()) / 86400;
        ?>
        <div class="course-item">
            <div class="course-info">
                <h4><?= htmlspecialchars($d['title']) ?> (<?= $d['type'] ?>)</h4>
                <p>Due: <?= date("M d, Y", strtotime($d['due_date'])) ?></p>
                <span class="deadline-badge <?= $daysLeft <= 2 ? 'badge-urgent' : 'badge-upcoming' ?>">
                    <?= $daysLeft <= 2 ? 'Urgent' : 'Upcoming' ?>
                </span>
            </div>

            <?php if ($d['type'] === 'Assignment'): ?>
            <div class="course-progress">
                <div class="progress-bar" style="width: <?= (int)$d['progress'] ?>%"></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">No upcoming deadlines</div>
    <?php endif; ?>
</div>

<!-- NOTES -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Notes</h2>
    </div>

    <div class="notes-grid">
        <?php while ($note = $notes->fetch_assoc()): ?>
        <div class="note-tile">
            <div class="note-color-bar" style="background-color: <?= $note['color'] ?>"></div>
            <h3 class="note-title"><?= htmlspecialchars($note['title']) ?></h3>
            <div class="note-content">
                <?= htmlspecialchars(substr($note['content'], 0, 120)) ?>…
            </div>
            <div class="note-meta">
                <span><?= htmlspecialchars($note['tag']) ?></span>
                <span><?= date("M d", strtotime($note['updated_at'])) ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- SCHEDULE (STATIC) -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Today's Schedule</h2>
    </div>
    <div class="course-item">
        <h4>10:00 AM – Data Structures</h4>
    </div>
    <div class="course-item">
        <h4>2:00 PM – Algorithms Lab</h4>
    </div>
</div>

</div>
</div>

</body>
</html>
