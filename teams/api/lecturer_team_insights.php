<?php
// teams/api/lecturer_team_insights.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'technician'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_access.php';

$teamId = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
$lecturerId = (int) $_SESSION['user_id'];

if ($teamId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid team_id']);
    exit;
}

try {
    // Verify access to this team.
    if (!team_user_can_access_team($conn, $teamId, $lecturerId, $_SESSION['user_role'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied for this team']);
        exit;
    }

    $stmt = $conn->prepare("\n        SELECT\n            t.id,\n            t.title,\n            t.unit_id,\n            t.assessment_type,\n            t.status,\n            t.created_at,\n            u.name AS unit_name,\n            u.code AS unit_code\n        FROM teams t\n        JOIN units u ON u.id = t.unit_id\n        WHERE t.id = ?\n        LIMIT 1\n    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare team lookup: ' . $conn->error);
    }
    $stmt->bind_param('i', $teamId);
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
    $stmt = $conn->prepare("\n        SELECT\n            tf.id,\n            tf.original_name AS file_name,\n            tf.filepath AS file_path,\n            tf.mime_type,\n            tf.file_size,\n            tf.uploader_id,\n            tf.uploaded_at,\n            s.name AS uploader_name\n        FROM team_files tf\n        LEFT JOIN students s ON s.id = tf.uploader_id\n        WHERE tf.team_id = ?\n        ORDER BY tf.uploaded_at DESC, tf.id DESC\n        LIMIT 200\n    ");
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
    $stmt = $conn->prepare("\n        SELECT\n            t.id,\n            t.title,\n            t.description,\n            t.status,\n            t.due_date,\n            t.created_at,\n            t.updated_at,\n            t.assigned_to,\n            s.name AS assignee_name\n        FROM team_tasks t\n        LEFT JOIN students s ON s.id = t.assigned_to\n        WHERE t.team_id = ?\n        ORDER BY t.updated_at DESC, t.id DESC\n        LIMIT 300\n    ");
    if ($stmt) {
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['assigned_to'] = isset($row['assigned_to']) ? (int) $row['assigned_to'] : null;
            $tasks[] = $row;

            $statusKey = (string) ($row['status'] ?? 'Backlog');
            if (!isset($kanbanCounts[$statusKey])) {
                $kanbanCounts[$statusKey] = 0;
            }
            $kanbanCounts[$statusKey]++;

            if (isset($members[$row['assigned_to']])) {
                $members[$row['assigned_to']]['tasks_assigned']++;
            }
        }
        $stmt->close();
    }

    // Checklist + signoffs (only if tables exist).
    $checklistTableExists = false;
    $signoffsTableExists = false;
    try {
        $r = $conn->query("SHOW TABLES LIKE 'submission_checklist'");
        $checklistTableExists = $r && $r->num_rows > 0;
        $r2 = $conn->query("SHOW TABLES LIKE 'submission_signoffs'");
        $signoffsTableExists = $r2 && $r2->num_rows > 0;
    } catch (Exception $e) {
        // Tables don't exist - skip
    }

    if ($checklistTableExists) {
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
    }

    if ($signoffsTableExists) {
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
    }

    // Standups.
    $stmt = $conn->prepare("\n        SELECT su.id, su.user_id, su.yesterday, su.today, su.blockers, su.created_at, s.name AS user_name\n        FROM team_standups su\n        LEFT JOIN students s ON s.id = su.user_id\n        WHERE su.team_id = ?\n        ORDER BY su.created_at DESC\n        LIMIT 200\n    ");
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

    // Activity log (last 14 days).
    $stmt = $conn->prepare("\n        SELECT log.id, log.user_id, log.team_id, log.action_type, log.action_detail, log.created_at, s.name AS user_name\n        FROM team_activity_log log\n        LEFT JOIN students s ON s.id = log.user_id\n        WHERE log.team_id = ?\n          AND log.created_at >= NOW() - INTERVAL 14 DAY\n        ORDER BY log.created_at DESC\n        LIMIT 500\n    ");
    if ($stmt) {
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['user_id'] = (int) ($row['user_id'] ?? 0);
            $arr = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'action_type' => $row['action_type'],
                'action_detail' => $row['action_detail'],
                'created_at' => $row['created_at'],
                'user_name' => $row['user_name']
            ];
            $activities[] = $arr;
            if (isset($members[$row['user_id']])) {
                $members[$row['user_id']]['activity_count_14d']++;
                $members[$row['user_id']]['last_activity_at'] = $row['created_at'];
                $members[$row['user_id']]['activity_types'][$row['action_type']] =
                    ($members[$row['user_id']]['activity_types'][$row['action_type']] ?? 0) + 1;
            }
        }
        $stmt->close();
    }

    // Peer evaluation summary.
    $stmt = $conn->prepare("SELECT 1");
    if ($stmt) {
        if (collation_check($conn)) {
            $peerStmt = $conn->prepare("\n                SELECT\n                    evaluatee_id,\n                    COUNT(*) AS responses,\n                    AVG(contribution) AS avg_contribution,\n                    AVG(communication) AS avg_communication,\n                    AVG(quality) AS avg_quality,\n                    AVG(reliability) AS avg_reliability,\n                    AVG((contribution + communication + quality + reliability) / 4.0) AS avg_overall,\n                    MAX(s.student_name) AS evaluatee_name,\n                    MAX(s.reg_no) AS evaluatee_reg_no\n                FROM peer_evaluations pe\n                LEFT JOIN students s ON s.id = pe.evaluatee_id\n                WHERE pe.course_id = (\n                    SELECT t.unit_id FROM teams t WHERE t.id = ?\n                )\n                GROUP BY pe.evaluatee_id\n                ORDER BY avg_overall DESC\n            ");
            if ($peerStmt) {
                $peerStmt->bind_param('i', $teamId);
                $peerStmt->execute();
                $res = $peerStmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $peerSummary[] = $row;
                }
                $peerStmt->close();
            }
        }
        $stmt->close();
    }

    $memberList = array_values($members);

    // Health score.
    $healthScore = 0;
    $tasksDone = 0;
    $activity7d = 0;
    $heatmap = [];

    if (count($tasks) > 0) {
        foreach ($tasks as $tk) {
            if ($tk['status'] === 'Done') $tasksDone++;
        }
    }
    if (count($activities) > 0) {
        $now = time();
        foreach ($activities as $act) {
            $ts = strtotime($act['created_at']);
            if ($ts && $now - $ts <= 7 * 86400) $activity7d++;
            if ($ts) {
                $day = date('Y-m-d', $ts);
                $heatmap[$day] = ($heatmap[$day] ?? 0) + 1;
            }
        }
    }

    $totalTasks = max(1, count($tasks));
    $completionRatio = $tasksDone / $totalTasks;
    $activityRatio = min(1, $activity7d / 30);
    $healthScore = (int) round(($completionRatio * 40) + ($activityRatio * 60));

    echo json_encode([
        'success' => true,
        'team' => $team,
        'summary' => [
            'member_count' => count($memberList),
            'file_count' => count($files),
            'task_count' => count($tasks),
            'standup_count' => count($standups),
            'activity_count' => count($activities),
            'kanban_counts' => $kanbanCounts
        ],
        'members' => $memberList,
        'files' => $files,
        'tasks' => $tasks,
        'standups' => $standups,
        'activities' => $activities,
        'checklist' => $checklist,
        'signoffs' => $signoffs,
        'peer_summary' => $peerSummary,
        'health' => [
            'score' => $healthScore,
            'tasks_done' => $tasksDone,
            'activity_7d' => $activity7d,
            'heatmap' => $heatmap
        ]
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

function collation_check($conn) {
    // Check if peer_evaluations table exists
    try {
        $r = $conn->query("SHOW TABLES LIKE 'peer_evaluations'");
        return $r && $r->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}