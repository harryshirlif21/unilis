<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$student_id = $_SESSION['user_id'];

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_my_file_requests') {
    // Get all file requests for this student
    $stmt = $conn->prepare("
        SELECT lfr.*, t.title AS team_title, l.name AS lecturer_name
        FROM lecturer_file_requests lfr
        JOIN teams t ON lfr.team_id = t.id
        JOIN lecturers l ON t.lecturer_id = l.id
        WHERE lfr.student_id = ?
        ORDER BY lfr.requested_at DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'requests' => $requests]);
    exit;
}

if ($action === 'upload_requested_file') {
    $request_id = intval($_POST['request_id'] ?? 0);
    
    // Validate request exists and belongs to this student
    $verify_stmt = $conn->prepare("
        SELECT lfr.id, lfr.status 
        FROM lecturer_file_requests lfr
        WHERE lfr.id = ? AND lfr.student_id = ?
    ");
    $verify_stmt->bind_param("ii", $request_id, $student_id);
    $verify_stmt->execute();
    $request = $verify_stmt->get_result()->fetch_assoc();
    $verify_stmt->close();
    
    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }
    
    if ($request['status'] !== 'approved') {
        echo json_encode(['success' => false, 'error' => 'Request has not been approved']);
        exit;
    }
    
    // Handle file upload
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload error: ' . $file['error']]);
        exit;
    }
    
    // Create uploads directory for requested files
    $upload_dir = "assets/uploads/requested_files/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = time() . "_" . basename($file['name']);
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Update request status to completed
        $update_stmt = $conn->prepare("
            UPDATE lecturer_file_requests 
            SET status = 'completed', responded_at = NOW(), response_message = 'File uploaded successfully'
            WHERE id = ?
        ");
        $update_stmt->bind_param("i", $request_id);
        
        if ($update_stmt->execute()) {
            // Get request details for notification
            $details_stmt = $conn->prepare("
                SELECT lfr.*, t.title AS team_title, l.name AS lecturer_name
                FROM lecturer_file_requests lfr
                JOIN teams t ON lfr.team_id = t.id
                JOIN lecturers l ON t.lecturer_id = l.id
                WHERE lfr.id = ?
            ");
            $details_stmt->bind_param("i", $request_id);
            $details_stmt->execute();
            $details = $details_stmt->get_result()->fetch_assoc();
            $details_stmt->close();
            
            if ($details) {
                // Notify lecturer
                $notification_title = "File Request Completed";
                $notification_message = "Student {$details['student_name']} has uploaded the requested file for team: {$details['team_title']}. Request: {$details['request_title']}";
                $notification_link = "lecturer/dashboard.php";
                
                $notif_stmt = $conn->prepare("
                    INSERT INTO notifications (title, message, link, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $notif_stmt->bind_param("sss", $notification_title, $notification_message, $notification_link);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                // Send email to lecturer
                send_file_completed_email($details['lecturer_name'], $notification_title, $notification_message, $details['student_name'], $details['team_title'], $details['request_title']);
            }
            
            echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update request status']);
        }
        $update_stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
    }
    exit;
}

function send_file_completed_email($lecturer_email, $title, $message, $student_name, $team_title, $request_title) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'unilis512@gmail.com';
        $mail->Password   = 'sbmxmiafbtfkmkck';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('unilis512@gmail.com', 'UNILIS File Request System');
        $mail->addAddress($lecturer_email);
        
        $mail->isHTML(true);
        $mail->Subject = "File Request Completed: $title";
        
        $mail->Body = "
        <html><body>
        <h2>📁 File Request Completed</h2>
        <p>Hello <strong>Lecturer</strong>,</p>
        <p>Student <strong>$student_name</strong> has uploaded the requested file for team: <strong>$team_title</strong>.</p>
        <div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;'>
            <h3 style='color: #28a745; margin-top: 0;'>Request Details:</h3>
            <p><strong>Request Title:</strong> $request_title</p>
            <p><strong>Student:</strong> $student_name</p>
            <p><strong>Team:</strong> $team_title</p>
        </div>
        <p>The uploaded file is now available in the team files section.</p>
        <p><a href='https://unilis.jhubafrica.com/lecturer/dashboard.php' style='background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>View in Dashboard</a></p>
        <hr>
        <small>UNILIS Automated File Request System</small>
        </body></html>
        ";
        
        $mail->send();
    } catch (Exception $e) {
        error_log("File completion email failed: " . $mail->ErrorInfo);
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
