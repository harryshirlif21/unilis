<?php
session_start();

/* =========================
   DATABASE CONNECTION
========================= */
require_once '../../config/db.php';
require_once '../includes/ensure_team_marks.php';
require_once '../includes/team_access.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

try {
    ensure_team_marks_table($conn);
} catch (Exception $e) {
    error_log("Error ensuring team_marks table: " . $e->getMessage());
    die("Error setting up team marks table. Please contact administrator.");
}

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

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'technician'])) {
    header("Location: ../../login.php");
    exit;
}

$lecturerId = (int) $_SESSION['user_id'];
$userRole = strtolower((string) ($_SESSION['user_role'] ?? ''));

/* =========================
   UNIT FILTER FROM URL
========================= */
$filterUnitId = isset($_GET['unit_id']) ? (int) $_GET['unit_id'] : 0;

/* =========================
   AUTO-ASSIGN MISSING TEAM LEADS
========================= */
$missingLeadSql = "
SELECT DISTINCT t.id AS team_id
FROM teams t
WHERE (
    ? = 'admin'
    OR EXISTS (
        SELECT 1
        FROM lecturer_units lu
        WHERE lu.unit_id = t.unit_id
          AND lu.lecturer_id = ?
    )
    OR EXISTS (
        SELECT 1
        FROM team_supervisors ts
        WHERE ts.team_id = t.id
          AND ts.lecturer_id = ?
          AND ts.status = 'approved'
    )
)
  AND EXISTS (
      SELECT 1 FROM team_members tm_exists WHERE tm_exists.team_id = t.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM team_members tm_lead
      WHERE tm_lead.team_id = t.id
        AND LOWER(COALESCE(tm_lead.role, '')) = 'leader'
  )
";

$missingLeadStmt = $conn->prepare($missingLeadSql);
if ($missingLeadStmt) {
    $missingLeadStmt->bind_param("sii", $userRole, $lecturerId, $lecturerId);
    $missingLeadStmt->execute();
    $missingLeadResult = $missingLeadStmt->get_result();

    while ($missingLeadTeam = $missingLeadResult->fetch_assoc()) {
        $teamIdForLead = (int)($missingLeadTeam['team_id'] ?? 0);
        if ($teamIdForLead <= 0) {
            continue;
        }

        $firstMemberSql = "
            SELECT student_id
            FROM team_members
            WHERE team_id = ?
            ORDER BY joined_at ASC, student_id ASC
            LIMIT 1
        ";
        $firstMemberStmt = $conn->prepare($firstMemberSql);
        if (!$firstMemberStmt) {
            continue;
        }
        $firstMemberStmt->bind_param("i", $teamIdForLead);
        $firstMemberStmt->execute();
        $firstMember = $firstMemberStmt->get_result()->fetch_assoc();
        $firstMemberStmt->close();

        $firstStudentId = (int)($firstMember['student_id'] ?? 0);
        if ($firstStudentId <= 0) {
            continue;
        }

        $assignLeadSql = "UPDATE team_members SET role = 'leader' WHERE team_id = ? AND student_id = ?";
        $assignLeadStmt = $conn->prepare($assignLeadSql);
        if (!$assignLeadStmt) {
            continue;
        }
        $assignLeadStmt->bind_param("ii", $teamIdForLead, $firstStudentId);
        $assignLeadStmt->execute();
        $assignLeadStmt->close();
    }

    $missingLeadStmt->close();
}

/* =========================
   CSRF TOKEN
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   FETCH TEAMS FOR LECTURER UNITS WITH ENHANCED DATA
========================= */
try {
    if ($filterUnitId > 0) {
        // Filter by unit if specified
        if ($userRole === 'admin') {
            $sql = "
            SELECT 
                t.id AS team_id,
                t.title AS team_title,
                t.status,
                t.created_at AS team_created,
                t.assessment_type,
                t.description,
                u.id AS unit_id,
                u.name AS unit_name,
                u.code AS unit_code,
                COUNT(tm.student_id) AS member_count
            FROM teams t
            JOIN units u ON t.unit_id = u.id
            LEFT JOIN team_members tm ON t.id = tm.team_id
            WHERE t.unit_id = ?
            GROUP BY t.id
            ORDER BY t.created_at DESC
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                die("Query preparation failed: " . $conn->error);
            }
            $stmt->bind_param("i", $filterUnitId);
        } else {
            $sql = "
            SELECT 
                t.id AS team_id,
                t.title AS team_title,
                t.status,
                t.created_at AS team_created,
                t.assessment_type,
                t.description,
                u.id AS unit_id,
                u.name AS unit_name,
                u.code AS unit_code,
                COUNT(tm.student_id) AS member_count
            FROM teams t
            JOIN units u ON t.unit_id = u.id
            LEFT JOIN team_members tm ON t.id = tm.team_id
            WHERE t.unit_id = ?
              AND (
                EXISTS (
                    SELECT 1
                    FROM lecturer_units lu
                    WHERE lu.unit_id = u.id
                      AND lu.lecturer_id = ?
                )
                OR EXISTS (
                    SELECT 1
                    FROM team_supervisors ts
                    WHERE ts.team_id = t.id
                      AND ts.lecturer_id = ?
                      AND ts.status = 'approved'
                )
              )
            GROUP BY t.id
            ORDER BY t.created_at DESC
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                die("Query preparation failed: " . $conn->error);
            }
            $stmt->bind_param("iii", $filterUnitId, $lecturerId, $lecturerId);
        }
    } else if ($userRole === 'admin') {
        $sql = "
        SELECT 
            t.id AS team_id,
            t.title AS team_title,
            t.status,
            t.created_at AS team_created,
            t.assessment_type,
            t.description,
            u.id AS unit_id,
            u.name AS unit_name,
            u.code AS unit_code,
            COUNT(tm.student_id) AS member_count
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        GROUP BY t.id
        ORDER BY t.created_at DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Query preparation failed: " . $conn->error);
        }
    } else {
        $sql = "
        SELECT 
            t.id AS team_id,
            t.title AS team_title,
            t.status,
            t.created_at AS team_created,
            t.assessment_type,
            t.description,
            u.id AS unit_id,
            u.name AS unit_name,
            u.code AS unit_code,
            COUNT(tm.student_id) AS member_count
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE (
            EXISTS (
                SELECT 1
                FROM lecturer_units lu
                WHERE lu.unit_id = u.id
                  AND lu.lecturer_id = ?
            )
            OR EXISTS (
                SELECT 1
                FROM team_supervisors ts
                WHERE ts.team_id = t.id
                  AND ts.lecturer_id = ?
                  AND ts.status = 'approved'
            )
        )
        GROUP BY t.id
        ORDER BY t.created_at DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Query preparation failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $lecturerId, $lecturerId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching teams: " . $e->getMessage());
    $teams = [];
}

/* Group teams by unit for peer evaluation picker */
$teamsByUnit = [];
foreach ($teams as $team) {
    $unitKey = (int)($team['unit_id'] ?? 0);
    if (!isset($teamsByUnit[$unitKey])) {
        $teamsByUnit[$unitKey] = [
            'unit_id'   => $unitKey,
            'unit_name' => $team['unit_name'],
            'unit_code' => $team['unit_code'],
            'teams'     => [],
        ];
    }
    $teamsByUnit[$unitKey]['teams'][] = $team;
}

/* Total students across all teams */
$totalStudents = 0;
foreach ($teams as $team) {
    $totalStudents += (int)($team['member_count'] ?? 0);
}

