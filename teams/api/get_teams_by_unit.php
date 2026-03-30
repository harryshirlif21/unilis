<?php
// teams/api/get_teams_by_unit.php - Get teams for a specific unit

require_once __DIR__ . '/../../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in as lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$unit_id = $_GET['unit_id'] ?? null;
if (!$unit_id) {
    echo json_encode(['success' => false, 'error' => 'Unit ID is required']);
    exit;
}

$lecturer_id = $_SESSION['user_id'];

try {
    // Get teams for this unit that belong to this lecturer
    $stmt = $conn->prepare("
        SELECT t.id, t.title 
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE u.id = ? AND lu.lecturer_id = ?
        ORDER BY t.title
    ");
    
    $stmt->bind_param("ii", $unit_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'teams' => $teams]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
