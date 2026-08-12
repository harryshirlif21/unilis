<?php
// teams/api/get_teams_by_unit.php - Get teams for a specific unit OR list units with team counts

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/ensure_team_registrations.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['lecturer', 'admin', 'technician'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

ensure_team_registrations_tables($conn);

$lecturer_id = (int) $_SESSION['user_id'];
$unit_id = isset($_GET['unit_id']) ? (int) $_GET['unit_id'] : 0;

try {
    if ($unit_id > 0) {
        if (($_SESSION['user_role'] ?? '') === 'admin') {
            $stmt = $conn->prepare("
                SELECT DISTINCT t.id, t.title, tr.assessment_type, tr.id AS registration_id
                FROM teams t
                JOIN team_registrations tr ON tr.team_id = t.id AND tr.status = 'active'
                WHERE tr.unit_id = ?
                ORDER BY t.title, tr.assessment_type
            ");
            $stmt->bind_param('i', $unit_id);
        } else {
            $stmt = $conn->prepare("
                SELECT DISTINCT t.id, t.title, tr.assessment_type, tr.id AS registration_id
                FROM teams t
                JOIN team_registrations tr ON tr.team_id = t.id AND tr.status = 'active'
                JOIN units u ON u.id = tr.unit_id
                JOIN lecturer_units lu ON u.id = lu.unit_id
                WHERE tr.unit_id = ? AND lu.lecturer_id = ?
                ORDER BY t.title, tr.assessment_type
            ");
            $stmt->bind_param('ii', $unit_id, $lecturer_id);
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

    if (($_SESSION['user_role'] ?? '') === 'admin') {
        $stmt = $conn->prepare("
            SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
                   COUNT(DISTINCT tr.team_id) AS team_count
            FROM units u
            JOIN team_registrations tr ON tr.unit_id = u.id AND tr.status = 'active'
            GROUP BY u.id, u.name, u.code
            HAVING team_count > 0
            ORDER BY u.name
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code,
                   COUNT(DISTINCT tr.team_id) AS team_count
            FROM units u
            JOIN lecturer_units lu ON u.id = lu.unit_id AND lu.lecturer_id = ?
            JOIN team_registrations tr ON tr.unit_id = u.id AND tr.status = 'active'
            GROUP BY u.id, u.name, u.code
            HAVING team_count > 0
            ORDER BY u.name
        ");
        $stmt->bind_param('i', $lecturer_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = [
            'unit_id' => (int) $row['unit_id'],
            'unit_name' => $row['unit_name'],
            'unit_code' => $row['unit_code'],
            'team_count' => (int) $row['team_count'],
        ];
    }

    $stmt->close();

    echo json_encode(['success' => true, 'units' => $units]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