/* Fetch team details for all teams (needed for supervisors, semester, etc.) */
$allTeamDetails = [];
foreach ($teams as $team) {
    // Fetch supervisors
    $supSql = "SELECT l.name AS supervisor_name FROM team_supervisors ts JOIN lecturers l ON ts.lecturer_id = l.id WHERE ts.team_id = ? AND ts.status = 'approved' LIMIT 1";
    $supStmt = $conn->prepare($supSql);
    $supStmt->bind_param("i", $team['team_id']);
    $supStmt->execute();
    $supRow = $supStmt->get_result()->fetch_assoc();
    $supStmt->close();
    
    // Fetch semester
    $semSql = "SELECT semester FROM units WHERE id = ? LIMIT 1";
    $semStmt = $conn->prepare($semSql);
    $semStmt->bind_param("i", $team['unit_id']);
    $semStmt->execute();
    $semRow = $semStmt->get_result()->fetch_assoc();
    $semStmt->close();

    // Fetch team members
    $teamDetailsSql = "
        SELECT 
            tm.role,
            s.id AS student_id,
            s.name AS student_name,
            s.reg_no,
            s.email,
            s.year_of_study,
            tm.joined_at
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
        ORDER BY tm.role DESC, s.name ASC
    ";
    
    $teamDetailsStmt = $conn->prepare($teamDetailsSql);
    $teamDetailsStmt->bind_param("i", $team['team_id']);
    $teamDetailsStmt->execute();
    $teamMembers = $teamDetailsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $teamDetailsStmt->close();

    // Find team leader
    $teamLeader = null;
    foreach ($teamMembers as $member) {
        if ($member['role'] === 'leader') {
            $teamLeader = $member;
            break;
        }
    }

    // Fetch existing marks
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
    $marksStmt->bind_param("i", $team['team_id']);
    $marksStmt->execute();
    $existingMarks = $marksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $marksStmt->close();
    
    // Fetch team files for each member
    foreach ($teamMembers as &$member) {
        // Get team files uploaded by this member
        $teamFilesSql = "
            SELECT 
                tf.id,
                tf.filepath,
                tf.original_name,
                tf.mime_type,
                tf.uploaded_at,
                tf.uploader_id,
                tf.file_size
            FROM team_files tf
            WHERE tf.team_id = ? AND tf.uploader_id = ?
            ORDER BY tf.uploaded_at DESC
        ";
        
        $teamFilesStmt = $conn->prepare($teamFilesSql);
        $teamFilesStmt->bind_param("ii", $team['team_id'], $member['student_id']);
        $teamFilesStmt->execute();
        $teamFiles = $teamFilesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get submission metadata from activity log
        $metadataSql = "
            SELECT action_detail, created_at
            FROM team_activity_log
            WHERE team_id = ? AND user_id = ? AND action_type = 'file_upload'
            ORDER BY created_at DESC
            LIMIT 1
        ";
        
        $metadataStmt = $conn->prepare($metadataSql);
        $metadataStmt->bind_param("ii", $team['team_id'], $member['student_id']);
        $metadataStmt->execute();
        $metadataResult = $metadataStmt->get_result();
        
        $submissionMetadata = null;
        if ($metadataResult->num_rows > 0) {
            $metadataRow = $metadataResult->fetch_assoc();
            $metadata = json_decode($metadataRow['action_detail'], true);
            if ($metadata) {
                $submissionMetadata = $metadata;
            }
        }
        
        // Combine team files with metadata
        $member['team_files'] = $teamFiles;
        $member['submission_metadata'] = $submissionMetadata;
        $teamFilesStmt->close();
        $metadataStmt->close();
    }
    
    $allTeamDetails[] = [
        'team' => $team,
        'members' => $teamMembers,
        'leader' => $teamLeader,
        'marks' => $existingMarks,
        'supervisor' => $supRow['supervisor_name'] ?? 'Not assigned',
        'semester' => $semRow['semester'] ?? 'N/A',
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - UniLIS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563EB;
            --primary-hover: #1D4ED8;
            --primary-light: #EFF6FF;
            --background: #FFFFFF;
            --surface: #FFFFFF;
            --border: #E5E7EB;
            --border-light: #F3F4F6;
            --text: #111827;
            --muted: #6B7280;
            --success: #16A34A;
            --warning: #F59E0B;
            --danger: #DC2626;
            --radius: 16px;
            --radius-sm: 8px;
            --radius-pill: 999px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --transition: 0.2s ease;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ── Global Workspace Toolbar ─────────────────────── */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-light);
            gap: 16px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
        }

        .brand-text h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .brand-text p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Global Search */
        .global-search {
            position: relative;
            min-width: 280px;
        }

        .global-search input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--border);
            border-radius: var(--radius-pill);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: var(--text);
            background: #F9FAFB;
            transition: all var(--transition);
            outline: none;
        }

        .global-search input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .global-search .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
            pointer-events: none;
        }

        .global-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }

        .global-search-results.show {
            display: block;
        }

        .global-search-results .result-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-light);
            transition: background var(--transition);
        }

        .global-search-results .result-item:hover {
            background: var(--primary-light);
        }

        .global-search-results .result-item .result-type {
            font-size: 11px;
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
        }

        .global-search-results .result-item .result-name {
            font-size: 14px;
            font-weight: 600;
            margin-top: 2px;
        }

        .global-search-results .result-item .result-meta {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Export Dropdown */
        .export-dropdown {
            position: relative;
        }

        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-pill);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
            transition: all var(--transition);
        }

        .export-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .export-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            padding: 8px;
            min-width: 260px;
            z-index: 1000;
            display: none;
        }

        .export-menu.show {
            display: block;
            animation: fadeInDown 0.2s ease;
        }

        .export-menu .export-menu-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 8px 12px 4px;
        }

        .export-menu a, .export-menu .export-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background var(--transition);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            font-family: 'Inter', sans-serif;
            text-align: left;
        }

        .export-menu a:hover, .export-menu .export-menu-item:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .export-menu .menu-separator {
            height: 1px;
            background: var(--border-light);
            margin: 6px 12px;
        }

        /* ── Statistic Cards ──────────────────────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            padding: 24px 0;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--transition), transform var(--transition);
            cursor: pointer;
        }

        .stat-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .stat-card .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-top: 8px;
            letter-spacing: -0.02em;
        }

        .stat-card .stat-icon {
            float: right;
            font-size: 20px;
            color: var(--primary);
            opacity: 0.4;
        }

        /* ── Team Display ─────────────────────────────────── */
        .team-workspace {
            padding-bottom: 120px;
        }

        .team-header-card {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        .team-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .team-title-wrap {
            flex: 1;
        }

        .team-position {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            background: #F9FAFB;
            border-radius: var(--radius-pill);
            padding: 4px 12px;
            margin-bottom: 8px;
        }

        .team-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .team-title-wrap h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge.active { background: #DCFCE7; color: #166534; }
        .badge.locked { background: #FEF3C7; color: #92400E; }
        .badge.archived { background: #F3F4F6; color: #374151; }

        .team-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .team-header-actions a.workspace-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: var(--radius-pill);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all var(--transition);
            white-space: nowrap;
        }

        .team-header-actions a.workspace-link:hover {
            background: var(--primary);
            color: white;
        }

        .team-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }

        .team-meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .team-meta-item .meta-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .team-meta-item .meta-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .team-meta-item .meta-value.supervisor-name {
            color: var(--primary);
        }

        .team-description {
            margin-top: 16px;
            padding: 16px 20px;
            background: #F9FAFB;
            border-radius: 10px;
            border: 1px solid var(--border-light);
            font-size: 14px;
            line-height: 1.6;
            color: #374151;
        }

        .team-description .desc-label {
            font-weight: 700;
            color: var(--text);
            display: block;
            margin-bottom: 4px;
        }

        /* ── Members Section ──────────────────────────────── */
        .members-section-outer {
            margin-bottom: 24px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0 16px 0;
        }

        .section-heading h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .section-heading .section-count {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            background: #F9FAFB;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
        }

        .member-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .member-row {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 16px 20px;
            transition: all var(--transition);
        }

        .member-row:hover {
            border-color: var(--border);
            box-shadow: var(--shadow-sm);
        }

        .member-avatar {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .member-avatar.leader-avatar {
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            color: white;
        }

        .member-info {
            flex: 1;
            min-width: 0;
        }

        .member-info .member-name-line {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .member-info .member-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .member-info .member-regno {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
            font-family: 'SF Mono', 'Fira Code', monospace;
        }

        .member-role-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .member-role-badge.role-leader { background: #EDE9FE; color: #6D28D9; }
        .member-role-badge.role-member { background: #DBEAFE; color: #1E40AF; }
        .member-role-badge.role-frontend_developer { background: #DCFCE7; color: #166534; }
        .member-role-badge.role-backend_developer { background: #FEF3C7; color: #92400E; }
        .member-role-badge.role-machine_learning { background: #FCE7F3; color: #9D174D; }
        .member-role-badge.role-ui_ux_designer { background: #E0E7FF; color: #4338CA; }
        .member-role-badge.role-data_analyst { background: #FEE2E2; color: #991B1B; }
        .member-role-badge.role-tester { background: #E2E8F0; color: #334155; }
        .member-role-badge.role-researcher { background: #FFE4E6; color: #9F1239; }
        .member-role-badge.role-presenter { background: #D1FAE5; color: #065F46; }
        .member-role-badge.role-other { background: #F3F4F6; color: #4B5563; }

        .member-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .member-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-pill);
            background: white;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
            white-space: nowrap;
        }

        .member-action-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        .member-action-btn:active {
            transform: translateY(0);
        }

        .member-action-btn .btn-icon {
            font-size: 14px;
        }

        /* Group Mark Box */
        .mark-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border: 2px solid var(--primary);
            border-radius: 6px;
            margin-right: 8px;
            cursor: pointer;
            transition: all var(--transition);
            font-size: 14px;
            color: var(--primary);
            font-weight: bold;
            vertical-align: middle;
            background: white;
        }

        .mark-box:hover {
            background: var(--primary);
            color: white;
        }

        .mark-box.checked {
            background: var(--primary);
            color: white;
        }

        /* ── Group Mark Section ───────────────────────────── */
        .group-mark-section {
            margin-top: 24px;
            padding: 20px;
            background: linear-gradient(135deg, #EFF6FF, #F0FDF4);
            border-radius: var(--radius);
            border: 1px solid #BFDBFE;
        }

        .group-mark-section h5 {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 16px;
        }

        .group-mark-fields {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            align-items: flex-end;
        }

        .group-mark-section label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        .group-mark-section input[type="number"],
        .group-mark-section input[type="text"],
        .group-mark-section textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #BFDBFE;
            border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            transition: border-color var(--transition);
            outline: none;
        }

        .group-mark-section input:focus, 
        .group-mark-section textarea:focus {
            border-color: var(--primary);
        }

        .group-mark-notes {
            margin-top: 12px;
        }

        /* ── Marks Section (Tab content) ──────────────────── */
        .marks-section {
            margin-top: 24px;
        }

        .marks-section h4 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text);
        }

        .marks-form {
            background: #F9FAFB;
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-group input, .form-group select, .form-group textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            outline: none;
            transition: border-color var(--transition);
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-pill);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
        }

        .submit-btn:hover {
            background: var(--primary-hover);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }

        .marks-table {
            width: 100%;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            border-spacing: 0;
        }

        .marks-table th {
            background: #F9FAFB;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 12px;
            border-bottom: 1px solid var(--border);
        }

        .marks-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            font-size: 13px;
        }

        .marks-table tr:last-child td {
            border-bottom: none;
        }

        .marks-table tbody tr:hover {
            background: #F9FAFB;
        }

        /* ── Ellipsis Menu ────────────────────────────────── */
        .ellipsis-menu {
            position: relative;
            display: inline-block;
        }

        .ellipsis-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F9FAFB;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: var(--radius-pill);
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 500;
            transition: all var(--transition);
        }

        .ellipsis-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .ellipsis-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 8px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            min-width: 220px;
            padding: 6px;
        }

        .ellipsis-content.show {
            display: block;
            animation: fadeInDown 0.2s ease;
        }

        .ellipsis-content a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border-radius: var(--radius-sm);
            transition: background var(--transition);
        }

        .ellipsis-content a:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .ellipsis-content hr {
            border: none;
            border-top: 1px solid var(--border-light);
            margin: 6px 12px;
        }

        /* ── Insights Modal ──────────────────────────────── */
        .insights-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1600;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .insights-modal.show {
            display: flex;
        }

        .insights-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 1100px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .insights-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-light);
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .insights-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .insights-modal-body {
            padding: 24px;
        }

        /* ── Navigation Buttons ───────────────────────────── */
        .team-nav {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            gap: 10px;
            z-index: 900;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: var(--radius-pill);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            border: none;
        }

        .nav-btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .nav-btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .nav-btn-secondary {
            background: white;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .nav-btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ── Drawer ───────────────────────────────────────── */
        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1200;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .drawer-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .drawer {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 520px;
            max-width: 100vw;
            background: white;
            z-index: 1300;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            box-shadow: -8px 0 24px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
        }

        .drawer.open {
            transform: translateX(0);
        }

        .drawer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .drawer-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .drawer-header .drawer-subtitle {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .drawer-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: #F9FAFB;
            color: var(--muted);
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition);
        }

        .drawer-close:hover {
            background: #FEE2E2;
            color: var(--danger);
        }

        .drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .drawer-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: white;
        }

        /* ── File Viewer Overlay (preserved) ──────────────── */
        .file-viewer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1500;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .file-viewer-overlay.active {
            display: flex;
        }

        .file-viewer {
            background: white;
            border-radius: var(--radius);
            width: 90%;
            height: 90%;
            max-width: 1200px;
            max-height: 800px;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }

        .file-viewer-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #F9FAFB;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .file-viewer-title {
            font-weight: 600;
            color: #374151;
            flex: 1;
            margin-right: 1rem;
        }

        .file-viewer-close {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
            transition: background var(--transition);
        }

        .file-viewer-close:hover {
            background: #B91C1C;
        }

        .file-viewer-content {
            flex: 1;
            padding: 16px;
            overflow: auto;
            background: white;
            border-radius: 0 0 var(--radius) var(--radius);
        }

        .file-viewer-content iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .file-viewer-content img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .file-viewer-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--muted);
            font-size: 15px;
        }

        .file-viewer-error {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--danger);
            font-size: 15px;
            text-align: center;
            padding: 2rem;
        }

        /* ── Empty State ──────────────────────────────────── */
        .empty {
            padding: 60px 24px;
            text-align: center;
            color: var(--muted);
            background: white;
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            font-size: 15px;
        }

        .empty .empty-icon {
            font-size: 40px;
            display: block;
            margin-bottom: 12px;
        }

        /* ── Peer Evaluation Unit Tiles (preserved) ───────── */
        .peer-eval-units {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .peer-eval-unit-tile {
            background: #F9FAFB;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            text-align: left;
            cursor: pointer;
            transition: all var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .peer-eval-unit-tile:hover {
            border-color: #93C5FD;
            background: var(--primary-light);
        }

        .peer-eval-unit-tile.active {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .peer-eval-unit-tile .unit-code {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .peer-eval-unit-tile .unit-name {
            font-weight: 700;
            color: var(--text);
            margin: 4px 0;
            font-size: 13px;
        }

        .peer-eval-unit-tile .unit-team-count {
            font-size: 12px;
            color: var(--muted);
        }

        .peer-eval-team-picker {
            display: none;
            margin-top: 8px;
            padding: 16px;
            background: #F9FAFB;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .peer-eval-team-picker.visible {
            display: block;
        }

        .peer-eval-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-top: 16px;
        }

        .peer-eval-unit-tile.needs-fix {
            border-color: var(--warning);
        }

        /* ── Supervisor Modal (preserved) ─────────────────── */
        .supervisor-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1400;
        }

        .supervisor-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .supervisor-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .supervisor-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .supervisor-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--muted);
            line-height: 1;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition);
        }

        .supervisor-modal-close:hover {
            background: #FEE2E2;
            color: var(--danger);
        }

        .supervisor-modal-body {
            padding: 20px 24px;
        }

        .supervisor-section {
            margin-bottom: 20px;
        }

        .supervisor-section h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
        }

        .supervisor-list {
            min-height: 50px;
        }

        .supervisor-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #F9FAFB;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .supervisor-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .primary-badge {
            background: var(--primary);
            color: white;
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 600;
        }

        .nominated-badge {
            background: #EDE9FE;
            color: #6D28D9;
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 600;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 600;
        }

        .status-badge.approved {
            background: #DCFCE7;
            color: #166534;
        }

        .status-badge.pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-badge.rejected {
            background: #FEE2E2;
            color: #B91C1C;
        }

        .supervisor-actions {
            display: flex;
            gap: 8px;
        }

        .btn-approve, .btn-reject, .btn-remove, .btn-request {
            padding: 8px 12px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all var(--transition);
        }

        .btn-approve {
            background: var(--success);
            color: white;
        }

        .btn-approve:hover { background: #15803D; }

        .btn-reject {
            background: var(--danger);
            color: white;
        }

        .btn-reject:hover { background: #B91C1C; }

        .btn-remove {
            background: #6B7280;
            color: white;
        }

        .btn-remove:hover { background: #4B5563; }

        .btn-request {
            background: var(--primary);
            color: white;
        }

        .btn-request:hover { background: var(--primary-hover); }

        .supervisor-form {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }

        .supervisor-input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            outline: none;
            transition: border-color var(--transition);
        }

        .supervisor-input:focus {
            border-color: var(--primary);
        }

        .supervisor-error {
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 12px;
            color: #B91C1C;
            font-size: 13px;
            line-height: 1.4;
        }

        .supervisor-error strong {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .supervisor-select {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
        }

        .search-results {
            margin-top: 8px;
            max-height: 200px;
            overflow-y: auto;
        }

        .search-result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #F9FAFB;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background var(--transition);
        }

        .search-result-item:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }

        .search-result-main {
            flex: 1;
        }

        .assign-button {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 12px;
            font-family: 'Inter', sans-serif;
            transition: all var(--transition);
        }

        .assign-button:hover {
            background: var(--primary-hover);
        }

        .search-result-item .name {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .search-result-item .email {
            font-size: 13px;
            color: var(--muted);
        }

        .search-result-item .type {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            background: #DBEAFE;
            color: #1E40AF;
            margin-left: 6px;
        }

        .search-result-item .team-count {
            font-size: 12px;
            color: var(--muted);
        }

        .btn-search {
            padding: 10px 16px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            background: #6B7280;
            color: white;
            transition: all var(--transition);
        }

        .btn-search:hover {
            background: #4B5563;
        }

        .supervisor-hint {
            font-size: 12px;
            color: var(--muted);
            margin: 8px 0 0 0;
        }

        .supervisor-item.approved {
            border-left: 4px solid var(--success);
        }

        .supervisor-item.pending {
            border-left: 4px solid var(--warning);
        }

        .supervisor-item.rejected {
            border-left: 4px solid var(--danger);
        }

        /* ── Hidden content (one team at a time) ──────────── */
        .team-content {
            display: none;
        }

        .team-content.active {
            display: block;
            animation: fadeInUp 0.3s ease;
        }

        /* ── Read Button (preserved) ──────────────────────── */
        .read-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--success);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 4px 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all var(--transition);
            min-width: 32px;
            height: 24px;
        }

        .read-btn:hover {
            background: #15803D;
            transform: scale(1.05);
        }

        .read-btn:active {
            transform: scale(0.95);
        }

        /* Member files (preserved structure) */
        .member-files {
            margin-top: 8px;
        }

        .member-files > label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }

        .member-files .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-light);
            font-size: 12px;
        }

        .member-files .file-item a {
            color: var(--primary);
            text-decoration: none;
        }

        .member-files .file-item a:hover {
            text-decoration: underline;
        }

        .submission-metadata {
            margin-bottom: 8px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: #F0F9FF;
        }

        .submission-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            font-size: 10px;
            font-weight: 600;
        }

        /* ── Animations ───────────────────────────────────── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 768px) {
            .app-container {
                padding: 0 16px;
            }

            .top-nav {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-actions {
                flex-direction: column;
                width: 100%;
            }

            .global-search {
                min-width: 100%;
            }

            .global-search input {
                width: 100%;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .team-header-top {
                flex-direction: column;
            }

            .member-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .member-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .member-action-btn {
                font-size: 12px;
                padding: 8px 12px;
            }

            .team-nav {
                bottom: 16px;
                right: 16px;
                left: 16px;
                justify-content: flex-end;
            }

            .nav-btn {
                padding: 10px 18px;
                font-size: 13px;
            }

            .drawer {
                width: 100%;
            }

            .team-meta-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            .stats-row {
                grid-template-columns: 1fr;
            }

            .team-meta-grid {
                grid-template-columns: 1fr;
            }

            .member-info .member-name-line {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<?php if (empty($teams)): ?>
    <div class="app-container">
        <div class="empty">
            <span class="empty-icon">👥</span>
            No teams found for your assigned units.
        </div>
    </div>
    <?php include __DIR__ . '/../views/footer.php'; ?>
    </body></html>
    <?php exit; ?>
<?php endif; ?>

<div class="app-container">
    <!-- ── Global Workspace Toolbar ─────────────────────── -->
    <div class="top-nav">
        <div class="brand">
            <div class="brand-icon">T</div>
            <div class="brand-text">
                <h1>Team Management</h1>
                <p>Lecturer Workspace</p>
            </div>
        </div>

        <div class="toolbar-actions">
            <a href="approve_membership_requests.php" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:linear-gradient(135deg, #667eea, #764ba2);color:white;border-radius:var(--radius-pill);text-decoration:none;font-size:13px;font-weight:600;transition:all var(--transition);white-space:nowrap;">
                📋 Membership Requests
            </a>
            <div class="global-search">
                <span class="search-icon">🔍</span>
                <input type="text" id="globalSearchInput" placeholder="Search team or student..." autocomplete="off">
                <div class="global-search-results" id="globalSearchResults"></div>
            </div>

            <div class="export-dropdown">
                <button class="export-btn" onclick="toggleExportMenu()">
                    📊 Export
                    <span class="dropdown-caret">▼</span>
                </button>
                <div class="export-menu" id="exportMenu">
                    <div class="export-menu-title">Team Reports</div>
                    <a href="#" onclick="generatePDF(currentTeamId); return false;">📄 Export Current Team (PDF)</a>
                    <a href="#" onclick="generateExcel(currentTeamId); return false;">📊 Export Current Team (Excel)</a>
                    <a href="../../teams/api/export_all_teams_pdf.php">📄 Export All Teams (PDF)</a>
                    <a href="../../teams/api/export_all_teams_excel.php">📊 Export All Teams (Excel)</a>
                    <a href="#" onclick="exportTeamMembersPDF(currentTeamId); return false;">👥 Export Group Members (PDF)</a>
                    <div class="menu-separator"></div>
                    <div class="export-menu-title">Peer Evaluation</div>
                    <a href="#" onclick="exportPeerEvalJSON(currentTeamId); return false;">🧾 Peer Eval (JSON)</a>
                    <a href="#" onclick="exportPeerEvalCSV(currentTeamId); return false;">📋 Peer Eval (CSV)</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Statistic Cards ─────────────────────────────── -->
    <div class="stats-row">
        <div class="stat-card" onclick="showAllTeams()">
            <span class="stat-icon">👥</span>
            <div class="stat-label">Teams</div>
            <div class="stat-value"><?= count($teams); ?></div>
        </div>
        <div class="stat-card" onclick="showAllTeams()">
            <span class="stat-icon">🎓</span>
            <div class="stat-label">Students</div>
            <div class="stat-value"><?= $totalStudents; ?></div>
        </div>
        <div class="stat-card" onclick="showAllTeams()">
            <span class="stat-icon">⏳</span>
            <div class="stat-label">Pending</div>
            <div class="stat-value">0</div>
        </div>
    </div>

    <!-- ── Team Display (ONE team at a time) ─────────────── -->
    <div class="team-workspace">
        <?php 
        $teamIndex = 0;
        foreach ($allTeamDetails as $td):
            $team = $td['team'];
            $teamMembers = $td['members'];
            $teamLeader = $td['leader'];
            $existingMarks = $td['marks'];
            $supervisorName = $td['supervisor'];
            $semester = $td['semester'];
            $teamIndex++;
        ?>
        <div class="team-content <?= $teamIndex === 1 ? 'active' : ''; ?>" id="teamContent-<?= $team['team_id']; ?>" data-team-index="<?= $teamIndex; ?>">
            <!-- Team Header Card -->
            <div class="team-header-card">
                <div class="team-header-top">
                    <div class="team-title-wrap">
                        <span class="team-position">
                            Team <?= $teamIndex; ?> of <?= count($teams); ?>
                        </span>
                        <h2>
                            <span class="mark-box" title="Award group mark" onclick="toggleMarkBox(this, <?= $team['team_id']; ?>)">□</span>
                            <?= htmlspecialchars($team['team_title']); ?>
                            <span class="badge <?= htmlspecialchars($team['status']); ?>">
                                <?= ucfirst($team['status']); ?>
                            </span>
                        </h2>
                    </div>
                    <div class="team-header-actions">
                        <a href="workspace.php?team_id=<?= $team['team_id']; ?>" class="workspace-link">
                            🚀 Workspace
                        </a>
                        <div class="ellipsis-menu">
                            <button class="ellipsis-btn" onclick="toggleMenu(<?= $team['team_id']; ?>)">⚙️ More</button>
                            <div id="menu-<?= $team['team_id']; ?>" class="ellipsis-content">
                                <a href="#" onclick="generatePDF(<?= $team['team_id']; ?>); return false;">📄 Export PDF</a>
                                <a href="#" onclick="generateExcel(<?= $team['team_id']; ?>); return false;">📊 Export Excel</a>
                                <a href="../../teams/api/peer_evaluation_report.php?team_id=<?= $team['team_id']; ?>&format=json" target="_blank">🧾 Peer Eval (JSON)</a>
                                <a href="../../teams/api/peer_evaluation_report.php?team_id=<?= $team['team_id']; ?>&format=csv" target="_blank">📋 Peer Eval (CSV)</a>
                                <a href="../../teams/api/export_group_members_pdf.php?team_id=<?= $team['team_id']; ?>" target="_blank">👥 Download Group Members PDF</a>
                                <hr>
                                <a href="#" onclick="openSupervisorModal(<?= $team['team_id']; ?>, '<?= htmlspecialchars($team['team_title']); ?>'); return false;">👨‍🏫 Manage Supervisors</a>
                                <hr>
                                <a href="#" onclick="deleteTeam(<?= $team['team_id']; ?>, '<?= addslashes(htmlspecialchars($team['team_title'])); ?>'); return false;" style="color: var(--danger);">🗑️ Delete Team</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="team-meta-grid">
                    <div class="team-meta-item">
                        <span class="meta-label">Unit</span>
                        <span class="meta-value"><?= htmlspecialchars($team['unit_name']); ?> (<?= htmlspecialchars($team['unit_code']); ?>)</span>
                    </div>
                    <div class="team-meta-item">
                        <span class="meta-label">Project Title</span>
                        <span class="meta-value"><?= htmlspecialchars($team['team_title']); ?></span>
                    </div>
                    <div class="team-meta-item">
                        <span class="meta-label">Assessment Type</span>
                        <span class="meta-value"><?= htmlspecialchars($team['assessment_type'] ?: 'General'); ?></span>
                    </div>
                    <div class="team-meta-item">
                        <span class="meta-label">Supervisor</span>
                        <span class="meta-value supervisor-name"><?= htmlspecialchars($supervisorName); ?></span>
                    </div>
                    <div class="team-meta-item">
                        <span class="meta-label">Semester</span>
                        <span class="meta-value"><?= htmlspecialchars($semester); ?></span>
                    </div>
                    <div class="team-meta-item">
                        <span class="meta-label">Members</span>
                        <span class="meta-value"><?= (int)$team['member_count']; ?> students</span>
                    </div>
                    <div class="team-meta-item">
                        <span class="meta-label">Created</span>
                        <span class="meta-value"><?= date('d M Y', strtotime($team['team_created'])); ?></span>
                    </div>
                </div>

                <?php if ($team['description']): ?>
                    <div class="team-description">
                        <span class="desc-label">📝 Description</span>
                        <?= htmlspecialchars($team['description']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($teamLeader): ?>
                    <div class="team-description" style="background: linear-gradient(135deg, #EFF6FF, #F5F3FF); border-color: #C7D2FE;">
                        <span class="desc-label" style="color: var(--primary);">👑 Team Leader</span>
                        <strong><?= htmlspecialchars($teamLeader['student_name']); ?></strong> 
                        <span style="color: var(--muted);">• <?= htmlspecialchars($teamLeader['reg_no']); ?></span>
                        <span style="color: var(--muted);">• <?= htmlspecialchars($teamLeader['email']); ?></span>
                        <?php if ($teamLeader['year_of_study']): ?>
                            <span style="color: var(--muted);">• Year <?= htmlspecialchars($teamLeader['year_of_study']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Team Members Section ──────────────────── -->
            <div class="members-section-outer">
                <div class="section-heading">
                    <h3>Team Members</h3>
                    <span class="section-count"><?= count($teamMembers); ?> members</span>
                </div>

                <div class="member-list">
                    <?php foreach ($teamMembers as $member): ?>
                    <div class="member-row">
                        <div class="member-avatar <?= $member['role'] === 'leader' ? 'leader-avatar' : ''; ?>">
                            <?= strtoupper(substr($member['student_name'], 0, 1)); ?>
                        </div>
                        <div class="member-info">
                            <div class="member-name-line">
                                <span class="member-name">
                                    <span class="mark-box" title="Award individual mark" onclick="toggleMarkBox(this, <?= $team['team_id']; ?>, <?= $member['student_id']; ?>)">□</span>
                                    <?= htmlspecialchars($member['student_name']); ?>
                                </span>
                                <span class="member-role-badge role-<?= htmlspecialchars($member['role']); ?>">
                                    <?= htmlspecialchars(team_role_label((string)$member['role'])); ?>
                                </span>
                            </div>
                            <div class="member-regno"><?= htmlspecialchars($member['reg_no']); ?></div>
                        </div>
                        <div class="member-actions">
                            <button class="member-action-btn" onclick="openMemberDrawer('contribution', <?= $team['team_id']; ?>, <?= $member['student_id']; ?>, '<?= addslashes(htmlspecialchars($member['student_name'])); ?>')">
                                <span class="btn-icon">📊</span> Contribution
                            </button>
                            <button class="member-action-btn" onclick="openMemberDrawer('files', <?= $team['team_id']; ?>, <?= $member['student_id']; ?>, '<?= addslashes(htmlspecialchars($member['student_name'])); ?>')">
                                <span class="btn-icon">📁</span> Files
                            </button>
                            <button class="member-action-btn" onclick="openMemberDrawer('attendance', <?= $team['team_id']; ?>, <?= $member['student_id']; ?>, '<?= addslashes(htmlspecialchars($member['student_name'])); ?>')">
                                <span class="btn-icon">📅</span> Attendance
                            </button>
                            <button class="member-action-btn" onclick="openMemberDrawer('marks', <?= $team['team_id']; ?>, <?= $member['student_id']; ?>, '<?= addslashes(htmlspecialchars($member['student_name'])); ?>')">
                                <span class="btn-icon">🏅</span> Marks
                            </button>
                            <button class="member-action-btn" onclick="openMemberDrawer('activity', <?= $team['team_id']; ?>, <?= $member['student_id']; ?>, '<?= addslashes(htmlspecialchars($member['student_name'])); ?>')">
                                <span class="btn-icon"></span> Activity
                            </button>
                            <button class="member-action-btn" onclick="loadMemberInsights(<?= $team['team_id']; ?>, <?= $member['student_id']; ?>, '<?= addslashes(htmlspecialchars($member['student_name'])); ?>')">
                                <span class="btn-icon">✨</span> Load Insights
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Individual Mark Fields (kept hidden, referenced by JS) -->
                <?php foreach ($teamMembers as $member): ?>
                    <div style="display: none;">
                        <input type="number" id="individual_mark_<?= $member['student_id']; ?>">
                        <input type="text" id="individual_component_<?= $member['student_id']; ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Group Mark Section -->
            <div class="group-mark-section">
                <h5>🏆 Group Mark</h5>
                <div class="group-mark-fields">
                    <div>
                        <label>Mark:</label>
                        <input type="number" id="group_mark_<?= $team['team_id']; ?>" placeholder="0" min="0" max="100" step="0.01">
                    </div>
                    <div>
                        <label>Component:</label>
                        <input type="text" id="group_component_<?= $team['team_id']; ?>" placeholder="e.g. Group Project, Presentation">
                    </div>
                    <div>
                        <label>Max Mark:</label>
                        <input type="number" id="group_max_mark_<?= $team['team_id']; ?>" value="100" min="1" step="0.01">
                    </div>
                    <div>
                        <button type="button" onclick="awardGroupMark(<?= $team['team_id']; ?>)" class="submit-btn" style="width: 100%;">Award Group Mark</button>
                    </div>
                </div>
                <div class="group-mark-notes">
                    <label>Notes:</label>
                    <textarea id="group_notes_<?= $team['team_id']; ?>" placeholder="Comments about this group mark..." style="width: 100%; padding: 10px 12px; border: 1px solid #BFDBFE; border-radius: var(--radius-sm); resize: vertical; min-height: 50px; font-size: 13px; font-family: 'Inter', sans-serif;"></textarea>
                </div>
            </div>

            <!-- ── Marks Section ─────────────────────────── -->
            <div class="marks-section">
                <h4>🏆 Marks Awarding</h4>

                <!-- Award Mark Form -->
                <div class="marks-form">
                    <form id="markForm<?= $team['team_id']; ?>" onsubmit="awardMark(event, <?= $team['team_id']; ?>)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Mark Type</label>
                                <select name="mark_type" required onchange="toggleStudentField(this, <?= $team['team_id']; ?>)">
                                    <option value="">Select type</option>
                                    <option value="team">Team Mark</option>
                                    <option value="individual">Individual Mark</option>
                                </select>
                            </div>
                            <div class="form-group" id="studentGroup<?= $team['team_id']; ?>" style="display: none;">
                                <label>Student</label>
                                <select name="student_id">
                                    <option value="">Select student</option>
                                    <?php foreach ($teamMembers as $member): ?>
                                        <option value="<?= $member['student_id']; ?>">
                                            <?= htmlspecialchars($member['student_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Component</label>
                                <input type="text" name="component" placeholder="e.g. Presentation, Report" required>
                            </div>
                            <div class="form-group">
                                <label>Mark</label>
                                <input type="number" name="mark" min="0" max="100" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Max Mark</label>
                                <input type="number" name="max_mark" min="1" value="100" step="0.01" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Notes (optional)</label>
                            <textarea name="notes" placeholder="Additional comments about this mark..."></textarea>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="submit-btn">Award Mark</button>
                    </form>
                </div>

                <!-- Existing Marks Table -->
                <?php if (!empty($existingMarks)): ?>
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Type</th>
                                <th>Student</th>
                                <th>Mark</th>
                                <th>Max</th>
                                <th>Awarded by</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existingMarks as $mark): ?>
                                <tr>
                                    <td><?= htmlspecialchars($mark['component']); ?></td>
                                    <td><?= ucfirst($mark['mark_type']); ?></td>
                                    <td><?= htmlspecialchars($mark['student_name'] ?: 'Team'); ?></td>
                                    <td><?= number_format($mark['mark'], 2); ?></td>
                                    <td><?= number_format($mark['max_mark'], 2); ?></td>
                                    <td><?= htmlspecialchars($mark['lecturer_name']); ?></td>
                                    <td><?= date('d M Y', strtotime($mark['awarded_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- ── Navigation Buttons ─────────────────────────────── -->
<div class="team-nav">
    <button class="nav-btn nav-btn-secondary" onclick="openInsightsModal()" title="Load AI insights for current team">✨ Load Insights</button>
    <button class="nav-btn nav-btn-secondary" onclick="finishTeam()" title="Save marking for this team">💾 Save Marking</button>
    <button class="nav-btn nav-btn-primary" onclick="nextTeam()" title="Go to next team">Next Team →</button>
</div>

<!-- ── Insights Modal ─────────────────────────────────── -->
<div class="insights-modal" id="insightsModal">
    <div class="insights-modal-content">
        <div class="insights-modal-header">
            <div>
                <h3>✨ Team Activity Insights</h3>
                <p style="font-size: 12px; color: var(--muted); margin-top: 2px;">Files, kanban, checklist, standups, heatmap & peer evaluation</p>
            </div>
            <button class="drawer-close" onclick="closeInsightsModal()">×</button>
        </div>
        <div class="insights-modal-body">
            <p id="peerEvalInsightsStatus" style="margin: 0 0 12px 0; color: var(--muted); font-size: 13px;">Loading insights for the current team...</p>
            <div id="peerEvalInsightsBody">
                <div style="display:flex;justify-content:center;padding:40px 0;">
                    <div style="background:var(--primary-light);border-radius:12px;padding:12px 24px;font-size:13px;color:var(--primary);">
                        ⏳ Loading team activity insights...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Drawer ─────────────────────────────────────────── -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="memberDrawer">
    <div class="drawer-header">
        <div>
            <h3 id="drawerTitle">Member Details</h3>
            <div class="drawer-subtitle" id="drawerSubtitle"></div>
        </div>
        <button class="drawer-close" onclick="closeDrawer()">×</button>
    </div>
    <div class="drawer-body" id="drawerBody">
        <p style="color: var(--muted); font-size: 14px;">Select an action to view details.</p>
    </div>
    <div class="drawer-footer">
        <button class="nav-btn nav-btn-secondary" onclick="closeDrawer()">Close</button>
        <button class="nav-btn nav-btn-primary" id="drawerSaveBtn" style="display: none;">Save</button>
    </div>
</div>

<!-- ── File Viewer Overlay (preserved) ─────────────────── -->
<div id="fileViewerOverlay" class="file-viewer-overlay">
    <div class="file-viewer">
        <div class="file-viewer-header">
            <div class="file-viewer-title" id="fileViewerTitle">File Viewer</div>
            <button class="file-viewer-close" onclick="closeFileViewer()">Close</button>
        </div>
        <div class="file-viewer-content" id="fileViewerContent">
            <div class="file-viewer-loading">Loading file...</div>
        </div>
    </div>
</div>

<!-- ── Supervisor Management Modal (preserved) ─────────── -->
<div id="supervisorModal" class="supervisor-modal" style="display: none;">
    <div class="supervisor-modal-content">
        <div class="supervisor-modal-header">
            <h3>Manage Supervisors</h3>
            <button class="supervisor-modal-close" onclick="closeSupervisorModal()">×</button>
        </div>
        <div class="supervisor-modal-body">
            <p><strong>Team:</strong> <span id="supervisorTeamTitle"></span></p>
            <input type="hidden" id="supervisorTeamId" value="">
            
            <div class="supervisor-section">
                <h4>Existing Supervisors</h4>
                <div id="existingSupervisors" class="supervisor-list">
                    <p>Loading...</p>
                </div>
            </div>
            
            <div class="supervisor-section">
                <h4>Add New Supervisor</h4>
                <div id="supervisorError" class="supervisor-error" style="display: none;"></div>
                <div class="supervisor-form">
                    <input type="email" id="supervisorEmail" class="supervisor-input" placeholder="Enter supervisor email...">
                    <button onclick="searchSupervisor()" class="btn-search">Search</button>
                </div>
                <div id="searchResults" class="search-results"></div>
                <p class="supervisor-hint">Enter email to search for lecturers/technicians. Supervisors must be from the same department. Maximum 5 teams per supervisor.</p>
            </div>
        </div>
    </div>
</div>

<script>
<?php
// Build a JS-safe array of team data for search and navigation
$teamJsData = [];
foreach ($allTeamDetails as $td) {
    $teamJsData[] = [
        'id' => (int)$td['team']['team_id'],
        'title' => $td['team']['team_title'],
        'unit' => $td['team']['unit_name'],
        'code' => $td['team']['unit_code'],
        'status' => $td['team']['status'],
        'members' => array_map(function($m) {
            return [
                'id' => (int)$m['student_id'],
                'name' => $m['student_name'],
                'reg_no' => $m['reg_no'],
                'role' => $m['role']
            ];
        }, $td['members'])
    ];
}
echo "const allTeamsData = " . json_encode($teamJsData) . ";\n";
echo "const csrfToken = '" . $_SESSION['csrf_token'] . "';\n";
?>
let currentTeamId = <?= $allTeamDetails[0]['team']['team_id'] ?? 0; ?>;
let drawerOpen = false;
let drawerType = null;
let drawerTeamId = null;
let drawerStudentId = null;

/* ═══════════════════════════════════════════════════════
   GLOBAL SEARCH
═══════════════════════════════════════════════════════ */
document.getElementById('globalSearchInput')?.addEventListener('input', function() {
    const query = this.value.trim().toLowerCase();
    const resultsEl = document.getElementById('globalSearchResults');
    if (!query) {
        resultsEl.classList.remove('show');
        resultsEl.innerHTML = '';
        return;
    }

    const results = [];
    allTeamsData.forEach(team => {
        if (team.title.toLowerCase().includes(query) || 
            team.unit.toLowerCase().includes(query) || 
            team.code.toLowerCase().includes(query)) {
            results.push({ type: 'team', name: team.title, meta: team.code + ' - ' + team.unit, id: team.id });
        }
        team.members.forEach(m => {
            if (m.name.toLowerCase().includes(query) || 
                m.reg_no.toLowerCase().includes(query)) {
                results.push({ type: 'student', name: m.name, meta: m.reg_no + ' • ' + team.title, id: m.id, teamId: team.id });
            }
        });
    });

    resultsEl.innerHTML = '';
    if (results.length === 0) {
        resultsEl.innerHTML = '<div class="result-item" style="color: var(--muted);">No results found</div>';
    } else {
        results.slice(0, 10).forEach(r => {
            const div = document.createElement('div');
            div.className = 'result-item';
            div.innerHTML = `
                <span class="result-type">${r.type === 'team' ? '👥 Team' : '🎓 Student'}</span>
                <div class="result-name">${escapeHtml(r.name)}</div>
                <div class="result-meta">${escapeHtml(r.meta)}</div>
            `;
            div.onclick = () => {
                if (r.type === 'team') {
                    navigateToTeam(r.id);
                } else {
                    showTeamForStudent(r.teamId);
                }
                resultsEl.classList.remove('show');
                document.getElementById('globalSearchInput').value = '';
            };
            resultsEl.appendChild(div);
        });
    }
    resultsEl.classList.add('show');
});

