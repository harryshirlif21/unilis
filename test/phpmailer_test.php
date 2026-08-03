<?php
/**
 * Simple PHPMailer Test
 * Tests if PHPMailer is properly loaded and configured
 */

?>
<!DOCTYPE html>
<html>
<head>
    <title>PHPMailer Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📧 PHPMailer Test</h1>
    
    <div class='test-section'>
        <h2>Test 1: Check PHPMailer Installation</h2>
        
        <?php
        // Test 1: Check if vendor/autoload.php exists
        $autoload_path = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload_path)) {
            echo "<div class='success'>✅ vendor/autoload.php found</div>";
            
            try {
                require_once $autoload_path;
                echo "<div class='success'>✅ Autoloader loaded successfully</div>";
                
                // Test 2: Check if PHPMailer class exists
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    echo "<div class='success'>✅ PHPMailer class found</div>";
                    
                    // Test 3: Check version
                    $version = PHPMailer\PHPMailer\PHPMailer::VERSION;
                    echo "<div class='success'>✅ PHPMailer version: $version</div>";
                    
                    // Test 4: Try to create instance
                    try {
                        $mail = new PHPMailer\PHPMailer\PHPMailer();
                        echo "<div class='success'>✅ PHPMailer instance created successfully</div>";
                        
                        // Test 5: Check configuration
                        echo "<h3>Email Configuration:</h3>";
                        echo "<div class='code'>";
                        echo "<p><strong>Host:</strong> " . (defined('EMAIL_HOST') ? EMAIL_HOST : 'Not defined') . "</p>";
                        echo "<p><strong>Port:</strong> " . (defined('EMAIL_PORT') ? EMAIL_PORT : 'Not defined') . "</p>";
                        echo "<p><strong>Username:</strong> " . (defined('EMAIL_USERNAME') ? EMAIL_USERNAME : 'Not defined') . "</p>";
                        echo "<p><strong>Encryption:</strong> " . (defined('EMAIL_ENCRYPTION') ? EMAIL_ENCRYPTION : 'Not defined') . "</p>";
                        echo "</div>";
                        
                    } catch (Exception $e) {
                        echo "<div class='error'>❌ Failed to create PHPMailer instance: " . $e->getMessage() . "</div>";
                    }
                    
                } else {
                    echo "<div class='error'>❌ PHPMailer class not found</div>";
                    echo "<p><strong>Possible solutions:</strong></p>";
                    echo "<ul>";
                    echo "<li>Run 'composer install' in the project root</li>";
                    echo "<li>Check if phpmailer/phpmailer is in composer.json</li>";
                    echo "<li>Verify vendor directory exists and is not empty</li>";
                    echo "</ul>";
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Failed to load autoloader: " . $e->getMessage() . "</div>";
            }
            
        } else {
            echo "<div class='error'>❌ vendor/autoload.php not found</div>";
            echo "<p><strong>Possible solutions:</strong></p>";
            echo "<ul>";
            echo "<li>Run 'composer install' in the project root</li>";
            echo "<li>Check if composer is installed on the server</li>";
            echo "<li>Verify the vendor directory exists</li>";
            echo "</ul>";
        }
        ?>
    </div>
    
    <div class='test-section'>
        <h2>Test 2: Composer Information</h2>
        
        <?php
        // Check composer.json
        $composer_json_path = __DIR__ . '/../composer.json';
        if (file_exists($composer_json_path)) {
            echo "<div class='success'>✅ composer.json found</div>";
            
            $composer_json = json_decode(file_get_contents($composer_json_path), true);
            if ($composer_json) {
                echo "<h3>Composer Dependencies:</h3>";
                echo "<div class='code'>";
                if (isset($composer_json['require'])) {
                    foreach ($composer_json['require'] as $package => $version) {
                        echo "<p><strong>$package:</strong> $version</p>";
                    }
                }
                echo "</div>";
                
                if (isset($composer_json['require']['phpmailer/phpmailer'])) {
                    echo "<div class='success'>✅ PHPMailer is listed in composer.json</div>";
                } else {
                    echo "<div class='error'>❌ PHPMailer not found in composer.json</div>";
                }
            } else {
                echo "<div class='error'>❌ Invalid composer.json format</div>";
            }
        } else {
            echo "<div class='error'>❌ composer.json not found</div>";
        }
        
        // Check vendor directory
        $vendor_dir = __DIR__ . '/../vendor';
        if (is_dir($vendor_dir)) {
            echo "<div class='success'>✅ vendor directory exists</div>";
            
            $phpmailer_dir = $vendor_dir . '/phpmailer/phpmailer';
            if (is_dir($phpmailer_dir)) {
                echo "<div class='success'>✅ PHPMailer vendor directory exists</div>";
                
                $phpmailer_src = $phpmailer_dir . '/src';
                if (is_dir($phpmailer_src)) {
                    echo "<div class='success'>✅ PHPMailer source directory exists</div>";
                    
                    $phpmailer_php = $phpmailer_src . '/PHPMailer.php';
                    if (file_exists($phpmailer_php)) {
                        echo "<div class='success'>✅ PHPMailer.php file exists</div>";
                    } else {
                        echo "<div class='error'>❌ PHPMailer.php file not found</div>";
                    }
                } else {
                    echo "<div class='error'>❌ PHPMailer source directory not found</div>";
                }
            } else {
                echo "<div class='error'>❌ PHPMailer vendor directory not found</div>";
            }
        } else {
            echo "<div class='error'>❌ vendor directory not found</div>";
        }
        ?>
    </div>
    
    <div class='test-section'>
        <h2>Test 3: Quick Fix Options</h2>
        
        <h3>Option 1: Run Composer Install</h3>
        <div class='code'>
            <p>cd /var/www/html/unilis</p>
            <p>composer install</p>
        </div>
        
        <h3>Option 2: Manual PHPMailer Check</h3>
        <div class='code'>
            <p>Check if these files exist:</p>
            <p>- vendor/autoload.php</p>
            <p>- vendor/phpmailer/phpmailer/src/PHPMailer.php</p>
            <p>- vendor/phpmailer/phpmailer/src/Exception.php</p>
            <p>- vendor/phpmailer/phpmailer/src/SMTP.php</p>
        </div>
        
        <h3>Option 3: Alternative Email Configuration</h3>
        <p>If PHPMailer cannot be installed, we can create a simple mail() function fallback.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_fallback'])) {
            echo "<h3>Creating fallback email function...</h3>";
            
            $fallback_code = '<?php
