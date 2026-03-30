<?php
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../includes/email_system.php';

/**
 * Simple Email System Test
 * Tests PHPMailer configuration and basic email sending
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email System Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>📧 Email System Test</h1>
    
    <div class='test-section'>
        <h2>Test 1: PHPMailer Configuration</h2>
        <?php
        try {
            $mail = getConfiguredMailer();
            echo "<div class='success'>✅ PHPMailer configuration loaded successfully</div>";
            echo "<p><strong>Host:</strong> " . EMAIL_HOST . "</p>";
            echo "<p><strong>Port:</strong> " . EMAIL_PORT . "</p>";
            echo "<p><strong>Username:</strong> " . EMAIL_USERNAME . "</p>";
            echo "<p><strong>Encryption:</strong> " . EMAIL_ENCRYPTION . "</p>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ PHPMailer configuration failed: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>
    
    <div class='test-section'>
        <h2>Test 2: Basic Email Sending</h2>
        <form method='post'>
            <label for='test_email'>Test Email Address:</label><br>
            <input type='email' id='test_email' name='test_email' value='mwendihillary@gmail.com' required style='width: 300px; padding: 5px;'><br><br>
            
            <button type='submit' name='test_basic_email' style='margin-top: 10px;'>Send Test Email</button>
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_basic_email'])) {
            $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo "<div style='margin-top: 20px;'>";
                echo "<h3>Testing email to: $test_email</h3>";
                
                try {
                    $success = send_notification_email(
                        $test_email,
                        'Test User',
                        'Test Email from UNILIS',
                        'Test Notification',
                        'This is a test email to verify the email system is working correctly.',
                        'https://unilis.jhubafrica.com',
                        'general'
                    );
                    
                    if ($success) {
                        echo "<div class='success'>✅ Email sent successfully! Check your inbox.</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to send email. Check error logs.</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
                }
                
                echo "</div>";
            } else {
                echo "<div class='error'>❌ Please enter a valid email address.</div>";
            }
        }
        ?>
    </div>
    
    <div class='test-section'>
        <h2>Test 3: Notification Email Template</h2>
        <form method='post'>
            <label for='notif_email'>Test Email Address:</label><br>
            <input type='email' id='notif_email' name='notif_email' value='mwendihillary@gmail.com' required style='width: 300px; padding: 5px;'><br><br>
            
            <button type='submit' name='test_notification_email' style='margin-top: 10px;'>Test Notification Template</button>
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_notification_email'])) {
            $test_email = filter_var($_POST['notif_email'], FILTER_VALIDATE_EMAIL);
            
            if ($test_email) {
                echo "<div style='margin-top: 20px;'>";
                echo "<h3>Testing notification email template to: $test_email</h3>";
                
                try {
                    $success = send_notification_email(
                        $test_email,
                        'Test Student',
                        '📚 New Notes Available',
                        'Test Notification Message',
                        'This is a test notification to verify the email template system is working correctly.',
                        'https://unilis.jhubafrica.com/student/dashboard.php?view=notes',
                        'notes'
                    );
                    
                    if ($success) {
                        echo "<div class='success'>✅ Notification email sent successfully! Check your inbox.</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to send notification email. Check error logs.</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
                }
                
                echo "</div>";
            } else {
                echo "<div class='error'>❌ Please enter a valid email address.</div>";
            }
        }
        ?>
    </div>
    
    <div class='test-section'>
        <h2>🔧 Debug Information</h2>
        <p><strong>PHPMailer Version:</strong> <?php echo PHPMailer\PHPMailer\PHPMailer::VERSION; ?></p>
        <p><strong>SMTP Server:</strong> <?php echo EMAIL_HOST; ?></p>
        <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>Error Log Location:</strong> <?php echo ini_get('error_log'); ?></p>
    </div>
    
    <div class='test-section'>
        <h2>📋 Next Steps</h2>
        <ol>
            <li>✅ Run database migrations to fix notification columns</li>
            <li>✅ Test enhanced attendance system with unit-based notifications</li>
            <li>✅ Verify email delivery and spam filters</li>
            <li>✅ Check PHP error logs for any issues</li>
        </ol>
    </div>
</body>
</html>
