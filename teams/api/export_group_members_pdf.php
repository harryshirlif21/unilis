<?php
// teams/api/export_group_members_pdf.php

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    die('Unauthorized access');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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
    $teamId = (int) ($_GET['team_id'] ?? 0);
    $lecturerId = (int) $_SESSION['user_id'];

    if ($teamId > 0) {
        $teamSql = "
            SELECT
                t.id,
                t.title AS team_title,
                t.created_at,
                t.status,
                u.name AS unit_name,
                u.code AS unit_code
            FROM teams t
            JOIN units u ON t.unit_id = u.id
            JOIN lecturer_units lu ON lu.unit_id = t.unit_id
            WHERE t.id = ? AND lu.lecturer_id = ?
            LIMIT 1
        ";
        $teamStmt = $conn->prepare($teamSql);
        if (!$teamStmt) {
            throw new Exception('Failed to prepare team lookup query: ' . $conn->error);
        }
        $teamStmt->bind_param('ii', $teamId, $lecturerId);
        $teamStmt->execute();
        $teams = $teamStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $teamStmt->close();
    } else {
        $teamSql = "
            SELECT
                t.id,
                t.title AS team_title,
                t.created_at,
                t.status,
                u.name AS unit_name,
                u.code AS unit_code
            FROM teams t
            JOIN units u ON t.unit_id = u.id
            JOIN lecturer_units lu ON lu.unit_id = t.unit_id
            WHERE lu.lecturer_id = ?
            ORDER BY u.code ASC, t.title ASC
        ";
        $teamStmt = $conn->prepare($teamSql);
        if (!$teamStmt) {
            throw new Exception('Failed to prepare teams query: ' . $conn->error);
        }
        $teamStmt->bind_param('i', $lecturerId);
        $teamStmt->execute();
        $teams = $teamStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $teamStmt->close();
    }

    if (empty($teams)) {
        throw new Exception('No accessible teams found for this export');
    }

    // Auto-assign leader for teams that do not yet have one.
    foreach ($teams as $t) {
        $currentTeamId = (int)($t['id'] ?? 0);
        if ($currentTeamId <= 0) {
            continue;
        }

        $leadCheckSql = "
            SELECT COUNT(*) AS lead_count
            FROM team_members
            WHERE team_id = ? AND LOWER(COALESCE(role, '')) = 'leader'
        ";
        $leadCheckStmt = $conn->prepare($leadCheckSql);
        if (!$leadCheckStmt) {
            continue;
        }
        $leadCheckStmt->bind_param('i', $currentTeamId);
        $leadCheckStmt->execute();
        $leadCountRow = $leadCheckStmt->get_result()->fetch_assoc();
        $leadCheckStmt->close();

        if ((int)($leadCountRow['lead_count'] ?? 0) > 0) {
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
        $firstMemberStmt->bind_param('i', $currentTeamId);
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
        $assignLeadStmt->bind_param('ii', $currentTeamId, $firstStudentId);
        $assignLeadStmt->execute();
        $assignLeadStmt->close();
    }

    // Fetch members per team.
    $teamsWithMembers = [];
    foreach ($teams as $t) {
        $currentTeamId = (int)($t['id'] ?? 0);
        $membersSql = "
            SELECT
                s.name AS student_name,
                s.reg_no,
                s.email,
                s.phone,
                s.year_of_study,
                tm.role,
                tm.joined_at
            FROM team_members tm
            JOIN students s ON s.id = tm.student_id
            WHERE tm.team_id = ?
            ORDER BY (LOWER(COALESCE(tm.role, '')) = 'leader') DESC, tm.joined_at ASC, s.name ASC
        ";
        $membersStmt = $conn->prepare($membersSql);
        if (!$membersStmt) {
            throw new Exception('Failed to prepare member query: ' . $conn->error);
        }
        $membersStmt->bind_param('i', $currentTeamId);
        $membersStmt->execute();
        $members = $membersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $membersStmt->close();

        $leaderCount = 0;
        foreach ($members as $member) {
            if (strtolower((string)($member['role'] ?? '')) === 'leader') {
                $leaderCount++;
            }
        }

        $teamsWithMembers[] = [
            'team' => $t,
            'members' => $members,
            'leader_count' => $leaderCount,
        ];
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Group Members Export</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #111827; }
            h1 { margin: 0 0 8px 0; color: #0f172a; }
            .meta { margin: 0 0 18px 0; color: #475569; font-size: 13px; }
            .meta-row { margin: 3px 0; }
            .team-block { margin-top: 20px; page-break-inside: avoid; }
            .team-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
            .team-subtitle { font-size: 12px; color: #475569; margin-bottom: 8px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 12px; }
            th { background: #f1f5f9; color: #0f172a; }
            .leader-row { background: #ecfdf5; }
            .footer { margin-top: 18px; color: #64748b; font-size: 11px; }
        </style>
    </head>
    <body>
        <h1>Group Members List</h1>
        <div class="meta">
            <div class="meta-row"><strong>Lecturer:</strong> ' . htmlspecialchars((string)($_SESSION['user_name'] ?? 'Lecturer')) . '</div>
            <div class="meta-row"><strong>Total Teams:</strong> ' . count($teamsWithMembers) . '</div>
        </div>';

    foreach ($teamsWithMembers as $bundle) {
        $team = $bundle['team'];
        $members = $bundle['members'];
        $leaderCount = (int)$bundle['leader_count'];

        $html .= '
        <div class="team-block">
            <div class="team-title">' . htmlspecialchars($team['team_title']) . '</div>
            <div class="team-subtitle">
                Unit: ' . htmlspecialchars($team['unit_name']) . ' (' . htmlspecialchars($team['unit_code']) . ') | 
                Status: ' . htmlspecialchars(ucfirst((string)$team['status'])) . ' | 
                Created: ' . date('d M Y', strtotime((string)$team['created_at'])) . ' | 
                Members: ' . count($members) . ' | Leads: ' . $leaderCount . '
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Reg No</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Year</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>';

        if (empty($members)) {
            $html .= '
                    <tr>
                        <td colspan="8" style="text-align:center;color:#64748b;">No members found for this team.</td>
                    </tr>';
        } else {
            $index = 1;
            foreach ($members as $member) {
                $rowClass = (strtolower((string)($member['role'] ?? '')) === 'leader') ? 'leader-row' : '';
                $html .= '
                    <tr class="' . $rowClass . '">
                        <td>' . $index++ . '</td>
                        <td>' . htmlspecialchars((string)$member['student_name']) . '</td>
                        <td>' . htmlspecialchars((string)$member['reg_no']) . '</td>
                        <td>' . htmlspecialchars(team_role_label((string)($member['role'] ?? 'member'))) . '</td>
                        <td>' . htmlspecialchars((string)$member['email']) . '</td>
                        <td>' . htmlspecialchars((string)($member['phone'] ?? 'N/A')) . '</td>
                        <td>' . htmlspecialchars((string)($member['year_of_study'] ?? 'N/A')) . '</td>
                        <td>' . (!empty($member['joined_at']) ? date('d M Y', strtotime((string)$member['joined_at'])) : 'N/A') . '</td>
                    </tr>';
            }
        }

        $html .= '
                </tbody>
            </table>
        </div>';
    }

    $html .= '
        <div class="footer">Generated on ' . date('d M Y H:i') . ' by UniLIS</div>
    </body>
    </html>';

    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = ($teamId > 0)
        ? 'group_members_team_' . $teamId . '_' . date('Y-m-d_H-i-s') . '.pdf'
        : 'group_members_all_teams_' . date('Y-m-d_H-i-s') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
} catch (Exception $e) {
    error_log('Group Members PDF Export Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error generating group members PDF: ' . $e->getMessage();
}
