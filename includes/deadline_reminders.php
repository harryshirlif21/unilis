<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_system.php';

/**
 * Deadline Reminder System
 * Sends email reminders to students 24 hours and 12 hours before assignment deadlines
 */

/**
 * Check and send deadline reminders
 * This function should be called by a cron job every hour
 * @return array Results of reminder processing
 */
function check_and_send_deadline_reminders() {
    $results = [
        '24hr_sent' => 0,
        '12hr_sent' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    try {
        // Get assignments with deadlines in the next 24 hours (but not already reminded)
        $assignments_24hr = get_assignments_needing_reminder(24, 24);
        
        foreach ($assignments_24hr as $assignment) {
            $success = send_assignment_reminders($assignment, 24);
            if ($success) {
                $results['24hr_sent']++;
                mark_reminder_sent($assignment['id'], 24);
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to send 24hr reminder for assignment {$assignment['id']}";
            }
        }
        
        // Get assignments with deadlines in the next 12 hours (but not already reminded)
        $assignments_12hr = get_assignments_needing_reminder(12, 12);
        
        foreach ($assignments_12hr as $assignment) {
            $success = send_assignment_reminders($assignment, 12);
            if ($success) {
                $results['12hr_sent']++;
                mark_reminder_sent($assignment['id'], 12);
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to send 12hr reminder for assignment {$assignment['id']}";
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "System error: " . $e->getMessage();
        error_log("Deadline reminder system error: " . $e->getMessage());
    }
    
    return $results;
}

/**
 * Get assignments that need deadline reminders
 * @param int $hours_before Hours before deadline to check
 * @param int $reminder_type Type of reminder (24 or 12)
 * @return array Assignments needing reminders
 */
function get_assignments_needing_reminder($hours_before, $reminder_type) {
    global $conn;
    
    $time_window_start = date('Y-m-d H:i:s', strtotime("+{$hours_before} hours"));
    $time_window_end = date('Y-m-d H:i:s', strtotime("+" . ($hours_before - 1) . " hours"));
    
    $stmt = $conn->prepare("
        SELECT 
            a.id, a.title, a.deadline, a.unit_id,
            u.name as unit_name, u.course_id,
            CASE 
                WHEN {$reminder_type} = 24 THEN a.reminder_24h_sent
                WHEN {$reminder_type} = 12 THEN a.reminder_12h_sent
                ELSE 0
            END as reminder_already_sent
        FROM assignments a
        JOIN units u ON a.unit_id = u.id
        WHERE a.deadline BETWEEN ? AND ?
        AND a.is_active = 1
        AND (
            CASE 
                WHEN {$reminder_type} = 24 THEN a.reminder_24h_sent = 0
                WHEN {$reminder_type} = 12 THEN a.reminder_12h_sent = 0
                ELSE 1=1
            END
        )
    ");
    
    $stmt->bind_param("ss", $time_window_end, $time_window_start);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = [];
    
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    
    $stmt->close();
    return $assignments;
}

/**
 * Send reminder emails to all students in the assignment's course
 * @param array $assignment Assignment details
 * @param int $hours_before Hours before deadline
 * @return bool Success status
 */
function send_assignment_reminders($assignment, $hours_before) {
    global $conn;
    
    try {
        // Get all students in the course
        $stmt = $conn->prepare("
            SELECT id, name, email FROM students 
            WHERE course_id = ? AND is_verified = 1
        ");
        $stmt->bind_param("i", $assignment['course_id']);
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        $students = [];
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
        
        if (empty($students)) {
            return true; // No students to remind
        }
        
        // Check if students have already submitted this assignment
        $students_to_remind = [];
        foreach ($students as $student) {
            if (!has_student_submitted_assignment($student['id'], $assignment['id'])) {
                $students_to_remind[] = $student;
            }
        }
        
        if (empty($students_to_remind)) {
            return true; // All students have submitted
        }
        
        // Send reminder emails
        $success_count = 0;
        foreach ($students_to_remind as $student) {
            $success = send_deadline_reminder_email(
                $student['email'],
                $student['name'],
                $assignment['title'],
                $assignment['unit_name'],
                $assignment['deadline'],
                $hours_before
            );
            
            if ($success) {
                $success_count++;
            }
        }
        
        // Consider it successful if at least 80% of emails were sent
        return ($success_count / count($students_to_remind)) >= 0.8;
        
    } catch (Exception $e) {
        error_log("Error sending assignment reminders: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a student has already submitted an assignment
 * @param int $student_id Student ID
 * @param int $assignment_id Assignment ID
 * @return bool True if submitted
 */
function has_student_submitted_assignment($student_id, $assignment_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM assignment_submissions 
        WHERE student_id = ? AND assignment_id = ?
    ");
    $stmt->bind_param("ii", $student_id, $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['count'] > 0;
}

/**
 * Mark reminder as sent for an assignment
 * @param int $assignment_id Assignment ID
 * @param int $reminder_type Type of reminder (24 or 12)
 * @return bool Success status
 */
function mark_reminder_sent($assignment_id, $reminder_type) {
    global $conn;
    
    try {
        if ($reminder_type == 24) {
            $stmt = $conn->prepare("UPDATE assignments SET reminder_24h_sent = 1 WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE assignments SET reminder_12h_sent = 1 WHERE id = ?");
        }
        
        $stmt->bind_param("i", $assignment_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error marking reminder sent: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset reminder flags for assignments (useful for testing or manual reset)
 * @param int $assignment_id Assignment ID (optional, null for all)
 * @return bool Success status
 */
function reset_reminder_flags($assignment_id = null) {
    global $conn;
    
    try {
        if ($assignment_id) {
            $stmt = $conn->prepare("UPDATE assignments SET reminder_24h_sent = 0, reminder_12h_sent = 0 WHERE id = ?");
            $stmt->bind_param("i", $assignment_id);
        } else {
            $stmt = $conn->prepare("UPDATE assignments SET reminder_24h_sent = 0, reminder_12h_sent = 0");
        }
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error resetting reminder flags: " . $e->getMessage());
        return false;
    }
}

/**
 * Get upcoming deadlines for dashboard display
 * @param int $student_id Student ID
 * @param int $limit Number of deadlines to return
 * @return array Upcoming deadlines
 */
function get_upcoming_deadlines_for_student($student_id, $limit = 5) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                a.id, a.title, a.deadline, a.unit_id,
                u.name as unit_name, u.course_id,
                CASE 
                    WHEN a.deadline > NOW() AND a.deadline <= DATE_ADD(NOW(), INTERVAL 24 HOUR) THEN 'urgent'
                    WHEN a.deadline > NOW() AND a.deadline <= DATE_ADD(NOW(), INTERVAL 72 HOUR) THEN 'soon'
                    ELSE 'normal'
                END as urgency,
                TIMESTAMPDIFF(HOUR, NOW(), a.deadline) as hours_remaining,
                EXISTS(
                    SELECT 1 FROM assignment_submissions asub 
                    WHERE asub.student_id = ? AND asub.assignment_id = a.id
                ) as is_submitted
            FROM assignments a
            JOIN units u ON a.unit_id = u.id
            JOIN student_units su ON u.id = su.unit_id AND su.student_id = ?
            WHERE a.deadline > NOW()
            AND a.is_active = 1
            ORDER BY a.deadline ASC
            LIMIT ?
        ");
        
        $stmt->bind_param("iii", $student_id, $student_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $deadlines = [];
        while ($row = $result->fetch_assoc()) {
            $deadlines[] = $row;
        }
        
        $stmt->close();
        return $deadlines;
        
    } catch (Exception $e) {
        error_log("Error getting upcoming deadlines: " . $e->getMessage());
        return [];
    }
}

// If this script is called directly (for cron job)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: application/json');
    
    $results = check_and_send_deadline_reminders();
    
    echo json_encode([
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'results' => $results
    ]);
}
?>
