<?php
/**
 * SmartLab Web Setup Script
 * Run this script in your browser to set up the database and create admin account
 */

// Environment detection
$is_production = (strpos($_SERVER['HTTP_HOST'] ?? '', 'unilis.jhubafrica.com') !== false);

if ($is_production) {
    require_once __DIR__.'/config/app_production.php';
    require_once __DIR__.'/config/database_production.php';
} else {
    require_once __DIR__.'/config/app.php';
    require_once __DIR__.'/config/database.php';
}

// Check if setup is already completed
$setup_file = __DIR__.'/.setup_completed';
if (file_exists($setup_file)) {
    $setup_time = file_get_contents($setup_file);
    echo "<div style='max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; font-family: Arial, sans-serif;'>";
    echo "<h2 style='color: #e74c3c;'>Setup Already Completed</h2>";
    echo "<p style='color: #666;'>SmartLab was already set up on: " . date('Y-m-d H:i:s', $setup_time) . "</p>";
    echo "<p>If you need to re-run setup, delete the file <code>.setup_completed</code> from the smart-lab directory.</p>";
    echo "<p><a href='".APP_URL."/index.php?url=auth/login' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Go to Login</a></p>";
    echo "</div>";
    exit;
}

// Process setup form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'unilis_smartlab';
    $db_user = $_POST['db_user'] ?? 'lab_admin';
    $db_password = $_POST['db_password'] ?? '';
    $admin_email = $_POST['admin_email'] ?? 'labadmin@unilis.jhubafrica.com';
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_confirm = $_POST['admin_confirm'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($db_password)) {
        $errors[] = "Database password is required";
    }
    
    if (empty($admin_email)) {
        $errors[] = "Admin email is required";
    }
    
    if (empty($admin_password)) {
        $errors[] = "Admin password is required";
    }
    
    if ($admin_password !== $admin_confirm) {
        $errors[] = "Admin passwords do not match";
    }
    
    if (strlen($admin_password) < 8) {
        $errors[] = "Admin password must be at least 8 characters";
    }
    
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid admin email format";
    }
    
    if (empty($errors)) {
        try {
            // Test database connection
            $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
            if ($conn->connect_error) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
            
            // Execute database setup
            $sql_file = __DIR__.'/database/init.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                
                // Split SQL into individual statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        if (!$conn->query($statement)) {
                            throw new Exception("SQL Error: " . $conn->error);
                        }
                    }
                }
            }
            
            // Create admin user
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (email, password, full_name, reg_number, role, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                password = VALUES(password),
                full_name = VALUES(full_name),
                reg_number = VALUES(reg_number),
                role = VALUES(role),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $admin_email,
                $hashed_password,
                'SmartLab Administrator',
                'LAB001',
                'lab_admin',
                1
            ]);
            
            // Mark setup as completed
            file_put_contents($setup_file, time());
            
            echo "<div style='max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #27ae60; border-radius: 8px; font-family: Arial, sans-serif; background: #d5f4e6;'>";
            echo "<h2 style='color: #27ae60;'>✅ Setup Completed Successfully!</h2>";
            echo "<h3>Admin Account Created:</h3>";
            echo "<p><strong>Email:</strong> " . htmlspecialchars($admin_email) . "</p>";
            echo "<p><strong>Password:</strong> " . htmlspecialchars($admin_password) . "</p>";
            echo "<p><strong>Role:</strong> Lab Administrator</p>";
            
            echo "<h3>Database Configuration:</h3>";
            echo "<p><strong>Host:</strong> " . htmlspecialchars($db_host) . "</p>";
            echo "<p><strong>Database:</strong> " . htmlspecialchars($db_name) . "</p>";
            echo "<p><strong>User:</strong> " . htmlspecialchars($db_user) . "</p>";
            
            echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
            echo "<h4 style='color: #f39c12; margin-top: 0;'>⚠️ Important Security Notes:</h4>";
            echo "<ul style='color: #666;'>";
            echo "<li><strong>Change the admin password immediately</strong> after first login</li>";
            echo "<li><strong>Delete the setup.php file</strong> from the server after setup</li>";
            echo "<li><strong>Store credentials securely</strong> and do not share them</li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<p><a href='".APP_URL."/index.php?url=auth/login' style='background: #27ae60; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-size: 16px;'>Go to Login Page</a></p>";
            echo "</div>";
            
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Setup failed: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        echo "<div style='color: #e74c3c; background: #fadbd8; padding: 10px; border-radius: 4px; margin-bottom: 20px;'>";
        foreach ($errors as $error) {
            echo "<p>• " . htmlspecialchars($error) . "</p>";
        }
        echo "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartLab Setup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .setup-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            margin: 20px;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .setup-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .setup-header p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section h3 {
            color: #34495e;
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .setup-button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .setup-button:hover {
            transform: translateY(-2px);
        }
        
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .security-notice h4 {
            color: #f39c12;
            margin-bottom: 10px;
        }
        
        .security-notice ul {
            color: #666;
            margin-left: 20px;
        }
        
        .security-notice li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🧪 SmartLab Setup</h1>
            <p>Configure your SmartLab database and create the administrator account</p>
        </div>
        
        <form method="POST" action="">
            <div class="form-section">
                <h3>📊 Database Configuration</h3>
                
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'unilis_smartlab') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database User</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'lab_admin') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_password">Database Password</label>
                    <input type="password" id="db_password" name="db_password" required>
                </div>
            </div>
            
            <div class="form-section">
                <h3>👤 Administrator Account</h3>
                
                <div class="form-group">
                    <label for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? 'labadmin@unilis.jhubafrica.com') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="admin_confirm">Confirm Password</label>
                    <input type="password" id="admin_confirm" name="admin_confirm" required minlength="8">
                </div>
            </div>
            
            <button type="submit" class="setup-button">🚀 Complete Setup</button>
        </form>
        
        <div class="security-notice">
            <h4>🔐 Security Information</h4>
            <ul>
                <li>This setup script creates the database structure and default admin account</li>
                <li>After setup, delete this file from your server</li>
                <li>Change the default admin password immediately after first login</li>
                <li>Ensure your database credentials are secure</li>
            </ul>
        </div>
    </div>
</body>
</html>
