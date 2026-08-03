<?php
// teams/api/get_teams_by_unit.php - Get teams for a specific unit OR list units with team counts

require_once __DIR__ . '/../../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in as lecturer or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['lecturer', 'admin', 'technician'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int) $_SESSION['user_id'];
$unit_id = $_GET['unit_id'] ?? null;

try {
    if ($unit_id) {
        // Get teams for this unit that belong to this lecturer
        if (($_SESSION['user_role'] ?? '') === 'admin') {
            $stmt = $conn->prepare("
                SELECT t.id, t.title 
                FROM teams t
                JOIN units u ON t.unit_id = u.id
                WHERE u.id = ?
                ORDER BY t.title
            ");
            $stmt->bind_param("i", $unit_id);
        } else {
            $stmt = $conn->prepare("
                SELECT t.id, t.title 
                FROM teams t
                JOIN units u ON t.unit_id = u.id
                JOIN lecturer_units lu ON u.id = lu.unit_id
                WHERE u.id = ? AND lu.lecturer_id = ?
                ORDER BY t.title
            ");
            $stmt->bind_param("ii", $unit_id, $lecturer_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $teams = [];
        while ($row = $result->fetch_assoc()) {
            $teams[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode(['success' => true, 'teams' => $teams]);
        exit;
    }
    
    // No unit_id provided - return list of units with team counts for this lecturer
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        $stmt = $conn->prepare("
            SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
                   COUNT(t.id) AS team_count
            FROM units u
            LEFT JOIN teams t ON t.unit_id = u.id
            GROUP BY u.id
            HAVING team_count > 0
            ORDER BY u.name
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
                   COUNT(t.id) AS team_count
            FROM units u
            JOIN lecturer_units lu ON u.id = lu.unit_id AND lu.lecturer_id = ?
            LEFT JOIN teams t ON t.unit_id = u.id
            GROUP BY u.id
            HAVING team_count > 0
            ORDER BY u.name
        ");
        $stmt->bind_param("i", $lecturer_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = [
            'unit_id' => (int)$row['unit_id'],
            'unit_name' => $row['unit_name'],
            'unit_code' => $row['unit_code'],
            'team_count' => (int)$row['team_count']
        ];
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'units' => $units]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}