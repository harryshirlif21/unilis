<?php
/**
 * Test Admin Login Functionality
 * Tests if the created admin account can login successfully
 */

require_once __DIR__.'/config/database_production.php';

echo "<h2>Admin Login Test</h2>";

try {
    // Get database connection
    $pdo = getDB();
    echo "<p style='color: green;'>✅ Database connected: " . DB_NAME . "</p>";
    
    // Test admin login
    $email = 'admin@unilis.jhubafrica.com';
    $password = 'Admin@2024';
    
    echo "<h3>Testing Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p style='color: green;'>✅ Admin user found in database</p>";
        echo "<p><strong>User ID:</strong> " . htmlspecialchars($user['id']) . "</p>";
        echo "<p><strong>Full Name:</strong> " . htmlspecialchars($user['full_name']) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($user['role']) . "</p>";
        
        // Test password verification
        if (password_verify($password, $user['password'])) {
            echo "<p style='color: green;'>✅ Password verification successful</p>";
            
            // Test session creation
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['auth_method'] = 'password';
            
            echo "<p style='color: green;'>✅ Session created successfully</p>";
            echo "<p><strong>Session Data:</strong></p>";
            echo "<pre>";
            echo "User ID: " . $_SESSION['user_id'] . "\n";
            echo "User Email: " . $_SESSION['user_email'] . "\n";
            echo "User Role: " . $_SESSION['user_role'] . "\n";
            echo "User Name: " . $_SESSION['user_name'] . "\n";
            echo "Auth Method: " . $_SESSION['auth_method'] . "\n";
            echo "</pre>";
            
            // Test redirect
            echo "<p style='color: blue;'>🔗 <a href='".APP_URL."/index.php?url=auth/login'>Go to SmartLab Login</a></p>";
            
        } else {
            echo "<p style='color: red;'>❌ Password verification failed</p>";
            echo "<p>Stored hash: " . htmlspecialchars($user['password']) . "</p>";
            echo "<p>Input password: " . htmlspecialchars($password) . "</p>";
            echo "<p>Generated hash: " . htmlspecialchars(password_hash($password, PASSWORD_DEFAULT)) . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Admin user not found</p>";
        echo "<p>Query: SELECT * FROM users WHERE email = '" . htmlspecialchars($email) . "' AND is_active = 1 LIMIT 1</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<div style='background: #f8f9fa; border: 1px solid #e9ecef; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li>Go to <a href='".APP_URL."/index.php?url=auth/login'>SmartLab Login</a></li>";
echo "<li>Use admin credentials: admin@unilis.jhubafrica.com / Admin@2024</li>";
echo "<li>Verify login redirects to dashboard</li>";
echo "<li>Test role-based functionality</li>";
echo "</ol>";
echo "</div>";
?>
