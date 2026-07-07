<?php
// teams/api/lecturer_team_insights.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

$teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
$lecturerId = (int) $_SESSION['user_id'];

if ($teamId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
    exit;
}

try {
    // Verify lecturer access to this team via assigned unit.
    $stmt = $conn->prepare("\n        SELECT\n            t.id,\n            t.title,\n            t.unit_id,\n            t.assessment_type,\n            t.status,\n            t.created_at,\n            u.name AS unit_name,\n            u.code AS unit_code\n        FROM teams t\n        JOIN units u ON u.id = t.unit_id\n        JOIN lecturer_units lu ON lu.unit_id = t.unit_id\n        WHERE t.id = ? AND lu.lecturer_id = ?\n        LIMIT 1\n    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare team lookup: ' . $conn->error);
    }
    $stmt->bind_param('ii', $teamId, $lecturerId);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$team) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied for this team']);
        exit;
    }

    // Team members.
    $stmt = $conn->prepare("\n        SELECT\n            tm.student_id,\n            tm.role,\n            tm.joined_at,\n            s.name AS student_name,\n            s.reg_no,\n            s.email\n        FROM team_members tm\n        JOIN students s ON s.id = tm.student_id\n        WHERE tm.team_id = ?\n        ORDER BY tm.role DESC, s.name ASC\n    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare member query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result();
    $members = [];
    $memberIds = [];
    while ($row = $res->fetch_assoc()) {
        $sid = (int) $row['student_id'];
        $row['student_id'] = $sid;
        $members[$sid] = [
            'student_id' => $sid,
            'student_name' => $row['student_name'],
            'reg_no' => $row['reg_no'],
            'email' => $row['email'],
            'role' => $row['role'],
            'joined_at' => $row['joined_at'],
            'files_uploaded' => 0,
            'recent_files' => [],
            'tasks_created' => 0,
            'tasks_assigned' => 0,
            'standups_count' => 0,
            'checklist_signoffs' => 0,
            'activity_count_14d' => 0,
            'last_activity_at' => null,
            'last_activity' => null,
            'activity_types' => []
        ];
        $memberIds[] = $sid;
    }
    $stmt->close();

    $files = [];
    $tasks = [];
    $standups = [];
    $activities = [];
    $checklist = [];
    $signoffs = [];
    $peerSummary = [];
    $kanbanCounts = [
        'Backlog' => 0,
        'In Progress' => 0,
        'In Review' => 0,
        'Done' => 0
    ];

    // Files.
    $stmt = $conn->prepare("\n        SELECT\n            tf.id,\n            tf.original_name AS file_name,\n            tf.filepath AS file_path,\n            tf.mime_type,\n            tf.file_size,\n            tf.version,\n            tf.uploader_id,\n            tf.uploaded_at,\n            s.name AS uploader_name\n        FROM team_files tf\n        LEFT JOIN students s ON s.id = tf.uploader_id\n        WHERE tf.team_id = ?\n        ORDER BY tf.uploaded_at DESC, tf.id DESC\n        LIMIT 200\n    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare files query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['uploader_id'] = (int) ($row['uploader_id'] ?? 0);
        $files[] = $row;
        $sid = $row['uploader_id'];
        if (isset($members[$sid])) {
            $members[$sid]['files_uploaded']++;
            if (count($members[$sid]['recent_files']) < 5) {
                $members[$sid]['recent_files'][] = [
                    'id' => (int) $row['id'],
                    'file_name' => $row['file_name'],
                    'uploaded_at' => $row['uploaded_at']
                ];
            }
        }
    }
    $stmt->close();

    // Tasks/Kanban.
    $stmt = $conn->prepare("\n        SELECT\n            t.id,\n            t.title,\n            t.description,\n            t.status,\n            t.priority,\n            t.due_date,\n            t.created_at,\n            t.updated_at,\n            t.created_by,\n            t.assigned_to,\n            creator.name AS creator_name,\n            assignee.name AS assignee_name\n        FROM team_tasks t\n        LEFT JOIN students creator ON creator.id = t.created_by\n        LEFT JOIN students assignee ON assignee.id = t.assigned_to\n        WHERE t.team_id = ?\n        ORDER BY t.updated_at DESC, t.id DESC\n        LIMIT 300\n    ");
    if ($stmt) {
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['created_by'] = isset($row['created_by']) ? (int) $row['created_by'] : null;
            $row['assigned_to'] = isset($row['assigned_to']) ? (int) $row['assigned_to'] : null;
            $tasks[] = $row;

            $statusKey = (string) ($row['status'] ?? 'Backlog');
            if (!isset($kanbanCounts[$statusKey])) {
                $kanbanCounts[$statusKey] = 0;
            }
            $kanbanCounts[$statusKey]++;

            if (isset($members[$row['created_by']])) {
                $members[$row['created_by']]['tasks_created']++;
            }
            if (isset($members[$row['assigned_to']])) {
                $members[$row['assigned_to']]['tasks_assigned']++;
            }
        }
        $stmt->close();
    }

    // Checklist + signoffs.
    $stmt = $conn->prepare("SELECT * FROM submission_checklist WHERE team_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $checklist[] = $row;
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("\n        SELECT ss.id, ss.user_id, ss.signed_at, s.name AS user_name\n        FROM submission_signoffs ss\n        LEFT JOIN students s ON s.id = ss.user_id\n        WHERE ss.team_id = ?\n        ORDER BY ss.signed_at DESC\n    ");
    if ($stmt) {
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['user_id'] = (int) ($row['user_id'] ?? 0);
            $signoffs[] = $row;
            if (isset($members[$row['user_id']])) {
                $members[$row['user_id']]['checklist_signoffs']++;
            }
        }
        $stmt->close();
    }

    // Standups.
    $stmt = $conn->prepare("\n        SELECT\n            se.id,\n            se.user_id,\n            se.did_today AS yesterday,\n            se.will_do_next AS today,\n            se.blockers,\n            se.created_at,\n            s.name AS user_name\n        FROM standup_entries se\n        LEFT JOIN students s ON s.id = se.user_id\n        WHERE se.team_id = ?\n        ORDER BY se.created_at DESC\n        LIMIT 200\n    ");
    if ($stmt) {
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['user_id'] = (int) ($row['user_id'] ?? 0);
            $standups[] = $row;
            if (isset($members[$row['user_id']])) {
                $members[$row['user_id']]['standups_count']++;
            }
        }
        $stmt->close();
    }

    // Activity log (and per-member action stats).
    $stmt = $conn->prepare("\n        SELECT\n            l.id,\n            l.user_id,\n            l.action_type,\n            l.action_detail,\n            l.created_at,\n            s.name AS user_name\n        FROM team_activity_log l\n        LEFT JOIN students s ON s.id = l.user_id\n        WHERE l.team_id = ?\n        ORDER BY l.created_at DESC\n        LIMIT 300\n    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare activity query: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) ($row['user_id'] ?? 0);
        $activities[] = $row;

        $sid = $row['user_id'];
        if (isset($members[$sid])) {
            $members[$sid]['activity_types'][$row['action_type']] =
                (int) ($members[$sid]['activity_types'][$row['action_type']] ?? 0) + 1;

            if ($members[$sid]['last_activity_at'] === null) {
                $members[$sid]['last_activity_at'] = $row['created_at'];
                $members[$sid]['last_activity'] = [
                    'action_type' => $row['action_type'],
                    'action_detail' => $row['action_detail'],
                    'created_at' => $row['created_at']
                ];
            }

            $createdTs = strtotime((string) $row['created_at']);
            if ($createdTs !== false && $createdTs >= strtotime('-14 days')) {
                $members[$sid]['activity_count_14d']++;
            }
        }
    }
    $stmt->close();

    // Peer evaluation summary.
    $stmt = $conn->prepare("\n        SELECT\n            p.evaluatee_id,\n            s.name AS evaluatee_name,\n            COUNT(*) AS responses,\n            ROUND(AVG(p.contribution), 2) AS avg_contribution,\n            ROUND(AVG(p.communication), 2) AS avg_communication,\n            ROUND(AVG(p.quality), 2) AS avg_quality,\n            ROUND(AVG(p.reliability), 2) AS avg_reliability,\n            ROUND(AVG((p.contribution + p.communication + p.quality + p.reliability) / 4.0), 2) AS avg_overall\n        FROM peer_evaluations p\n        JOIN students s ON s.id = p.evaluatee_id\n        WHERE p.team_id = ?\n        GROUP BY p.evaluatee_id, s.name\n        ORDER BY s.name ASC\n    ");
    if ($stmt) {
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $peerSummary[] = $row;
        }
        $stmt->close();
    }

    // Health score + heatmap (same model as health.php).
    $tasksDone = (int) ($kanbanCounts['Done'] ?? 0);
    $activityCount7d = 0;
    $heatMap = [];
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $heatMap[$day] = 0;
    }
    foreach ($activities as $activity) {
        $ts = strtotime((string) $activity['created_at']);
        if ($ts !== false) {
            if ($ts >= strtotime('-7 days')) {
                $activityCount7d++;
            }
            $dayKey = date('Y-m-d', $ts);
            if (isset($heatMap[$dayKey])) {
                $heatMap[$dayKey]++;
            }
        }
    }

    $tasksScore = min($tasksDone / 10.0, 1.0);
    $activityScore = min($activityCount7d / 20.0, 1.0);
    $deadlineFactor = 0.5;
    $healthScore = round((($tasksScore * 40) + ($activityScore * 30) + ($deadlineFactor * 30)));

    echo json_encode([
        'success' => true,
        'team' => $team,
        'summary' => [
            'member_count' => count($members),
            'file_count' => count($files),
            'task_count' => count($tasks),
            'standup_count' => count($standups),
            'activity_count' => count($activities),
            'kanban_counts' => $kanbanCounts
        ],
        'health' => [
            'score' => $healthScore,
            'tasks_done' => $tasksDone,
            'activity_7d' => $activityCount7d,
            'heatmap' => $heatMap
        ],
        'members' => array_values($members),
        'files' => $files,
        'tasks' => $tasks,
        'checklist' => $checklist,
        'signoffs' => $signoffs,
        'standups' => $standups,
        'activities' => $activities,
        'peer_summary' => $peerSummary
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
