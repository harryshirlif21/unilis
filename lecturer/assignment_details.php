<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'lecturer') {
    header('Location: ../login.php');
    exit;
}

$lecturerId = (int)$_SESSION['user_id'];
$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$unitId = (int)($_GET['unit_id'] ?? 0);

if ($assignmentId > 0) {
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.description, a.deadline, a.created_at, a.file_path,
               u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
               c.name AS course_name, COUNT(DISTINCT s.student_id) AS submission_count
        FROM assignments a
        JOIN units u ON u.id = a.unit_id
        LEFT JOIN courses c ON c.id = u.course_id
        JOIN lecturer_units lu ON lu.unit_id = u.id AND lu.lecturer_id = ?
        LEFT JOIN submissions s ON s.assignment_id = a.id
        WHERE a.id = ?
        GROUP BY a.id, a.title, a.description, a.deadline, a.created_at, a.file_path,
                 u.id, u.name, u.code, c.name
    ");
    $stmt->bind_param('ii', $lecturerId, $assignmentId);
} elseif ($unitId > 0) {
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.description, a.deadline, a.created_at, a.file_path,
               u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
               c.name AS course_name, COUNT(DISTINCT s.student_id) AS submission_count
        FROM assignments a
        JOIN units u ON u.id = a.unit_id AND u.id = ?
        LEFT JOIN courses c ON c.id = u.course_id
        JOIN lecturer_units lu ON lu.unit_id = u.id AND lu.lecturer_id = ?
        LEFT JOIN submissions s ON s.assignment_id = a.id
        GROUP BY a.id, a.title, a.description, a.deadline, a.created_at, a.file_path,
                 u.id, u.name, u.code, c.name
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param('ii', $unitId, $lecturerId);
} else {
    header('Location: dashboard.php');
    exit;
}

$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$assignments) {
    header('Location: dashboard.php');
    exit;
}

$unit = $assignments[0];
$assignment = $assignmentId > 0 ? $assignments[0] : null;
if ($assignment) {
    $assignments = [$assignment];
}

$submissionStmt = $conn->prepare("
    SELECT s.id, s.file_path, s.submitted_at, s.marks, s.is_graded,
           st.name AS student_name, st.reg_no, c.name AS course_name
    FROM submissions s
    JOIN students st ON st.id = s.student_id
    LEFT JOIN courses c ON c.id = st.course_id
    JOIN assignments a ON a.id = s.assignment_id
    JOIN lecturer_units lu ON lu.unit_id = a.unit_id AND lu.lecturer_id = ?
    WHERE s.assignment_id = ?
    ORDER BY st.name ASC
");
$submissions = [];
foreach ($assignments as $item) {
    $submissionStmt->bind_param('ii', $lecturerId, $item['id']);
    $submissionStmt->execute();
    $submissions[$item['id']] = $submissionStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$submissionStmt->close();

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assignments - <?= e($unit['unit_name']) ?></title>
    <link rel="stylesheet" href="./css/styles.css">
    <style>
        .assignment-details { max-width: 1180px; margin: 30px auto; padding: 0 20px; }
        .assignment-details-header { display:flex; justify-content:space-between; gap:18px; align-items:center; margin-bottom:24px; }
        .assignment-details-header h1 { margin:0; color:#172554; }
        .assignment-details-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:22px; margin-bottom:24px; box-shadow:0 3px 12px rgba(15,23,42,.07); }
        .assignment-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:15px; margin:18px 0; }
        .assignment-meta strong { display:block; color:#64748b; font-size:12px; text-transform:uppercase; margin-bottom:4px; }
        .assignment-file, .download-btn { display:inline-block; padding:10px 14px; border-radius:8px; background:#2563eb; color:#fff; text-decoration:none; }
        .submission-count { color:#1d4ed8; font-weight:700; }
        .submissions-table { width:100%; border-collapse:collapse; margin-top:18px; }
        .submissions-table th, .submissions-table td { padding:11px; border-bottom:1px solid #e2e8f0; text-align:left; }
        .submissions-table th { background:#eff6ff; color:#1e3a8a; }
        .muted { color:#64748b; }
        @media (max-width:700px) { .assignment-details-header { align-items:flex-start; flex-direction:column; } .submissions-table { display:block; overflow-x:auto; } }
    </style>
</head>
<body>
<main class="assignment-details">
    <div class="assignment-details-header">
        <div>
            <a href="dashboard.php">&larr; Back to dashboard</a>
            <h1><?= e($unit['unit_name']) ?> assignments</h1>
        </div>
    </div>

    <?php foreach ($assignments as $item): ?>
        <section class="assignment-details-card">
            <h2><?= e($item['title']) ?></h2>
            <div class="assignment-meta">
                <div><strong>Course</strong><?= e($item['course_name'] ?: 'Not specified') ?></div>
                <div><strong>Unit</strong><?= e($item['unit_name']) ?> (<?= e($item['unit_code']) ?>)</div>
                <div><strong>Sent</strong><?= e($item['created_at']) ?></div>
                <div><strong>Deadline</strong><?= e($item['deadline']) ?></div>
                <div><strong>Students submitted</strong><span class="submission-count"><?= (int)$item['submission_count'] ?></span></div>
            </div>
            <?php if (trim((string)$item['description']) !== ''): ?>
                <p><strong>Instructions:</strong><br><?= nl2br(e($item['description'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($item['file_path'])): ?>
                <a class="assignment-file" href="../assets/uploads/assignments/<?= rawurlencode($item['file_path']) ?>" target="_blank" rel="noopener">
                    <i class="fas fa-paperclip"></i> View assignment file
                </a>
            <?php endif; ?>
            <a class="download-btn" href="assignment_submissions_pdf.php?assignment_id=<?= (int)$item['id'] ?>">Download submissions</a>

            <h3>Submitted answers</h3>
            <?php $rows = $submissions[$item['id']] ?? []; ?>
            <?php if (!$rows): ?>
                <p class="muted">No students have submitted this assignment yet.</p>
            <?php else: ?>
                <table class="submissions-table">
                    <thead><tr><th>Student</th><th>Registration number</th><th>Submitted</th><th>Marks awarded</th><th>Answer file</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['student_name']) ?></td>
                            <td><?= e($row['reg_no']) ?></td>
                            <td><?= e($row['submitted_at']) ?></td>
                            <td><?= $row['marks'] === null ? 'Not graded' : e((string)$row['marks']) ?></td>
                            <td><?php if (!empty($row['file_path'])): ?><a href="../assets/uploads/submissions/<?= rawurlencode($row['file_path']) ?>" target="_blank" rel="noopener">Download</a><?php else: ?>—<?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
