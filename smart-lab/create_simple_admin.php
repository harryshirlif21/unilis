<?php
/**
 * SmartLab Admin Creation & Database Setup
 * Uses production database connection with Docker support
 */

require_once __DIR__.'/config/database_production.php';

// Override host for local development if needed
// Uncomment the next line to use localhost instead of Docker container name
// define('DB_HOST', 'localhost');

echo "<h2>SmartLab Admin Creation & Database Setup</h2>";

// Display connection mode
$hostToCheck = defined('DB_HOST') ? DB_HOST : 'unilis-db';
$isDocker = ($hostToCheck === 'unilis-db' || $hostToCheck === 'smart-labs-db');
$connectionMode = $isDocker ? '🐳 Docker Mode' : '💻 Local/Localhost Mode';
echo "<p style='color: #0066cc;'><strong>Connection Mode:</strong> $connectionMode</p>";

try {
    $db = getDB();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    if (defined('DB_HOST') && defined('DB_NAME')) {
        echo "<p style='color: blue;'><strong>Host:</strong> " . DB_HOST . " | <strong>Database:</strong> " . DB_NAME . "</p>";
    }
    
    // ===== DATABASE MIGRATION: Add missing practicals fields =====
    echo "<h3>Checking Practicals Table Structure...</h3>";
    
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'practicals'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Practicals table exists</p>";
            
            // Check for missing columns
            $columnsStmt = $db->query("DESCRIBE practicals");
            $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
            
            // Add duration_hours if missing
            if (!in_array('duration_hours', $columnNames)) {
                echo "<p style='color: orange;'>⚠️ Adding missing column: duration_hours</p>";
                $db->exec("ALTER TABLE practicals ADD COLUMN duration_hours INT DEFAULT 2 AFTER scheduled_date");
                echo "<p style='color: green;'>✅ Column duration_hours added successfully</p>";
            } else {
                echo "<p style='color: green;'>✅ Column duration_hours exists</p>";
            }
            
            // Add results_template if missing
            if (!in_array('results_template', $columnNames)) {
                echo "<p style='color: orange;'>⚠️ Adding missing column: results_template</p>";
                $db->exec("ALTER TABLE practicals ADD COLUMN results_template LONGTEXT AFTER safety_notes");
                echo "<p style='color: green;'>✅ Column results_template added successfully</p>";
            } else {
                echo "<p style='color: green;'>✅ Column results_template exists</p>";
            }
            
            // Add calculations_template if missing
            if (!in_array('calculations_template', $columnNames)) {
                echo "<p style='color: orange;'>⚠️ Adding missing column: calculations_template</p>";
                $db->exec("ALTER TABLE practicals ADD COLUMN calculations_template LONGTEXT AFTER results_template");
                echo "<p style='color: green;'>✅ Column calculations_template added successfully</p>";
            } else {
                echo "<p style='color: green;'>✅ Column calculations_template exists</p>";
            }
            
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 6px; margin: 20px 0;'>";
            echo "<h4 style='color: #155724; margin-top: 0;'>✅ Practicals Table Migration Complete</h4>";
            echo "<p style='color: #155724;'>All required fields are now available in the practicals table.</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error checking practicals table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // ===== END DATABASE MIGRATION =====
    
    // Check if users table exists
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        echo "<p style='color: red;'>❌ Users table not found. Creating it...</p>";
        
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
        
        $db->exec($createTableSQL);
        echo "<p style='color: green;'>✅ Users table created successfully</p>";
    } else {
        echo "<p style='color: green;'>✅ Users table exists</p>";
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
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? OR reg_number = ? LIMIT 1");
    $checkStmt->execute([$admin_data['email'], $admin_data['reg_number']]);
    
    if ($checkStmt->fetch()) {
        echo "<p style='color: orange;'>⚠️ Admin user already exists</p>";
        
        // Update existing admin password
        $updateStmt = $db->prepare("
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
        
        echo "<p style='color: green;'>✅ Admin password updated successfully</p>";
    } else {
        // Insert new admin
        $columns = implode(', ', array_keys($admin_data));
        $placeholders = implode(', ', array_fill(0, count($admin_data), '?'));
        
        $insertSQL = "INSERT INTO users ($columns) VALUES ($placeholders)";
        $insertStmt = $db->prepare($insertSQL);
        $insertStmt->execute(array_values($admin_data));
        
        echo "<p style='color: green;'>✅ Admin user created successfully</p>";
    }
    
    echo "<h3>Admin Login Credentials:</h3>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($admin_data['email']) . "</p>";
    echo "<p><strong>Password:</strong> Admin@2024</p>";
    echo "<p><strong>Role:</strong> " . htmlspecialchars($admin_data['role']) . "</p>";
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 20px 0;'>";
    echo "<h4 style='color: #f39c12; margin-top: 0;'>🔐 Security Notes:</h4>";
    echo "<ul style='color: #666;'>";
    echo "<li><strong>Change password immediately</strong> after first login</li>";
    echo "<li><strong>Delete this script</strong> from server after use</li>";
    echo "<li><strong>Store credentials securely</strong></li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><a href='".APP_URL."/index.php?url=auth/login' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Go to SmartLab Login</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 6px; margin: 20px 0; color: #721c24;'>";
    echo "<h3 style='margin-top: 0;'>❌ Connection Error</h3>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<hr style='border: 1px solid #f5c6cb;'>";
    echo "<h4>Troubleshooting Steps:</h4>";
    echo "<ol>";
    echo "<li><strong>Check Docker Container:</strong><br><code>docker ps</code><br>Look for container with name containing 'db' or 'unilis'</li>";
    echo "<li><strong>Start Docker Containers:</strong><br><code>docker-compose up -d</code><br>Run from the smart-lab project directory</li>";
    echo "<li><strong>For XAMPP Local Development:</strong><br>";
    echo "   a) Start MySQL service (XAMPP Control Panel)<br>";
    echo "   b) Open <code>config/database_production.php</code><br>";
    echo "   c) Uncomment: <code>define('DB_HOST', 'localhost');</code></li>";
    echo "<li><strong>Verify Database Exists:</strong><br>";
    echo "   Create it with: <code>CREATE DATABASE unilis_smartlab;</code></li>";
    echo "<li><strong>Check Credentials:</strong><br>";
    echo "   Default - User: lab_admin | Password: lab_password</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 20px 0;'>";
    echo "<h4>📝 Expected Configuration:</h4>";
    echo "<p><strong>Database Name:</strong> unilis_smartlab</p>";
    echo "<p><strong>Database User:</strong> lab_admin</p>";
    echo "<p><strong>Expected Hosts (tried in order):</strong></p>";
    echo "<ul>";
    echo "<li>unilis-db (Docker)</li>";
    echo "<li>localhost (XAMPP/Local)</li>";
    echo "<li>127.0.0.1 (Fallback)</li>";
    echo "</ul>";
    echo "</div>";
}
?>
