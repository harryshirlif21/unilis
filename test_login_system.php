<?php
/**
 * Test script to verify login system works with all user types
 */

require_once 'config/db.php';

echo "<h2>Login System Test</h2>";

// Test database connection
if ($conn && isset($conn->connected_db)) {
    echo "<p style='color: green;'>Successfully connected to {$conn->connected_db} database</p>";
} else {
    echo "<p style='color: red;'>Database connection failed</p>";
    exit;
}

// Test SmartLab users table
if ($conn->connected_db === 'SmartLab') {
    echo "<h3>Testing SmartLab Users Table</h3>";
    
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>Users table exists</p>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE users");
        echo "<h4>Users Table Structure:</h4>";
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Check existing users
        $result = $conn->query("SELECT id, email, full_name, role, is_active FROM users LIMIT 5");
        echo "<h4>Existing Users:</h4>";
        if ($result->num_rows > 0) {
            echo "<table border='1'><tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th><th>Active</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr><td>{$row['id']}</td><td>{$row['email']}</td><td>{$row['full_name']}</td><td>{$row['role']}</td><td>{$row['is_active']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users found. You may need to run the setup script.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>Users table does not exist</p>";
    }
}

// Test UNILIS tables (if connected to UNILIS database)
if ($conn->connected_db === 'UNILIS') {
    echo "<h3>Testing UNILIS Database Tables</h3>";
    
    $tables = ['admins', 'lecturers', 'students'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>$table table exists</p>";
            
            // Count records
            $result = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $result->fetch_assoc()['count'];
            echo "<p>$table has $count records</p>";
        } else {
            echo "<p style='color: orange;'>$table table does not exist</p>";
        }
    }
}

// Test login functionality
echo "<h3>Testing Login Functionality</h3>";

// Test SmartLab login
if ($conn->connected_db === 'SmartLab') {
    $test_email = 'labadmin@unilis.jhubafrica.com';
    $test_password = 'SmartLab@2024';
    
    echo "<h4>Testing SmartLab Login:</h4>";
    
    $sql = "SELECT id, password, full_name, reg_number, role, is_active FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param('s', $test_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            echo "<p style='color: green;'>User found: {$user['full_name']} ({$user['role']})</p>";
            
            if (password_verify($test_password, $user['password'])) {
                echo "<p style='color: green;'>Password verification successful</p>";
                
                if ($user['is_active'] == 1) {
                    echo "<p style='color: green;'>User is active - login would succeed</p>";
                    
                    // Show redirect target
                    $redirects = [
                        'admin' => 'admin/dashboard.php',
                        'lab_admin' => 'admin/dashboard.php',
                        'lecturer' => 'lecturer/dashboard.php',
                        'technician' => 'smart-lab/index.php?url=dashboard',
                        'student' => 'student/dashboard.php'
                    ];
                    $redirect = $redirects[$user['role']] ?? 'student/dashboard.php';
                    echo "<p>Would redirect to: $redirect</p>";
                } else {
                    echo "<p style='color: orange;'>User is not active</p>";
                }
            } else {
                echo "<p style='color: red;'>Password verification failed</p>";
            }
        } else {
            echo "<p style='color: orange;'>Test user not found. You may need to run setup_default_admin.php</p>";
        }
    } else {
        echo "<p style='color: red;'>Failed to prepare login query</p>";
    }
}

echo "<h3>Environment Information</h3>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Database Host: " . $conn->host_info . "</p>";
echo "<p>Current Database: " . $conn->connected_db . "</p>";

echo "<h3>Next Steps</h3>";
echo "<ul>";
echo "<li>Run setup_default_admin.php to create default admin account if needed</li>";
echo "<li>Test login with different user roles</li>";
echo "<li>Verify redirects work correctly for each role</li>";
echo "</ul>";

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>
