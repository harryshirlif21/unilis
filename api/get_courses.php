<?php
/**
 * API endpoint to fetch courses by department
 */

header('Content-Type: application/json');
require_once '../config/db.php';

// Get department ID from query parameter
$departmentId = $_GET['department_id'] ?? 0;

if (!$departmentId || !is_numeric($departmentId)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, name 
        FROM courses 
        WHERE department_id = ? 
        ORDER BY name
    ");
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = [
            'id' => (int)$row['id'],
            'name' => htmlspecialchars($row['name'])
        ];
    }
    
    $stmt->close();
    echo json_encode($courses);
    
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    echo json_encode([]);
}
?>
