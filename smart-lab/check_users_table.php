<?php
/**
 * Check Users Table Structure
 * Diagnose authentication issues
 */

require_once __DIR__.'/config/database_production.php';

echo "<h2>Users Table Structure Analysis</h2>";

try {
    $pdo = getDB();
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Table Columns:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check existing users
    echo "<h3>Existing Users:</h3>";
    $stmt = $pdo->query("SELECT id, reg_number, email, full_name, role, is_active FROM users ORDER BY created_at DESC LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($users)) {
        echo "<table border='1' style='border-collapse: collapse; margin-top: 20px;'>";
        echo "<tr><th>ID</th><th>Reg Number</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['reg_number']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No users found in database</p>";
    }
    
    // Test admin login with email
    echo "<h3>Test Admin Email Login:</h3>";
    $adminEmail = 'admin@unilis.jhubafrica.com';
    $adminPassword = 'Admin@2024';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$adminEmail]);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        echo "<p style='color: green;'>✅ Admin user found by email</p>";
        echo "<p><strong>ID:</strong> " . htmlspecialchars($adminUser['id']) . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($adminUser['email']) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($adminUser['role']) . "</p>";
        
        if (password_verify($adminPassword, $adminUser['password'])) {
            echo "<p style='color: green;'>✅ Email login would work</p>";
        } else {
            echo "<p style='color: red;'>❌ Email login failed - password mismatch</p>";
            echo "<p>Stored hash: " . htmlspecialchars($adminUser['password']) . "</p>";
            echo "<p>Test password: " . htmlspecialchars($adminPassword) . "</p>";
            echo "<p>Generated hash: " . htmlspecialchars(password_hash($adminPassword, PASSWORD_DEFAULT)) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Admin user not found by email</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
