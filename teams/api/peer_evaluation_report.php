<?php
// teams/api/peer_evaluation_report.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php'; // mysqli $conn

try {
    $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
    $format = strtolower(trim((string)($_GET['format'] ?? 'json'))); // json|csv
    $lecturerId = (int)$_SESSION['user_id'];

    if ($teamId <= 0) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
        exit;
    }
    if (!in_array($format, ['json', 'csv'], true)) {
        $format = 'json';
    }

    // Ensure lecturer owns the unit that this team belongs to
    $stmt = $conn->prepare("
        SELECT t.id, t.title, t.unit_id, u.name AS unit_name, u.code AS unit_code
        FROM teams t
        JOIN units u ON u.id = t.unit_id
        JOIN lecturer_units lu ON lu.unit_id = t.unit_id
        WHERE t.id = ? AND lu.lecturer_id = ?
        LIMIT 1
    ");
    if (!$stmt) throw new Exception('Failed to prepare team access query: ' . $conn->error);
    $stmt->bind_param('ii', $teamId, $lecturerId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$team) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied for this team']);
        exit;
    }

    // Summary report
    $stmt = $conn->prepare("
        SELECT
            p.evaluatee_id,
            s.name AS evaluatee_name,
            COUNT(*) AS responses,
            ROUND(AVG(p.contribution), 2) AS avg_contribution,
            ROUND(AVG(p.communication), 2) AS avg_communication,
            ROUND(AVG(p.quality), 2) AS avg_quality,
            ROUND(AVG(p.reliability), 2) AS avg_reliability,
            ROUND(AVG((p.contribution + p.communication + p.quality + p.reliability) / 4.0), 2) AS avg_overall
        FROM peer_evaluations p
        JOIN students s ON s.id = p.evaluatee_id
        WHERE p.team_id = ?
        GROUP BY p.evaluatee_id, s.name
        ORDER BY s.name ASC
    ");
    if (!$stmt) throw new Exception('Failed to prepare report query: ' . $conn->error);
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="peer_evaluation_team_' . $teamId . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Team', 'Unit', 'Evaluatee', 'Responses', 'Avg Contribution', 'Avg Communication', 'Avg Quality', 'Avg Reliability', 'Avg Overall']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $team['title'],
                ($team['unit_code'] ?? '') . ' ' . ($team['unit_name'] ?? ''),
                $r['evaluatee_name'],
                $r['responses'],
                $r['avg_contribution'],
                $r['avg_communication'],
                $r['avg_quality'],
                $r['avg_reliability'],
                $r['avg_overall']
            ]);
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'team' => $team,
        'summary' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

