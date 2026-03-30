<?php
/**
 * API endpoint to fetch units by course
 */

header('Content-Type: application/json');
require_once '../config/db.php';

// Get course ID from query parameter
$courseId = $_GET['course_id'] ?? 0;

if (!$courseId || !is_numeric($courseId)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, name, code, year, semester 
        FROM units 
        WHERE course_id = ? 
        ORDER BY year, semester, name
    ");
    $stmt->bind_param("i", $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = [
            'id' => (int)$row['id'],
            'name' => htmlspecialchars($row['name']),
            'code' => htmlspecialchars($row['code']),
            'year' => (int)$row['year'],
            'semester' => (int)$row['semester']
        ];
    }
    
    $stmt->close();
    echo json_encode($units);
    
} catch (Exception $e) {
    error_log("Error fetching units: " . $e->getMessage());
    echo json_encode([]);
}
?>
