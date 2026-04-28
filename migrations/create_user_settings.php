<?php
require_once '../config/db.php';

// Create user_settings table
$sql = "CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('student', 'lecturer', 'admin') NOT NULL,
    theme ENUM('dark', 'light', 'auto') DEFAULT 'dark',
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'Africa/Nairobi',
    notifications_enabled BOOLEAN DEFAULT 1,
    email_notifications BOOLEAN DEFAULT 1,
    two_factor_enabled BOOLEAN DEFAULT 0,
    privacy_profile_visible BOOLEAN DEFAULT 1,
    privacy_show_email BOOLEAN DEFAULT 0,
    privacy_show_phone BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_setting (user_id, user_role),
    FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES lecturers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

try {
    $conn->query($sql);
    echo "User settings table created successfully!";
} catch (Exception $e) {
    echo "Error creating user settings table: " . $e->getMessage();
}
?>
