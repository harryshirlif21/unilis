-- SmartLab Practical Lifecycle System Database Schema

-- Experiments table for technician-created lab experiments
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

-- Experiment sections (structured lab manual content)
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

-- Dynamic apparatus list for experiments
CREATE TABLE IF NOT EXISTS experiment_apparatus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity VARCHAR(50),
    display_order INT DEFAULT 0,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_experiment_order (experiment_id, display_order)
);

-- Procedure steps for experiments
CREATE TABLE IF NOT EXISTS experiment_procedure_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    step_number INT NOT NULL,
    step_description TEXT NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_experiment_order (experiment_id, step_number)
);

-- Results table structure definition
CREATE TABLE IF NOT EXISTS experiment_results_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_id INT NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    column_type ENUM('text', 'number', 'calculation') DEFAULT 'text',
    column_order INT DEFAULT 0,
    formula TEXT, -- For calculation columns
    FOREIGN KEY (experiment_id) REFERENCES experiments(id) ON DELETE CASCADE,
    INDEX idx_experiment_order (experiment_id, column_order)
);

-- Schedules table linking experiments to lab sessions
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
    lab_location_radius INT DEFAULT 50, -- meters
    max_students INT DEFAULT 30,
    status ENUM('scheduled', 'active', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (experiment_id) REFERENCES experiments(id),
    INDEX idx_date_status (scheduled_date, status),
    INDEX idx_technician (technician_id)
);

-- Student submissions for practicals
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

-- Submission data (content for each section)
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

-- Results data (tabular data for experiments)
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

-- Enhanced OTP codes table for technician auth codes
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL,
    schedule_id INT NOT NULL,
    technician_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES lab_schedules(id),
    INDEX idx_schedule_status (schedule_id, status),
    INDEX idx_code (code)
);

-- Ensure existing tables exist
-- Note: qr_sessions, lab_attendance, users tables should already exist
