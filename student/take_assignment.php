<?php
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // Dompdf autoload
use Dompdf\Dompdf;

session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.html");
    exit;
}

$student_id = $_SESSION['user_id'];
$course_id = $_SESSION['course_id'] ?? 0;
$year_of_study = $_SESSION['year_of_study'] ?? 1;

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
            SELECT s.file_path, s.submitted_at, s.comment, s.marks, a.title, u.name AS unit_name
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN units u ON a.unit_id = u.id
            WHERE s.student_id = ? AND u.course_id = ? AND u.year = ?
            ORDER BY s.submitted_at DESC
        ");
        $sub_query->bind_param("iii", $student_id, $course_id, $year_of_study);
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
                    <th>Marks</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
        ";

        if ($subs->num_rows === 0) {
            $html .= "<tr><td colspan='5'>No assignments submitted yet.</td></tr>";
        } else {
            while ($s = $subs->fetch_assoc()) {
                $marks = is_null($s['marks']) ? "Not graded" : htmlspecialchars($s['marks']);
                $comment = !empty($s['comment']) ? htmlspecialchars($s['comment']) : "No comment";
                $html .= "<tr>
                            <td>" . htmlspecialchars($s['unit_name']) . "</td>
                            <td>" . htmlspecialchars($s['title']) . "</td>
                            <td>" . date('d M Y, h:i A', strtotime($s['submitted_at'])) . "</td>
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
    <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</header>

<div class="container">
<!-- ================= Interactive Assignments ================= -->
<section class="card interactive-assignments">
    <h2>Interactive Assignments / CATs</h2>

    <div class="unit-filter">
        <label>Filter by Unit:</label>
        <select id="ia-unit-filter">
            <option value="">-- All Units --</option>
            <?php
            $uf = $conn->prepare("
                SELECT id, name 
                FROM units 
                WHERE course_id = ? AND year = ? 
                ORDER BY name ASC
            ");
            $uf->bind_param("ii", $course_id, $year_of_study);
            $uf->execute();
            $units_result = $uf->get_result();

            while ($unit_row = $units_result->fetch_assoc()) {
                echo '<option value="' . $unit_row['id'] . '">' 
                     . htmlspecialchars($unit_row['name']) 
                     . '</option>';
            }
            $uf->close();
            ?>
        </select>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Title</th>
                    <th>Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="interactive-rows">
            <?php
            $query = $conn->prepare("
                SELECT 
                    a.id, 
                    a.title, 
                    a.due_date, 
                    u.name AS unit_name,
                    u.id   AS unit_id
                FROM interactive_assignments a
                JOIN units u ON a.unit_id = u.id
                WHERE u.course_id = ? 
                  AND u.year = ? 
                  AND a.due_date >= NOW()
                ORDER BY a.due_date ASC
            ");
            $query->bind_param("ii", $course_id, $year_of_study);
            $query->execute();
            $result = $query->get_result();

            if ($result->num_rows === 0) {
                echo "<tr><td colspan='4' class='text-center'>No active interactive assignments or CATs at the moment.</td></tr>";
            } else {
                while ($row = $result->fetch_assoc()) {

                    // Check if already submitted
                    $check = $conn->prepare("
                        SELECT 1 
                        FROM interactive_submissions 
                        WHERE assignment_id = ? AND student_id = ?
                    ");
                    $check->bind_param("ii", $row['id'], $student_id);
                    $check->execute();
                    $submitted = $check->get_result()->num_rows > 0;
                    $check->close();

                    $action_html = $submitted
                        ? '<span class="submitted">Submitted</span>'
                        : '<a href="take_interactive_assignment.php?id=' . $row['id'] . '" class="action-btn">Answer MCQs</a>';

                    echo "
                    <tr data-unit=\"{$row['unit_id']}\">
                        <td>" . htmlspecialchars($row['unit_name']) . "</td>
                        <td>" . htmlspecialchars($row['title']) . "</td>
                        <td>" . date('d M Y, h:i A', strtotime($row['due_date'])) . "</td>
                        <td>$action_html</td>
                    </tr>";
                }
            }
            $query->close();
            ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ================= Submitted Assignments ================= -->
<section class="card submitted-assignments">
    <h2>Submitted Assignments</h2>
    <form method="POST">
        <button type="submit" name="generate_pdf" class="btn-generate-pdf">
            <i class="fas fa-file-pdf"></i> Generate PDF of All Submitted Assignments
        </button>
    </form>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Title</th>
                    <th>Date Submitted</th>
                    <th>Marks</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
            <?php
            try {
                $sub_query = $conn->prepare("
                    SELECT s.file_path, s.submitted_at, s.comment, s.marks, a.title, u.name AS unit_name
                    FROM submissions s
                    JOIN assignments a ON s.assignment_id = a.id
                    JOIN units u ON a.unit_id = u.id
                    WHERE s.student_id = ? AND u.course_id = ? AND u.year = ?
                    ORDER BY s.submitted_at DESC
                ");
                $sub_query->bind_param("iii", $student_id, $course_id, $year_of_study);
                $sub_query->execute();
                $subs = $sub_query->get_result();

                if ($subs->num_rows === 0) {
                    echo "<tr><td colspan='5'>No assignments submitted yet.</td></tr>";
                } else {
                    while ($s = $subs->fetch_assoc()) {
                        $marks = is_null($s['marks']) ? "<em>Not graded</em>" : htmlspecialchars($s['marks']);
                        $comment = !empty($s['comment']) ? htmlspecialchars($s['comment']) : "<em>No comment</em>";
                        echo "<tr>
                            <td>" . htmlspecialchars($s['unit_name']) . "</td>
                            <td>" . htmlspecialchars($s['title']) . "</td>
                            <td>" . date('d M Y, h:i A', strtotime($s['submitted_at'])) . "</td>
                            <td>$marks</td>
                            <td>$comment</td>
                        </tr>";
                    }
                }

                $sub_query->close();
            } catch (mysqli_sql_exception $e) {
                echo "<tr><td colspan='5'>Error loading submitted assignments.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ================= Regular Assignments ================= -->
<section class="card regular-assignments">
    <h2>Assignments for Year <?= htmlspecialchars($year_of_study) ?></h2>
    <div class="grid">
        <?php
        try {
            $a_query = $conn->prepare("
                SELECT a.id, a.title, a.deadline, a.file_path, u.name AS unit_name
                FROM assignments a
                JOIN units u ON a.unit_id = u.id
                WHERE u.course_id = ? AND u.year = ?
                ORDER BY u.name ASC, a.deadline DESC
            ");
            $a_query->bind_param("ii", $course_id, $year_of_study);
            $a_query->execute();
            $assignments = $a_query->get_result();

            if ($assignments->num_rows === 0) {
                echo "<p>No assignments found for your course and year.</p>";
            } else {
                $units = [];
                while ($a = $assignments->fetch_assoc()) {
                    $units[$a['unit_name']][] = $a;
                }

                $now = new DateTime();
                $unitIndex = 0;

                foreach ($units as $unitName => $unitAssignments) {
                    $modalId = "modal-$unitIndex";
                    echo "<div class='unit-card'>
                        <h3>$unitName</h3>
                        <button class='btn-view' data-modal-target='$modalId'>View Assignments</button>
                        <div id='$modalId' class='modal'>
                            <div class='modal-content'>
                                <h4>Assignments for $unitName</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Deadline</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                    foreach ($unitAssignments as $a) {
                        $filePath = $a['file_path'] ?? '';
                        $fullPath = "../assets/uploads/assignments/" . htmlspecialchars($filePath);

                        $deadline = new DateTime($a['deadline']);
$passed = $now > $deadline;

// Check if already submitted
$submissionQuery = $conn->prepare("SELECT file_path FROM submissions WHERE assignment_id = ? AND student_id = ?");
$submissionQuery->bind_param("ii", $a['id'], $student_id);
$submissionQuery->execute();
$submissionResult = $submissionQuery->get_result()->fetch_assoc();
$submissionQuery->close();

$alreadySubmitted = !empty($submissionResult['file_path']);

// Disable if deadline passed OR already submitted
$disabledAttr = ($passed || $alreadySubmitted) ? "disabled" : "";


                        $actions = '';
                        if (!empty($filePath) && file_exists($fullPath)) {
                            $actions .= "<a href='$fullPath' target='_blank' class='action-btn'>View</a> | <a href='$fullPath' download class='action-btn'>Download</a><br>";
                        }

                        $actions .= "<form method='POST' enctype='multipart/form-data' action='submit_assignment.php'>
                            <input type='hidden' name='assignment_id' value='{$a['id']}'>
                            <input type='file' name='file' accept='.pdf,.doc,.docx' required $disabledAttr>
                            <button type='submit' $disabledAttr class='btn-submit'>Submit</button>
                        </form>";

                        // Already submitted file
                        $submissionQuery = $conn->prepare("SELECT file_path FROM submissions WHERE assignment_id = ? AND student_id = ?");
                        $submissionQuery->bind_param("ii", $a['id'], $student_id);
                        $submissionQuery->execute();
                        $submissionResult = $submissionQuery->get_result()->fetch_assoc();
                        $submissionQuery->close();

                        if (!empty($submissionResult['file_path'])) {
                            $submittedFile = "../assets/uploads/submissions/" . htmlspecialchars($submissionResult['file_path']);
                            if (file_exists($submittedFile)) {
                                $actions .= "<br><strong>Your Submission:</strong> <a href='$submittedFile' target='_blank'>View</a> | <a href='$submittedFile' download>Download</a>";
                            }
                        }

                        echo "<tr>
                                <td>" . htmlspecialchars($a['title']) . "</td>
                                <td>" . date("d M Y, h:i A", strtotime($a['deadline'])) . "</td>
                                <td>$actions</td>
                              </tr>";
                    }

                    echo "</tbody></table>
                          <button class='close-modal'>Close</button>
                        </div></div></div>";
                    $unitIndex++;
                }
            }
            $a_query->close();
        } catch (mysqli_sql_exception $e) {
            echo "<p>Error loading assignments.</p>";
        }
        ?>
    </div>
</section>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Open modal
    const viewButtons = document.querySelectorAll("[data-modal-target]");
    viewButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            const modalId = btn.getAttribute("data-modal-target");
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add("active");
        });
    });

    // Close modal
    const closeButtons = document.querySelectorAll(".close-modal");
    closeButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            const modal = btn.closest(".modal");
            if (modal) modal.classList.remove("active");
        });
    });

    // Close modal on overlay click
    const modals = document.querySelectorAll(".modal");
    modals.forEach(modal => {
        modal.addEventListener("click", (e) => {
            if (e.target === modal) modal.classList.remove("active");
        });
    });
});

<!-- JavaScript for unit filtering -->

document.addEventListener("DOMContentLoaded", () => {
    const filterSelect = document.getElementById("ia-unit-filter");
    const rows = document.querySelectorAll("#interactive-rows tr[data-unit]");

    if (!filterSelect || rows.length === 0) return;

    filterSelect.addEventListener("change", () => {
        const selectedUnit = filterSelect.value;

        rows.forEach(row => {
            const unitId = row.getAttribute("data-unit");
            row.style.display = (selectedUnit === "" || unitId === selectedUnit) 
                ? "" 
                : "none";
        });
    });
});

</script>
</body>
</html>