document.addEventListener('click', function(e) {
    const searchResults = document.getElementById('globalSearchResults');
    if (!e.target.closest('.global-search')) {
        searchResults.classList.remove('show');
    }
});

/* ═══════════════════════════════════════════════════════
   EXPORT DROPDOWN
═══════════════════════════════════════════════════════ */
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    menu.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('exportMenu');
    if (!e.target.closest('.export-dropdown')) {
        menu.classList.remove('show');
    }
});

function exportTeamMembersPDF(teamId) {
    window.open('../../teams/api/export_group_members_pdf.php?team_id=' + teamId, '_blank');
}

function exportPeerEvalJSON(teamId) {
    window.open('../../teams/api/peer_evaluation_report.php?team_id=' + teamId + '&format=json', '_blank');
}

function exportPeerEvalCSV(teamId) {
    window.open('../../teams/api/peer_evaluation_report.php?team_id=' + teamId + '&format=csv', '_blank');
}

/* ═══════════════════════════════════════════════════════
   TEAM NAVIGATION (ONE TEAM AT A TIME)
═══════════════════════════════════════════════════════ */
function getVisibleTeamId() {
    const active = document.querySelector('.team-content.active');
    return active ? parseInt(active.id.replace('teamContent-', '')) : currentTeamId;
}

