<?php
require_once '../config/db.php';
require_once '../includes/notifications.php';
require_once '../includes/ensure_assignment_submission_schema.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit;
}

ensure_assignment_submission_schema($conn);

$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['assignment_id']) || !isset($_FILES['file'])) {
    header("Location: take_assignment.php");
    exit;
}

$assignment_id = intval($_POST['assignment_id']);
$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['submission_error'] = "File upload error. Please try again.";
    header("Location: take_assignment.php");
    exit;
}

$assign_stmt = $conn->prepare("
    SELECT a.id, a.title, a.deadline, a.allow_late_submission, a.lecturer_id, a.unit_id
    FROM assignments a
    JOIN units u ON u.id = a.unit_id
    WHERE a.id = ?
      AND u.course_id = ?
      AND u.year = ?
    LIMIT 1
");
$course_id = (int)($_SESSION['course_id'] ?? 0);
$year_of_study = (int)($_SESSION['year_of_study'] ?? 1);
$assign_stmt->bind_param("iii", $assignment_id, $course_id, $year_of_study);
$assign_stmt->execute();
$assignment = $assign_stmt->get_result()->fetch_assoc();
$assign_stmt->close();

if (!$assignment) {
    $_SESSION['submission_error'] = "Assignment not found or not available for your course/year.";
    header("Location: take_assignment.php");
    exit;
}

$deadline = new DateTime($assignment['deadline']);
$now = new DateTime();
$is_late = $now > $deadline ? 1 : 0;

if ($is_late && !(int)$assignment['allow_late_submission']) {
    $_SESSION['submission_error'] = "The deadline has passed and late submissions are not allowed for this assignment.";
    header("Location: take_assignment.php");
    exit;
}

$upload_dir = "../assets/uploads/submissions/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$filename = time() . "_" . basename($file['name']);
$target_path = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    $_SESSION['submission_error'] = "File upload failed.";
    header("Location: take_assignment.php");
    exit;
}

$existing = $conn->prepare("SELECT id, file_path FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1");
$existing->bind_param("ii", $assignment_id, $student_id);
$existing->execute();
$existing_row = $existing->get_result()->fetch_assoc();
$existing->close();

if ($existing_row) {
    $stmt = $conn->prepare("
        UPDATE submissions
        SET file_path = ?, submitted_at = NOW(), is_late = ?, is_graded = 0
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $filename, $is_late, $existing_row['id']);
} else {
    $stmt = $conn->prepare("
        INSERT INTO submissions (student_id, assignment_id, file_path, submitted_at, is_late)
        VALUES (?, ?, ?, NOW(), ?)
    ");
    $stmt->bind_param("iisi", $student_id, $assignment_id, $filename, $is_late);
}

if ($stmt->execute()) {
    $late_note = $is_late ? ' (late submission)' : '';
    $_SESSION['submission_success'] = "Assignment submitted successfully{$late_note}.";

    $student_stmt = $conn->prepare("SELECT name, email FROM students WHERE id = ?");
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    $student_stmt->close();

    if ($student && $assignment['lecturer_id']) {
        notify_student_assignment_submitted($conn, $student_id, $assignment_id, $student['name'], $student['email']);
        notify_lecturer_assignment_submitted(
            $conn,
            (int)$assignment['lecturer_id'],
            $student['name'],
            $student['email'],
            $assignment_id,
            $assignment['title']
        );
    }
} else {
    $_SESSION['submission_error'] = "Failed to save submission in database.";
    @unlink($target_path);
}

$stmt->close();
header("Location: take_assignment.php");
exit;
