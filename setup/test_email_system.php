<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_system.php';
require_once __DIR__ . '/../includes/deadline_reminders.php';

/**
 * Test Email and Deadline Reminder System
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Email System Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 4px; }
        button { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📧 Email System Test Dashboard</h1>
    
    <div class='test-section'>
        <h2>Test Basic Email Functionality</h2>
        <form method='post'>
            <label>Test Email Address:</label><br>
            <input type='email' name='test_email' value='mwendihillary@gmail.com' size='30' required><br><br>
            <button type='submit' name='test_basic' class='btn-primary'>Test Basic Email</button>
            <button type='submit' name='test_deadline' class='btn-warning'>Test Deadline Reminder</button>
            <button type='submit' name='test_notification' class='btn-success'>Test Notification Email</button>
        </form>
    </div>";

// Handle test requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = $_POST['test_email'] ?? '';
    
    if (!empty($test_email)) {
        echo "<div class='test-section'>";
        
        if (isset($_POST['test_basic'])) {
            echo "<h3>Testing Basic Email...</h3>";
            $success = send_notification_email(
                $test_email,
                'Test User',
                'Test Email from UNILIS',
                'System Test',
                'This is a test email to verify the email system is working correctly.',
                'https://unilis.jhubafrica.com',
                'general'
            );
            
            if ($success) {
                echo "<div class='success'>✅ Basic email sent successfully to $test_email</div>";
            } else {
                echo "<div class='error'>❌ Failed to send basic email to $test_email</div>";
            }
        }
        
        if (isset($_POST['test_deadline'])) {
            echo "<h3>Testing Deadline Reminder...</h3>";
            $future_deadline = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $success = send_deadline_reminder_email(
                $test_email,
                'Test Student',
                'Test Assignment',
                'Test Unit',
                $future_deadline,
                2
            );
            
            if ($success) {
                echo "<div class='success'>✅ Deadline reminder sent successfully to $test_email</div>";
                echo "<div class='info'>Deadline used: $future_deadline (2 hours from now)</div>";
            } else {
                echo "<div class='error'>❌ Failed to send deadline reminder to $test_email</div>";
            }
        }
        
        if (isset($_POST['test_notification'])) {
            echo "<h3>Testing Notification Email...</h3>";
            $success = send_notification_email(
                $test_email,
                'Test Student',
                '📚 New Notes Available',
                'System Test Notification',
                'This is a test notification to verify the email template system is working correctly.',
                'https://unilis.jhubafrica.com/student/dashboard.php',
                'notes'
            );
            
            if ($success) {
                echo "<div class='success'>✅ Notification email sent successfully to $test_email</div>";
            } else {
                echo "<div class='error'>❌ Failed to send notification email to $test_email</div>";
            }
        }
        
        echo "</div>";
    }
}

echo "
    <div class='test-section'>
        <h2>System Status Check</h2>
        <form method='post'>
            <button type='submit' name='check_system' class='btn-primary'>Check System Status</button>
        </form>
    </div>";

if (isset($_POST['check_system'])) {
    echo "<div class='test-section'>";
    echo "<h3>System Status Report</h3>";
    
    // Check database connection
    try {
        $conn->query("SELECT 1");
        echo "<div class='success'>✅ Database connection: OK</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Database connection: Failed - " . $e->getMessage() . "</div>";
    }
    
    // Check email configuration
    try {
        $mail = getConfiguredMailer();
        echo "<div class='success'>✅ Email configuration: OK</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Email configuration: Failed - " . $e->getMessage() . "</div>";
    }
    
    // Check assignments table structure
    try {
        $result = $conn->query("SHOW COLUMNS FROM assignments LIKE 'reminder_24h_sent'");
        if ($result->num_rows > 0) {
            echo "<div class='success'>✅ Reminder columns: Present</div>";
        } else {
            echo "<div class='error'>❌ Reminder columns: Missing - Run migration first</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Assignments table check: Failed - " . $e->getMessage() . "</div>";
    }
    
    // Check for upcoming deadlines
    try {
        $upcoming = get_upcoming_deadlines_for_student(1, 3); // Test with student ID 1
        echo "<div class='info'>ℹ️ Upcoming deadlines found: " . count($upcoming) . "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Deadline check: Failed - " . $e->getMessage() . "</div>";
    }
    
    echo "</div>";
}

echo "
    <div class='test-section'>
        <h2>Manual Deadline Reminder Test</h2>
        <p>This will run the deadline reminder check manually (same as cron job):</p>
        <form method='post'>
            <button type='submit' name='run_reminders' class='btn-warning'>Run Manual Reminder Check</button>
        </form>
    </div>";

if (isset($_POST['run_reminders'])) {
    echo "<div class='test-section'>";
    echo "<h3>Manual Reminder Check Results</h3>";
    
    $results = check_and_send_deadline_reminders();
    
    echo "<pre>";
    print_r($results);
    echo "</pre>";
    
    if ($results['24hr_sent'] > 0 || $results['12hr_sent'] > 0) {
        echo "<div class='success'>✅ Sent {$results['24hr_sent']} 24hr reminders and {$results['12hr_sent']} 12hr reminders</div>";
    } else {
        echo "<div class='info'>ℹ️ No reminders needed at this time</div>";
    }
    
    if (!empty($results['errors'])) {
        echo "<div class='error'>❌ Errors encountered:</div>";
        foreach ($results['errors'] as $error) {
            echo "<div class='error'>- $error</div>";
        }
    }
    
    echo "</div>";
}

echo "
    <div class='test-section'>
        <h2>📊 Email Templates Preview</h2>
        <p>The following email templates are available:</p>
        <ul>
            <li><strong>Notes:</strong> 📚 Blue gradient header with study materials theme</li>
            <li><strong>Assignment:</strong> ✏️ Orange/yellow gradient with assignment theme</li>
            <li><strong>Attendance:</strong> 📋 Pink gradient with attendance theme</li>
            <li><strong>Submission:</strong> ✓ Purple gradient with submission confirmation</li>
            <li><strong>Deadline Reminder:</strong> ⏰ Red gradient with urgency indicators</li>
        </ul>
    </div>
    
    <div class='test-section'>
        <h2>🔗 Quick Links</h2>
        <ul>
            <li><a href='deadline_reminder_cron.php'>Cron Job Setup Instructions</a></li>
            <li><a href='../migrations/add_deadline_reminders.php'>Run Database Migration</a></li>
            <li><a href='../student/dashboard.php'>Student Dashboard</a></li>
        </ul>
    </div>
</body>
</html>";
?>