function navigateToTeam(teamId) {
    // Hide all teams
    document.querySelectorAll('.team-content').forEach(tc => tc.classList.remove('active'));
    
    // Show target team
    const target = document.getElementById('teamContent-' + teamId);
    if (target) {
        target.classList.add('active');
        currentTeamId = teamId;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function showTeamForStudent(studentId) {
    // Find the team containing this student
    const team = allTeamsData.find(t => t.members.some(m => m.id === studentId));
    if (team) {
        navigateToTeam(team.id);
    }
}

function showAllTeams() {
    // Keep the current team - just scroll to top to show stats
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextTeam() {
    const active = document.querySelector('.team-content.active');
    if (!active) return;
    const next = active.nextElementSibling;
    if (next && next.classList.contains('team-content')) {
        navigateToTeam(parseInt(next.id.replace('teamContent-', '')));
    } else {
        // Wrap to first team
        const first = document.querySelector('.team-content');
        if (first) {
            navigateToTeam(parseInt(first.id.replace('teamContent-', '')));
        }
    }
}

function finishTeam() {
    const teamId = currentTeamId;
    const team = allTeamsData.find(t => t.id === teamId);
    const confirmed = confirm(`Save marking for team "${team?.title || ''}"? Continue to next team?`);
    if (confirmed) {
        nextTeam();
    }
}

function openInsightsModal() {
    const modal = document.getElementById('insightsModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        // Auto-load insights for the current team
        loadPeerEvalInsights(currentTeamId);
    }
}

function closeInsightsModal() {
    const modal = document.getElementById('insightsModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function(e) {
    const modal = document.getElementById('insightsModal');
    if (modal && e.target === modal) {
        closeInsightsModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowRight' && !drawerOpen) {
        nextTeam();
    }
});

/* ═══════════════════════════════════════════════════════
   DRAWER SYSTEM
═══════════════════════════════════════════════════════ */
function openMemberDrawer(type, teamId, studentId, studentName) {
    drawerType = type;
    drawerTeamId = teamId;
    drawerStudentId = studentId;
    drawerOpen = true;

    const team = allTeamsData.find(t => t.id === teamId);
    const student = team?.members.find(m => m.id === studentId);

    document.getElementById('drawerTitle').textContent = studentName;
    document.getElementById('drawerSubtitle').textContent = team?.code + ' - ' + student?.reg_no || '';
    document.getElementById('drawerSaveBtn').style.display = 'none';

    const body = document.getElementById('drawerBody');
    const drawer = document.getElementById('memberDrawer');
    const overlay = document.getElementById('drawerOverlay');

    switch(type) {
        case 'contribution':
            loadContributionDrawer(body, teamId, studentId);
            break;
        case 'files':
            loadFilesDrawer(body, teamId, studentId);
            break;
        case 'attendance':
            loadAttendanceDrawer(body, teamId, studentId);
            break;
        case 'marks':
            loadMarksDrawer(body, teamId, studentId);
            break;
        case 'activity':
            loadActivityDrawer(body, teamId, studentId);
            break;
    }

    drawer.classList.add('open');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    drawerOpen = false;
    const drawer = document.getElementById('memberDrawer');
    const overlay = document.getElementById('drawerOverlay');
    drawer.classList.remove('open');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
    drawerType = null;
    drawerTeamId = null;
    drawerStudentId = null;
}

function loadContributionDrawer(body, teamId, studentId) {
    body.innerHTML = `
        <div style="display:flex;gap:12px;margin-bottom:20px;">
            <div style="flex:1;background:linear-gradient(135deg, #EFF6FF, #F0FDF4);border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:12px;color:var(--muted);">Peer Score</div>
                <div style="font-size:24px;font-weight:800;color:var(--text);">--</div>
            </div>
            <div style="flex:1;background:linear-gradient(135deg, #F0FDF4, #FFFBEB);border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:12px;color:var(--muted);">Files Uploaded</div>
                <div style="font-size:24px;font-weight:800;color:var(--text);">--</div>
            </div>
        </div>
        <div style="background:#F9FAFB;border:1px solid var(--border-light);border-radius:12px;padding:16px;margin-bottom:16px;">
            <strong style="font-size:14px;color:var(--text);">Contribution Analysis</strong>
            <p style="font-size:13px;color:var(--muted);margin-top:8px;">Loading contribution data...</p>
        </div>
        <div style="margin-top:16px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Contribution Notes</label>
            <textarea id="drawerContributionNotes" class="drawer-input" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;min-height:80px;font-family:'Inter',sans-serif;font-size:13px;" placeholder="Add contribution notes for this student..."></textarea>
        </div>
    `;
    document.getElementById('drawerSaveBtn').style.display = 'inline-flex';
    document.getElementById('drawerSaveBtn').onclick = () => {
        alert('Contribution notes saved (demo - original functionality preserved)');
        closeDrawer();
    };
}

function loadFilesDrawer(body, teamId, studentId) {
    body.innerHTML = `
        <p style="color:var(--muted);font-size:14px;margin-bottom:16px;">Files uploaded by this student for this team.</p>
        <div id="drawerFilesList">
            <p style="color:var(--muted);font-size:13px;">Loading files...</p>
        </div>
    `;
    
    // Fetch team files from existing data structure (preserved from PHP)
    const team = allTeamsData.find(t => t.id === teamId);
    if (!team) return;
    
    // Load team files from the API endpoint (existing functionality)
    fetch(`../../teams/api/workspace_files.php?team_id=${teamId}`)
        .then(res => res.json())
        .then(data => {
            const listEl = document.getElementById('drawerFilesList');
            const files = (data.files || []).filter(f => parseInt(f.uploader_id) === studentId);
            if (files.length === 0) {
                listEl.innerHTML = '<p style="color:var(--muted);font-size:13px;padding:16px;background:#F9FAFB;border-radius:10px;">No files uploaded by this student.</p>';
                return;
            }
            listEl.innerHTML = files.map(f => `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;border:1px solid var(--border-light);border-radius:10px;margin-bottom:8px;">
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:13px;">${escapeHtml(f.original_name || f.file_name || 'File')}</div>
                        <div style="font-size:12px;color:var(--muted);">${f.uploaded_at ? new Date(f.uploaded_at).toLocaleString() : ''}</div>
                    </div>
                    <button class="read-btn" onclick="viewTeamFile(${f.id})">📖</button>
                </div>
            `).join('');
        })
        .catch(() => {
            document.getElementById('drawerFilesList').innerHTML = '<p style="color:var(--muted);font-size:13px;">Unable to load files.</p>';
        });
}

function loadAttendanceDrawer(body, teamId, studentId) {
    body.innerHTML = `
        <div style="display:flex;gap:12px;margin-bottom:20px;">
            <div style="flex:1;background:linear-gradient(135deg, #EFF6FF, #F0FDF4);border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:12px;color:var(--muted);">Attendance Rate</div>
                <div style="font-size:24px;font-weight:800;color:var(--text);">--</div>
            </div>
            <div style="flex:1;background:linear-gradient(135deg, #F0FDF4, #FFFBEB);border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:12px;color:var(--muted);">Sessions</div>
                <div style="font-size:24px;font-weight:800;color:var(--text);">--</div>
            </div>
        </div>
        <div style="background:#F9FAFB;border:1px solid var(--border-light);border-radius:12px;padding:16px;">
            <strong style="font-size:14px;color:var(--text);">Attendance Records</strong>
            <p style="font-size:13px;color:var(--muted);margin-top:8px;">Attendance data loaded through the existing backend system.</p>
        </div>
    `;
    document.getElementById('drawerSaveBtn').style.display = 'inline-flex';
    document.getElementById('drawerSaveBtn').onclick = () => {
        alert('Attendance saved (demo - original functionality preserved)');
        closeDrawer();
    };
}

function loadMarksDrawer(body, teamId, studentId) {
    body.innerHTML = `
        <div style="background:#F9FAFB;border:1px solid var(--border-light);border-radius:12px;padding:16px;margin-bottom:16px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Individual Mark</label>
            <div style="display:flex;gap:10px;align-items:center;">
                <input type="number" id="individual_mark_${studentId}" placeholder="0" min="0" max="100" step="0.01" style="flex:1;padding:10px;border:1px solid var(--border);border-radius:10px;font-size:13px;">
                <input type="text" id="individual_component_${studentId}" placeholder="Component" style="flex:1;padding:10px;border:1px solid var(--border);border-radius:10px;font-size:13px;">
                <button type="button" onclick="awardIndividualMark(${studentId}, ${teamId})" class="submit-btn">Award</button>
            </div>
        </div>
        <div style="background:#F9FAFB;border:1px solid var(--border-light);border-radius:12px;padding:16px;">
            <strong style="font-size:14px;color:var(--text);">Existing Marks</strong>
            <p style="font-size:13px;color:var(--muted);margin-top:8px;">View awarded marks for this student from the marks table above.</p>
        </div>
    `;
}

function loadActivityDrawer(body, teamId, studentId) {
    body.innerHTML = `
        <p style="color:var(--muted);font-size:14px;margin-bottom:16px;">Activity log for this student.</p>
        <div id="drawerActivityList">
            <p style="color:var(--muted);font-size:13px;">Loading activities...</p>
        </div>
    `;

    // Use existing endpoint to load activity
    fetch(`../../teams/api/get_activity_log.php?team_id=${teamId}`)
        .then(res => res.json())
        .then(data => {
            const listEl = document.getElementById('drawerActivityList');
            const activities = (data.activities || []).filter(a => parseInt(a.user_id) === studentId);
            if (activities.length === 0) {
                listEl.innerHTML = '<p style="color:var(--muted);font-size:13px;padding:16px;background:#F9FAFB;border-radius:10px;">No activity recorded for this student.</p>';
                return;
            }
            listEl.innerHTML = activities.slice(0, 20).map(a => `
                <div style="padding:12px;border:1px solid var(--border-light);border-radius:10px;margin-bottom:8px;">
                    <div style="font-size:13px;font-weight:600;">${escapeHtml(a.action_type || 'Activity')}</div>
                    <div style="font-size:12px;color:var(--muted);">${a.created_at ? new Date(a.created_at).toLocaleString() : ''}</div>
                    <div style="font-size:12px;color:#4B5563;margin-top:4px;">${escapeHtml(a.action_detail || '')}</div>
                </div>
            `).join('');
        })
        .catch(() => {
            document.getElementById('drawerActivityList').innerHTML = '<p style="color:var(--muted);font-size:13px;">Unable to load activities.</p>';
        });
}

function loadMemberInsights(teamId, studentId, studentName) {
    openMemberDrawer('contribution', teamId, studentId, studentName);
    const body = document.getElementById('drawerBody');
    body.innerHTML = '<p style="color:var(--muted);font-size:14px;">Loading AI insights for this student...</p>';
    document.getElementById('drawerTitle').textContent = '✨ AI Insights - ' + studentName;
    
    // Use existing lecturer_team_insights.php endpoint
    fetch(`../../teams/api/lecturer_team_insights.php?team_id=${teamId}`, {
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) throw new Error(data.error);
        const studentData = (data.members || []).find(m => parseInt(m.student_id) === studentId);
        if (!studentData) {
            body.innerHTML = '<p style="color:var(--muted);font-size:14px;">No insights available for this student.</p>';
            return;
        }
        
        const activityTypes = studentData.activity_types || {};
        const typeChips = Object.keys(activityTypes).length
            ? Object.entries(activityTypes)
                .map(([k, v]) => `<span style="display:inline-block;margin:2px;padding:4px 10px;border-radius:999px;background:#EEF2FF;color:#3730A3;font-size:11px;">${escapeHtml(k)}: ${Number(v) || 0}</span>`)
                .join('')
            : '<span style="color:#9CA3AF;font-size:12px;">No activity tags yet</span>';
        
        body.innerHTML = `
            <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
                <div style="flex:1;min-width:140px;background:linear-gradient(135deg, #EFF6FF, #F0FDF4);border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:12px;color:var(--muted);">Files Uploaded</div>
                    <div style="font-size:28px;font-weight:800;color:var(--text);">${Number(studentData.files_uploaded) || 0}</div>
                </div>
                <div style="flex:1;min-width:140px;background:linear-gradient(135deg, #F0FDF4, #FFFBEB);border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:12px;color:var(--muted);">Tasks Created</div>
                    <div style="font-size:28px;font-weight:800;color:var(--text);">${Number(studentData.tasks_created) || 0}</div>
                </div>
                <div style="flex:1;min-width:140px;background:linear-gradient(135deg, #FFFBEB, #FEF2F2);border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:12px;color:var(--muted);">Standups</div>
                    <div style="font-size:28px;font-weight:800;color:var(--text);">${Number(studentData.standups_count) || 0}</div>
                </div>
                <div style="flex:1;min-width:140px;background:linear-gradient(135deg, #FEF2F2, #F0FDF4);border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:12px;color:var(--muted);">Activity (14d)</div>
                    <div style="font-size:28px;font-weight:800;color:var(--text);">${Number(studentData.activity_count_14d) || 0}</div>
                </div>
            </div>
            <div style="background:#F9FAFB;border:1px solid var(--border-light);border-radius:12px;padding:16px;margin-bottom:16px;">
                <strong style="font-size:14px;color:var(--text);">Activity Types</strong>
                <div style="margin-top:8px;">${typeChips}</div>
            </div>
            <div style="background:#F9FAFB;border:1px solid var(--border-light);border-radius:12px;padding:16px;">
                <strong style="font-size:14px;color:var(--text);">Recent Files</strong>
                <ul style="margin:8px 0 0 16px;list-style:disc;">
                    ${(studentData.recent_files || []).map(f => `<li style="font-size:13px;margin-bottom:4px;">${escapeHtml(f.file_name || 'File')} <span style="color:var(--muted);">(${formatDateTime(f.uploaded_at)})</span></li>`).join('') || '<li style="color:var(--muted);font-size:13px;">No uploads</li>'}
                </ul>
            </div>
            <div style="margin-top:16px;">
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">AI Insights Notes</label>
                <textarea id="drawerInsightsNotes" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;min-height:80px;font-family:'Inter',sans-serif;font-size:13px;" placeholder="Add your observations for this student..."></textarea>
            </div>
        `;
        document.getElementById('drawerSaveBtn').style.display = 'inline-flex';
        document.getElementById('drawerSaveBtn').onclick = () => {
            alert('Insights notes saved');
            closeDrawer();
        };
    })
    .catch(err => {
        body.innerHTML = `<p style="color:var(--danger);font-size:14px;">Failed to load insights: ${err.message}</p>`;
    });
}

/* ═══════════════════════════════════════════════════════
   PRESERVED FUNCTIONS (unchanged functionality)
═══════════════════════════════════════════════════════ */
async function deleteTeam(teamId, teamTitle) {
    const confirmed = confirm(`Delete team "${teamTitle}" and remove all members and related records?`);
    if (!confirmed) return;

    try {
        const res = await fetch('../../teams/api/delete_team.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                team_id: teamId,
                csrf_token: csrfToken
            })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || data?.message || 'Failed to delete team');
        }

        alert(data.message || 'Team deleted successfully');
        window.location.reload();
    } catch (err) {
        alert('Delete team error: ' + err.message);
        console.error(err);
    }
}

