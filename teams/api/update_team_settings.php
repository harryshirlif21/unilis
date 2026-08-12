<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_limits.php';
require_once __DIR__ . '/../includes/team_membership.php';
require_once __DIR__ . '/../includes/ensure_team_registrations.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $teamId = (int) ($input['team_id'] ?? 0);
    $maxMembers = isset($input['max_members']) ? (int) $input['max_members'] : null;
    $unitId = isset($input['unit_id']) ? (int) $input['unit_id'] : null;
    $changePrimaryUnit = !empty($input['change_primary_unit']);
    $assessmentType = trim((string) ($input['assessment_type'] ?? ''));
    $csrfToken = (string) ($input['csrf_token'] ?? '');
    $userId = (int) $_SESSION['user_id'];

    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid team_id is required']);
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    if ($maxMembers === null && !$changePrimaryUnit && ($unitId === null || $unitId <= 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nothing to update']);
        exit;
    }

    ensure_team_max_members_column($conn);

    $roleStmt = $conn->prepare('SELECT role FROM team_members WHERE team_id = ? AND student_id = ? LIMIT 1');
    if (!$roleStmt) {
        throw new RuntimeException('Failed to prepare leader check: ' . $conn->error);
    }

    $roleStmt->bind_param('ii', $teamId, $userId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow || strtolower((string) ($roleRow['role'] ?? '')) !== 'leader') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only team leaders can update team settings']);
        exit;
    }

    $teamStmt = $conn->prepare('SELECT id, unit_id, max_members, course_id, year FROM teams WHERE id = ? LIMIT 1');
    if (!$teamStmt) {
        throw new RuntimeException('Failed to prepare team lookup: ' . $conn->error);
    }

    $teamStmt->bind_param('i', $teamId);
    $teamStmt->execute();
    $teamRow = $teamStmt->get_result()->fetch_assoc();
    $teamStmt->close();

    if (!$teamRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Team not found']);
        exit;
    }

    $currentCount = get_team_member_count($conn, $teamId);
    $messages = [];
    $updatedMaxMembers = (int) ($teamRow['max_members'] ?? TEAM_MEMBERS_ABSOLUTE_CAP);
    $updatedUnitId = (int) ($teamRow['unit_id'] ?? 0);

    if ($maxMembers !== null) {
        if ($maxMembers < 2 || $maxMembers > TEAM_MEMBERS_ABSOLUTE_CAP) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Max members must be between 2 and 15']);
            exit;
        }

        if ($maxMembers < $currentCount) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Cannot set max members below current team size ({$currentCount})",
            ]);
            exit;
        }

        if ($maxMembers !== (int) ($teamRow['max_members'] ?? 0)) {
            $updateStmt = $conn->prepare('UPDATE teams SET max_members = ? WHERE id = ? LIMIT 1');
            if (!$updateStmt) {
                throw new RuntimeException('Failed to prepare max members update: ' . $conn->error);
            }

            $updateStmt->bind_param('ii', $maxMembers, $teamId);
            $updateStmt->execute();
            $updateStmt->close();

            $updatedMaxMembers = $maxMembers;
            $messages[] = 'Team member limit updated';
        }
    }

    if ($changePrimaryUnit) {
        if ($unitId === null || $unitId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Select a unit to change to']);
            exit;
        }

        if ($assessmentType === '') {
            $assessmentType = trim((string) ($teamRow['assessment_type'] ?? 'project'));
        }
        if ($assessmentType === '') {
            $assessmentType = 'project';
        }

        $changeResult = team_change_primary_unit($conn, $teamId, $unitId, $assessmentType, $userId);
        if (!empty($changeResult['changed'])) {
            $updatedUnitId = (int) ($changeResult['unit_id'] ?? $unitId);
            $messages[] = 'Primary unit updated';
        }
    } elseif ($unitId !== null && $unitId > 0) {
        if ($assessmentType === '') {
            $assessmentType = trim((string) ($teamRow['assessment_type'] ?? 'project'));
        }
        if ($assessmentType === '') {
            $assessmentType = 'project';
        }

        $existingReg = $conn->prepare("
            SELECT id FROM team_registrations
            WHERE team_id = ? AND unit_id = ? AND assessment_type = ? AND status = 'active'
            LIMIT 1
        ");
        $existingReg->bind_param('iis', $teamId, $unitId, $assessmentType);
        $existingReg->execute();
        $existingRow = $existingReg->get_result()->fetch_assoc();
        $existingReg->close();

        if (!$existingRow) {
            team_add_registration($conn, $teamId, $unitId, $assessmentType, $userId);
            $updatedUnitId = $unitId;
            $messages[] = 'Team registered for the selected unit and assessment';
        }
    }

    if ($messages === []) {
        echo json_encode([
            'success' => true,
            'message' => 'No changes were needed',
            'max_members' => $updatedMaxMembers,
            'unit_id' => $updatedUnitId,
            'member_count' => $currentCount,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => implode('. ', $messages),
        'max_members' => $updatedMaxMembers,
        'unit_id' => $updatedUnitId,
        'member_count' => $currentCount,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
