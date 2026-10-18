<?php
require_once '../config/db.php';
require_once '../includes/ensure_assignment_submission_schema.php';
require_once __DIR__ . '/../includes/academic_year_history.php';

// Load Dompdf only if available (avoids fatal error on servers without vendor/)
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}
use Dompdf\Dompdf;

session_start();

// Ensure late-submission schema exists, but don't crash the page if it fails
try {
    ensure_assignment_submission_schema($conn);
} catch (Throwable $e) {
    error_log('ensure_assignment_submission_schema failed: ' . $e->getMessage());
}

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = (int)$_SESSION['user_id'];
$student_stmt = $conn->prepare("
    SELECT id, course_id, year_of_study, year_joined
    FROM students
    WHERE id = ?
");
$student_stmt->bind_param('i', $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
$student_stmt->close();
if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

$course_id = (int)$student['course_id'];
$currentAcademicYear = academic_year_history_current_label($conn);
$academicYearOptions = academic_year_history_student_options($student, $currentAcademicYear, $conn);
if ($academicYearOptions === []) {
    $academicYearOptions = [$currentAcademicYear => max(1, (int)($student['year_of_study'] ?? 1))];
}
$selectedAcademicYear = trim((string)($_GET['academic_year'] ?? $_SESSION['student_dashboard_academic_year'] ?? $currentAcademicYear));
if ($selectedAcademicYear === '' || !array_key_exists($selectedAcademicYear, $academicYearOptions)) {
    $selectedAcademicYear = $currentAcademicYear;
}
$_SESSION['student_dashboard_academic_year'] = $selectedAcademicYear;
$selectedAcademicYearRange = academic_year_history_date_range($conn, $selectedAcademicYear);
$assignmentPeriodStart = $selectedAcademicYearRange[0];
$assignmentPeriodEnd = $selectedAcademicYearRange[1];
$year_of_study = max(1, (int)$academicYearOptions[$selectedAcademicYear]);
$readOnlyHistory = $selectedAcademicYear !== $currentAcademicYear;
$yearTiles = [];
foreach ($academicYearOptions as $label => $studyYear) {
    $yearTiles[] = [
        'label' => (string)$label,
        'studyYear' => (int)$studyYear,
        'isCurrent' => $label === $currentAcademicYear,
        'isSelected' => $label === $selectedAcademicYear,
    ];
}

// Handle PDF generation
if (isset($_POST['generate_pdf'])) {
    try {
        // Fetch student info
        $stu_query = $conn->prepare("
            SELECT s.name, s.reg_no, s.email, s.year_of_study, c.name AS course_name, u.name AS university_name
            FROM students s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN universities u ON s.university_id = u.id
            WHERE s.id = ?
        ");
        $stu_query->bind_param("i", $student_id);
        $stu_query->execute();
        $student = $stu_query->get_result()->fetch_assoc();
        $stu_query->close();

        // Fetch submitted assignments
        $sub_query = $conn->prepare("
            SELECT s.file_path, s.submitted_at, s.is_late, s.comment, s.marks, a.title, u.name AS unit_name
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN units u ON a.unit_id = u.id
            WHERE s.student_id = ? AND u.course_id = ? AND u.year = ?
              AND (
                  a.academic_year = ?
                  OR (a.created_at >= ? AND a.created_at < ?)
              )
            ORDER BY s.submitted_at DESC
        ");
        $sub_query->bind_param(
            "iiisss",
            $student_id,
            $course_id,
            $year_of_study,
            $selectedAcademicYear,
            $assignmentPeriodStart,
            $assignmentPeriodEnd
        );
        $sub_query->execute();
        $subs = $sub_query->get_result();
        $sub_query->close();

        // Generate a unique signature (hash of student ID + timestamp)
        $signature = hash('sha256', $student_id . time());

        // Build HTML for PDF
        $html = "
        <div style='text-align:center; margin-bottom:20px;'>
            <h1>" . htmlspecialchars($student['university_name']) . "</h1>
            <h2>Submitted Assignments Report</h2>
        </div>

        <table style='width:100%; margin-bottom:20px; font-size:14px;'>
            <tr><td><strong>Name:</strong> " . htmlspecialchars($student['name']) . "</td>
                <td><strong>Reg No:</strong> " . htmlspecialchars($student['reg_no']) . "</td></tr>
            <tr><td><strong>Email:</strong> " . htmlspecialchars($student['email']) . "</td>
                <td><strong>Course:</strong> " . htmlspecialchars($student['course_name']) . "</td></tr>
            <tr><td><strong>Year of Study:</strong> " . htmlspecialchars($student['year_of_study']) . "</td>
                <td><strong>Date Generated:</strong> " . date('d M Y, h:i A') . "</td></tr>
        </table>

        <table border='1' cellpadding='5' cellspacing='0' width='100%'>
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Title</th>
                    <th>Date Submitted</th>
                    <th>Status</th>
                    <th>Marks</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
        ";

        if ($subs->num_rows === 0) {
            $html .= "<tr><td colspan='6'>No assignments submitted yet.</td></tr>";
        } else {
            while ($s = $subs->fetch_assoc()) {
                $marks = is_null($s['marks']) ? "Not graded" : htmlspecialchars($s['marks']);
                $comment = !empty($s['comment']) ? htmlspecialchars($s['comment']) : "No comment";
                $status = !empty($s['is_late']) ? 'Late' : 'On time';
                $html .= "<tr>
                            <td>" . htmlspecialchars($s['unit_name']) . "</td>
                            <td>" . htmlspecialchars($s['title']) . "</td>
                            <td>" . date('d M Y, h:i A', strtotime($s['submitted_at'])) . "</td>
                            <td>$status</td>
                            <td>$marks</td>
                            <td>$comment</td>
                          </tr>";
            }
        }

        $html .= "</tbody></table>";

        // Signature at the bottom
        $html .= "<p style='margin-top:20px; font-size:12px; text-align:right;'>
                    Unique Signature: <strong>$signature</strong>
                  </p>";

        // Generate PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("submitted_assignments.pdf", ["Attachment" => true]);
        exit;

    } catch (mysqli_sql_exception $e) {
        die("Error generating PDF: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assignments Dashboard</title>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- External CSS -->
<link rel="stylesheet" href="css/take_assignment.css">

</head>
<body>

<header class="header">
    <h1>Assignments Dashboard</h1>
    <nav class="header-nav" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <a href="dashboard.php" class="back-btn"><i class="fas fa-home"></i> Dashboard</a>
        <a href="course_view.php" class="back-btn"><i class="fas fa-book-open"></i> Training</a>
        <a href="take_assessment.php" class="back-btn"><i class="fas fa-clipboard-check"></i> Exams</a>
        <a href="my_progress.php" class="back-btn"><i class="fas fa-chart-line"></i> Progress</a>
    </nav>
</header>

<div class="container">
<section class="card academic-year-selector">
    <h2>Assignments for <?= htmlspecialchars($selectedAcademicYear) ?> • Year <?= $year_of_study ?></h2>
    <?php if ($readOnlyHistory): ?>
        <p class="history-notice">Viewing archived assignments. Past-year assignments are read-only.</p>
    <?php endif; ?>
    <nav class="year-tile-grid" aria-label="Academic-year selection">
        <?php foreach ($yearTiles as $yearTile): ?>
            <a class="year-tile <?= $yearTile['isSelected'] ? 'active' : '' ?>"
               href="take_assignment.php?academic_year=<?= urlencode($yearTile['label']) ?>"
               title="Open <?= htmlspecialchars($yearTile['label']) ?> assignments">
                <?= htmlspecialchars($yearTile['label']) ?>
                <small><?= $yearTile['isCurrent'] ? 'Current' : 'Year ' . (int)$yearTile['studyYear'] ?></small>
            </a>
        <?php endforeach; ?>
    </nav>
</section>

<?php if (!empty($_SESSION['submission_success'])): ?>
    <div class="alert alert-success" style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        <?= htmlspecialchars($_SESSION['submission_success']) ?>
    </div>
    <?php unset($_SESSION['submission_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['submission_error'])): ?>
    <div class="alert alert-error" style="background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
        <?= htmlspecialchars($_SESSION['submission_error']) ?>
    </div>
    <?php unset($_SESSION['submission_error']); ?>
<?php endif; ?>

<?php
$assignmentUnits = [];
$assignmentLoadErrors = [];
$now = new DateTime();

try {
    $interactiveQuery = $conn->prepare("
        SELECT a.id, a.title, a.due_date, u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
               EXISTS (
                   SELECT 1 FROM interactive_submissions s
                   WHERE s.assignment_id = a.id AND s.student_id = ?
               ) AS is_submitted
        FROM interactive_assignments a
        JOIN units u ON a.unit_id = u.id
        WHERE u.course_id = ? AND u.year = ?
          AND (
              a.academic_year = ?
              OR (a.created_at >= ? AND a.created_at < ?)
          )
          " . ($readOnlyHistory ? "" : "AND a.due_date >= NOW()") . "
        ORDER BY u.name ASC, a.due_date ASC
    ");
    $interactiveQuery->bind_param(
        'iiisss',
        $student_id,
        $course_id,
        $year_of_study,
        $selectedAcademicYear,
        $assignmentPeriodStart,
        $assignmentPeriodEnd
    );
    $interactiveQuery->execute();
    $interactiveResult = $interactiveQuery->get_result();
    while ($assignment = $interactiveResult->fetch_assoc()) {
        $unitId = (int)$assignment['unit_id'];
        $assignmentUnits[$unitId]['name'] = (string)$assignment['unit_name'];
        $assignmentUnits[$unitId]['code'] = (string)($assignment['unit_code'] ?? '');
        $assignmentUnits[$unitId]['interactive'][] = $assignment;
    }
    $interactiveQuery->close();
} catch (mysqli_sql_exception $e) {
    error_log('Error loading interactive student assignments: ' . $e->getMessage());
    $assignmentLoadErrors[] = 'Interactive assignments could not be loaded.';
}

try {
    $assignmentQuery = $conn->prepare("
        SELECT a.id, a.title, a.deadline, a.file_path, a.allow_late_submission,
               u.id AS unit_id, u.name AS unit_name, u.code AS unit_code
        FROM assignments a
        JOIN units u ON a.unit_id = u.id
        WHERE u.course_id = ? AND u.year = ?
          AND (
              a.academic_year = ?
              OR (a.created_at >= ? AND a.created_at < ?)
          )
        ORDER BY u.name ASC, a.deadline ASC
    ");
    $assignmentQuery->bind_param(
        'iisss',
        $course_id,
        $year_of_study,
        $selectedAcademicYear,
        $assignmentPeriodStart,
        $assignmentPeriodEnd
    );
    $assignmentQuery->execute();
    $assignmentResult = $assignmentQuery->get_result();
    while ($assignment = $assignmentResult->fetch_assoc()) {
        $unitId = (int)$assignment['unit_id'];
        $assignmentUnits[$unitId]['name'] = (string)$assignment['unit_name'];
        $assignmentUnits[$unitId]['code'] = (string)($assignment['unit_code'] ?? '');
        $assignmentUnits[$unitId]['regular'][] = $assignment;
    }
    $assignmentQuery->close();
} catch (mysqli_sql_exception $e) {
    error_log('Error loading regular student assignments: ' . $e->getMessage());
    $assignmentLoadErrors[] = 'Regular assignments could not be loaded.';
}

if ($assignmentUnits !== []) {
    uasort($assignmentUnits, static function (array $left, array $right): int {
        return strcasecmp($left['name'], $right['name']);
    });
}
?>

<section class="card assignment-units">
    <div class="assignment-units-heading">
        <h2>Assignments by Unit</h2>
        <form method="POST">
            <button type="submit" name="generate_pdf" class="btn-generate-pdf">
                <i class="fas fa-file-pdf"></i> Submitted Assignments PDF
            </button>
        </form>
    </div>
    <?php foreach ($assignmentLoadErrors as $loadError): ?>
        <p class="alert alert-error"><?= htmlspecialchars($loadError) ?></p>
    <?php endforeach; ?>
    <?php if ($assignmentUnits === [] && $assignmentLoadErrors === []): ?>
        <div class="empty-assignments">
            <i class="fas fa-folder-open" aria-hidden="true"></i>
            <p>No assignments found for <?= htmlspecialchars($selectedAcademicYear) ?>, Year <?= $year_of_study ?>.</p>
        </div>
    <?php elseif ($assignmentUnits !== []): ?>
        <div class="unit-tile-grid">
            <?php foreach ($assignmentUnits as $unitId => $unitData): ?>
                <?php
                $regularAssignments = $unitData['regular'] ?? [];
                $interactiveAssignments = $unitData['interactive'] ?? [];
                $unitAssignmentCount = count($regularAssignments) + count($interactiveAssignments);
                $modalId = 'assignment-unit-modal-' . (int)$unitId;
                $submittedCount = 0;
                foreach ($regularAssignments as $regularIndex => $regularAssignment) {
                    $submissionCheck = $conn->prepare("
                        SELECT file_path, is_late, submitted_at, marks, comment
                        FROM submissions
                        WHERE assignment_id = ? AND student_id = ?
                    ");
                    $regularAssignmentId = (int)$regularAssignment['id'];
                    $submissionCheck->bind_param('ii', $regularAssignmentId, $student_id);
                    $submissionCheck->execute();
                    $submission = $submissionCheck->get_result()->fetch_assoc();
                    $submissionCheck->close();
                    $regularAssignments[$regularIndex]['submission'] = $submission ?: null;
                    if (!empty($submission['file_path'])) {
                        $submittedCount++;
                    }
                }
                foreach ($interactiveAssignments as $interactiveAssignment) {
                    if (!empty($interactiveAssignment['is_submitted'])) {
                        $submittedCount++;
                    }
                }
                ?>
                <article class="assignment-unit-tile">
                    <div class="assignment-unit-icon"><i class="fas fa-book-open" aria-hidden="true"></i></div>
                    <?php if ($unitData['code'] !== ''): ?>
                        <span class="assignment-unit-code"><?= htmlspecialchars($unitData['code']) ?></span>
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($unitData['name']) ?></h3>
                    <p><?= $unitAssignmentCount ?> assignment<?= $unitAssignmentCount === 1 ? '' : 's' ?>
                        <?php if ($submittedCount > 0): ?>
                            <span class="assignment-submitted-count">· <?= $submittedCount ?> submitted</span>
                        <?php endif; ?>
                    </p>
                    <button type="button" class="btn-view" data-modal-target="<?= htmlspecialchars($modalId) ?>">
                        View Assignments <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </article>

                <div id="<?= htmlspecialchars($modalId) ?>" class="modal" role="dialog" aria-modal="true"
                     aria-hidden="true" aria-labelledby="<?= htmlspecialchars($modalId) ?>-title">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h4 id="<?= htmlspecialchars($modalId) ?>-title"><?= htmlspecialchars($unitData['name']) ?></h4>
                                <span class="assignment-modal-year"><?= htmlspecialchars($selectedAcademicYear) ?> · Year <?= $year_of_study ?></span>
                            </div>
                            <button type="button" class="close-modal" aria-label="Close assignments">&times;</button>
                        </div>
                        <div class="modal-body">
                            <?php if ($regularAssignments !== []): ?>
                                <section class="assignment-modal-section">
                                    <h5>Regular Assignments (<?= count($regularAssignments) ?>)</h5>
                                    <div class="table-wrapper">
                                        <table>
                                            <thead><tr><th>Title</th><th>Deadline</th><th>Assignment File</th><th>Status</th><th>Actions</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($regularAssignments as $assignment): ?>
                                                <?php
                                                $deadline = new DateTime($assignment['deadline']);
                                                $passed = $now > $deadline;
                                                $allowLate = (int)($assignment['allow_late_submission'] ?? 1) === 1;
                                                $blockedByDeadline = $passed && !$allowLate;
                                                $filePath = (string)($assignment['file_path'] ?? '');
                                                $fileFullPath = __DIR__ . '/../assets/uploads/assignments/' . $filePath;
                                                $fileUrl = '../assets/uploads/assignments/' . rawurlencode($filePath);
                                                $submission = $assignment['submission'] ?? null;
                                                $alreadySubmitted = !empty($submission['file_path']);
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($assignment['title']) ?></td>
                                                    <td><?= date('d M Y, h:i A', strtotime($assignment['deadline'])) ?></td>
                                                    <td>
                                                        <?php if ($filePath !== '' && file_exists($fileFullPath)): ?>
                                                            <a class="action-btn view-file-btn" href="<?= htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">View file</a>
                                                            <a class="action-btn download-file-btn" href="<?= htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') ?>" download>Download</a>
                                                        <?php else: ?>
                                                            <em>No file</em>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($blockedByDeadline): ?>
                                                            <span class="assignment-status closed">Closed</span>
                                                        <?php elseif ($passed): ?>
                                                            <span class="assignment-status late">Late submissions allowed</span>
                                                        <?php else: ?>
                                                            <span class="assignment-status open">Open</span>
                                                        <?php endif; ?>
                                                        <?php if ($alreadySubmitted && !empty($submission['is_late'])): ?><small>(submitted late)</small><?php endif; ?>
                                                    </td>
                                                    <td class="actions-cell">
                                                        <?php if (!$readOnlyHistory && !$blockedByDeadline): ?>
                                                            <form method="POST" enctype="multipart/form-data" action="submit_assignment.php" class="submit-form">
                                                                <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                                                                <input type="file" name="file" accept=".pdf,.doc,.docx" required>
                                                                <button type="submit" class="btn-submit"><?= $alreadySubmitted ? 'Resubmit' : ($passed ? 'Submit (Late)' : 'Submit') ?></button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($alreadySubmitted): ?>
                                                            <?php
                                                            $submittedFile = (string)$submission['file_path'];
                                                            $submittedPath = __DIR__ . '/../assets/uploads/submissions/' . $submittedFile;
                                                            $submittedUrl = '../assets/uploads/submissions/' . rawurlencode($submittedFile);
                                                            ?>
                                                            <?php if (file_exists($submittedPath)): ?>
                                                                <div class="submission-status">
                                                                    <span class="status-label">Your submission<?= !empty($submission['submitted_at']) ? ' · ' . date('d M Y, h:i A', strtotime($submission['submitted_at'])) : '' ?></span>
                                                                    <span>Mark: <?= $submission['marks'] === null ? 'Not graded' : htmlspecialchars((string)$submission['marks']) ?></span>
                                                                    <span><?= !empty($submission['comment']) ? htmlspecialchars($submission['comment']) : 'No comment' ?></span>
                                                                    <a class="action-btn" href="<?= htmlspecialchars($submittedUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">View</a>
                                                                    <a class="action-btn" href="<?= htmlspecialchars($submittedUrl, ENT_QUOTES, 'UTF-8') ?>" download>Download</a>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ($readOnlyHistory): ?><span class="history-read-only">Read-only history</span><?php endif; ?>
                                                        <?php if ($readOnlyHistory && !$alreadySubmitted): ?><em>No submission recorded</em><?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <?php if ($interactiveAssignments !== []): ?>
                                <section class="assignment-modal-section">
                                    <h5>Interactive Assignments / CATs (<?= count($interactiveAssignments) ?>)</h5>
                                    <div class="table-wrapper">
                                        <table>
                                            <thead><tr><th>Title</th><th>Deadline</th><th>Status</th><th>Action</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($interactiveAssignments as $assignment): ?>
                                                <?php $interactiveId = (int)$assignment['id']; ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($assignment['title']) ?></td>
                                                    <td><?= date('d M Y, h:i A', strtotime($assignment['due_date'])) ?></td>
                                                    <td><?= !empty($assignment['is_submitted']) ? '<span class="submitted">Submitted</span>' : ($readOnlyHistory ? '<span class="history-read-only">Not submitted</span>' : '<span class="assignment-status open">Open</span>') ?></td>
                                                    <td>
                                                        <?php if (!$readOnlyHistory && empty($assignment['is_submitted'])): ?>
                                                            <a href="take_interactive_assignment.php?id=<?= $interactiveId ?>" class="action-btn">Answer MCQs</a>
                                                        <?php elseif ($readOnlyHistory): ?>
                                                            <span class="history-read-only">Read-only history</span>
                                                        <?php else: ?>
                                                            <span class="submitted">Submitted</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const openModal = (modal) => {
        modal.classList.add("active");
        modal.setAttribute("aria-hidden", "false");
        const closeButton = modal.querySelector(".close-modal");
        if (closeButton) closeButton.focus();
    };
    const closeModal = (modal) => {
        modal.classList.remove("active");
        modal.setAttribute("aria-hidden", "true");
    };

    const viewButtons = document.querySelectorAll("[data-modal-target]");
    viewButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            const modalId = btn.getAttribute("data-modal-target");
            const modal = document.getElementById(modalId);
            if (modal) openModal(modal);
        });
    });

    const closeButtons = document.querySelectorAll(".close-modal");
    closeButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            const modal = btn.closest(".modal");
            if (modal) closeModal(modal);
        });
    });

    const modals = document.querySelectorAll(".modal");
    modals.forEach(modal => {
        modal.addEventListener("click", (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            document.querySelectorAll(".modal.active").forEach(modal => closeModal(modal));
        }
    });
});

</script>
</body>
</html>
