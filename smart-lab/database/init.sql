-- SmartLab Database Initialization Script
-- This script sets up the database structure and default data

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS unilis_smartlab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unilis_smartlab;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    reg_number VARCHAR(100),
    role ENUM('student', 'lecturer', 'technician', 'lab_admin', 'admin') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Labs table
CREATE TABLE IF NOT EXISTS labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    room_number VARCHAR(50),
    capacity INT DEFAULT 30,
    equipment TEXT,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

-- QR Sessions table
CREATE TABLE IF NOT EXISTS qr_sessions (
    id VARCHAR(32) PRIMARY KEY,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Lab Attendance table
CREATE TABLE IF NOT EXISTS lab_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    schedule_id INT,
    login_method ENUM('qr_code', 'biometric', 'manual') DEFAULT 'qr_code',
    attendance_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    check_out_time TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_schedule (user_id, schedule_id),
    INDEX idx_attendance_time (attendance_time)
);

-- OTP Codes table (for technician auth codes)
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    schedule_id INT NOT NULL,
    technician_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (technician_id) REFERENCES users(id),
    INDEX idx_schedule_status (schedule_id, status),
    INDEX idx_code (code)
);

-- Experiments table
CREATE TABLE IF NOT EXISTS experiments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    unit_code VARCHAR(50) NOT NULL,
    unit_name VARCHAR(255) NOT NULL,
    `group` VARCHAR(100),
    technician_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('draft', 'published') DEFAULT 'draft',
    INDEX idx_technician (technician_id),
    INDEX idx_status (status)
);

-- Experiment sections
CREATE TABLE IF NOT EXISTS experiment_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    section_type ENUM('objective', 'theory', 'apparatus', 'diagram', 'procedure', 'results_structure', 'analysis', 'discussion', 'conclusion', 'references') NOT NULL,
    section_title VARCHAR(255),
    content TEXT,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_experiment_order (experiment_id, display_order)
);

-- Experiment apparatus
CREATE TABLE IF NOT EXISTS experiment_apparatus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity VARCHAR(50),
    display_order INT DEFAULT 0,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_experiment_order (experiment_id, display_order)
);

-- Experiment procedure steps
CREATE TABLE IF NOT EXISTS experiment_procedure_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    step_number INT NOT NULL,
    step_description TEXT NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_experiment_order (experiment_id, step_number)
);

-- Experiment results structure
CREATE TABLE IF NOT EXISTS experiment_results_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    column_type ENUM('text', 'number', 'calculation') DEFAULT 'text',
    column_order INT DEFAULT 0,
    formula TEXT,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_experiment_order (experiment_id, column_order)
);

-- Lab Schedules table
CREATE TABLE IF NOT EXISTS lab_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    technician_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    scheduled_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    lab_location_lat DECIMAL(10, 8),
    lab_location_lng DECIMAL(11, 8),
    lab_location_radius INT DEFAULT 50,
    max_students INT DEFAULT 30,
    status ENUM('scheduled', 'active', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id),
    INDEX idx_date_status (scheduled_date, status),
    INDEX idx_technician (technician_id)
);

-- Student Submissions table
CREATE TABLE IF NOT EXISTS student_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    experiment_id INT NOT NULL,
    schedule_id INT NOT NULL,
    status ENUM('draft', 'submitted') DEFAULT 'draft',
    submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_schedule (user_id, schedule_id),
    FOREIGN KEY (schedule_id) REFERENCES lab_schedules(id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_schedule (schedule_id)
);

-- Submission Data table
CREATE TABLE IF NOT EXISTS submission_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    section_type ENUM('objective', 'theory', 'apparatus', 'diagram', 'procedure', 'results', 'analysis', 'discussion', 'conclusion', 'references') NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES student_submissions(id) ON DELETE CASCADE,
    INDEX idx_submission_section (submission_id, section_type)
);

-- Submission Results table
CREATE TABLE IF NOT EXISTS submission_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    row_number INT NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES student_submissions(id) ON DELETE CASCADE,
    INDEX idx_submission_row (submission_id, row_number),
    INDEX idx_submission_column (submission_id, column_name)
);

-- Activity Log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created (created_at)
);

-- Insert default lab
INSERT IGNORE INTO labs (id, name, room_number, capacity, equipment, status) 
VALUES (1, 'SmartLab Main', 'LAB-001', 30, 'Computers, Microscopes, Safety Equipment', 'active');

-- Insert default technician (will be created by setup script)
-- This is a placeholder - the actual user will be created by the setup script
INSERT IGNORE INTO users (id, email, password, full_name, reg_number, role, is_active) 
VALUES (1, 'labadmin@unilis.jhubafrica.com', '', 'SmartLab Administrator', 'LAB001', 'lab_admin', 1);

-- Create sample experiment (will be updated by setup script)
INSERT IGNORE INTO experiments (id, title, unit_code, unit_name, technician_id, status) 
VALUES (1, 'Sample Laboratory Experiment', 'LAB001', 'Introduction to Laboratory Techniques', 1, 'published');

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_lab_attendance_user_time ON lab_attendance(user_id, attendance_time);
CREATE INDEX IF NOT EXISTS idx_lab_schedules_date ON lab_schedules(scheduled_date, status);
CREATE INDEX IF NOT EXISTS idx_student_submissions_user ON student_submissions(user_id, status);
CREATE INDEX IF NOT EXISTS idx_activity_log_user_time ON activity_log(user_id, created_at);

-- Set up foreign key constraints
ALTER TABLE lab_attendance ADD CONSTRAINT fk_lab_attendance_schedule 
FOREIGN KEY (schedule_id) REFERENCES lab_schedules(id) ON DELETE SET NULL;

-- Practical Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    practical_id VARCHAR(32) NOT NULL,
    verification_method ENUM('qr', 'rfid', 'fingerprint', 'manual') DEFAULT 'qr',
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_practical (student_id, practical_id),
    INDEX idx_marked_at (marked_at)
);

ALTER TABLE otp_codes ADD CONSTRAINT fk_otp_codes_schedule 
FOREIGN KEY (schedule_id) REFERENCES lab_schedules(id) ON DELETE CASCADE;

ALTER TABLE experiments ADD CONSTRAINT fk_experiments_technician 
FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE lab_schedules ADD CONSTRAINT fk_lab_schedules_experiment 
FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE;

ALTER TABLE lab_schedules ADD CONSTRAINT fk_lab_schedules_technician 
FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE student_submissions ADD CONSTRAINT fk_student_submissions_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE student_submissions ADD CONSTRAINT fk_student_submissions_experiment 
FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE;
