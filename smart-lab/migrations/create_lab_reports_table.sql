-- Migration: Create lab_reports table for student submissions
-- Date: 2026-05-12
-- Description: Table to store student lab reports with observations, calculations, results, and conclusions

CREATE TABLE IF NOT EXISTS lab_reports (
    id VARCHAR(32) PRIMARY KEY,
    practical_id VARCHAR(32) NOT NULL,
    student_id VARCHAR(32) NOT NULL,
    status ENUM('in_progress', 'submitted') DEFAULT 'in_progress',
    observations_json LONGTEXT,
    calculations LONGTEXT,
    result LONGTEXT,
    conclusion LONGTEXT,
    submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (practical_id) REFERENCES practicals(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_practical_student (practical_id, student_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_submitted (submitted_at)
);