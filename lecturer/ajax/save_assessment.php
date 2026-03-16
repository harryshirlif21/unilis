<?php
// ajax/save_assessment.php  — create or update an assessment header
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lecturer_id   = $_SESSION['user_id'];
$assessment_id = intval($_POST['assessment_id'] ?? 0);
$unit_id       = intval($_POST['unit_id']       ?? 0);
$title         = trim($_POST['title']           ?? '');
$type          = trim($_POST['type']            ?? '');
$instructions  = trim($_POST['instructions']    ?? '');
$time_limit    = intval($_POST['time_limit']    ?? 0);
$total_marks   = intval($_POST['total_marks']   ?? 0);
$pass_mark     = intval($_POST['pass_mark']     ?? 0);
$due_date      = trim($_POST['due_date']        ?? '');
$module_id     = intval($_POST['module_id']     ?? 0) ?: null;
$lesson_id     = intval($_POST['lesson_id']     ?? 0) ?: null;

$allowed_types = ['quiz', 'assignment', 'cat', 'exam'];

try {
    if ($assessment_id) {
        // UPDATE — only update fields that are provided
        $fields = [];
        $params = [];
        $types  = '';

        if ($title)                          { $fields[] = 'title = ?';           $params[] = $title;        $types .= 's'; }
        if (isset($_POST['instructions']))   { $fields[] = 'instructions = ?';    $params[] = $instructions; $types .= 's'; }
        if (isset($_POST['time_limit']))     { $fields[] = 'time_limit_mins = ?'; $params[] = $time_limit;   $types .= 'i'; }
        if (isset($_POST['total_marks']))    { $fields[] = 'total_marks = ?';     $params[] = $total_marks;  $types .= 'i'; }
        if (isset($_POST['pass_mark']))      { $fields[] = 'pass_mark = ?';       $params[] = $pass_mark;    $types .= 'd'; }
        if (isset($_POST['due_date'])) {
            if ($due_date) { $fields[] = 'due_date = ?'; $params[] = $due_date; $types .= 's'; }
            else           { $fields[] = 'due_date = NULL'; }
        }

        if (empty($fields)) {
            echo json_encode(['success' => true, 'message' => 'No changes', 'assessment_id' => $assessment_id]);
            exit;
        }

        $params[] = $assessment_id;
        $params[] = $lecturer_id;
        $types   .= 'ii';

        $sql  = "UPDATE assessments SET " . implode(', ', $fields) . " WHERE id = ? AND lecturer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Assessment updated', 'assessment_id' => $assessment_id]);

    } else {
        // INSERT new assessment
        if (!$unit_id || !$title || !in_array($type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'unit_id, title and valid type are required']);
            exit;
        }

        // Verify unit ownership
        $stmt = $conn->prepare("SELECT id FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
        $stmt->bind_param("ii", $lecturer_id, $unit_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
        }
        $stmt->close();

        $due = $due_date ?: null;
        $stmt = $conn->prepare("
            INSERT INTO assessments
                (unit_id, lecturer_id, module_id, lesson_id, title, type, instructions, time_limit_mins, total_marks, pass_mark, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiisssiids",
            $unit_id, $lecturer_id, $module_id, $lesson_id,
            $title, $type, $instructions,
            $time_limit, $total_marks, $pass_mark, $due
        );
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Assessment created', 'assessment_id' => $new_id]);
    }
} catch (mysqli_sql_exception $e) {
    error_log("save_assessment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}