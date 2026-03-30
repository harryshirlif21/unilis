<?php
require_once '../config/db.php';
require_once '../includes/notifications.php';
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit;
}

$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id']) && isset($_FILES['file'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $file = $_FILES['file'];

    $upload_dir = "../assets/uploads/submissions/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = time() . "_" . basename($file['name']);
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $stmt = $conn->prepare("INSERT INTO submissions (student_id, assignment_id, file_path, submitted_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $student_id, $assignment_id, $filename);

        if ($stmt->execute()) {
            $_SESSION['submission_success'] = "Assignment submitted successfully.";

            // === GET STUDENT AND ASSIGNMENT DETAILS FOR NOTIFICATIONS ===
            $student_stmt = $conn->prepare("SELECT name, email FROM students WHERE id = ?");
            $student_stmt->bind_param("i", $student_id);
            $student_stmt->execute();
            $student = $student_stmt->get_result()->fetch_assoc();
            $student_stmt->close();

            $assign_stmt = $conn->prepare("SELECT title, lecturer_id FROM assignments WHERE id = ?");
            $assign_stmt->bind_param("i", $assignment_id);
            $assign_stmt->execute();
            $assignment = $assign_stmt->get_result()->fetch_assoc();
            $assign_stmt->close();

            if ($student && $assignment) {
                // Send notification to the student
                notify_student_assignment_submitted($conn, $student_id, $assignment_id, $student['name'], $student['email']);

                // Send notification to the lecturer
                notify_lecturer_assignment_submitted($conn, $assignment['lecturer_id'], $student['name'], $student['email'], $assignment_id, $assignment['title']);
            }
        } else {
            $_SESSION['submission_error'] = "Failed to save submission in database.";
        }
    } else {
        $_SESSION['submission_error'] = "File upload failed.";
    }
}

header("Location: dashboard.php");
exit;
