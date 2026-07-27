<?php
// Excel export for all teams data (CSV format)
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

try {
    ensure_team_marks_table($conn);

    $lecturerId = $_SESSION['user_id'];

    // Fetch all teams for this lecturer with members and marks
    $teamsSql = "
        SELECT
            t.id AS team_id,
            t.title AS team_title,
            t.status,
            t.created_at AS team_created,
            t.assessment_type,
            t.description,
            u.name AS unit_name,
            u.code AS unit_code,
            COUNT(tm.student_id) AS member_count
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE lu.lecturer_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ";

    $stmt = $conn->prepare($teamsSql);
    $stmt->bind_param("i", $lecturerId);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Build the whole report in memory so a failure mid-way can still return
    // a readable error page instead of a half-written download.
    $buffer = fopen('php://temp', 'r+');

    // BOM so Excel reads UTF-8 correctly
    fwrite($buffer, "\xEF\xBB\xBF");

    fputcsv($buffer, ['All Teams Report - UniLIS']);
    fputcsv($buffer, ['Generated on', date('d M Y H:i')]);
    fputcsv($buffer, ['Lecturer', $_SESSION['user_name'] ?? 'Unknown']);
    fputcsv($buffer, ['Teams', count($teams)]);
    fputcsv($buffer, []);

    if (empty($teams)) {
        fputcsv($buffer, ['No teams found for this lecturer.']);
    }

    $membersStmt = $conn->prepare("
        SELECT
            tm.role,
            s.name AS student_name,
            s.reg_no,
            s.email,
            s.year_of_study
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
        ORDER BY tm.role DESC, s.name ASC
    ");

    $marksStmt = $conn->prepare("
        SELECT
            tm.mark,
            tm.max_mark,
            tm.mark_type,
            tm.component,
            tm.notes,
            tm.awarded_at,
            tm.student_id,
            s.name AS student_name
        FROM team_marks tm
        LEFT JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
        ORDER BY tm.awarded_at DESC
    ");

    foreach ($teams as $team) {
        // Team header
        fputcsv($buffer, ['Team', $team['team_title']]);
        fputcsv($buffer, ['Unit', $team['unit_name'] . ' (' . $team['unit_code'] . ')']);
        fputcsv($buffer, ['Type', $team['assessment_type'] ?: 'General']);
        fputcsv($buffer, ['Status', ucfirst((string)$team['status'])]);
        fputcsv($buffer, ['Created', date('d M Y', strtotime($team['team_created']))]);
        fputcsv($buffer, ['Members', $team['member_count']]);
        if ($team['description']) {
            fputcsv($buffer, ['Description', $team['description']]);
        }
        fputcsv($buffer, []);

        // Members
        $membersStmt->bind_param("i", $team['team_id']);
        $membersStmt->execute();
        $members = $membersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        fputcsv($buffer, ['Team Members']);
        fputcsv($buffer, ['Name', 'Role', 'Reg No', 'Email', 'Year']);
        foreach ($members as $member) {
            fputcsv($buffer, [
                $member['student_name'],
                team_role_label((string)$member['role']),
                $member['reg_no'],
                $member['email'],
                $member['year_of_study'] ?: 'N/A',
            ]);
        }
        if (empty($members)) {
            fputcsv($buffer, ['No members yet']);
        }
        fputcsv($buffer, []);

        // Marks
        $marksStmt->bind_param("i", $team['team_id']);
        $marksStmt->execute();
        $marks = $marksStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!empty($marks)) {
            fputcsv($buffer, ['Awarded Marks']);
            fputcsv($buffer, ['Component', 'Type', 'Student', 'Mark', 'Max Mark', 'Date', 'Notes']);
            foreach ($marks as $mark) {
                fputcsv($buffer, [
                    $mark['component'],
                    ucfirst((string)$mark['mark_type']),
                    $mark['student_name'] ?: 'Team',
                    number_format((float)$mark['mark'], 2),
                    number_format((float)$mark['max_mark'], 2),
                    date('d M Y', strtotime($mark['awarded_at'])),
                    $mark['notes'] ?: '',
                ]);
            }
            fputcsv($buffer, []);
        }

        fputcsv($buffer, []); // Space between teams
    }

    $membersStmt->close();
    $marksStmt->close();

    // Set headers for download
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="all_teams_report_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    rewind($buffer);
    fpassthru($buffer);
    fclose($buffer);

} catch (Throwable $e) {
    error_log("Excel Export Error: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo "Error generating Excel: " . $e->getMessage();
}
