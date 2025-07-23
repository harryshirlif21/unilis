<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];
try {
    $student_stmt = $conn->prepare("SELECT id, name, email, reg_no, course_id, year_of_study, year_joined FROM students WHERE id = ?");
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    if (!$student) {
        throw new Exception("Student not found.");
    }
    $course_id = $student['course_id'];
    $year_of_study = $student['year_of_study'];

    // Fetch course name
    $course_stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $course_stmt->bind_param("i", $course_id);
    $course_stmt->execute();
    $course = $course_stmt->get_result()->fetch_assoc();
    $course_name = $course ? $course['name'] : 'Unknown Course';
    $course_stmt->close();
    $student_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching student/course: " . $e->getMessage());
    $_SESSION['error'] = "Error loading student data.";
    header("Location: ../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* [Previous CSS styles unchanged, copied from your code] */
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --text-color: #333;
            --light-bg: #ecf0f1;
            --white: #ffffff;
            --border-color: #ddd;
            --danger-color: #e74c3c;
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.2);
            --info-color: #007bff;
            --warning-color: #ffc107;
            --success-color: #28a745;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; min-height: 100vh; background-color: var(--light-bg); color: var(--text-color); line-height: 1.6; display: flex; flex-direction: column; }
        .header { background-color: var(--secondary-color); color: var(--white); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); position: sticky; top: 0; z-index: 100; }
        .header h1 { margin: 0; font-size: 1.8em; font-weight: 400; }
        .header .student-info { font-size: 1.1em; font-weight: 300; }
        .hamburger-menu { font-size: 1.8em; cursor: pointer; background: none; border: none; color: var(--white); padding: 5px 10px; border-radius: 5px; transition: background-color 0.2s ease; }
        .hamburger-menu:hover { background-color: rgba(255, 255, 255, 0.1); }
        .off-canvas-menu { position: fixed; top: 0; right: -300px; width: 280px; height: 100vh; background-color: var(--secondary-color); box-shadow: -4px 0 15px rgba(0, 0, 0, 0.2); transition: right 0.3s ease-in-out; z-index: 200; display: flex; flex-direction: column; padding: 25px 25px 40px 25px; box-sizing: border-box; overflow-y: auto; }
        .off-canvas-menu.active { right: 0; }
        .off-canvas-menu .close-btn { font-size: 2em; color: var(--white); align-self: flex-end; cursor: pointer; margin-bottom: 20px; transition: color 0.2s ease; }
        .off-canvas-menu .close-btn:hover { color: var(--danger-color); }
        .off-canvas-menu .menu-item { display: flex; align-items: center; width: 100%; padding: 12px 15px; margin-bottom: 10px; border: none; background: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 8px; cursor: pointer; text-align: left; text-decoration: none; font-size: 1.05em; transition: background-color 0.3s ease, transform 0.2s ease; gap: 10px; box-sizing: border-box; }
        .off-canvas-menu .menu-item:hover { background-color: var(--primary-color); transform: translateY(-2px); }
        .off-canvas-menu .menu-item.logout { margin-top: auto; background-color: var(--danger-color); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 150; transition: opacity 0.3s ease; opacity: 0; }
        .overlay.active { display: block; opacity: 1; }
        .content { flex: 1; padding: 30px; background: var(--light-bg); overflow-y: auto; width: 100%; box-sizing: border-box; }
        .content h2 { color: var(--secondary-color); margin-bottom: 25px; font-size: 2.2em; border-bottom: 2px solid var(--primary-color); padding-bottom: 15px; text-align: center; }
        .stat-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 40px; padding: 0 10px; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .stat-card { background-color: var(--white); border-radius: 12px; box-shadow: var(--shadow-light); padding: 25px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px; transition: transform 0.2s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .icon { font-size: 2.5em; color: var(--primary-color); margin-bottom: 10px; }
        .stat-card .number { font-size: 2.8em; font-weight: bold; color: var(--secondary-color); margin-bottom: 5px; }
        .stat-card .label { font-size: 0.95em; color: #666; }
        .stat-card.courses .icon, .stat-card.courses .number { color: var(--info-color); }
        .stat-card.due .icon, .stat-card.due .number { color: var(--warning-color); }
        .stat-card.meetings .icon, .stat-card.meetings .number { color: var(--accent-color); }
        .stat-card.submitted .icon, .stat-card.submitted .number { color: var(--success-color); }
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 40px; padding: 0 10px; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .chart-container { background-color: var(--white); border-radius: 12px; box-shadow: var(--shadow-light); padding: 25px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 250px; }
        .chart-container h3 { margin-top: 0; color: var(--secondary-color); font-size: 1.4em; margin-bottom: 20px; text-align: center; }
        .chart-placeholder { width: 100%; height: 180px; background-color: #f0f0f0; border: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: center; color: #aaa; font-style: italic; font-size: 0.9em; }
        .recent-activity-section { margin-bottom: 40px; padding: 0 10px; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .recent-activity-section h3 { color: var(--secondary-color); font-size: 1.8em; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .table-container { background-color: var(--white); border-radius: 12px; box-shadow: var(--shadow-light); padding: 20px; overflow-x: auto; margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95em; min-width: 600px; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        table th { background-color: var(--light-bg); color: var(--secondary-color); font-weight: bold; text-transform: uppercase; }
        table tbody tr:nth-child(even) { background-color: #f9f9f9; }
        table tbody tr:hover { background-color: #f0f8ff; }
        table td .action-link { color: var(--primary-color); text-decoration: none; font-weight: bold; transition: color 0.2s ease; }
        table td .action-link:hover { color: var(--accent-color); text-decoration: underline; }
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; padding: 0 10px; max-width: 1200px; margin-left: auto; margin-right: auto; }
        .action-card { background-color: var(--white); border-radius: 12px; box-shadow: var(--shadow-light); padding: 30px; text-align: center; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 180px; }
        .action-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-medium); }
        .action-card .icon { font-size: 3.5em; color: var(--primary-color); margin-bottom: 15px; transition: color 0.2s ease; }
        .action-card:hover .icon { color: var(--accent-color); }
        .action-card h3 { font-size: 1.4em; color: var(--secondary-color); margin-top: 0; margin-bottom: 10px; }
        .action-card p { font-size: 0.9em; color: #666; margin: 0; }
        .submission-form { margin-top: 10px; display: flex; gap: 10px; align-items: center; }
        .submission-form input[type="file"] { font-size: 0.9em; }
        .submission-form button { background-color: var(--primary-color); color: var(--white); border: none; padding: 8px 14px; border-radius: 5px; cursor: pointer; font-size: 0.9em; transition: background-color 0.2s ease; }
        .submission-form button:hover { background-color: var(--accent-color); }
        .success-message { color: var(--success-color); margin-bottom: 20px; font-weight: bold; }
        .error-message { color: var(--danger-color); margin-bottom: 20px; font-weight: bold; }
        @media (max-width: 992px) {
            .stat-cards-grid, .charts-grid, .recent-activity-section, .action-grid { padding: 0 15px; }
        }
        @media (max-width: 768px) {
            .header { padding: 10px 20px; }
            .header h1 { font-size: 1.5em; }
            .header .student-info { font-size: 0.95em; }
            .content { padding: 20px; }
            .stat-cards-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
            .stat-card .number { font-size: 2.2em; }
            .stat-card .label { font-size: 0.85em; }
            .charts-grid { grid-template-columns: 1fr; gap: 20px; }
            .chart-container { min-height: 220px; }
            .recent-activity-section h3 { font-size: 1.5em; }
            table { min-width: 500px; }
            .action-grid { gap: 15px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
            .action-card { padding: 20px; min-height: 160px; }
            .action-card .icon { font-size: 3em; }
            .action-card h3 { font-size: 1.2em; }
        }
        @media (max-width: 480px) {
            .header .student-info { display: none; }
            .content { padding: 15px; }
            .stat-cards-grid { grid-template-columns: 1fr; }
            .action-grid { grid-template-columns: 1fr; }
            .action-card { min-height: 150px; }
            .chart-container { min-height: 200px; }
            table { font-size: 0.85em; min-width: 400px; }
            table th, table td { padding: 8px 10px; }
        }
    </style>
</head>
<body>
<!-- Top Header Bar -->
<header class="header">
    <h1>UNILIS Student Dashboard</h1>
    <div class="student-info">Welcome, <?= htmlspecialchars($student['name']) ?></div>
    <button class="hamburger-menu" id="hamburgerMenu"><i class="fas fa-bars"></i></button>
</header>

<!-- Off-Canvas Menu -->
<div class="off-canvas-menu" id="offCanvasMenu">
    <button class="close-btn" id="closeMenuBtn">×</button>
    <h2><?= htmlspecialchars($student['name']) ?></h2>
    <p>Student ID: <?= htmlspecialchars($student['reg_no']) ?></p>
    <p>Program: <?= htmlspecialchars($course_name) ?></p>
    <p>Year of Study: Year <?= htmlspecialchars($year_of_study) ?></p>
    <p>Email: <?= htmlspecialchars($student['email']) ?></p>
    <p>Joined: <?= htmlspecialchars($student['year_joined']) ?></p>
    <a href="submit_assignment.php" class="menu-item"><i class="fas fa-upload"></i> Submit Assignment</a>
    <a href="#" class="menu-item" onclick="alert('Messages not implemented yet!')"><i class="fas fa-envelope"></i> Messages</a>
    <a href="#" class="menu-item" onclick="alert('Profile Settings not implemented yet!')"><i class="fas fa-user-cog"></i> Profile Settings</a>
    <a href="../logout.php" class="menu-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>


<!-- Main Content Area -->
<div class="content">
    <h2>Your Academic Overview</h2>

    <!-- Overview Statistics Section -->
    <?php
    try {
        $units_stmt = $conn->prepare("SELECT COUNT(*) as count FROM units WHERE course_id = ? AND year = ?");
        $units_stmt->bind_param("ii", $course_id, $year_of_study);
        $units_stmt->execute();
        $units_count = $units_stmt->get_result()->fetch_assoc()['count'];
        $units_stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching units count: " . $e->getMessage());
        $units_count = 0;
        $_SESSION['error'] = "Unable to load units count.";
    }

    try {
        $assignments_stmt = $conn->prepare("SELECT COUNT(*) as count FROM assignments WHERE unit_id IN (SELECT id FROM units WHERE course_id = ? AND year = ?) AND deadline >= NOW()");
        $assignments_stmt->bind_param("ii", $course_id, $year_of_study);
        $assignments_stmt->execute();
        $assignments_count = $assignments_stmt->get_result()->fetch_assoc()['count'];
        $assignments_stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching assignments count: " . $e->getMessage());
        $assignments_count = 0;
        $_SESSION['error'] = "Unable to load assignments count.";
    }

    try {
        $meetings_stmt = $conn->prepare("SELECT COUNT(*) as count FROM meetings WHERE unit_id IN (SELECT id FROM units WHERE course_id = ? AND year = ?) AND scheduled_time >= NOW()");
        $meetings_stmt->bind_param("ii", $course_id, $year_of_study);
        $meetings_stmt->execute();
        $meetings_count = $meetings_stmt->get_result()->fetch_assoc()['count'];
        $meetings_stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching meetings count: " . $e->getMessage());
        $meetings_count = 0;
        $_SESSION['error'] = "Unable to load meetings count.";
    }

    try {
        $submitted_stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE student_id = ? AND assignment_id IN (SELECT id FROM assignments WHERE unit_id IN (SELECT id FROM units WHERE course_id = ? AND year = ?))");
        $submitted_stmt->bind_param("iii", $student_id, $course_id, $year_of_study);
        $submitted_stmt->execute();
        $submitted_count = $submitted_stmt->get_result()->fetch_assoc()['count'];
        $submitted_stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Error fetching submitted assignments count: " . $e->getMessage());
        $submitted_count = 0;
        $_SESSION['error'] = "Unable to load submitted assignments count.";
    }
    ?>
    <div class="stat-cards-grid">
        <div class="stat-card courses">
            <div class="icon"><i class="fas fa-book-open"></i></div>
            <div class="number"><?= $units_count ?></div>
            <div class="label">Active Units</div>
        </div>
        <div class="stat-card due">
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="number"><?= $assignments_count ?></div>
            <div class="label">Assignments Due</div>
        </div>
        <div class="stat-card meetings">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="number"><?= $meetings_count ?></div>
            <div class="label">Upcoming Meetings</div>
        </div>
        <div class="stat-card submitted">
            <div class="icon"><i class="fas fa-check-double"></i></div>
            <div class="number"><?= $submitted_count ?></div>
            <div class="label">Assignments Submitted</div>
        </div>
    </div>

    <!-- Data Visualization Section (Placeholders) -->
    <div class="charts-grid">
        <div class="chart-container">
            <h3>Unit Progress</h3>
            <div class="chart-placeholder">Progress Bars Placeholder (e.g., per unit)</div>
        </div>
        <div class="chart-container">
            <h3>Assignment Status</h3>
            <div class="chart-placeholder">Pie Chart Placeholder (Submitted vs. Pending)</div>
        </div>
    </div>

    <!-- Messages -->
    <?php
    if (isset($_SESSION['submission_success'])) {
        echo "<p class='success-message'>" . htmlspecialchars($_SESSION['submission_success']) . "</p>";
        unset($_SESSION['submission_success']);
    }
    if (isset($_SESSION['submission_error'])) {
        echo "<p class='error-message'>" . htmlspecialchars($_SESSION['submission_error']) . "</p>";
        unset($_SESSION['submission_error']);
    }
    if (isset($_SESSION['error'])) {
        echo "<p class='error-message'>" . htmlspecialchars($_SESSION['error']) . "</p>";
        unset($_SESSION['error']);
    }
    ?>

    <!-- Notes Section -->
    <div class="recent-activity-section">
        <h3>Notes for Year <?= htmlspecialchars($year_of_study) ?></h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Unit Code</th>
                        <th>File</th>
                        <th>Uploaded At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $units_query = $conn->prepare("SELECT id, name, code FROM units WHERE course_id = ? AND year = ?");
                        $units_query->bind_param("ii", $course_id, $year_of_study);
                        $units_query->execute();
                        $units = $units_query->get_result();

                        if ($units->num_rows === 0) {
                            echo "<tr><td colspan='5'>No units found for your course and year.</td></tr>";
                        } else {
                            while ($unit = $units->fetch_assoc()) {
                                $unit_id = $unit['id'];
                                $unit_name = htmlspecialchars($unit['name']);
                                $unit_code = htmlspecialchars($unit['code']);

                                $notes_query = $conn->prepare("SELECT file_path, uploaded_at FROM notes WHERE unit_id = ? ORDER BY uploaded_at DESC");
                                $notes_query->bind_param("i", $unit_id);
                                $notes_query->execute();
                                $notes = $notes_query->get_result();

                                if ($notes->num_rows > 0) {
                                    while ($note = $notes->fetch_assoc()) {
                                        $file = htmlspecialchars($note['file_path']);
                                        $uploaded_at = date("d M Y, h:i A", strtotime($note['uploaded_at']));
                                        $full_path = "../assets/uploads/" . $file;
                                        $fileExists = file_exists($full_path);
                                        $fileDisplay = $file ? $file : '<span style="color:red;">No filename</span>';

                                        echo "<tr>
                                            <td>$unit_name</td>
                                            <td>$unit_code</td>
                                            <td>$fileDisplay</td>
                                            <td>$uploaded_at</td>
                                            <td>";
                                        if ($fileExists) {
                                            echo "<a href='$full_path' target='_blank' class='action-link'>View</a> | <a href='$full_path' download class='action-link'>Download</a>";
                                        } else {
                                            echo "<span style='color: red;'>File missing</span>";
                                        }
                                        echo "</td></tr>";
                                    }
                                } else {
                                    echo "<tr>
                                        <td>$unit_name</td>
                                        <td>$unit_code</td>
                                        <td colspan='3'>No notes uploaded yet.</td>
                                    </tr>";
                                }
                                $notes_query->close();
                            }
                        }
                        $units_query->close();
                    } catch (mysqli_sql_exception $e) {
                        error_log("Error fetching notes: " . $e->getMessage());
                        echo "<tr><td colspan='5'>Error loading notes. Please contact the administrator.</td></tr>";
                        $_SESSION['error'] = "Unable to load notes.";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assignments Section -->
<div class="recent-activity-section">
    <h3>Assignments for Year <?= htmlspecialchars($year_of_study) ?></h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Title</th>
                    <th>Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $assignments_query = $conn->prepare("
                        SELECT a.id, a.title, a.description, a.deadline, a.file_path, u.name AS unit_name
                        FROM assignments a
                        JOIN units u ON a.unit_id = u.id
                        WHERE u.course_id = ? AND u.year = ?
                        ORDER BY a.deadline DESC
                    ");
                    $assignments_query->bind_param("ii", $course_id, $year_of_study);
                    $assignments_query->execute();
                    $assignments = $assignments_query->get_result();

                    if ($assignments->num_rows === 0) {
                        echo "<tr><td colspan='4'>No assignments found for your course and year.</td></tr>";
                    } else {
                        $now = new DateTime();
                        while ($assignment = $assignments->fetch_assoc()) {
                            $filePath = !empty($assignment['file_path']) ? htmlspecialchars($assignment['file_path']) : '';
                            $fullPath = "../assets/uploads/assignments/" . $filePath;

                            // Check if deadline passed
                            $deadline = new DateTime($assignment['deadline']);
                            $deadlinePassed = $now > $deadline;

                            $actions = '';
                            if (!empty($filePath) && file_exists($fullPath)) {
                                $actions .= "<a href='$fullPath' target='_blank' class='action-link'>View</a> | <a href='$fullPath' download class='action-link'>Download</a><br>";
                            }

                            // Submission form - disable if deadline passed
                            $disabledAttr = $deadlinePassed ? "disabled title='Deadline passed, submission closed'" : "";

                            $actions .= "
                                <form method='POST' enctype='multipart/form-data' action='submit_assignment.php' class='submission-form'>
                                    <input type='hidden' name='assignment_id' value='{$assignment['id']}'>
                                    <input type='file' name='file' accept='.pdf,.doc,.docx' required $disabledAttr>
                                    <button type='submit' $disabledAttr>Submit</button>
                                </form>";

                            echo "<tr>
                                <td>" . htmlspecialchars($assignment['unit_name']) . "</td>
                                <td>" . htmlspecialchars($assignment['title']) . "</td>
                                <td>" . date("d M Y, h:i A", strtotime($assignment['deadline'])) . "</td>
                                <td>$actions</td>
                            </tr>";
                        }
                    }
                    $assignments_query->close();
                } catch (mysqli_sql_exception $e) {
                    error_log("Error fetching assignments: " . $e->getMessage());
                    echo "<tr><td colspan='4'>Error loading assignments. Please contact the administrator.</td></tr>";
                    $_SESSION['error'] = "Unable to load assignments.";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

    <!-- Submitted Assignments Section -->
   <!-- Submitted Assignments Section -->
<div class="recent-activity-section">
    <h3>Submitted Assignments</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Title</th>
                    <th>Date Submitted</th>
                    <th>Marks</th>
                    <th>Comment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $submissions_query = $conn->prepare("
                        SELECT s.file_path, s.submitted_at, s.comment, s.marks, a.title, u.name AS unit_name
                        FROM submissions s
                        JOIN assignments a ON s.assignment_id = a.id
                        JOIN units u ON a.unit_id = u.id
                        WHERE s.student_id = ? AND u.course_id = ? AND u.year = ?
                        ORDER BY s.submitted_at DESC
                    ");
                    $submissions_query->bind_param("iii", $student_id, $course_id, $year_of_study);
                    $submissions_query->execute();
                    $submissions = $submissions_query->get_result();

                    if ($submissions->num_rows === 0) {
                        echo "<tr><td colspan='6'>No assignments submitted yet.</td></tr>";
                    } else {
                        while ($submission = $submissions->fetch_assoc()) {
                            $filePath = htmlspecialchars($submission['file_path']);
                            $fullPath = "../assets/uploads/submissions/" . $filePath;
                            $actions = file_exists($fullPath) ?
                                "<a href='$fullPath' target='_blank' class='action-link'>View</a> | <a href='$fullPath' download class='action-link'>Download</a>" :
                                "<span style='color: red;'>File missing</span>";

                            $marksDisplay = is_null($submission['marks']) ? "<em>Not graded</em>" : htmlspecialchars($submission['marks']);
                            $commentDisplay = !empty($submission['comment']) ? htmlspecialchars($submission['comment']) : "<em>No comment</em>";

                            echo "<tr>
                                <td>" . htmlspecialchars($submission['unit_name']) . "</td>
                                <td>" . htmlspecialchars($submission['title']) . "</td>
                                <td>" . date("d M Y, h:i A", strtotime($submission['submitted_at'])) . "</td>
                                <td>$marksDisplay</td>
                                <td>$commentDisplay</td>
                                <td>$actions</td>
                            </tr>";
                        }
                    }
                    $submissions_query->close();
                } catch (mysqli_sql_exception $e) {
                    error_log("Error fetching submissions: " . $e->getMessage());
                    echo "<tr><td colspan='6'>Error loading submitted assignments. Please contact the administrator.</td></tr>";
                    $_SESSION['error'] = "Unable to load submitted assignments.";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>


    <!-- Meetings Section -->
    <div class="recent-activity-section">
        <h3>Upcoming Meetings</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Unit</th>
                        <th>Scheduled Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $now = date('Y-m-d H:i:s');
                        $meeting_query = $conn->prepare("
                            SELECT m.id, m.title, m.scheduled_time, u.name AS unit_name 
                            FROM meetings m 
                            JOIN units u ON m.unit_id = u.id 
                            WHERE u.course_id = ? AND u.year = ? AND m.scheduled_time >= ?
                            ORDER BY m.scheduled_time ASC
                        ");
                        $meeting_query->bind_param("iis", $course_id, $year_of_study, $now);
                        $meeting_query->execute();
                        $meetings = $meeting_query->get_result();

                        if ($meetings->num_rows === 0) {
                            echo "<tr><td colspan='4'>No meetings scheduled.</td></tr>";
                        } else {
                            while ($meeting = $meetings->fetch_assoc()) {
                                echo "<tr>
                                    <td>" . htmlspecialchars($meeting['title']) . "</td>
                                    <td>" . htmlspecialchars($meeting['unit_name']) . "</td>
                                    <td>" . date("d M Y, h:i A", strtotime($meeting['scheduled_time'])) . "</td>
                                    <td><a class='action-link' href='meeting_ide.php?meeting_id=" . htmlspecialchars($meeting['id']) . "' target='_blank'>Join Meeting</a></td>
                                </tr>";
                            }
                        }
                        $meeting_query->close();
                    } catch (mysqli_sql_exception $e) {
                        error_log("Error fetching meetings: " . $e->getMessage());
                        echo "<tr><td colspan='4'>Error loading meetings. Please contact the administrator.</td></tr>";
                        $_SESSION['error'] = "Unable to load meetings.";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Action Cards Section -->
    <div class="action-grid">
        <div class="action-card" onclick="window.location.href='submit_assignment.php'">
            <div class="icon"><i class="fas fa-upload"></i></div>
            <h3>Submit Assignment</h3>
            <p>Upload your completed assignments.</p>
        </div>
        <div class="action-card" onclick="alert('Messages not implemented yet!')">
            <div class="icon"><i class="fas fa-envelope"></i></div>
            <h3>Check Messages</h3>
            <p>Communicate with lecturers and peers.</p>
        </div>
        <div class="action-card" onclick="alert('Profile Settings not implemented yet!')">
            <div class="icon"><i class="fas fa-user-circle"></i></div>
            <h3>Update Profile</h3>
            <p>Manage your personal information.</p>
        </div>
    </div>
</div>

<script>
    const hamburgerBtn = document.getElementById('hamburgerMenu');
    const closeMenuBtn = document.getElementById('closeMenuBtn');
    const offCanvasMenu = document.getElementById('offCanvasMenu');
    const menuOverlay = document.getElementById('menuOverlay');

    function toggleOffCanvasMenu() {
        offCanvasMenu.classList.toggle('active');
        menuOverlay.classList.toggle('active');
    }

    hamburgerBtn.addEventListener('click', toggleOffCanvasMenu);
    closeMenuBtn.addEventListener('click', toggleOffCanvasMenu);
    menuOverlay.addEventListener('click', toggleOffCanvasMenu);

    const menuItems = document.querySelectorAll('.off-canvas-menu .menu-item');
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            setTimeout(toggleOffCanvasMenu, 150);
        });
    });
</script>
</body>
</html>