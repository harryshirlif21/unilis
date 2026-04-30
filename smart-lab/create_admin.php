<?php
/**
 * Simple Admin Creation Script
 * Inserts an admin user into the existing users table
 */

// Include production database configuration
require_once __DIR__.'/config/database_production.php';

echo "<h2>SmartLab Admin Creation</h2>";

try {
    // Use production database connection
    $pdo = getDB();
    echo "<p style='color: green;'>Connected to database: " . DB_NAME . " on " . DB_HOST . "</p>";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        echo "<p style='color: red;'>Users table not found. Please check database setup.</p>";
        exit;
    } else {
        echo "<p style='color: green;'>Users table exists</p>";
        
        // Show table structure for debugging
        $columnsStmt = $pdo->query("DESCRIBE users");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p style='color: blue;'>Table has " . count($columns) . " columns</p>";
    }
    
    // Admin user data - only essential columns
    $admin_data = [
        'reg_number' => 'ADMIN001',
        'full_name' => 'SmartLab Administrator',
        'email' => 'admin@unilis.jhubafrica.com',
        'password' => password_hash('Admin@2024', PASSWORD_DEFAULT),
        'role' => 'admin',
        'is_active' => 1
    ];
    
    // Check if admin already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR reg_number = ? LIMIT 1");
    $checkStmt->execute([$admin_data['email'], $admin_data['reg_number']]);
    
    if ($checkStmt->fetch()) {
        echo "<p style='color: orange;'>Admin user already exists</p>";
        
        // Update existing admin password
        $updateStmt = $pdo->prepare("
            UPDATE users SET 
                password = ?, 
                full_name = ?, 
                role = ?, 
                is_active = 1
            WHERE email = ?
        ");
        $updateStmt->execute([
            $admin_data['password'],
            $admin_data['full_name'],
            $admin_data['role'],
            $admin_data['email']
        ]);
        
        echo "<p style='color: green;'>Admin password updated successfully</p>";
    } else {
        // Insert new admin
        $columns = implode(', ', array_keys($admin_data));
        $placeholders = implode(', ', array_fill(0, count($admin_data), '?'));
        
        $insertSQL = "INSERT INTO users ($columns) VALUES ($placeholders)";
        $insertStmt = $pdo->prepare($insertSQL);
        $insertStmt->execute(array_values($admin_data));
        
        echo "<p style='color: green;'>Admin user created successfully</p>";
    }
    
    echo "<h3>Admin Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($admin_data['email']) . "</p>";
    echo "<p><strong>Password:</strong> Admin@2024</p>";
    echo "<p><strong>Role:</strong> " . htmlspecialchars($admin_data['role']) . "</p>";
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 20px 0;'>";
    echo "<h4 style='color: #f39c12; margin-top: 0;'>Security Notes:</h4>";
    echo "<ul style='color: #666;'>";
    echo "<li>Change password immediately after first login</li>";
    echo "<li>Delete this script from server after use</li>";
    echo "<li>Store credentials securely</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><a href='https://unilis.jhubafrica.com/smart-lab/index.php?url=auth/login' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Go to SmartLab Login</a></p>";
    
    // Display current users in the table
    echo "<div style='margin-top: 30px;'>";
    echo "<h3>Current Users in Database:</h3>";
    
    try {
        $usersStmt = $pdo->query("SELECT id, reg_number, full_name, email, role, department, is_active, created_at FROM users ORDER BY created_at DESC");
        $users = $usersStmt->fetchAll();
        
        if (!empty($users)) {
            echo "<table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
            echo "<thead>";
            echo "<tr style='background: #f8f9fa;'>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>ID</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Reg Number</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Full Name</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Email</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Role</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Department</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Status</th>";
            echo "<th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Created</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";
            
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars(substr($user['id'], 0, 8)) . "...</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($user['reg_number'] ?? 'N/A') . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($user['full_name']) . "</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($user['email']) . "</td>";
                
                // Role with color coding
                $roleColor = [
                    'admin' => '#dc3545',
                    'lecturer' => '#6f42c1', 
                    'technician' => '#20c997',
                    'student' => '#007bff'
                ];
                $color = $roleColor[$user['role']] ?? '#6c757d';
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>";
                echo "<span style='background: {$color}; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;'>";
                echo htmlspecialchars(ucfirst($user['role']));
                echo "</span>";
                echo "</td>";
                
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($user['department'] ?? 'N/A') . "</td>";
                
                // Status
                $statusColor = $user['is_active'] ? '#28a745' : '#dc3545';
                $statusText = $user['is_active'] ? 'Active' : 'Inactive';
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>";
                echo "<span style='background: {$statusColor}; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px;'>";
                echo $statusText;
                echo "</span>";
                echo "</td>";
                
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . date('M j, Y', strtotime($user['created_at'])) . "</td>";
                echo "</tr>";
            }
            
            echo "</tbody>";
            echo "</table>";
            
            // Summary statistics
            echo "<div style='margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;'>";
            echo "<h4>Summary Statistics:</h4>";
            echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;'>";
            
            $roleCounts = [];
            $activeCount = 0;
            foreach ($users as $user) {
                $roleCounts[$user['role']] = ($roleCounts[$user['role']] ?? 0) + 1;
                if ($user['is_active']) $activeCount++;
            }
            
            echo "<div><strong>Total Users:</strong> " . count($users) . "</div>";
            echo "<div><strong>Active Users:</strong> " . $activeCount . "</div>";
            echo "<div><strong>Admins:</strong> " . ($roleCounts['admin'] ?? 0) . "</div>";
            echo "<div><strong>Lecturers:</strong> " . ($roleCounts['lecturer'] ?? 0) . "</div>";
            echo "<div><strong>Technicians:</strong> " . ($roleCounts['technician'] ?? 0) . "</div>";
            echo "<div><strong>Students:</strong> " . ($roleCounts['student'] ?? 0) . "</div>";
            
            echo "</div>";
            echo "</div>";
            
        } else {
            echo "<p style='color: orange; text-align: center; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;'>No users found in the database.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error fetching users: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL server is running</li>";
    echo "<li>Docker containers are running (if using Docker)</li>";
    echo "<li>Database credentials are correct</li>";
    echo "</ul>";
}
?>
