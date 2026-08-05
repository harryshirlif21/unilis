<?php
// PDF export for team data
session_start();

// Auth check - allow lecturers, admins, technicians (supervisors) and students (team leaders)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['lecturer', 'admin', 'technician', 'student'])) {
    http_response_code(401);
    die('Unauthorized access');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/team_access.php';
require_once __DIR__ . '/../includes/ensure_team_marks.php';
require_once __DIR__ . '/../../vendor/autoload.php';

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

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $teamId = (int)($_GET['team_id'] ?? 0);
    $lecturerId = $_SESSION['user_id'];
    
    if ($teamId <= 0) {
        throw new Exception('Invalid team ID');
    }

    // Verify access: Team Leader, Class/Group Supervisor, or assigned Lecturer
    if (!canManageTeam($conn, $teamId, $lecturerId, $_SESSION['user_role'])) {
        throw new Exception('You do not have access to this team');
    }

    // Get comprehensive team data
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
        LIMIT 20
    ";
    $activityStmt = $conn->prepare($activitySql);
    $activityStmt->bind_param("i", $teamId);
    $activityStmt->execute();
    $activities = $activityStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $activityStmt->close();

    // Generate HTML content
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Team Report - ' . htmlspecialchars($team['team_title']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
            h2 { color: #34495e; margin-top: 30px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .team-info { background-color: #ecf0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .member-leader { background-color: #d4edda; }
            .member-regular { background-color: #f8f9fa; }
        </style>
    </head>
    <body>
        <h1>Team Report: ' . htmlspecialchars($team['team_title']) . '</h1>
        
        <div class="team-info">
            <p><strong>Unit:</strong> ' . htmlspecialchars($team['unit_name']) . ' (' . htmlspecialchars($team['unit_code']) . ')</p>
            <p><strong>Status:</strong> ' . ucfirst($team['status']) . '</p>
            <p><strong>Created:</strong> ' . date('d M Y', strtotime($team['team_created'])) . '</p>';
            
    if ($team['description']) {
        $html .= '<p><strong>Description:</strong> ' . htmlspecialchars($team['description']) . '</p>';
    }
    
    $html .= '</div>';

    // Team Members Section
    $html .= '<h2>Team Members</h2>
        <table>
            <tr>
                <th>Name</th>
                <th>Registration No</th>
                <th>Role</th>
                <th>Email</th>
                <th>Joined Date</th>
            </tr>';
    
    foreach ($members as $member) {
        $rowClass = $member['role'] === 'leader' ? 'member-leader' : 'member-regular';
        $html .= '
            <tr class="' . $rowClass . '">
                <td>' . htmlspecialchars($member['student_name']) . '</td>
                <td>' . htmlspecialchars($member['reg_no']) . '</td>
                <td>' . htmlspecialchars(team_role_label((string)$member['role'])) . '</td>
                <td>' . htmlspecialchars($member['email']) . '</td>
                <td>' . date('d M Y', strtotime($member['joined_at'])) . '</td>
            </tr>';
    }
    
    $html .= '</table>';

    // Documents Section
    $html .= '<h2>Uploaded Documents</h2>
        <table>
            <tr>
                <th>File Name</th>
                <th>Uploader</th>
                <th>Upload Date</th>
                <th>File Size</th>
            </tr>';
    
    foreach ($files as $file) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($file['original_name']) . '</td>
                <td>' . htmlspecialchars($file['uploader_name']) . '</td>
                <td>' . date('d M Y H:i', strtotime($file['uploaded_at'])) . '</td>
                <td>' . number_format($file['file_size'] / 1024, 1) . ' KB</td>
            </tr>';
    }
    
    $html .= '</table>';

    // Marks Section
    if (!empty($marks)) {
        $html .= '<h2>Awarded Marks</h2>
            <table>
                <tr>
                    <th>Component</th>
                    <th>Type</th>
                    <th>Student</th>
                    <th>Mark</th>
                    <th>Max Mark</th>
                    <th>Awarded By</th>
                    <th>Date</th>
                    <th>Notes</th>
                </tr>';
        
        foreach ($marks as $mark) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($mark['component']) . '</td>
                    <td>' . ucfirst($mark['mark_type']) . '</td>
                    <td>' . htmlspecialchars($mark['student_name'] ?: 'Team') . '</td>
                    <td>' . number_format($mark['mark'], 2) . '</td>
                    <td>' . number_format($mark['max_mark'], 2) . '</td>
                    <td>' . htmlspecialchars($mark['lecturer_name']) . '</td>
                    <td>' . date('d M Y', strtotime($mark['awarded_at'])) . '</td>
                    <td>' . htmlspecialchars($mark['notes'] ?: '') . '</td>
                </tr>';
        }
        
        $html .= '</table>';
    }

    // Activity Log Section
    if (!empty($activities)) {
        $html .= '<h2>Activity Log</h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>';
        
        foreach ($activities as $activity) {
            $html .= '
                <tr>
                    <td>' . date('d M Y H:i', strtotime($activity['created_at'])) . '</td>
                    <td>' . htmlspecialchars($activity['student_name'] ?: 'System') . '</td>
                    <td>' . htmlspecialchars($activity['action_type']) . '</td>
                    <td>' . htmlspecialchars($activity['action_detail'] ?: '') . '</td>
                </tr>';
        }
        
        $html .= '</table>';
    }

    $html .= '
        <p><small>Generated on ' . date('d M Y H:i') . ' by UniLIS System</small></p>
    </body>
    </html>';

    // Configure DomPDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);

    // Create PDF
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Generate filename
    $filename = 'team_' . $teamId . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $team['team_title']) . '_' . date('Y-m-d') . '.pdf';

    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $dompdf->output();

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating PDF: ' . $e->getMessage();
}
?>
