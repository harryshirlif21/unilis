<?php
include_once 'db.php';

/**
 * Secure query helper functions
 */
function executeQuery($sql, $params = [], $types = "") {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        $database->closeConnection();
        return false;
    }
    
    // For SELECT queries, return result set
    if (strtoupper(substr(trim($sql), 0, 6)) === 'SELECT') {
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        $database->closeConnection();
        return $data;
    }
    
    // For INSERT, return insert ID
    $insert_id = $stmt->insert_id;
    $stmt->close();
    $database->closeConnection();
    
    return $insert_id ?: $result;
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

// Validate user role and meeting access
function validateUserMeetingAccess($user_id, $meeting_id, $role = 'lecturer') {
    $sql = "SELECT m.id, m.lecturer_id, u.role 
            FROM meetings m 
            JOIN users u ON u.id = ? 
            WHERE m.id = ?";
    
    $result = executeQuery($sql, [$user_id, $meeting_id], "ii");
    
    if (empty($result)) {
        return false;
    }
    
    $meeting = $result[0];
    
    if ($role === 'lecturer') {
        return $meeting['lecturer_id'] == $user_id && $meeting['role'] === 'lecturer';
    } else {
        // Student access - check if student is enrolled in the unit
        $sql = "SELECT 1 FROM student_unit su 
                JOIN meetings m ON m.unit_id = su.unit_id 
                WHERE su.student_id = ? AND m.id = ?";
        $result = executeQuery($sql, [$user_id, $meeting_id], "ii");
        return !empty($result);
    }
}
?>