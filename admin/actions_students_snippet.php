<?php
// ─────────────────────────────────────────────────────────────
// ADD THESE THREE ACTIONS TO YOUR EXISTING actions.php file
// ─────────────────────────────────────────────────────────────

// === GET ALL STUDENTS ===
if ($action === 'get_all_students') {
    header('Content-Type: application/json');

    $result = $conn->query("
        SELECT id, reg_no, name, email, year_of_study, year_joined, is_verified, verified_at
        FROM students
        ORDER BY name ASC
    ");

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    echo json_encode(['status' => 'success', 'students' => $students]);
    exit;
}

// === DELETE SINGLE STUDENT ===
if ($action === 'delete_student') {
    header('Content-Type: application/json');

    $student_id = (int)($_POST['student_id'] ?? 0);
    if ($student_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Student deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete student: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// === BULK DELETE STUDENTS ===
if ($action === 'bulk_delete_students') {
    header('Content-Type: application/json');

    $raw_ids = $_POST['student_ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $raw_ids)));

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid student IDs provided.']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmt = $conn->prepare("DELETE FROM students WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);

    if ($stmt->execute()) {
        $count = $stmt->affected_rows;
        echo json_encode(['status' => 'success', 'message' => "$count student(s) deleted successfully."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Bulk delete failed: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}
