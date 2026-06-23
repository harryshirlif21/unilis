-- Migration: Add report_file upload columns to lab_reports
-- Date: 2026-06-22
-- Description: Allows students to upload their completed physical datasheet back as a report file
-- Safe to run multiple times (IF NOT EXISTS / IF NOT EXISTS column guards)

-- Create table if it doesn't exist yet (idempotent)
CREATE TABLE IF NOT EXISTS lab_reports (
    id VARCHAR(32) PRIMARY KEY,
    practical_id VARCHAR(32) NOT NULL,
    student_id VARCHAR(32) NOT NULL,
    status ENUM('in_progress', 'submitted') DEFAULT 'in_progress',
    observations_json LONGTEXT,
    calculations LONGTEXT,
    result LONGTEXT,
    conclusion LONGTEXT,
    report_file VARCHAR(500) DEFAULT NULL,
    report_uploaded_at TIMESTAMP NULL DEFAULT NULL,
    submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_practical_student (practical_id, student_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
);

-- Add columns if the table already exists without them
ALTER TABLE lab_reports
    ADD COLUMN IF NOT EXISTS report_file VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS report_uploaded_at TIMESTAMP NULL DEFAULT NULL;