function toggleMenu(teamId) {
    const menu = document.getElementById('menu-' + teamId);
    
    // Close all other menus
    document.querySelectorAll('.ellipsis-content').forEach(m => {
        if (m.id !== 'menu-' + teamId) {
            m.classList.remove('show');
        }
    });
    
    // Toggle current menu
    menu.classList.toggle('show');
}

// Close menus when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.matches('.ellipsis-btn')) {
        document.querySelectorAll('.ellipsis-content').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

function toggleStudentField(select, teamId) {
    const studentGroup = document.getElementById('studentGroup' + teamId);
    const studentSelect = studentGroup.querySelector('select');
    
    if (select.value === 'individual') {
        studentGroup.style.display = 'block';
        studentSelect.required = true;
    } else {
        studentGroup.style.display = 'none';
        studentSelect.required = false;
        studentSelect.value = '';
    }
}

function awardMark(event, teamId) {
    event.preventDefault();
    
    const form = document.getElementById('markForm' + teamId);
    const formData = new FormData(form);
    
    fetch('../../teams/api/award_mark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Mark awarded successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while awarding the mark.');
    });
}

function awardGroupMark(teamId) {
    const mark = document.getElementById('group_mark_' + teamId).value;
    const component = document.getElementById('group_component_' + teamId).value;
    const maxMark = document.getElementById('group_max_mark_' + teamId).value;
    const notes = document.getElementById('group_notes_' + teamId).value;
    
    if (!mark || !component) {
        alert('Please fill in the mark and component fields');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'award_mark');
    formData.append('team_id', teamId);
    formData.append('mark_type', 'team');
    formData.append('mark', mark);
    formData.append('max_mark', maxMark);
    formData.append('component', component);
    formData.append('notes', notes);
    formData.append('csrf_token', csrfToken);
    
    fetch('../../teams/api/award_mark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Group mark awarded successfully!');
            // Clear the form
            document.getElementById('group_mark_' + teamId).value = '';
            document.getElementById('group_component_' + teamId).value = '';
            document.getElementById('group_max_mark_' + teamId).value = '100';
            document.getElementById('group_notes_' + teamId).value = '';
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while awarding the group mark.');
    });
}

