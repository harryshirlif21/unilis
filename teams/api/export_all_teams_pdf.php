<?php
// PDF export for all teams data with enhanced marks display
session_start();

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    die('Unauthorized access');
}

require_once __DIR__ . '/../../config/db.php';
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
    
    if (empty($teams)) {
        throw new Exception('No teams found');
    }
    
    // Generate HTML content
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>All Teams Report - UniLIS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .team-section { margin-bottom: 30px; page-break-inside: avoid; }
        .team-header { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        .team-title { font-size: 18px; font-weight: bold; color: #333; }
        .team-meta { font-size: 12px; color: #666; margin-top: 5px; }
        .members-section { margin-top: 15px; }
        .member { padding: 8px 0; border-bottom: 1px solid #eee; }
        .member:last-child { border-bottom: none; }
        .member-name { font-weight: bold; }
        .member-details { font-size: 12px; color: #666; }
        .member-files { font-size: 11px; color: #888; margin-top: 3px; }
        .marks-section { margin-top: 15px; }
        .marks-form { background: #f9f9f9; padding: 10px; border-radius: 5px; margin-top: 10px; }
        .marks-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .marks-table th, .marks-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .marks-table th { background: #f2f2f2; font-weight: bold; }
        .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; color: white; }
        .active { background: #28a745; }
        .locked { background: #ffc107; color: #000; }
        .archived { background: #6c757d; }
        .no-marks { color: #999; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>All Teams Report</h1>
        <p>Generated on: ' . date('d M Y H:i') . '</p>
        <p>Lecturer: ' . htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') . '</p>
    </div>';
    
    foreach ($teams as $team) {
        // Fetch team members
        $membersSql = "
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
        ";
        
        $membersStmt = $conn->prepare($membersSql);
        $membersStmt->bind_param("i", $team['team_id']);
        $membersStmt->execute();
        $members = $membersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $membersStmt->close();
        
        // Fetch team marks
        $marksSql = "
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
        ";
        
        $marksStmt = $conn->prepare($marksSql);
        $marksStmt->bind_param("i", $team['team_id']);
        $marksStmt->execute();
        $marks = $marksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $marksStmt->close();
        
        // Fetch student submissions for each member
        foreach ($members as &$member) {
            $submissionsSql = "
                SELECT 
                    s.file_path,
                    s.submitted_at,
                    a.title AS assignment_title
                FROM submissions s
                LEFT JOIN assignments a ON s.assignment_id = a.id
                WHERE s.student_id = ? AND s.file_path IS NOT NULL
                ORDER BY s.submitted_at DESC
            ";
            
            $submissionsStmt = $conn->prepare($submissionsSql);
            $submissionsStmt->bind_param("i", $member['student_id']);
            $submissionsStmt->execute();
            $member['submissions'] = $submissionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $submissionsStmt->close();
        }
        
        $html .= '<div class="team-section">
            <div class="team-header">
                <div class="team-title">' . htmlspecialchars($team['team_title']) . '</div>
                <div class="team-meta">
                    Unit: ' . htmlspecialchars($team['unit_name']) . ' (' . htmlspecialchars($team['unit_code']) . ') | 
                    Type: ' . htmlspecialchars($team['assessment_type'] ?: 'General') . ' | 
                    Created: ' . date('d M Y', strtotime($team['team_created'])) . ' | 
                    Members: ' . $team['member_count'] . ' | 
                    Status: <span class="status-badge ' . $team['status'] . '">' . ucfirst($team['status']) . '</span>
                </div>
            </div>';
        
        if ($team['description']) {
            $html .= '<p><strong>Description:</strong> ' . htmlspecialchars($team['description']) . '</p>';
        }
        
        $html .= '<div class="members-section">
            <h4>Team Members</h4>';
        
        foreach ($members as $member) {
            $html .= '<div class="member">
                <div class="member-name">' . htmlspecialchars($member['student_name']) . ' (' . htmlspecialchars(team_role_label((string)$member['role'])) . ')</div>
                <div class="member-details">
                    Reg No: ' . htmlspecialchars($member['reg_no']) . ' | 
                    Email: ' . htmlspecialchars($member['email']) . ' | 
                    Year: ' . htmlspecialchars($member['year_of_study'] ?: 'N/A') . '
                </div>';
            
            // Add submissions if any
            if (!empty($member['submissions'])) {
                $html .= '<div class="member-files">
                    📁 Submissions: ';
                $submissionNames = [];
                foreach ($member['submissions'] as $submission) {
                    $submissionNames[] = htmlspecialchars(basename($submission['file_path'])) . ' (' . htmlspecialchars($submission['assignment_title'] ?: 'Assignment') . ')';
                }
                $html .= implode(', ', $submissionNames) . '
                </div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        if (!empty($marks)) {
            // Show existing marks
            $html .= '<div class="marks-section">
                <h4>Awarded Marks</h4>
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Type</th>
                            <th>Student</th>
                            <th>Mark</th>
                            <th>Max</th>
                            <th>Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($marks as $mark) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($mark['component']) . '</td>
                    <td>' . ucfirst($mark['mark_type']) . '</td>
                    <td>' . htmlspecialchars($mark['student_name'] ?: 'Team') . '</td>
                    <td>' . number_format($mark['mark'], 2) . '</td>
                    <td>' . number_format($mark['max_mark'], 2) . '</td>
                    <td>' . date('d M Y', strtotime($mark['awarded_at'])) . '</td>
                    <td>' . htmlspecialchars($mark['notes'] ?: '-') . '</td>
                </tr>';
            }
            
            $html .= '</tbody>
                </table>
            </div>';
        } else {
            // Show marks form placeholder with checkboxes
            $html .= '<div class="marks-section">
                <h4>Marks Assessment Form</h4>
                <div class="marks-form">
                    <p class="no-marks">No marks have been awarded yet for this team.</p>
                    <p><strong>Marking Options:</strong></p>
                    
                    <!-- Group Mark Box -->
                    <div style="margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #3b82f6; border-radius: 2px; margin-right: 8px;">□</span>
                            <strong>Group Mark:</strong> Award a single mark to the entire team
                        </div>
                        <div style="font-size: 11px; color: #666; margin-left: 24px;">
                            Component: ___________________  Mark: ___ / ___  Max Mark: ___
                        </div>
                    </div>
                    
                    <!-- Individual Mark Boxes -->
                    <div style="margin: 15px 0;">
                        <strong>Individual Marks:</strong> Award separate marks to each team member
                    </div>';
            
            foreach ($members as $member) {
                $html .= '<div style="margin: 10px 0; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #3b82f6; border-radius: 2px; margin-right: 8px;">□</span>
                        <strong>' . htmlspecialchars($member['student_name']) . ' (' . htmlspecialchars(team_role_label((string)$member['role'])) . ')</strong>
                    </div>
                    <div style="font-size: 11px; color: #666; margin-left: 24px;">
                        Component: ___________________  Mark: ___ / ___  Max Mark: ___
                    </div>
                </div>';
            }
            
            $html .= '<p style="font-size: 10px; color: #888; margin-top: 15px;">
                <em>Note: Use the lecturer teams interface to fill in these marking fields and award marks.</em>
            </p>
                </div>
            </div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</body></html>';
    
    // Generate PDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Download PDF
    $dompdf->stream('all_teams_report_' . date('Y-m-d_H-i-s') . '.pdf', ['Attachment' => true]);
    
} catch (Exception $e) {
    error_log("PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    echo "Error generating PDF: " . $e->getMessage();
}
?>
