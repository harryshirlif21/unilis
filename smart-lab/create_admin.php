<?php
/**
 * Simple Admin Creation Script
 * Inserts an admin user into the existing users table
 */

// Database connection
$host = 'localhost';
$dbname = 'unilis';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Admin user data
$admin_data = [
    'id' => uniqid('admin_', true), // Generate unique ID
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

try {
    // Check if admin already exists
    $check_sql = "SELECT id FROM users WHERE email = :email OR reg_number = :reg_number";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['email' => $admin_data['email'], 'reg_number' => $admin_data['reg_number']]);
    
    if ($check_stmt->fetch()) {
        echo "Admin user already exists with this email or registration number.\n";
        echo "Email: " . $admin_data['email'] . "\n";
        echo "Reg Number: " . $admin_data['reg_number'] . "\n";
        exit;
    }
    
    // Insert admin user
    $sql = "INSERT INTO users (
        id, reg_number, full_name, email, password, role, lab_id, 
        department, biometric_hash, device_fingerprint, is_active
    ) VALUES (
        :id, :reg_number, :full_name, :email, :password, :role, :lab_id,
        :department, :biometric_hash, :device_fingerprint, :is_active
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($admin_data);
    
    echo "Admin user created successfully!\n";
    echo "Login Credentials:\n";
    echo "Email: " . $admin_data['email'] . "\n";
    echo "Password: Admin@2024\n";
    echo "Role: " . $admin_data['role'] . "\n";
    echo "\nIMPORTANT: Change the password after first login!\n";
    
} catch (PDOException $e) {
    echo "Error creating admin: " . $e->getMessage() . "\n";
}
?>
