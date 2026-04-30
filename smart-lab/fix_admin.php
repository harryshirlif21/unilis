<?php
/**
 * Fix Admin User Record
 * One-time script to fix the admin user ID format and password hash
 */

require_once __DIR__.'/config/database_production.php';

echo "<h2>Fix Admin User Record</h2>";

try {
    $pdo = getDB();
    echo "<p style='color: green;'>Connected to database: " . DB_NAME . " on " . DB_HOST . "</p>";
    
    // Step 1: Show current admin record
    echo "<h3>Step 1: Current Admin Record</h3>";
    $stmt = $pdo->prepare("SELECT id, reg_number, email, role, is_active, LEFT(password, 20) as pw_preview FROM users WHERE reg_number = 'ADMIN001' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Reg Number</th><th>Email</th><th>Role</th><th>Active</th><th>Password Preview</th></tr>";
        echo "<tr>";
        echo "<td>" . htmlspecialchars($admin['id']) . "</td>";
        echo "<td>" . htmlspecialchars($admin['reg_number']) . "</td>";
        echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
        echo "<td>" . htmlspecialchars($admin['role']) . "</td>";
        echo "<td>" . ($admin['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . htmlspecialchars($admin['pw_preview']) . "...</td>";
        echo "</tr>";
        echo "</table>";
        
        // Check if ID format is problematic
        if (strpos($admin['id'], 'admin_') === 0) {
            echo "<p style='color: orange;'>Admin ID uses uniqid() format (admin_...), this may cause issues. Will fix.</p>";
        } else {
            echo "<p style='color: green;'>Admin ID format looks good.</p>";
        }
    } else {
        echo "<p style='color: red;'>Admin user not found with reg_number ADMIN001</p>";
        exit;
    }
    
    // Step 2: Generate new proper ID
    echo "<h3>Step 2: Generate New Proper ID</h3>";
    $newId = bin2hex(random_bytes(16));
    echo "<p><strong>New ID:</strong> " . htmlspecialchars($newId) . "</p>";
    echo "<p><strong>Format:</strong> 32-character hex string (proper UUID format)</p>";
    
    // Step 3: Update admin user
    echo "<h3>Step 3: Update Admin User</h3>";
    $newPassword = 'Admin@2025';
    $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    $updateStmt = $pdo->prepare("
        UPDATE users SET 
            id = ?, 
            password = ?,
            is_active = 1
        WHERE reg_number = 'ADMIN001'
    ");
    
    $result = $updateStmt->execute([$newId, $newPasswordHash]);
    
    if ($result) {
        echo "<p style='color: green;'>Admin user updated successfully</p>";
    } else {
        echo "<p style='color: red;'>Failed to update admin user</p>";
        exit;
    }
    
    // Step 4: Display updated credentials
    echo "<h3>Step 4: Updated Admin Credentials</h3>";
    echo "<div style='background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p><strong>Registration Number:</strong> ADMIN001</p>";
    echo "<p><strong>Email:</strong> admin@unilis.jhubafrica.com</p>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($newPassword) . "</p>";
    echo "<p><strong>Role:</strong> admin</p>";
    echo "<p><strong>Status:</strong> Active</p>";
    echo "</div>";
    
    // Step 5: Test login manually
    echo "<h3>Step 5: Test Login Authentication</h3>";
    
    // Simulate the auth query
    $authStmt = $pdo->prepare("SELECT * FROM users WHERE reg_number = ? AND is_active = 1");
    $authStmt->execute(['ADMIN001']);
    $user = $authStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p style='color: green;'>Auth query found user successfully</p>";
        
        // Test password verification
        if (password_verify($newPassword, $user['password'])) {
            echo "<p style='color: green; font-weight: bold;'>Password verification PASSED - Admin login will work!</p>";
            
            // Show session data that would be created
            echo "<div style='background: #f0f8ff; border: 1px solid #2196f3; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>Session Data Preview:</h4>";
            echo "<p><strong>user_id:</strong> " . htmlspecialchars($user['id']) . "</p>";
            echo "<p><strong>user_role:</strong> " . htmlspecialchars($user['role']) . "</p>";
            echo "<p><strong>user_name:</strong> " . htmlspecialchars($user['full_name']) . "</p>";
            echo "<p><strong>auth_method:</strong> password</p>";
            echo "</div>";
            
        } else {
            echo "<p style='color: red; font-weight: bold;'>Password verification FAILED - Login will not work!</p>";
        }
    } else {
        echo "<p style='color: red;'>Auth query failed - user not found or inactive</p>";
    }
    
    // Step 6: Security notes and cleanup
    echo "<h3>Step 6: Security Instructions</h3>";
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4 style='color: #f39c12; margin-top: 0;'>Important Security Notes:</h4>";
    echo "<ul style='color: #666;'>";
    echo "<li>Change the admin password immediately after first login</li>";
    echo "<li>Delete this fix_admin.php file from the server immediately</li>";
    echo "<li>Store the new credentials securely</li>";
    echo "<li>The admin can now login using: ADMIN001 / Admin@2025</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><a href='https://unilis.jhubafrica.com/smart-lab/index.php?url=auth/login' style='background: #4caf50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;'>Test Admin Login Now</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>Database connection is working</li>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Docker containers are running (if using Docker)</li>";
    echo "</ul>";
}
?>