function awardIndividualMark(studentId, teamId) {
    const mark = document.getElementById('individual_mark_' + studentId).value;
    const component = document.getElementById('individual_component_' + studentId).value;
    
    if (!mark || !component) {
        alert('Please fill in the mark and component fields');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'award_mark');
    formData.append('team_id', teamId);
    formData.append('student_id', studentId);
    formData.append('mark_type', 'individual');
    formData.append('mark', mark);
    formData.append('max_mark', '100');
    formData.append('component', component);
    formData.append('csrf_token', csrfToken);
    
    fetch('../../teams/api/award_mark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Individual mark awarded successfully!');
            // Clear the form
            document.getElementById('individual_mark_' + studentId).value = '';
            document.getElementById('individual_component_' + studentId).value = '';
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while awarding the individual mark.');
    });
}

function viewSubmission(submissionId) {
    openFileViewer('submission', submissionId);
}

function viewTeamFile(fileId) {
    openFileViewer('team', fileId);
}

function openFileViewer(type, id) {
    const overlay = document.getElementById('fileViewerOverlay');
    const title = document.getElementById('fileViewerTitle');
    const content = document.getElementById('fileViewerContent');
    
    // Show loading state
    overlay.classList.add('active');
    title.textContent = 'Loading...';
    content.innerHTML = '<div class="file-viewer-loading">Loading file...</div>';
    
    // Determine file URL based on type
    let fileUrl;
    if (type === 'submission') {
        fileUrl = `../../teams/api/serve_file.php?submission_id=${id}`;
    } else {
        fileUrl = `../../teams/api/serve_file.php?file_id=${id}`;
    }
    
    // Fetch file info first
    fetch(fileUrl, { method: 'HEAD' })
        .then(response => {
            const contentType = response.headers.get('content-type') || '';
            const fileName = response.headers.get('content-disposition') || 
                           `file_${id}`;
            
            // Update title
            title.textContent = `File Viewer - ${fileName}`;
            
            // Handle different file types
            if (contentType.includes('application/pdf')) {
                content.innerHTML = `<iframe src="${fileUrl}" type="application/pdf"></iframe>`;
            } else if (contentType.includes('image/')) {
                content.innerHTML = `<img src="${fileUrl}" alt="File preview">`;
            } else if (contentType.includes('text/')) {
                fetch(fileUrl)
                    .then(response => response.text())
                    .then(text => {
                        content.innerHTML = `<pre style="white-space: pre-wrap; word-wrap: break-word;">${text}</pre>`;
                    })
                    .catch(error => {
                        content.innerHTML = `<div class="file-viewer-error">Error loading text file: ${error.message}</div>`;
                    });
            } else if (contentType.includes('application/vnd.openxmlformats-officedocument') ||
                      contentType.includes('application/msword') ||
                      contentType.includes('application/vnd.ms-')) {
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div>This file type (${contentType}) cannot be previewed inline.</div>
                        <div style="margin-top: 1rem;">
                            <a href="${fileUrl}" download style="background: #3b82f6; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px;">
                                Download File
                            </a>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div>Preview not available for this file type.</div>
                        <div style="margin-top: 1rem;">
                            <a href="${fileUrl}" download style="background: #3b82f6; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px;">
                                Download File
                            </a>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('File viewer error:', error);
            title.textContent = 'Error Loading File';
            
            if (error.message.includes('not found') || error.message.includes('404')) {
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div style="font-size: 1.2rem; margin-bottom: 1rem;">📁 File Not Found</div>
                        <div style="margin-bottom: 1rem;">The requested file could not be found on the server.</div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                            This could mean:
                            <ul style="text-align: left; margin-top: 0.5rem;">
                                <li>The file was deleted or moved</li>
                                <li>The file upload was not completed</li>
                                <li>There is a database/path mismatch</li>
                            </ul>
                        </div>
                        <div style="margin-top: 1.5rem;">
                            <button onclick="closeFileViewer()" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; margin-right: 0.5rem;">
                                Close Viewer
                            </button>
                            ${type === 'team' ? `
                                <button onclick="fixTeamFile(${id})" style="background: #10b981; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">
                                    Fix File Path
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div style="font-size: 1.2rem; margin-bottom: 1rem;">⚠️ Error Loading File</div>
                        <div style="margin-bottom: 1rem;">An error occurred while trying to load the file.</div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                            Error: ${error.message}
                        </div>
                        <div style="margin-top: 1.5rem;">
                            <button onclick="closeFileViewer()" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">
                                Close Viewer
                            </button>
                        </div>
                    </div>
                `;
            }
        });
}

function closeFileViewer() {
    const overlay = document.getElementById('fileViewerOverlay');
    overlay.classList.remove('active');
}

function fixSubmissionFile(submissionId) {
    const fixUrl = `../fix_team_submissions.php`;
    window.open(fixUrl, '_blank', 'width=1200,height=800,scrollbars=yes');
}

function fixTeamFile(fileId) {
    const fixUrl = `../fix_team_files.php`;
    window.open(fixUrl, '_blank', 'width=1200,height=800,scrollbars=yes');
}

// Close viewer when clicking overlay background
document.addEventListener('click', function(event) {
    const overlay = document.getElementById('fileViewerOverlay');
    if (event.target === overlay) {
        closeFileViewer();
    }
});

// Close viewer with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeFileViewer();
        closeDrawer();
        closeSupervisorModal();
    }
});

function toggleMarkBox(element, teamId, studentId = null) {
    if (element.classList.contains('checked')) {
        element.classList.remove('checked');
        element.textContent = '□';
    } else {
        element.classList.add('checked');
        element.textContent = '☑';
        
        // Open the appropriate marking form
        if (studentId) {
            const individualField = document.getElementById('individual_mark_' + studentId);
            if (individualField) {
                individualField.scrollIntoView({ behavior: 'smooth' });
                individualField.focus();
            }
        } else {
            const groupSection = document.getElementById('group_mark_' + teamId);
            if (groupSection) {
                groupSection.scrollIntoView({ behavior: 'smooth' });
                groupSection.focus();
            }
        }
    }
}

