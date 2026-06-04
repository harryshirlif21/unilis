-- Smart Lab RFID and CO2 Sensor Database Schema
-- Run this migration to create tables for storing RFID scans and CO2 file metadata

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS unilis_smart_lab
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE unilis_smart_lab;

-- RFID Scans Table
CREATE TABLE IF NOT EXISTS rfid_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(50) NOT NULL,
    scan_time VARCHAR(8),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_uid (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CO2 Files Metadata Table (stores path to daily JSON files)
CREATE TABLE IF NOT EXISTS co2_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    file_date DATE UNIQUE NOT NULL,
    reading_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_file_date (file_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View for latest CO2 file
CREATE OR REPLACE VIEW latest_co2_file AS
SELECT * FROM co2_files
ORDER BY file_date DESC
LIMIT 1;

-- View for last 7 days of CO2 files
CREATE OR REPLACE VIEW co2_files_last_7d AS
SELECT * FROM co2_files
WHERE file_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY file_date DESC;

