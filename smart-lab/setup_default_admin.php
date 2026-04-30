<?php
/**
 * Default Lab Admin Account Setup Script
 * This script creates a default lab administrator account for initial deployment
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

// Default admin credentials
$DEFAULT_ADMIN = [
    'email' => 'labadmin@unilis.jhubafrica.com',
    'password' => 'SmartLab@2024', // Change this in production!
    'full_name' => 'SmartLab Administrator',
    'reg_number' => 'LAB001',
    'role' => 'lab_admin',
    'is_active' => 1
];

function createDefaultAdmin() {
    global $DEFAULT_ADMIN;
    
    try {
        $db = getDB();
        
        // Check if admin already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$DEFAULT_ADMIN['email']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo "Admin account already exists: " . $DEFAULT_ADMIN['email'] . "\n";
            return true;
        }
        
        // Hash password
        $hashed_password = password_hash($DEFAULT_ADMIN['password'], PASSWORD_DEFAULT);
        
        // Insert admin user
        $stmt = $db->prepare("
            INSERT INTO users (email, password, full_name, reg_number, role, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $DEFAULT_ADMIN['email'],
            $hashed_password,
            $DEFAULT_ADMIN['full_name'],
            $DEFAULT_ADMIN['reg_number'],
            $DEFAULT_ADMIN['role'],
            $DEFAULT_ADMIN['is_active']
        ]);
        
        if ($result) {
            echo "Default admin account created successfully!\n";
            echo "Email: " . $DEFAULT_ADMIN['email'] . "\n";
            echo "Password: " . $DEFAULT_ADMIN['password'] . "\n";
            echo "IMPORTANT: Change this password immediately after first login!\n";
            return true;
        } else {
            echo "Failed to create admin account\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "Error creating admin account: " . $e->getMessage() . "\n";
        return false;
    }
}

function setupDefaultLabData() {
    try {
        $db = getDB();
        
        // Create default lab
        $stmt = $db->prepare("
            INSERT IGNORE INTO labs (id, name, room_number, capacity, equipment, status)
            VALUES (1, 'SmartLab Main', 'LAB-001', 30, 'Computers, Microscopes, Safety Equipment', 'active')
        ");
        $stmt->execute();
        
        // Create default experiment template
        $stmt = $db->prepare("
            INSERT IGNORE INTO experiments (id, title, unit_code, unit_name, technician_id, status)
            VALUES (1, 'Sample Laboratory Experiment', 'LAB001', 'Introduction to Laboratory Techniques', 1, 'published')
        ");
        $stmt->execute();
        
        echo "Default lab data setup completed\n";
        return true;
        
    } catch (Exception $e) {
        echo "Error setting up default lab data: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run setup
echo "=== SmartLab Default Admin Setup ===\n";
echo "Environment: " . ($is_production ? 'Production' : 'Development') . "\n\n";

$admin_created = createDefaultAdmin();
$data_setup = setupDefaultLabData();

if ($admin_created && $data_setup) {
    echo "\n=== Setup Completed Successfully ===\n";
    echo "You can now login with the default admin credentials.\n";
    echo "Access the system at: " . APP_URL . "\n";
} else {
    echo "\n=== Setup Failed ===\n";
    echo "Please check the error messages above.\n";
}

// For web access, provide HTML output
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html><html><head><title>SmartLab Setup</title></head><body><pre>";
    echo str_replace("\n", "<br>", ob_get_contents());
    echo "</pre></body></html>";
}
?>
