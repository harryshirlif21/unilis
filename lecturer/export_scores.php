<?php
require_once '../config/db.php';
session_start();

// Redirect if not logged in or not a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../index.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$assignment_id = intval($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'pdf';

if ($assignment_id <= 0) {
    $_SESSION['error'] = "Invalid assignment ID.";
    header("Location: create_questions.php");
    exit;
}

// Get assignment details
try {
    $assignment_stmt = $conn->prepare("
        SELECT a.id, a.title, a.due_date, u.name AS unit_name, u.code AS unit_code
        FROM interactive_assignments a 
        JOIN units u ON a.unit_id = u.id 
        WHERE a.id = ? AND a.lecturer_id = ?
    ");
    $assignment_stmt->bind_param("ii", $assignment_id, $lecturer_id);
    $assignment_stmt->execute();
    $assignment = $assignment_stmt->get_result()->fetch_assoc();
    $assignment_stmt->close();
    
    if (!$assignment) {
        $_SESSION['error'] = "Assignment not found or you don't have permission to view it.";
        header("Location: create_questions.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching assignment: " . $e->getMessage());
    $_SESSION['error'] = "Error loading assignment.";
    header("Location: create_questions.php");
    exit;
}

// Get student scores
try {
    $scores_stmt = $conn->prepare("
        SELECT 
            s.id as submission_id,
            s.score,
            s.submitted_at,
            st.name as student_name,
            st.reg_no,
            st.email
        FROM interactive_submissions s
        JOIN students st ON s.student_id = st.id
        WHERE s.assignment_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $scores_stmt->bind_param("i", $assignment_id);
    $scores_stmt->execute();
    $scores = $scores_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $scores_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching scores: " . $e->getMessage());
    $scores = [];
}

// Calculate statistics
$total_students = count($scores);
$total_score = array_sum(array_column($scores, 'score'));
$average_score = $total_students > 0 ? round($total_score / $total_students, 2) : 0;
$max_score = $total_students > 0 ? max(array_column($scores, 'score')) : 0;
$min_score = $total_students > 0 ? min(array_column($scores, 'score')) : 0;

if ($format === 'excel') {
    // Export as Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="scores_' . $assignment['title'] . '_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='6' style='background-color: #3498db; color: white; padding: 15px; font-size: 16px;'>" . htmlspecialchars($assignment['title']) . " - Student Scores</th></tr>";
    echo "<tr><th colspan='6' style='background-color: #ecf0f1; padding: 10px;'>Unit: " . htmlspecialchars($assignment['unit_name']) . " (" . htmlspecialchars($assignment['unit_code']) . ")</th></tr>";
    echo "<tr><th colspan='6' style='background-color: #ecf0f1; padding: 10px;'>Due Date: " . date("d M Y, h:i A", strtotime($assignment['due_date'])) . "</th></tr>";
    echo "<tr><th colspan='6' style='background-color: #ecf0f1; padding: 10px;'>Generated: " . date("d M Y, h:i A") . "</th></tr>";
    echo "<tr><th colspan='6' style='background-color: #ecf0f1; padding: 10px;'>Total Submissions: " . $total_students . " | Average Score: " . $average_score . " | Highest: " . $max_score . " | Lowest: " . $min_score . "</th></tr>";
    echo "<tr></tr>";
    echo "<tr style='background-color: #2c3e50; color: white; font-weight: bold;'>";
    echo "<th style='padding: 10px;'>Student Name</th>";
    echo "<th style='padding: 10px;'>Registration Number</th>";
    echo "<th style='padding: 10px;'>Email</th>";
    echo "<th style='padding: 10px;'>Score</th>";
    echo "<th style='padding: 10px;'>Submitted At</th>";
    echo "<th style='padding: 10px;'>Grade</th>";
    echo "</tr>";
    
    foreach ($scores as $score) {
        $grade = '';
        if ($score['score'] >= 80) $grade = 'A';
        elseif ($score['score'] >= 70) $grade = 'B';
        elseif ($score['score'] >= 60) $grade = 'C';
        elseif ($score['score'] >= 50) $grade = 'D';
        else $grade = 'F';
        
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($score['student_name']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($score['reg_no']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($score['email']) . "</td>";
        echo "<td style='padding: 8px; text-align: center; font-weight: bold;'>" . $score['score'] . "</td>";
        echo "<td style='padding: 8px;'>" . date("d M Y, h:i A", strtotime($score['submitted_at'])) . "</td>";
        echo "<td style='padding: 8px; text-align: center; font-weight: bold;'>" . $grade . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

// Export as PDF
require_once '../vendor/autoload.php';

try {
    $pdf = new \Dompdf\Dompdf();
    $pdf->setPaper('A4', 'landscape');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .title { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
            .subtitle { font-size: 16px; color: #666; margin-bottom: 5px; }
            .stats { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .stats h3 { margin: 0 0 10px 0; color: #2c3e50; }
            .stats p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #2c3e50; color: white; font-weight: bold; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .score { text-align: center; font-weight: bold; }
            .grade { text-align: center; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">' . htmlspecialchars($assignment['title']) . '</div>
            <div class="subtitle">Unit: ' . htmlspecialchars($assignment['unit_name']) . ' (' . htmlspecialchars($assignment['unit_code']) . ')</div>
            <div class="subtitle">Due Date: ' . date("d M Y, h:i A", strtotime($assignment['due_date'])) . '</div>
        </div>
        
        <div class="stats">
            <h3>Statistics</h3>
            <p><strong>Total Submissions:</strong> ' . $total_students . '</p>
            <p><strong>Average Score:</strong> ' . $average_score . '</p>
            <p><strong>Highest Score:</strong> ' . $max_score . '</p>
            <p><strong>Lowest Score:</strong> ' . $min_score . '</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Registration Number</th>
                    <th>Email</th>
                    <th>Score</th>
                    <th>Grade</th>
                    <th>Submitted At</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($scores as $score) {
        $grade = '';
        if ($score['score'] >= 80) $grade = 'A';
        elseif ($score['score'] >= 70) $grade = 'B';
        elseif ($score['score'] >= 60) $grade = 'C';
        elseif ($score['score'] >= 50) $grade = 'D';
        else $grade = 'F';
        
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($score['student_name']) . '</td>
                    <td>' . htmlspecialchars($score['reg_no']) . '</td>
                    <td>' . htmlspecialchars($score['email']) . '</td>
                    <td class="score">' . $score['score'] . '</td>
                    <td class="grade">' . $grade . '</td>
                    <td>' . date("d M Y, h:i A", strtotime($score['submitted_at'])) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            Generated on ' . date("d M Y, h:i A") . ' | UNILIS Learning Management System
        </div>
    </body>
    </html>';
    
    $pdf->loadHtml($html);
    $pdf->render();
    
    $filename = 'scores_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $assignment['title']) . '_' . date('Y-m-d') . '.pdf';
    $pdf->stream($filename, array("Attachment" => true));
    
} catch (Exception $e) {
    error_log("PDF generation error: " . $e->getMessage());
    $_SESSION['error'] = "Error generating PDF. Please try again.";
    header("Location: view_scores.php?id=" . $assignment_id);
    exit;
}
?>
