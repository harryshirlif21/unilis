<?php
// Excel export for all teams data
session_start();

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(401);
    die('Unauthorized access');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/ensure_team_marks.php';
require_once __DIR__ . '/../../vendor/autoload.php';

ensure_team_marks_table($conn);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
    
    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set title
    $sheet->setCellValue('A1', 'All Teams Report - UniLIS');
    $sheet->setCellValue('A2', 'Generated on: ' . date('d M Y H:i'));
    $sheet->setCellValue('A3', 'Lecturer: ' . ($_SESSION['user_name'] ?? 'Unknown'));
    
    // Style title
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A2:A3')->getFont()->setSize(11);
    
    $row = 5;
    
    foreach ($teams as $team) {
        // Team header
        $sheet->setCellValue('A' . $row, 'Team: ' . $team['team_title']);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $row++;
        
        // Team details
        $sheet->setCellValue('A' . $row, 'Unit: ' . $team['unit_name'] . ' (' . $team['unit_code'] . ')');
        $sheet->setCellValue('B' . $row, 'Type: ' . ($team['assessment_type'] ?: 'General'));
        $sheet->setCellValue('C' . $row, 'Status: ' . ucfirst($team['status']));
        $sheet->setCellValue('D' . $row, 'Created: ' . date('d M Y', strtotime($team['team_created'])));
        $sheet->setCellValue('E' . $row, 'Members: ' . $team['member_count']);
        $row++;
        
        if ($team['description']) {
            $sheet->setCellValue('A' . $row, 'Description: ' . $team['description']);
            $sheet->mergeCells('A' . $row . ':E' . $row);
            $row++;
        }
        
        $row++;
        
        // Members section
        $sheet->setCellValue('A' . $row, 'Team Members');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        // Members header
        $sheet->setCellValue('A' . $row, 'Name');
        $sheet->setCellValue('B' . $row, 'Role');
        $sheet->setCellValue('C' . $row, 'Reg No');
        $sheet->setCellValue('D' . $row, 'Email');
        $sheet->setCellValue('E' . $row, 'Year');
        
        // Style members header
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        $sheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
        
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
        
        foreach ($members as $member) {
            $sheet->setCellValue('A' . $row, $member['student_name']);
            $sheet->setCellValue('B' . $row, ucfirst($member['role']));
            $sheet->setCellValue('C' . $row, $member['reg_no']);
            $sheet->setCellValue('D' . $row, $member['email']);
            $sheet->setCellValue('E' . $row, $member['year_of_study'] ?: 'N/A');
            $sheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }
        
        $row++;
        
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
        
        if (!empty($marks)) {
            // Marks section
            $sheet->setCellValue('A' . $row, 'Awarded Marks');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
            
            // Marks header
            $sheet->setCellValue('A' . $row, 'Component');
            $sheet->setCellValue('B' . $row, 'Type');
            $sheet->setCellValue('C' . $row, 'Student');
            $sheet->setCellValue('D' . $row, 'Mark');
            $sheet->setCellValue('E' . $row, 'Max Mark');
            $sheet->setCellValue('F' . $row, 'Date');
            
            // Style marks header
            $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
            $sheet->getStyle('A' . $row . ':F' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
            
            foreach ($marks as $mark) {
                $sheet->setCellValue('A' . $row, $mark['component']);
                $sheet->setCellValue('B' . $row, ucfirst($mark['mark_type']));
                $sheet->setCellValue('C' . $row, $mark['student_name'] ?: 'Team');
                $sheet->setCellValue('D' . $row, $mark['mark']);
                $sheet->setCellValue('E' . $row, $mark['max_mark']);
                $sheet->setCellValue('F' . $row, date('d M Y', strtotime($mark['awarded_at'])));
                $sheet->getStyle('A' . $row . ':F' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $row++;
            }
        }
        
        $row += 2; // Space between teams
    }
    
    // Auto-size columns
    foreach (range('A', 'F') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="all_teams_report_' . date('Y-m-d_H-i-s') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    // Create writer and save
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    error_log("Excel Export Error: " . $e->getMessage());
    http_response_code(500);
    echo "Error generating Excel: " . $e->getMessage();
}
?>
