<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_system.php';

/**
 * Enhanced Student Attendance System
 * Generates unique codes for each student with 2-minute expiry
 */

/**
 * Create enhanced attendance session with individual student codes
 * @param int $unit_id Unit ID
 * @param int $lecturer_id Lecturer ID
 * @param int $duration_minutes Duration in minutes
 * @param bool $send_email Whether to send emails
 * @return array Session details
 */
function createEnhancedAttendanceSession($conn, $unit_id, $lecturer_id, $duration_minutes, $send_email = false) {
    try {
        // Get unit and course info
        $unit_stmt = $conn->prepare("SELECT name, course_id FROM units WHERE id = ?");
        $unit_stmt->bind_param("i", $unit_id);
        $unit_stmt->execute();
        $unit = $unit_stmt->get_result()->fetch_assoc();
        $unit_stmt->close();

        if (!$unit) {
            throw new Exception("Unit not found");
        }

        // Create attendance session
        $session_deadline = date('Y-m-d H:i:s', time() + ($duration_minutes * 60));
        
        $session_stmt = $conn->prepare("
            INSERT INTO attendance_sessions 
            (unit_id, lecturer_id, session_code, duration_minutes, deadline, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        // Generate main session code (for reference)
        $main_session_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $session_stmt->bind_param("iisis", $unit_id, $lecturer_id, $main_session_code, $duration_minutes, $session_deadline);
        $session_stmt->execute();
        $session_id = $conn->insert_id;
        $session_stmt->close();

        // Get all students enrolled in this specific unit
        $students_stmt = $conn->prepare("
            SELECT s.id, s.name, s.email 
            FROM students s
            JOIN student_unit_enrollments sue ON s.id = sue.student_id
            WHERE sue.unit_id = ? AND s.is_verified = 1
        ");
        $students_stmt->bind_param("i", $unit_id);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();
        
        $students = [];
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $students_stmt->close();

        if (empty($students)) {
            return [
                'session_id' => $session_id,
                'session_code' => $main_session_code,
                'deadline' => $session_deadline,
                'unit_name' => $unit['name'],
                'students_count' => 0,
                'codes_generated' => 0
            ];
        }

        // Generate unique 6-digit codes for each student (2-minute expiry)
        $student_codes = [];
        $code_expiry = date('Y-m-d H:i:s', time() + 120); // 2 minutes from now
        
        foreach ($students as $student) {
            // Generate unique code for this student
            do {
                $student_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Check if this code already exists for this session
                $check_stmt = $conn->prepare("
                    SELECT COUNT(*) as count FROM student_attendance_codes 
                    WHERE session_id = ? AND code = ?
                ");
                $check_stmt->bind_param("is", $session_id, $student_code);
                $check_stmt->execute();
                $exists = $check_stmt->get_result()->fetch_assoc()['count'] > 0;
                $check_stmt->close();
            } while ($exists);
            
            // Store student's unique code
            $insert_stmt = $conn->prepare("
                INSERT INTO student_attendance_codes 
                (session_id, student_id, code, expires_at, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $insert_stmt->bind_param("iiss", $session_id, $student['id'], $student_code, $code_expiry);
            $insert_stmt->execute();
            $insert_stmt->close();

            $record_stmt = $conn->prepare("
                INSERT INTO attendance_records (session_id, student_id, attended, attended_at, created_at)
                VALUES (?, ?, 0, NULL, NOW())
                ON DUPLICATE KEY UPDATE session_id = session_id
            ");
            $record_stmt->bind_param("ii", $session_id, $student['id']);
            $record_stmt->execute();
            $record_stmt->close();
            
            $student_codes[] = [
                'student_id' => $student['id'],
                'student_name' => $student['name'],
                'student_email' => $student['email'],
                'code' => $student_code,
                'expires_at' => $code_expiry
            ];
        }

        // Send notifications to all students
        $notification_title = "Attendance Session Started";
        $notification_message = "Your lecturer has started an attendance session for {$unit['name']}. Your personal code is valid for 2 minutes.";
        $notification_link = "student/dashboard.php?attendance_session={$session_id}";
        
        $recipients = [];
        foreach ($students as $student) {
            $recipients[] = [
                'email' => $student['email'],
                'name' => $student['name']
            ];
        }
        
        // Create notifications for each student
        $notif_stmt = $conn->prepare("
            INSERT INTO notifications 
            (user_id, user_role, title, message, link, attendance_session_id, is_read, created_at) 
            VALUES (?, 'student', ?, ?, ?, ?, 0, NOW())
        ");
        
        foreach ($students as $student) {
            $notif_stmt->bind_param("isssi", $student['id'], $notification_title, $notification_message, $notification_link, $session_id);
            $notif_stmt->execute();
        }
        $notif_stmt->close();
        
        // Send emails if requested
        $email_results = ['success' => 0, 'failed' => 0];
        if ($send_email) {
            $email_subject = "📋 Attendance Code: {$unit['name']}";
            $email_results = send_bulk_attendance_emails($student_codes, $unit['name'], $session_deadline, $email_subject);
        }

        return [
            'session_id' => $session_id,
            'session_code' => $main_session_code,
            'deadline' => $session_deadline,
            'unit_name' => $unit['name'],
            'students_count' => count($students),
            'codes_generated' => count($student_codes),
            'student_codes' => $student_codes,
            'email_results' => $email_results
        ];
        
    } catch (Exception $e) {
        error_log("Error creating enhanced attendance session: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Send bulk attendance emails with individual codes
 * @param array $student_codes Array of student codes
 * @param string $unit_name Unit name
 * @param string $deadline Session deadline
 * @param string $subject Email subject
 * @return array Results
 */
function send_bulk_attendance_emails($student_codes, $unit_name, $deadline, $subject) {
    $results = ['success' => 0, 'failed' => 0, 'errors' => []];
    
    foreach ($student_codes as $student) {
        $success = send_student_attendance_email(
            $student['student_email'],
            $student['student_name'],
            $student['code'],
            $unit_name,
            $deadline
        );
        
        if ($success) {
            $results['success']++;
        } else {
            $results['failed']++;
            $results['errors'][] = "Failed to send to: {$student['student_email']}";
        }
    }
    
    return $results;
}

/**
 * Send individual attendance email to student
 * @param string $email Student email
 * @param string $name Student name
 * @param string $code Student's unique code
 * @param string $unit_name Unit name
 * @param string $deadline Session deadline
 * @return bool Success status
 */
function send_student_attendance_email($email, $name, $code, $unit_name, $deadline) {
    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "📋 Your Attendance Code: {$unit_name}";

        $deadline_formatted = date('h:i A', strtotime($deadline));
        $expiry_time = date('h:i A', strtotime('+2 minutes'));

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; background: #f4f4f4; margin: 0; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0; font-size: 24px;'>📋 Attendance Code</h1>
                        <p style='margin: 5px 0 0; opacity: 0.9;'>Your personal code for marking attendance</p>
                    </div>
                    <div style='padding: 30px; text-align: center;'>
                        <p>Dear <strong>{$name}</strong>,</p>
                        <p>Your lecturer has started an attendance session for <strong>{$unit_name}</strong>.</p>
                        
                        <div style='background: #f8f9fa; padding: 30px; border-radius: 12px; margin: 25px 0; border: 2px solid #e9ecef;'>
                            <h2 style='margin: 0 0 15px; color: #2c3e50;'>Your Personal Code:</h2>
                            <div style='font-size: 48px; font-weight: bold; color: #f5576c; letter-spacing: 8px; margin: 20px 0; font-family: monospace; background: #fff; padding: 20px; border-radius: 8px; border: 2px solid #f5576c;'>
                                {$code}
                            </div>
                            <p style='margin: 15px 0 5px; color: #666;'><strong>⏰ Valid for 2 minutes only!</strong></p>
                            <p style='margin: 5px 0; color: #666;'>Expires at: <strong>{$expiry_time}</strong></p>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='https://unilis.jhubafrica.com/student/dashboard.php' 
                               style='background: #f5576c; color: white; padding: 15px 30px; 
                                      text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;
                                      display: inline-block; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                📝 Mark Attendance Now
                            </a>
                        </div>
                        
                        <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                            <p style='margin: 0; color: #856404;'><strong>⚡ Quick Instructions:</strong></p>
                            <ol style='margin: 10px 0 0 20px; color: #666;'>
                                <li>Go to your student dashboard</li>
                                <li>Click on the attendance notification</li>
                                <li>Enter your personal code above</li>
                                <li>Complete within 2 minutes!</li>
                            </ol>
                        </div>
                        
                        <p style='color: #666; font-size: 14px;'>If you have any issues, contact your lecturer immediately.</p>
                        
                        <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                        <p style='color: #7f8c8d; font-size: 12px;'>© UNILIS — This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "
Dear {$name},

Your lecturer has started an attendance session for {$unit_name}.

Your personal attendance code is: {$code}
This code is valid for 2 minutes only and expires at {$expiry_time}.

Please go to your student dashboard to mark your attendance immediately.

© UNILIS
        ";

        $mail->send();
        error_log("Student attendance email sent successfully to: $email (code: $code)");
        return true;
    } catch (Exception $e) {
        error_log("Student attendance email failed to: $email - " . $e->getMessage());
        return false;
    }
}

/**
 * Validate student attendance code
 * @param int $student_id Student ID
 * @param string $code Attendance code
 * @param int $session_id Session ID
 * @return array Validation result
 */
function validateStudentAttendanceCode($conn, $student_id, $code, $session_id) {
    try {
        // Check if code exists and is valid
        $stmt = $conn->prepare("
            SELECT sac.*, COALESCE(asr.attended, 0) AS attended, asr.attended_at
            FROM student_attendance_codes sac
            LEFT JOIN attendance_records asr ON sac.session_id = asr.session_id AND sac.student_id = asr.student_id
            WHERE sac.student_id = ? 
            AND sac.code = ? 
            AND sac.session_id = ?
            AND sac.expires_at > NOW()
            AND sac.used_at IS NULL
        ");
        $stmt->bind_param("isi", $student_id, $code, $session_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            return [
                'success' => false,
                'valid' => false,
                'message' => 'Invalid or expired code',
                'code_type' => 'invalid'
            ];
        }

        if ((int) $result['attended'] === 1) {
            return [
                'success' => true,
                'valid' => true,
                'message' => 'You have already marked attendance for this session',
                'code_type' => 'already_marked',
                'attended_at' => $result['attended_at']
            ];
        }

        // Mark code as used
        $use_stmt = $conn->prepare("
            UPDATE student_attendance_codes 
            SET used_at = NOW() 
            WHERE student_id = ? AND code = ? AND session_id = ?
        ");
        $use_stmt->bind_param("isi", $student_id, $code, $session_id);
        $use_stmt->execute();
        $use_stmt->close();

        $attend_stmt = $conn->prepare("
            INSERT INTO attendance_records (session_id, student_id, attended, attended_at, created_at)
            VALUES (?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE attended = 1, attended_at = NOW()
        ");
        $attend_stmt->bind_param("ii", $session_id, $student_id);
        $attend_stmt->execute();
        $attend_stmt->close();

        return [
            'success' => true,
            'valid' => true,
            'message' => 'Attendance marked successfully',
            'code_type' => 'success',
            'attended_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        error_log("Error validating student attendance code: " . $e->getMessage());
        return [
            'success' => false,
            'valid' => false,
            'message' => 'System error. Please try again.',
            'code_type' => 'error'
        ];
    }
}

/**
 * Submit attendance using personal (enhanced) or shared (legacy) session code.
 */
function submitAttendance($conn, $session_id, $student_id, $code) {
    $session_id = (int) $session_id;
    $student_id = (int) $student_id;
    $code = trim($code);

    if ($session_id <= 0 || $student_id <= 0 || $code === '') {
        return ['success' => false, 'message' => 'Missing required fields.'];
    }

    $enhanced = validateStudentAttendanceCode($conn, $student_id, $code, $session_id);
    if (!empty($enhanced['success'])) {
        return ['success' => true, 'message' => $enhanced['message']];
    }

    $stmt = $conn->prepare("
        SELECT session_code, deadline FROM attendance_sessions WHERE id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        return ['success' => false, 'message' => 'Attendance session not found.'];
    }

    if (strtotime($session['deadline']) < time()) {
        return ['success' => false, 'message' => 'Attendance session has expired.'];
    }

    if (!hash_equals((string) $session['session_code'], $code)) {
        return ['success' => false, 'message' => $enhanced['message'] ?? 'Invalid or expired code'];
    }

    $record_stmt = $conn->prepare("
        SELECT attended FROM attendance_records WHERE session_id = ? AND student_id = ? LIMIT 1
    ");
    $record_stmt->bind_param("ii", $session_id, $student_id);
    $record_stmt->execute();
    $record = $record_stmt->get_result()->fetch_assoc();
    $record_stmt->close();

    if (!$record) {
        return ['success' => false, 'message' => 'You are not enrolled for this attendance session.'];
    }

    if ((int) $record['attended'] === 1) {
        return ['success' => true, 'message' => 'You have already marked attendance for this session.'];
    }

    $update_stmt = $conn->prepare("
        UPDATE attendance_records SET attended = 1, attended_at = NOW()
        WHERE session_id = ? AND student_id = ?
    ");
    $update_stmt->bind_param("ii", $session_id, $student_id);
    $update_stmt->execute();
    $update_stmt->close();

    return ['success' => true, 'message' => 'Attendance marked successfully!'];
}

/**
 * Get student's active attendance sessions
 * @param int $student_id Student ID
 * @return array Active sessions
 */
function getStudentActiveAttendanceSessions($conn, $student_id) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                ats.id AS session_id,
                ats.session_code AS main_code,
                u.name AS unit_name,
                ats.deadline,
                ats.created_at,
                sac.code AS student_code,
                sac.expires_at,
                sac.used_at,
                COALESCE(ar.attended, 0) AS attended,
                ar.attended_at
            FROM attendance_sessions ats
            JOIN units u ON ats.unit_id = u.id
            JOIN student_unit_enrollments sue ON sue.unit_id = ats.unit_id AND sue.student_id = ?
            LEFT JOIN attendance_records ar ON ats.id = ar.session_id AND ar.student_id = ?
            LEFT JOIN student_attendance_codes sac ON sac.id = (
                SELECT sac2.id FROM student_attendance_codes sac2
                WHERE sac2.session_id = ats.id AND sac2.student_id = ?
                ORDER BY sac2.created_at DESC
                LIMIT 1
            )
            WHERE ats.deadline > NOW()
            AND COALESCE(ar.attended, 0) = 0
            ORDER BY ats.deadline ASC
        ");
        $stmt->bind_param("iii", $student_id, $student_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
        
        $stmt->close();
        return $sessions;
        
    } catch (Exception $e) {
        error_log("Error getting student attendance sessions: " . $e->getMessage());
        return [];
    }
}

/**
 * Request new attendance code for student
 * @param int $student_id Student ID
 * @param int $session_id Session ID
 * @return array Result
 */
function requestNewAttendanceCode($conn, $student_id, $session_id) {
    try {
        // Check if session is still active
        $session_stmt = $conn->prepare("
            SELECT deadline FROM attendance_sessions 
            WHERE id = ? AND deadline > NOW()
        ");
        $session_stmt->bind_param("i", $session_id);
        $session_stmt->execute();
        $session = $session_stmt->get_result()->fetch_assoc();
        $session_stmt->close();

        if (!$session) {
            return [
                'success' => false,
                'message' => 'Attendance session has ended'
            ];
        }

        // Check if student already has a valid unused code
        $code_stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM student_attendance_codes 
            WHERE student_id = ? AND session_id = ? AND expires_at > NOW() AND used_at IS NULL
        ");
        $code_stmt->bind_param("ii", $student_id, $session_id);
        $code_stmt->execute();
        $has_valid_code = $code_stmt->get_result()->fetch_assoc()['count'] > 0;
        $code_stmt->close();

        if ($has_valid_code) {
            return [
                'success' => false,
                'message' => 'You already have a valid code for this session'
            ];
        }

        // Generate new unique code
        do {
            $new_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) as count FROM student_attendance_codes 
                WHERE session_id = ? AND code = ? AND expires_at > NOW()
            ");
            $check_stmt->bind_param("is", $session_id, $new_code);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_assoc()['count'] > 0;
            $check_stmt->close();
        } while ($exists);

        // Insert new code
        $new_expiry = date('Y-m-d H:i:s', time() + 120); // 2 minutes
        $insert_stmt = $conn->prepare("
            INSERT INTO student_attendance_codes 
            (session_id, student_id, code, expires_at, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insert_stmt->bind_param("iiss", $session_id, $student_id, $new_code, $new_expiry);
        $insert_stmt->execute();
        $insert_stmt->close();

        // Get student details for email
        $student_stmt = $conn->prepare("SELECT name, email FROM students WHERE id = ?");
        $student_stmt->bind_param("i", $student_id);
        $student_stmt->execute();
        $student = $student_stmt->get_result()->fetch_assoc();
        $student_stmt->close();

        // Get unit details
        $unit_stmt = $conn->prepare("
            SELECT u.name FROM units u 
            JOIN attendance_sessions ats ON u.id = ats.unit_id 
            WHERE ats.id = ?
        ");
        $unit_stmt->bind_param("i", $session_id);
        $unit_stmt->execute();
        $unit = $unit_stmt->get_result()->fetch_assoc();
        $unit_stmt->close();

        // Send email with new code
        if ($student && $unit) {
            send_student_attendance_email(
                $student['email'],
                $student['name'],
                $new_code,
                $unit['name'],
                $session['deadline']
            );
        }

        return [
            'success' => true,
            'message' => 'New code sent to your email',
            'code' => $new_code,
            'expires_at' => $new_expiry
        ];
        
    } catch (Exception $e) {
        error_log("Error requesting new attendance code: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'System error. Please try again.'
        ];
    }
}
?>
