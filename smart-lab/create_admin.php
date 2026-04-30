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
        echo "<p style='color: red;'>Users table not found. Creating it...</p>";
        
        // Create users table
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS users (
            id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
            reg_number VARCHAR(50) UNIQUE,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('student', 'lecturer', 'technician', 'admin') NOT NULL DEFAULT 'student',
            lab_id CHAR(36) NULL,
            department VARCHAR(100) NULL,
            biometric_hash VARCHAR(255) NULL,
            device_fingerprint VARCHAR(255) NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_reg_number (reg_number),
            INDEX idx_email (email),
            INDEX idx_lab_id (lab_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTableSQL);
        echo "<p style='color: green;'>Users table created successfully</p>";
    } else {
        echo "<p style='color: green;'>Users table exists</p>";
    }
    
    // Admin user data
    $admin_data = [
        'id' => uniqid('admin_', true),
        'reg_number' => 'ADMIN001',
        'full_name' => 'SmartLab Administrator',
        'email' => 'admin@unilis.jhubafrica.com',
        'password' => password_hash('Admin@2024', PASSWORD_DEFAULT),
        'role' => 'admin',
        'lab_id' => null,
        'department' => 'Computer Science',
        'biometric_hash' => null,
        'device_fingerprint' => null,
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
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
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
