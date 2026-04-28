<?php
/**
 * Database Migration Script - Extended User Profiles
 * Adds profile fields for students, lecturers, and admins
 */

require_once 'config/db.php';
require_once 'includes/notifications.php';

echo "<h2>🔧 Creating Extended User Profile System</h2>";

try {
    // Create user_profiles table
    $sql = "CREATE TABLE IF NOT EXISTS user_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_role ENUM('student', 'lecturer', 'admin') NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone_number VARCHAR(20),
        profile_picture VARCHAR(255) DEFAULT NULL,
        bio TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE,
        INDEX idx_user_role (user_role),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
    echo "<div style='color: green; margin: 10px 0;'>✅ user_profiles table created</div>";

    // Create student_profiles table
    $sql = "CREATE TABLE IF NOT EXISTS student_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        registration_number VARCHAR(50) NOT NULL UNIQUE,
        course_id INT,
        year_of_study INT,
        semester INT DEFAULT 1,
        academic_year VARCHAR(20),
        gpa DECIMAL(3,2) DEFAULT 0.00,
        total_units INT DEFAULT 0,
        completed_units INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
        INDEX idx_reg_number (registration_number),
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
    echo "<div style='color: green; margin: 10px 0;'>✅ student_profiles table created</div>";

    // Create lecturer_profiles table
    $sql = "CREATE TABLE IF NOT EXISTS lecturer_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        staff_id VARCHAR(50) NOT NULL UNIQUE,
        department VARCHAR(255),
        specialization VARCHAR(255),
        qualification VARCHAR(255),
        experience_years INT DEFAULT 0,
        research_interests TEXT,
        office_location VARCHAR(255),
        office_hours VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES lecturers(id) ON DELETE CASCADE,
        INDEX idx_staff_id (staff_id),
        INDEX idx_department (department)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
    echo "<div style='color: green; margin: 10px 0;'>✅ lecturer_profiles table created</div>";

    // Create admin_profiles table
    $sql = "CREATE TABLE IF NOT EXISTS admin_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        admin_id VARCHAR(50) NOT NULL UNIQUE,
        access_level ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
        department VARCHAR(255),
        permissions JSON,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE,
        INDEX idx_admin_id (admin_id),
        INDEX idx_access_level (access_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
    echo "<div style='color: green; margin: 10px 0;'>✅ admin_profiles table created</div>";

    // Create profile_images table for storing image metadata
    $sql = "CREATE TABLE IF NOT EXISTS profile_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        image_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
    echo "<div style='color: green; margin: 10px 0;'>✅ profile_images table created</div>";

    // Migrate existing student data
    echo "<h3>🔄 Migrating Existing Data</h3>";
    
    // Migrate students
    $sql = "SELECT s.id, s.name, s.email, s.reg_no, s.course_id, s.year_of_study, s.year_joined 
            FROM students s 
            LEFT JOIN user_profiles up ON s.id = up.user_id 
            WHERE up.user_id IS NULL";
    
    $result = $conn->query($sql);
    $migrated_students = 0;
    
    if ($result) {
        while ($student = $result->fetch_assoc()) {
            // Insert into user_profiles
            $sql = "INSERT INTO user_profiles (user_id, user_role, full_name, email) VALUES (?, 'student', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $student['id'], $student['name'], $student['email']);
            $stmt->execute();
            
            // Insert into student_profiles
            $sql = "INSERT INTO student_profiles (user_id, registration_number, course_id, year_of_study, academic_year) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $academic_year = $student['year_joined'];
            $stmt->bind_param("isiis", $student['id'], $student['reg_no'], $student['course_id'], $student['year_of_study'], $academic_year);
            $stmt->execute();
            
            $migrated_students++;
        }
        echo "<div style='color: green; margin: 10px 0;'>✅ Migrated $migrated_students students</div>";
    }

    // Migrate lecturers
    $sql = "SELECT l.id, l.name, l.email 
            FROM lecturers l 
            LEFT JOIN user_profiles up ON l.id = up.user_id 
            WHERE up.user_id IS NULL";
    
    $result = $conn->query($sql);
    $migrated_lecturers = 0;
    
    if ($result) {
        while ($lecturer = $result->fetch_assoc()) {
            // Insert into user_profiles
            $sql = "INSERT INTO user_profiles (user_id, user_role, full_name, email) VALUES (?, 'lecturer', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $lecturer['id'], $lecturer['name'], $lecturer['email']);
            $stmt->execute();
            
            // Insert into lecturer_profiles
            $staff_id = 'STAFF' . str_pad($lecturer['id'], 6, '0', STR_PAD_LEFT);
            $sql = "INSERT INTO lecturer_profiles (user_id, staff_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $lecturer['id'], $staff_id);
            $stmt->execute();
            
            $migrated_lecturers++;
        }
        echo "<div style='color: green; margin: 10px 0;'>✅ Migrated $migrated_lecturers lecturers</div>";
    }

    // Create uploads directory for profile pictures
    $uploads_dir = '../uploads/profile_pictures';
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
        echo "<div style='color: green; margin: 10px 0;'>✅ Created uploads directory</div>";
    }

    echo "<h3 style='color: green;'>✅ Profile System Setup Complete!</h3>";
    echo "<p>The extended user profile system has been successfully created with:</p>";
    echo "<ul>";
    echo "<li>✅ Extended user profiles with role-specific fields</li>";
    echo "<li>✅ Student profiles with academic information</li>";
    echo "<li>✅ Lecturer profiles with professional details</li>";
    echo "<li>✅ Admin profiles with access control</li>";
    echo "<li>✅ Profile image management system</li>";
    echo "<li>✅ Data migration from existing users</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<div style='color: red; margin: 10px 0;'>❌ Error: " . $e->getMessage() . "</div>";
}

$conn->close();
?>
