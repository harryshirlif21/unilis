<?php
/**
 * Cron Job Setup for Deadline Reminder System
 * 
 * This file provides instructions and examples for setting up automated deadline reminders
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Deadline Reminder Cron Job Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 5px; font-family: monospace; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #3498db; background: #f8f9fa; }
    </style>
</head>
<body>
    <h1>⏰ Deadline Reminder System Setup</h1>
    
    <div class='step'>
        <h2>Step 1: Run Database Migration</h2>
        <p>First, run the migration to add the necessary database columns:</p>
        <div class='code'>php migrations/add_deadline_reminders.php</div>
        <a href='add_deadline_reminders.php' class='button'>Run Migration Now</a>
    </div>
    
    <div class='step'>
        <h2>Step 2: Test the Reminder System</h2>
        <p>Test the deadline reminder system manually:</p>
        <div class='code'>php includes/deadline_reminders.php</div>
        <p>This will show you how many reminders would be sent.</p>
    </div>
    
    <div class='step'>
        <h2>Step 3: Set Up Cron Job</h2>
        <p>Add one of the following cron jobs to run the reminder system hourly:</p>
        
        <h3>Option A: Direct PHP Command</h3>
        <div class='code'>0 * * * * /usr/bin/php /path/to/unilis/includes/deadline_reminders.php</div>
        
        <h3>Option B: Using cURL (if PHP is not in PATH)</h3>
        <div class='code'>0 * * * * curl -s https://unilis.jhubafrica.com/includes/deadline_reminders.php</div>
        
        <h3>Option C: Using wget</h3>
        <div class='code'>0 * * * * wget -q -O - https://unilis.jhubafrica.com/includes/deadline_reminders.php</div>
        
        <p><strong>Note:</strong> Replace <code>/path/to/unilis/</code> with your actual file path.</p>
    </div>
    
    <div class='step'>
        <h2>Step 4: Verify Cron Job is Working</h2>
        <p>Check your cron logs or the deadline_reminders_log table to confirm reminders are being sent:</p>
        <div class='code'>SELECT * FROM deadline_reminders_log ORDER BY sent_at DESC LIMIT 10;</div>
    </div>
    
    <div class='step'>
        <h2>Step 5: Monitor Email Delivery</h2>
        <p>Monitor your email logs and PHP error logs to ensure emails are being sent successfully.</p>
    </div>
    
    <h2>🔧 Troubleshooting</h2>
    
    <h3>Common Issues:</h3>
    <ul>
        <li><strong>PHP not found:</strong> Use full path to PHP executable</li>
        <li><strong>File permissions:</strong> Ensure the PHP file is executable</li>
        <li><strong>Email sending issues:</strong> Check your email configuration in config/email.php</li>
        <li><strong>Database connection:</strong> Ensure database credentials are correct</li>
    </ul>
    
    <h3>Testing Commands:</h3>
    <div class='code'>
        # Test PHP execution<br>
        php -v<br><br>
        
        # Test the reminder script<br>
        php includes/deadline_reminders.php<br><br>
        
        # Check cron service status<br>
        sudo systemctl status cron
    </div>
    
    <h2>📊 Monitoring Dashboard</h2>
    <p>You can monitor the reminder system by checking:</p>
    <ul>
        <li><strong>deadline_reminders_log table:</strong> Shows all sent reminders</li>
        <li><strong>PHP error logs:</strong> Shows any email sending errors</li>
        <li><strong>Assignment table:</strong> Shows reminder flags (reminder_24h_sent, reminder_12h_sent)</li>
    </ul>
    
    <h2>🎯 Best Practices</h2>
    <ul>
        <li>Run cron job during off-peak hours (e.g., at minute 0 of each hour)</li>
        <li>Monitor email deliverability rates</li>
        <li>Set up logging for failed reminders</li>
        <li>Test with sample assignments before going live</li>
    </ul>
</body>
</html>";

// If accessed directly, show the setup page
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo $html_content;
}
?>
