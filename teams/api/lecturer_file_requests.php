<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$lecturer_id = $_SESSION['user_id'];

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_requests') {
    // Get all file requests for this lecturer
    $stmt = $conn->prepare("
        SELECT lfr.*, 
               s.name AS student_name, s.reg_no AS student_reg,
               t.title AS team_title,
               u.name AS unit_name
        FROM lecturer_file_requests lfr
        JOIN students s ON lfr.student_id = s.id
        JOIN teams t ON lfr.team_id = t.id
        JOIN units u ON t.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE lu.lecturer_id = ?
        ORDER BY lfr.created_at DESC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'requests' => $requests]);
    exit;
}

if ($action === 'get_teams') {
    // Get all teams for this lecturer
    $stmt = $conn->prepare("
        SELECT t.id, t.title, u.name AS unit_name, u.code AS unit_code,
               COUNT(tm.id) AS member_count
        FROM teams t
        JOIN units u ON t.unit_id = u.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        WHERE t.lecturer_id = ?
        GROUP BY t.id, t.title, u.name, u.code
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'teams' => $teams]);
    exit;
}

if ($action === 'get_team_members') {
    $team_id = intval($_POST['team_id'] ?? 0);
    
    // Verify lecturer owns this team
    $verify_stmt = $conn->prepare("SELECT id FROM teams WHERE id = ? AND lecturer_id = ?");
    $verify_stmt->bind_param("ii", $team_id, $lecturer_id);
    $verify_stmt->execute();
    if ($verify_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Team not found or access denied']);
        exit;
    }
    $verify_stmt->close();
    
    // Get team members
    $stmt = $conn->prepare("
        SELECT s.id, s.name, s.reg_no, s.email, tm.role, tm.joined_at
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        WHERE tm.team_id = ?
        ORDER BY tm.role DESC, s.name ASC
    ");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'members' => $members]);
    exit;
}

if ($action === 'submit_file_request') {
    // Get JSON data
    $json_data = json_decode(file_get_contents('php://input'), true);
    
    $team_id = intval($json_data['team_id'] ?? 0);
    $student_id = intval($json_data['student_id'] ?? 0);
    $request_title = trim($json_data['request_title'] ?? '');
    $request_description = trim($json_data['request_description'] ?? '');
    $file_type = $json_data['file_type'] ?? 'other';
    
    // Validate inputs
    if (empty($team_id) || empty($student_id) || empty($request_title)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    // Verify lecturer has access to this team and student is a member
    $verify_stmt = $conn->prepare("
        SELECT t.id 
        FROM teams t
        JOIN team_members tm ON t.id = tm.team_id
        JOIN units u ON t.unit_id = u.id
        JOIN lecturer_units lu ON u.id = lu.unit_id
        WHERE t.id = ? AND lu.lecturer_id = ? AND tm.student_id = ?
    ");
    $verify_stmt->bind_param("iii", $team_id, $lecturer_id, $student_id);
    $verify_stmt->execute();
    if ($verify_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid team or student or access denied']);
        exit;
    }
    $verify_stmt->close();
    
    // Create file request
    $stmt = $conn->prepare("
        INSERT INTO lecturer_file_requests 
        (lecturer_id, team_id, student_id, request_title, request_description, file_type, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iiissss", $lecturer_id, $team_id, $student_id, $request_title, $request_description, $file_type);
    
    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        
        // Send notification to student
        $student_stmt = $conn->prepare("SELECT name, email FROM students WHERE id = ?");
        $student_stmt->bind_param("i", $student_id);
        $student_stmt->execute();
        $student = $student_stmt->get_result()->fetch_assoc();
        $student_stmt->close();
        
        if ($student) {
            // Get team info
            $team_stmt = $conn->prepare("SELECT title FROM teams WHERE id = ?");
            $team_stmt->bind_param("i", $team_id);
            $team_stmt->execute();
            $team = $team_stmt->get_result()->fetch_assoc();
            $team_stmt->close();
            
            // Create notification
            $notification_title = "File Request: $request_title";
            $notification_message = "Your lecturer has requested a file from you for team: {$team['title']}. Request: $request_description";
            $notification_link = "student/dashboard.php";
            
            $notif_stmt = $conn->prepare("
                INSERT INTO notifications (title, message, link, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $notif_stmt->bind_param("sss", $notification_title, $notification_message, $notification_link);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Send email
            send_file_request_email($student['email'], $request_title, $notification_message, $student['name'], $team['title']);
        }
        
        echo json_encode(['success' => true, 'request_id' => $request_id, 'message' => 'File request submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to submit file request']);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_file_requests') {
    $team_id = intval($_POST['team_id'] ?? 0);
    
    // Verify lecturer owns this team
    $verify_stmt = $conn->prepare("SELECT id FROM teams WHERE id = ? AND lecturer_id = ?");
    $verify_stmt->bind_param("ii", $team_id, $lecturer_id);
    $verify_stmt->execute();
    if ($verify_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Team not found or access denied']);
        exit;
    }
    $verify_stmt->close();
    
    // Get file requests for this team
    $stmt = $conn->prepare("
        SELECT lfr.*, s.name AS student_name, s.reg_no AS student_reg_no
        FROM lecturer_file_requests lfr
        JOIN students s ON lfr.student_id = s.id
        WHERE lfr.team_id = ?
        ORDER BY lfr.requested_at DESC
    ");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'requests' => $requests]);
    exit;
}

if ($action === 'update_request_status') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $response_message = trim($_POST['response_message'] ?? '');
    
    // Validate inputs
    if (empty($request_id) || !in_array($new_status, ['approved', 'rejected', 'completed'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    // Verify lecturer owns this request
    $verify_stmt = $conn->prepare("
        SELECT lfr.id 
        FROM lecturer_file_requests lfr
        JOIN teams t ON lfr.team_id = t.id
        WHERE lfr.id = ? AND t.lecturer_id = ?
    ");
    $verify_stmt->bind_param("ii", $request_id, $lecturer_id);
    $verify_stmt->execute();
    if ($verify_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Request not found or access denied']);
        exit;
    }
    $verify_stmt->close();
    
    // Update request status
    $stmt = $conn->prepare("
        UPDATE lecturer_file_requests 
        SET status = ?, responded_at = NOW(), response_message = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $new_status, $response_message, $request_id);
    
    if ($stmt->execute()) {
        // Get request and student details for notification
        $details_stmt = $conn->prepare("
            SELECT lfr.*, s.name AS student_name, s.email AS student_email, t.title AS team_title
            FROM lecturer_file_requests lfr
            JOIN students s ON lfr.student_id = s.id
            JOIN teams t ON lfr.team_id = t.id
            WHERE lfr.id = ?
        ");
        $details_stmt->bind_param("i", $request_id);
        $details_stmt->execute();
        $details = $details_stmt->get_result()->fetch_assoc();
        $details_stmt->close();
        
        if ($details) {
            // Create notification based on status
            if ($new_status === 'approved') {
                $notification_title = "File Request Approved";
                $notification_message = "Your file request '{$details['request_title']}' for team: {$details['team_title']} has been approved. {$response_message}";
            } elseif ($new_status === 'rejected') {
                $notification_title = "File Request Rejected";
                $notification_message = "Your file request '{$details['request_title']}' for team: {$details['team_title']} has been rejected. {$response_message}";
            } elseif ($new_status === 'completed') {
                $notification_title = "File Request Completed";
                $notification_message = "Your file request '{$details['request_title']}' for team: {$details['team_title']} has been completed. {$response_message}";
            }
            
            $notification_link = "student/dashboard.php";
            
            $notif_stmt = $conn->prepare("
                INSERT INTO notifications (title, message, link, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $notif_stmt->bind_param("sss", $notification_title, $notification_message, $notification_link);
            $notif_stmt->execute();
            $notif_stmt->close();
            
            // Send email
            send_file_request_update_email($details['student_email'], $notification_title, $notification_message, $details['student_name'], $details['team_title'], $new_status);
        }
        
        echo json_encode(['success' => true, 'message' => 'Request status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update request status']);
    }
    $stmt->close();
    exit;
}

function send_file_request_email($email, $request_title, $message, $student_name, $team_title) {
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
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = "File Request: $request_title";
        
        $mail->Body = "
        <html><body>
        <h2>📁 New File Request</h2>
        <p>Hello <strong>$student_name</strong>,</p>
        <p>Your lecturer has requested a file from you for your team: <strong>$team_title</strong>.</p>
        <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff;'>
            <h3 style='color: #007bff; margin-top: 0;'>Request Details:</h3>
            <p><strong>Request Title:</strong> $request_title</p>
            <p><strong>Details:</strong> $message</p>
        </div>
        <p>Please upload the requested file through your student dashboard or contact your lecturer for more information.</p>
        <p><a href='https://unilis.jhubafrica.com/student/dashboard.php' style='background-color: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Go to Dashboard</a></p>
        <hr>
        <small>UNILIS Automated File Request System</small>
        </body></html>
        ";
        
        $mail->send();
    } catch (Exception $e) {
        error_log("File request email failed: " . $mail->ErrorInfo);
    }
}

function send_file_request_update_email($email, $title, $message, $student_name, $team_title, $status) {
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
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = "File Request Update: $title";
        
        $status_color = $status === 'approved' ? '#28a745' : ($status === 'rejected' ? '#dc3545' : '#6c757d');
        $status_icon = $status === 'approved' ? '✅' : ($status === 'rejected' ? '❌' : '📁');
        
        $mail->Body = "
        <html><body>
        <h2>$status_icon File Request Update: $title</h2>
        <p>Hello <strong>$student_name</strong>,</p>
        <p>Your file request for team: <strong>$team_title</strong> has been <strong style='color: $status_color; text-transform: uppercase;'>$status</strong>.</p>
        <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid $status_color;'>
            <p><strong>Update Message:</strong> $message</p>
        </div>
        <p>You can view the status of all your requests in your student dashboard.</p>
        <p><a href='https://unilis.jhubafrica.com/student/dashboard.php' style='background-color: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>View Dashboard</a></p>
        <hr>
        <small>UNILIS Automated File Request System</small>
        </body></html>
        ";
        
        $mail->send();
    } catch (Exception $e) {
        error_log("File request update email failed: " . $mail->ErrorInfo);
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
