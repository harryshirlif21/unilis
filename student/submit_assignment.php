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

$upload_error_messages = [
    UPLOAD_ERR_INI_SIZE => 'The selected file is larger than the server upload limit.',
    UPLOAD_ERR_FORM_SIZE => 'The selected file is larger than the form upload limit.',
    UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
    UPLOAD_ERR_NO_FILE => 'No file was selected.',
    UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is missing.',
    UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
    UPLOAD_ERR_EXTENSION => 'A server extension stopped the file upload.',
];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['submission_error'] = $upload_error_messages[$file['error']] ?? "File upload error. Please try again.";
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

$upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "submissions";
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        $_SESSION['submission_error'] = "The submission upload directory could not be created. Check that assets/uploads is writable by the web server.";
        header("Location: take_assignment.php");
        exit;
    }
}
if (!is_writable($upload_dir)) {
    $_SESSION['submission_error'] = "The submission upload directory is not writable.";
    header("Location: take_assignment.php");
    exit;
}

$original_name = basename((string)$file['name']);
$safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $original_name);
$safe_name = $safe_name !== '' ? $safe_name : 'submission_file';
$filename = time() . "_" . bin2hex(random_bytes(4)) . "_" . $safe_name;
$target_path = $upload_dir . DIRECTORY_SEPARATOR . $filename;

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
