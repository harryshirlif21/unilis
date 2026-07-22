<?php
// Excel export for team data (CSV format)
session_start();

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    die('Unauthorized access');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/ensure_team_marks.php';

function team_role_label(string $role): string
{
    $role = strtolower(trim($role));
    $labels = [
        'leader' => 'Team Lead',
        'member' => 'Member',
        'frontend_developer' => 'Frontend Developer',
        'backend_developer' => 'Backend Developer',
        'machine_learning' => 'Machine Learning',
        'ui_ux_designer' => 'UI/UX Designer',
        'data_analyst' => 'Data Analyst',
        'tester' => 'Tester',
        'researcher' => 'Researcher',
        'presenter' => 'Presenter',
        'other' => 'Other',
    ];

    return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

ensure_team_marks_table($conn);

try {
    $teamId = (int)($_GET['team_id'] ?? 0);
    $lecturerId = $_SESSION['user_id'];
    
    if ($teamId <= 0) {
        throw new Exception('Invalid team ID');
    }

    // Verify lecturer access
    $authSql = "
        SELECT 1
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE t.id = ? AND lu.lecturer_id = ?
        LIMIT 1
    ";
    $authStmt = $conn->prepare($authSql);
    $authStmt->bind_param("ii", $teamId, $lecturerId);
    $authStmt->execute();
    if ($authStmt->get_result()->num_rows === 0) {
        throw new Exception('You do not have access to this team');
    }
    $authStmt->close();

    // Get team data
    $teamSql = "
        SELECT 
            t.title AS team_title,
            t.status,
            t.created_at AS team_created,
            t.description,
            u.name AS unit_name,
            u.code AS unit_code
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        WHERE t.id = ?
    ";
    $teamStmt = $conn->prepare($teamSql);
    $teamStmt->bind_param("i", $teamId);
    $teamStmt->execute();
    $team = $teamStmt->get_result()->fetch_assoc();
    $teamStmt->close();

    // Get team members
    $membersSql = "
        SELECT 
            s.name AS student_name,
            s.reg_no,
            s.email,
            tm.role,
            tm.joined_at
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
        ORDER BY tm.role DESC, s.name ASC
    ";
    $membersStmt = $conn->prepare($membersSql);
    $membersStmt->bind_param("i", $teamId);
    $membersStmt->execute();
    $members = $membersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $membersStmt->close();

    // Get team files
    $filesSql = "
        SELECT 
            tf.original_name,
            tf.file_size,
            tf.mime_type,
            tf.uploaded_at,
            s.name AS uploader_name
        FROM team_files tf
        JOIN students s ON tf.uploader_id = s.id
        WHERE tf.team_id = ?
        ORDER BY tf.uploaded_at DESC
    ";
    $filesStmt = $conn->prepare($filesSql);
    $filesStmt->bind_param("i", $teamId);
    $filesStmt->execute();
    $files = $filesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $filesStmt->close();

    // Get marks
    $marksSql = "
        SELECT 
            tm.mark,
            tm.max_mark,
            tm.mark_type,
            tm.component,
            tm.notes,
            tm.awarded_at,
            tm.student_id,
            s.name AS student_name,
            l.name AS lecturer_name
        FROM team_marks tm
        LEFT JOIN students s ON tm.student_id = s.id
        LEFT JOIN lecturers l ON tm.awarded_by = l.id
        WHERE tm.team_id = ?
        ORDER BY tm.awarded_at DESC
    ";
    $marksStmt = $conn->prepare($marksSql);
    $marksStmt->bind_param("i", $teamId);
    $marksStmt->execute();
    $marks = $marksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $marksStmt->close();

    // Get activity log
    $activitySql = "
        SELECT 
            tal.action_type,
            tal.action_detail,
            tal.created_at,
            s.name AS student_name
        FROM team_activity_log tal
        LEFT JOIN students s ON tal.user_id = s.id
        WHERE tal.team_id = ?
        ORDER BY tal.created_at DESC
        LIMIT 50
    ";
    $activityStmt = $conn->prepare($activitySql);
    $activityStmt->bind_param("i", $teamId);
    $activityStmt->execute();
    $activities = $activityStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $activityStmt->close();

    // Generate filename
    $filename = 'team_' . $teamId . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $team['team_title']) . '_' . date('Y-m-d') . '.xlsx';

    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output CSV content (Excel can open CSV files)
    $output = fopen('php://output', 'w');

    // Add BOM for proper UTF-8 encoding in Excel
    fwrite($output, "\xEF\xBB\xBF");

    // Sheet 1: Team Overview
    fputcsv($output, ['Team Overview']);
    fputcsv($output, ['Team Name', $team['team_title']]);
    fputcsv($output, ['Unit', $team['unit_name'] . ' (' . $team['unit_code'] . ')']);
    fputcsv($output, ['Status', ucfirst($team['status'])]);
    fputcsv($output, ['Created', date('d M Y', strtotime($team['team_created']))]);
    if ($team['description']) {
        fputcsv($output, ['Description', $team['description']]);
    }
    fputcsv($output, []); // Empty row
    fputcsv($output, []); // Empty row

    // Sheet 2: Members
    fputcsv($output, ['Team Members']);
    fputcsv($output, ['Name', 'Registration No', 'Role', 'Email', 'Joined Date']);
    foreach ($members as $member) {
        fputcsv($output, [
            $member['student_name'],
            $member['reg_no'],
            team_role_label((string)$member['role']),
            $member['email'],
            date('d M Y', strtotime($member['joined_at']))
        ]);
    }
    fputcsv($output, []); // Empty row
    fputcsv($output, []); // Empty row

    // Sheet 3: Documents
    fputcsv($output, ['Uploaded Documents']);
    fputcsv($output, ['File Name', 'Uploader', 'Upload Date', 'File Size (KB)']);
    foreach ($files as $file) {
        fputcsv($output, [
            $file['original_name'],
            $file['uploader_name'],
            date('d M Y H:i', strtotime($file['uploaded_at'])),
            number_format($file['file_size'] / 1024, 1)
        ]);
    }
    fputcsv($output, []); // Empty row
    fputcsv($output, []); // Empty row

    // Sheet 4: Marks
    if (!empty($marks)) {
        fputcsv($output, ['Awarded Marks']);
        fputcsv($output, ['Component', 'Type', 'Student', 'Mark', 'Max Mark', 'Awarded By', 'Date', 'Notes']);
        foreach ($marks as $mark) {
            fputcsv($output, [
                $mark['component'],
                ucfirst($mark['mark_type']),
                $mark['student_name'] ?: 'Team',
                number_format($mark['mark'], 2),
                number_format($mark['max_mark'], 2),
                $mark['lecturer_name'],
                date('d M Y', strtotime($mark['awarded_at'])),
                $mark['notes'] ?: ''
            ]);
        }
        fputcsv($output, []); // Empty row
        fputcsv($output, []); // Empty row
    }

    // Sheet 5: Activity Log
    if (!empty($activities)) {
        fputcsv($output, ['Activity Log']);
        fputcsv($output, ['Date', 'Student', 'Action', 'Details']);
        foreach ($activities as $activity) {
            fputcsv($output, [
                date('d M Y H:i', strtotime($activity['created_at'])),
                $activity['student_name'] ?: 'System',
                $activity['action_type'],
                $activity['action_detail'] ?: ''
            ]);
        }
    }

    fclose($output);

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating Excel: ' . $e->getMessage();
}
?>
