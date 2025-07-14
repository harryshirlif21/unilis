<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$student_res = $conn->query("SELECT * FROM students WHERE id = $student_id");
$student = $student_res->fetch_assoc();

$course_id = $student['course_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <style>
        body {
            font-family: Arial;
            margin: 0;
            display: flex;
            height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #222;
            color: white;
            padding: 20px;
        }
        .main {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        h2, h3, h4 {
            margin-top: 0;
        }
        a {
            color: #3498db;
            text-decoration: none;
        }
        .section {
            margin-bottom: 40px;
        }
        ul {
            list-style: none;
            padding-left: 0;
        }
        li {
            margin-bottom: 10px;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2><?= htmlspecialchars($student['name']) ?></h2>
    <p><strong>Reg No:</strong> <?= htmlspecialchars($student['reg_no']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
    <p><strong>Year:</strong> <?= $student['year_of_study'] ?></p>
    <p><strong>Joined:</strong> <?= $student['year_joined'] ?></p>
    <br>
    <a href="../logout.php" style="color:red;">Logout</a>
</div>

<div class="main">

    <!-- Success or error messages -->
    <?php
    if (isset($_SESSION['submission_success'])) {
        echo "<p class='success'>{$_SESSION['submission_success']}</p>";
        unset($_SESSION['submission_success']);
    }
    if (isset($_SESSION['submission_error'])) {
        echo "<p class='error'>{$_SESSION['submission_error']}</p>";
        unset($_SESSION['submission_error']);
    }
    ?>

    <!-- Notes Section -->
    <div class="section">
        <h3>📘 Your Notes</h3>
        <?php
        $units = $conn->query("SELECT * FROM units WHERE course_id = $course_id");
        while ($unit = $units->fetch_assoc()) {
            echo "<h4>" . htmlspecialchars($unit['name']) . " (" . htmlspecialchars($unit['code']) . ")</h4>";
            $unit_id = $unit['id'];

            $notes = $conn->query("SELECT * FROM notes WHERE unit_id = $unit_id");
            if ($notes->num_rows > 0) {
                echo "<ul>";
                while ($note = $notes->fetch_assoc()) {
                    $filePath = htmlspecialchars($note['file_path']);
                    echo "<li>
                        <a href='../assets/uploads/{$filePath}' target='_blank'>View</a> |
                        <a href='../assets/uploads/{$filePath}' download>Download</a>
                    </li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No notes available for this unit.</p>";
            }
        }
        ?>
    </div>

    <!-- Assignments Section -->
    <div class="section">
        <h3>📄 Assignments</h3>
        <?php
        $units = $conn->query("SELECT * FROM units WHERE course_id = $course_id");
        while ($unit = $units->fetch_assoc()) {
            $unit_id = $unit['id'];
            $assignments = $conn->query("SELECT * FROM assignments WHERE unit_id = $unit_id");

            if ($assignments->num_rows > 0) {
                echo "<h4>" . htmlspecialchars($unit['name']) . " (" . htmlspecialchars($unit['code']) . ")</h4>";
                echo "<ul>";
                while ($assignment = $assignments->fetch_assoc()) {
                    echo "<li>
                        <strong>" . htmlspecialchars($assignment['title']) . "</strong> - Deadline: " . htmlspecialchars($assignment['deadline']) . "
                        <form method='POST' enctype='multipart/form-data' action='submit_assignment.php'>
                            <input type='hidden' name='assignment_id' value='{$assignment['id']}'>
                            <input type='file' name='file' required>
                            <button type='submit'>Submit</button>
                        </form>
                    </li>";
                }
                echo "</ul>";
            }
        }
        ?>
    </div>

</div>

</body>
</html>