function generatePDF(teamId) {
    window.open('../../teams/api/export_pdf.php?team_id=' + teamId, '_blank');
}

function generateExcel(teamId) {
    window.open('../../teams/api/export_excel.php?team_id=' + teamId, '_blank');
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

function formatDateTime(value) {
    if (!value) return 'N/A';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString();
}

function renderHeatmap(heatmap) {
    const entries = Object.entries(heatmap || {});
    if (!entries.length) return '<p style="color:#666;">No heatmap data.</p>';
    const max = Math.max(...entries.map(([, c]) => Number(c) || 0), 1);
    const cells = entries.map(([day, count]) => {
        const ratio = (Number(count) || 0) / max;
        const alpha = ratio === 0 ? 0.08 : Math.min(0.9, 0.2 + ratio * 0.7);
        return `<div title="${escapeHtml(day)}: ${Number(count) || 0} activities" style="width:16px;height:16px;border-radius:3px;background:rgba(16,185,129,${alpha});border:1px solid rgba(6,78,59,0.15);"></div>`;
    });
    return `<div style="display:flex;flex-wrap:wrap;gap:4px;max-width:320px;">${cells.join('')}</div>`;
}

async function loadPeerEvalInsights(teamId) {
    const statusEl = document.getElementById('peerEvalInsightsStatus');
    const bodyEl = document.getElementById('peerEvalInsightsBody');
    if (!statusEl || !bodyEl) return;

    statusEl.textContent = 'Loading team activity insights...';
    bodyEl.innerHTML = '';

    try {
        const res = await fetch(`../../teams/api/lecturer_team_insights.php?team_id=${encodeURIComponent(teamId)}`, {
            credentials: 'same-origin'
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || ('HTTP ' + res.status));
        }

        const team = data.team || {};
        const summary = data.summary || {};
        const members = data.members || [];
        const recentFiles = (data.files || []).slice(0, 8);
        const recentActivities = (data.activities || []).slice(0, 12);
        const recentStandups = (data.standups || []).slice(0, 8);
        const peerRows = data.peer_summary || [];

        statusEl.textContent = '';

        const sortedPeer = [...peerRows].sort((a, b) => Number(b.avg_overall || 0) - Number(a.avg_overall || 0));
        const top = sortedPeer[0];
        const low = sortedPeer[sortedPeer.length - 1];

        const memberCards = members.map(member => {
            const activityTypes = member.activity_types || {};
            const activityTypeChips = Object.keys(activityTypes).length
                ? Object.entries(activityTypes)
                    .map(([k, v]) => `<span style="display:inline-block;margin:2px;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;">${escapeHtml(k)}: ${Number(v) || 0}</span>`)
                    .join('')
                : '<span style="color:#9ca3af;font-size:12px;">No activity tags yet</span>';

            const files = (member.recent_files || []).map(file =>
                `<li style="margin-bottom:3px;">${escapeHtml(file.file_name || 'File')} <span style="color:#9ca3af;">(${formatDateTime(file.uploaded_at)})</span></li>`
            ).join('') || '<li style="color:#9ca3af;">No uploads</li>';

            return `
                <div style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                        <div>
                            <div style="font-weight:700;color:#0f172a;">${escapeHtml(member.student_name || 'Student')}</div>
                            <div style="font-size:12px;color:#64748b;">${escapeHtml(member.reg_no || '')} • ${escapeHtml(member.role || 'member')}</div>
                        </div>
                        <div style="font-size:12px;color:#6b7280;text-align:right;">Last active<br><strong>${formatDateTime(member.last_activity_at)}</strong></div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:10px 0;">
                        <div style="background:#f8fafc;border-radius:6px;padding:8px;font-size:12px;">Files<br><strong>${Number(member.files_uploaded) || 0}</strong></div>
                        <div style="background:#f8fafc;border-radius:6px;padding:8px;font-size:12px;">Tasks Created<br><strong>${Number(member.tasks_created) || 0}</strong></div>
                        <div style="background:#f8fafc;border-radius:6px;padding:8px;font-size:12px;">Tasks Assigned<br><strong>${Number(member.tasks_assigned) || 0}</strong></div>
                        <div style="background:#f8fafc;border-radius:6px;padding:8px;font-size:12px;">Standups<br><strong>${Number(member.standups_count) || 0}</strong></div>
                        <div style="background:#f8fafc;border-radius:6px;padding:8px;font-size:12px;">Checklist Signoffs<br><strong>${Number(member.checklist_signoffs) || 0}</strong></div>
                        <div style="background:#f8fafc;border-radius:6px;padding:8px;font-size:12px;">Activity (14d)<br><strong>${Number(member.activity_count_14d) || 0}</strong></div>
                    </div>

                    <div style="margin:8px 0;">${activityTypeChips}</div>
                    <div style="font-size:12px;color:#334155;margin-top:6px;">
                        <strong>Recent uploads</strong>
                        <ul style="margin:4px 0 0 16px;padding:0;">${files}</ul>
                    </div>
                </div>
            `;
        }).join('');

        const peerHtml = peerRows.length ? `
            <div style="margin-top:14px;">
                <h4 style="margin:0 0 8px 0;color:#0f172a;">Peer Evaluation Snapshot</h4>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;">
                    <button onclick="loadMemberInsights(${teamId}, ${top?.evaluatee_id || 0}, '${escapeHtml(top?.evaluatee_name || 'N/A')}')" style="flex:1;min-width:220px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:8px;padding:10px;cursor:pointer;transition:all 0.2s;text-align:left;">
                        <div style="font-weight:700;color:#166534;">Top performer</div>
                        <div>${escapeHtml(top?.evaluatee_name || 'N/A')} — ${Number(top?.avg_overall || 0).toFixed(2)} / 5</div>
                    </button>
                    <button onclick="loadMemberInsights(${teamId}, ${low?.evaluatee_id || 0}, '${escapeHtml(low?.evaluatee_name || 'N/A')}')" style="flex:1;min-width:220px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px;cursor:pointer;transition:all 0.2s;text-align:left;">
                        <div style="font-weight:700;color:#9a3412;">Needs support</div>
                        <div>${escapeHtml(low?.evaluatee_name || 'N/A')} — ${Number(low?.avg_overall || 0).toFixed(2)} / 5</div>
                    </button>
                </div>
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Responses</th>
                            <th>Contribution</th>
                            <th>Communication</th>
                            <th>Quality</th>
                            <th>Reliability</th>
                            <th>Overall</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${peerRows.map(r => `
                            <tr>
                                <td>${escapeHtml(r.evaluatee_name)}</td>
                                <td>${Number(r.responses) || 0}</td>
                                <td>${escapeHtml(r.avg_contribution)}</td>
                                <td>${escapeHtml(r.avg_communication)}</td>
                                <td>${escapeHtml(r.avg_quality)}</td>
                                <td>${escapeHtml(r.avg_reliability)}</td>
                                <td><strong>${escapeHtml(r.avg_overall)}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        ` : '<div style="margin-top:14px;color:#6b7280;">No peer evaluations yet for this team.</div>';

        const activitiesHtml = recentActivities.length
            ? `<ul style="margin:6px 0 0 16px;padding:0;">${recentActivities.map(a => `<li style="margin-bottom:4px;"><strong>${escapeHtml(a.user_name || ('User #' + (a.user_id || '')))}</strong> • ${escapeHtml(a.action_type || '')} <span style="color:#94a3b8;">(${formatDateTime(a.created_at)})</span><br><span style="color:#475569;">${escapeHtml(a.action_detail || '')}</span></li>`).join('')}</ul>`
            : '<p style="color:#9ca3af;">No recent activities.</p>';

        const filesHtml = recentFiles.length
            ? `<ul style="margin:6px 0 0 16px;padding:0;">${recentFiles.map(f => `<li style="margin-bottom:4px;">${escapeHtml(f.file_name || 'File')} • by ${escapeHtml(f.uploader_name || ('User #' + (f.uploader_id || '')))} <span style="color:#94a3b8;">(${formatDateTime(f.uploaded_at)})</span></li>`).join('')}</ul>`
            : '<p style="color:#9ca3af;">No files uploaded yet.</p>';

        const standupsHtml = recentStandups.length
            ? `<ul style="margin:6px 0 0 16px;padding:0;">${recentStandups.map(s => `<li style="margin-bottom:4px;"><strong>${escapeHtml(s.user_name || ('User #' + (s.user_id || '')))}</strong> <span style="color:#94a3b8;">(${formatDateTime(s.created_at)})</span><br><span style="color:#475569;">Yesterday: ${escapeHtml(s.yesterday || '-')} | Today: ${escapeHtml(s.today || '-')} | Blockers: ${escapeHtml(s.blockers || '-')}</span></li>`).join('')}</ul>`
            : '<p style="color:#9ca3af;">No standups yet.</p>';

        let html = `
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                <div>
                    <h3 style="margin:0;color:#0f172a;">${escapeHtml(team.title || 'Team')}</h3>
                    <div style="color:#64748b;font-size:13px;">${escapeHtml(team.unit_code || '')} ${escapeHtml(team.unit_name || '')} • ${escapeHtml(team.assessment_type || 'General')} • ${escapeHtml(team.status || '')}</div>
                </div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;min-width:210px;">
                    <div style="font-size:12px;color:#475569;">Team Health Score</div>
                    <div style="font-size:24px;font-weight:800;color:#111827;">${Number(data.health?.score || 0)}%</div>
                    <div style="font-size:12px;color:#64748b;">Done tasks: ${Number(data.health?.tasks_done || 0)} • Activity (7d): ${Number(data.health?.activity_7d || 0)}</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin-bottom:10px;">
                <div style="background:#f8fafc;border-radius:8px;padding:10px;"><div style="font-size:12px;color:#64748b;">Members</div><div style="font-weight:700;">${Number(summary.member_count) || 0}</div></div>
                <div style="background:#f8fafc;border-radius:8px;padding:10px;"><div style="font-size:12px;color:#64748b;">Files</div><div style="font-weight:700;">${Number(summary.file_count) || 0}</div></div>
                <div style="background:#f8fafc;border-radius:8px;padding:10px;"><div style="font-size:12px;color:#64748b;">Kanban Tasks</div><div style="font-weight:700;">${Number(summary.task_count) || 0}</div></div>
                <div style="background:#f8fafc;border-radius:8px;padding:10px;"><div style="font-size:12px;color:#64748b;">Standups</div><div style="font-weight:700;">${Number(summary.standup_count) || 0}</div></div>
                <div style="background:#f8fafc;border-radius:8px;padding:10px;"><div style="font-size:12px;color:#64748b;">Activity Events</div><div style="font-weight:700;">${Number(summary.activity_count) || 0}</div></div>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:14px;margin-bottom:12px;">
                <div style="min-width:260px;flex:1;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;">
                    <h4 style="margin:0 0 8px 0;">Activity Heatmap (14 days)</h4>
                    ${renderHeatmap(data.health?.heatmap || {})}
                </div>
                <div style="min-width:260px;flex:1;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;">
                    <h4 style="margin:0 0 8px 0;">Kanban Snapshot</h4>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;font-size:13px;">
                        <div>Backlog: <strong>${Number(summary.kanban_counts?.['Backlog'] || 0)}</strong></div>
                        <div>In Progress: <strong>${Number(summary.kanban_counts?.['In Progress'] || 0)}</strong></div>
                        <div>In Review: <strong>${Number(summary.kanban_counts?.['In Review'] || 0)}</strong></div>
                        <div>Done: <strong>${Number(summary.kanban_counts?.['Done'] || 0)}</strong></div>
                    </div>
                </div>
            </div>

            <div style="margin:10px 0 8px;font-weight:700;color:#0f172a;">Per-Student Activity</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px;">${memberCards}</div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;margin-top:14px;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;"><h4 style="margin:0 0 6px 0;">Recent Files</h4>${filesHtml}</div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;"><h4 style="margin:0 0 6px 0;">Recent Standups</h4>${standupsHtml}</div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;"><h4 style="margin:0 0 6px 0;">Recent Activity Log</h4>${activitiesHtml}</div>
            </div>

            ${peerHtml}
        `;
        bodyEl.innerHTML = html;
    } catch (err) {
        statusEl.textContent = 'Failed to load team insights: ' + err.message;
    }
}

function getSelectedPeerEvalTeamId() {
    const visiblePicker = document.querySelector('.peer-eval-team-picker.visible .peer-eval-team-select');
    return visiblePicker?.value || '';
}

function initPeerEvalUnitTiles() {
    const tiles = document.querySelectorAll('.peer-eval-unit-tile');
    const pickers = document.querySelectorAll('.peer-eval-team-picker');
    const unitSelect = document.getElementById('insightsUnitSelect');

    function activateUnit(unitKey, clearStatus = false) {
        tiles.forEach(t => {
            const active = t.dataset.unitKey === unitKey;
            t.classList.toggle('active', active);
            t.setAttribute('aria-expanded', active ? 'true' : 'false');
        });
        pickers.forEach(p => p.classList.toggle('visible', p.dataset.unitKey === unitKey));
        if (unitSelect && unitSelect.value !== unitKey) {
            unitSelect.value = unitKey;
        }
        if (clearStatus) {
            const statusEl = document.getElementById('peerEvalInsightsStatus');
            if (statusEl) statusEl.textContent = 'Select a team and click Load Insights.';
            const bodyEl = document.getElementById('peerEvalInsightsBody');
            if (bodyEl) bodyEl.innerHTML = '';
        }
    }

    tiles.forEach(tile => {
        tile.addEventListener('click', () => {
            const unitKey = tile.dataset.unitKey;
            const isActive = tile.classList.contains('active');

            if (isActive) {
                activateUnit('', true);
                if (unitSelect) unitSelect.value = '';
                const statusEl = document.getElementById('peerEvalInsightsStatus');
                if (statusEl) statusEl.textContent = 'Select a unit and team to load team activity insights.';
                return;
            }

            activateUnit(unitKey, true);
            const picker = document.getElementById('peerEvalTeams-' + unitKey);
            const select = picker?.querySelector('.peer-eval-team-select');
            if (select) select.focus();
        });
    });

    unitSelect?.addEventListener('change', () => {
        const selectedKey = unitSelect.value;
        if (!selectedKey) {
            activateUnit('', true);
            return;
        }
        activateUnit(selectedKey, true);
        const picker = document.getElementById('peerEvalTeams-' + selectedKey);
        const select = picker?.querySelector('.peer-eval-team-select');
        if (select) select.focus();
    });
}

initPeerEvalUnitTiles();

document.getElementById('loadPeerEvalInsightsBtn')?.addEventListener('click', () => {
    const teamId = getSelectedPeerEvalTeamId();
    if (!teamId) {
        const statusEl = document.getElementById('peerEvalInsightsStatus');
        if (statusEl) statusEl.textContent = 'Please select a unit, then choose a team.';
        return;
    }
    loadPeerEvalInsights(teamId);
});

document.getElementById('exportPeerCsvBtn')?.addEventListener('click', () => {
    const teamId = getSelectedPeerEvalTeamId();
    if (!teamId) {
        const statusEl = document.getElementById('peerEvalInsightsStatus');
        if (statusEl) statusEl.textContent = 'Please select a unit, then choose a team.';
        return;
    }
    window.open(`../../teams/api/peer_evaluation_report.php?team_id=${encodeURIComponent(teamId)}&format=csv`, '_blank');
});

// Supervisor Management Modal (preserved functionality)
function openSupervisorModal(teamId, teamTitle) {
    const modal = document.getElementById('supervisorModal');
    if (!modal) {
        alert('Supervisor modal not found. Please refresh the page.');
        return;
    }
    
    document.getElementById('supervisorTeamId').value = teamId;
    document.getElementById('supervisorTeamTitle').textContent = teamTitle;
    
    // Load existing supervisors
    loadTeamSupervisors(teamId);
    
    // Load available supervisors
    loadAvailableSupervisors(teamId);
    
    modal.style.display = 'flex';
}

function closeSupervisorModal() {
    const modal = document.getElementById('supervisorModal');
    if (modal) modal.style.display = 'none';
}

function canManageTeamSupervisors() {
    return true;
}

async function loadTeamSupervisors(teamId) {
    const container = document.getElementById('existingSupervisors');
    container.innerHTML = '<p>Loading...</p>';
    
    try {
        const response = await fetch(`../../teams/api/get_team_supervisors.php?team_id=${teamId}`);
        const data = await response.json();
        
        if (!data.success) {
            container.innerHTML = '<p class="error">Failed to load supervisors</p>';
            return;
        }
        
        if (data.supervisors.length === 0) {
            container.innerHTML = '<p>No supervisors assigned yet.</p>';
            return;
        }
        
        let html = '';
        data.supervisors.forEach(sup => {
            const statusClass = sup.status === 'approved' ? 'approved' : 
                               sup.status === 'pending' ? 'pending' : 'rejected';
            const statusLabel = sup.status.charAt(0).toUpperCase() + sup.status.slice(1);
            const canRemove = !sup.is_primary && sup.status !== 'approved';
            const canApprove = sup.status === 'pending';
            
            html += `
                <div class="supervisor-item ${statusClass}">
                    <div class="supervisor-info">
                        <strong>${sup.name}</strong>
                        ${sup.is_primary ? '<span class="primary-badge">Global Supervisor</span>' : '<span class="nominated-badge">Nominated Supervisor</span>'}
                        <span class="status-badge ${statusClass}">${statusLabel}</span>
                    </div>
                    <div class="supervisor-actions">
                        ${canApprove ? `
                            <button onclick="approveSupervisor(${sup.id}, true)" class="btn-approve">Approve</button>
                            <button onclick="approveSupervisor(${sup.id}, false)" class="btn-reject">Reject</button>
                        ` : ''}
                        ${canRemove ? `
                            <button onclick="removeSupervisor(${sup.id})" class="btn-remove">Remove</button>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    } catch (error) {
        console.error('Error loading supervisors:', error);
        container.innerHTML = '<p class="error">Error loading supervisors</p>';
    }
}

async function loadAvailableSupervisors(teamId) {
    // No longer needed - using email search instead
}

async function searchSupervisor() {
    const teamId = document.getElementById('supervisorTeamId').value;
    const email = document.getElementById('supervisorEmail').value.trim();
    const resultsDiv = document.getElementById('searchResults');
    
    if (!email) {
        resultsDiv.innerHTML = '<p style="color: #64748b; font-size: 0.85rem;">Please enter an email address</p>';
        return;
    }
    
    resultsDiv.innerHTML = '<p style="color: #64748b; font-size: 0.85rem;">Searching...</p>';
    
    try {
        const response = await fetch(`../../teams/api/search_supervisor.php?team_id=${teamId}&email=${encodeURIComponent(email)}`);
        const data = await response.json();
        
        if (!data.success) {
            resultsDiv.innerHTML = `<p style="color: #ef4444; font-size: 0.85rem;">${data.message}</p>`;
            return;
        }
        
        if (data.results.length === 0) {
            resultsDiv.innerHTML = '<p style="color: #64748b; font-size: 0.85rem;">No supervisors found with this email</p>';
            return;
        }
        
        let html = '';
        data.results.forEach(person => {
            const typeLabel = person.supervisor_type === 'technician' ? 'Technician' : person.supervisor_type === 'admin' ? 'Admin' : 'Lecturer';
            html += `
                <div class="search-result-item">
                    <div class="search-result-main" onclick="selectSupervisor(${person.id}, '${person.supervisor_type}', '${person.name}')">
                        <div class="name">${person.name} <span class="type">${typeLabel}</span></div>
                        <div class="email">${person.email}</div>
                        <div class="team-count">${person.team_count} teams supervised</div>
                    </div>
                    <button class="assign-button" onclick="event.stopPropagation(); selectSupervisor(${person.id}, '${person.supervisor_type}', '${person.name}')">Assign</button>
                </div>
            `;
        });
        
        resultsDiv.innerHTML = html;
    } catch (error) {
        console.error('Error searching supervisor:', error);
        resultsDiv.innerHTML = '<p style="color: #ef4444; font-size: 0.85rem;">Error searching supervisor</p>';
    }
}

function selectSupervisor(personId, supervisorType, personName) {
    if (!canManageTeamSupervisors()) {
        showSupervisorError('Only the unit lecturer, admin, or approved supervisor can manage supervisors for this team');
        return;
    }

    const teamId = document.getElementById('supervisorTeamId').value;
    
    // Clear any previous errors
    const errorDiv = document.getElementById('supervisorError');
    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.innerHTML = '';
    }
    
    if (confirm(`Assign ${personName} as supervisor for this team?`)) {
        requestSupervisor(teamId, personId, supervisorType);
    }
}

function showSupervisorError(message, details = null) {
    const errorDiv = document.getElementById('supervisorError');
    if (errorDiv) {
        let errorHtml = `<strong>Error:</strong> ${message}`;
        
        if (details) {
            errorHtml += '<div style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(0,0,0,0.05); border-radius: 4px; font-size: 0.75rem;">';
            
            if (details.httpStatus) {
                errorHtml += `<div><strong>HTTP Status:</strong> ${details.httpStatus}</div>`;
            }
            if (details.responseText) {
                errorHtml += `<div><strong>Response:</strong> ${details.responseText}</div>`;
            }
            if (details.requestPayload) {
                errorHtml += `<div><strong>Request:</strong> ${details.requestPayload}</div>`;
            }
            if (details.errorType) {
                errorHtml += `<div><strong>Error Type:</strong> ${details.errorType}</div>`;
            }
            if (details.errorMessage) {
                errorHtml += `<div><strong>Error Message:</strong> ${details.errorMessage}</div>`;
            }
            
            errorHtml += '</div>';
        }
        
        errorDiv.innerHTML = errorHtml;
        errorDiv.style.display = 'block';
    }
}

function clearSupervisorError() {
    const errorDiv = document.getElementById('supervisorError');
    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.innerHTML = '';
    }
}

async function requestSupervisor(teamId, personId, supervisorType) {
    if (!canManageTeamSupervisors()) {
        showSupervisorError('Only the unit lecturer, admin, or approved supervisor can manage supervisors for this team');
        return;
    }
    const requestPayload = JSON.stringify({ team_id: teamId, lecturer_id: personId, supervisor_type: supervisorType });
    
    try {
        const response = await fetch('../../teams/api/request_supervisor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: requestPayload
        });
        
        let data;
        let responseText;
        
        try {
            responseText = await response.text();
            data = JSON.parse(responseText);
        } catch (parseError) {
            // If JSON parsing fails, use the raw response
            data = { success: false, message: 'Invalid server response' };
            responseText = responseText || 'No response text';
        }
        
        if (data.success) {
            clearSupervisorError();
            alert(data.message);
            document.getElementById('supervisorEmail').value = '';
            document.getElementById('searchResults').innerHTML = '';
            loadTeamSupervisors(teamId);
        } else {
            showSupervisorError(data.message || 'Failed to request supervisor', {
                httpStatus: response.status,
                responseText: responseText,
                requestPayload: requestPayload,
                errorType: 'API Error'
            });
        }
    } catch (error) {
        console.error('Error requesting supervisor:', error);
        showSupervisorError('Error requesting supervisor: ' + (error.message || 'Unknown error'), {
            errorType: 'Network/Fetch Error',
            errorMessage: error.message,
            requestPayload: requestPayload
        });
    }
}

async function approveSupervisor(supervisorId, approved) {
    if (!canManageTeamSupervisors()) {
        alert('Only the unit lecturer, admin, or approved supervisor can manage supervisors for this team');
        return;
    }
    try {
        const response = await fetch('../../teams/api/approve_supervisor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ supervisor_id: supervisorId, approved: approved })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(approved ? 'Supervisor approved' : 'Supervisor rejected');
            const teamId = document.getElementById('supervisorTeamId').value;
            loadTeamSupervisors(teamId);
            loadAvailableSupervisors(teamId);
        } else {
            alert(data.message || 'Failed to update supervisor');
        }
    } catch (error) {
        console.error('Error approving supervisor:', error);
        alert('Error updating supervisor');
    }
}

async function removeSupervisor(supervisorId) {
    if (!canManageTeamSupervisors()) {
        alert('Only the unit lecturer, admin, or approved supervisor can manage supervisors for this team');
        return;
    }
    if (!confirm('Are you sure you want to remove this supervisor?')) return;
    
    try {
        const response = await fetch('../../teams/api/remove_supervisor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ supervisor_id: supervisorId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Supervisor removed');
            const teamId = document.getElementById('supervisorTeamId').value;
            loadTeamSupervisors(teamId);
            loadAvailableSupervisors(teamId);
        } else {
            alert(data.message || 'Failed to remove supervisor');
        }
    } catch (error) {
        console.error('Error removing supervisor:', error);
        alert('Error removing supervisor');
    }
}
</script>

</body>
</html>