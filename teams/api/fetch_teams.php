<?php
// teams/api/fetch_teams.php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../controllers/TeamController.php';

$assessmentId = (int)($_GET['assessment_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

if ($assessmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing assessment_id']);
    exit;
}

try {
    $controller = new TeamController($conn);
    $result = $controller->getTeam($assessmentId); // Using getTeam instead of getTeamsForAssessment
    echo json_encode(['success' => true, 'team' => $result]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}