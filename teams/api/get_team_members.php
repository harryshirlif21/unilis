<?php
// teams/api/get_team_members.php - Get members of a specific team

require_once __DIR__ . '/../../config/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in as lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$team_id = $_GET['team_id'] ?? null;
if (!$team_id) {
    echo json_encode(['success' => false, 'error' => 'Team ID is required']);
    exit;
}

$lecturer_id = $_SESSION['user_id'];

try {
    // Get team members for this team (verify lecturer has access)
    $stmt = $conn->prepare("
        SELECT tm.student_id, s.name, s.reg_no, tm.role
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        JOIN teams t ON tm.team_id = t.id
        JOIN units u ON t.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE tm.team_id = ? AND lu.lecturer_id = ?
        ORDER BY tm.role DESC, s.name
    ");
    
    $stmt->bind_param("ii", $team_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'members' => $members]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