// Fallback email function using PHP mail()
function sendFallbackEmail($to, $subject, $message, $headers = "") {
    $default_headers = "MIME-Version: 1.0\r\n";
    $default_headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $default_headers .= "From: noreply@unilis.jhubafrica.com\r\n";
    
    $all_headers = $default_headers . $headers;
    
    return mail($to, $subject, $message, $all_headers);
}
?>';
            
            if (file_put_contents(__DIR__ . '/../config/fallback_email.php', $fallback_code)) {
                echo "<div class='success'>✅ Fallback email function created</div>";
                echo "<p>File created: config/fallback_email.php</p>";
            } else {
                echo "<div class='error'>❌ Failed to create fallback email function</div>";
            }
        }
        ?>
        
        <form method='post'>
            <button type='submit' name='create_fallback' style='background: #6c757d; color: white;'>
                Create Fallback Email Function
            </button>
        </form>
    </div>
    
    <div class='test-section'>
        <h2>🔗 Quick Links</h2>
        <ul>
            <li><a href='../test/email_system_fix.php' target='_blank'>Email System Test</a></li>
            <li><a href='../fix/simple_collation_fix.php' target='_blank'>Fix Collation Issues</a></li>
            <li><a href='../student/dashboard.php' target='_blank'>Student Dashboard</a></li>
        </ul>
    </div>
</body>
</html>

