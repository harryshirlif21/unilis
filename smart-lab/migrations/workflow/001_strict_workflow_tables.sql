-- ============================================================
-- UNILIS SmartLabs - Academic Integrity Practical Workflow
-- Migration 001: New tables for strict attendance, datasheet,
-- blockchain, and report workflow enforcement
-- ============================================================

-- 1. attendance_verifications: Track all verification attempts
CREATE TABLE IF NOT EXISTS `attendance_verifications` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` CHAR(36) NOT NULL,
  `practical_id` CHAR(36) NOT NULL,
  `verification_method` ENUM('RFID','BIOMETRIC','DYNAMIC_QR','TECHNICIAN_CODE','NFC','EMAIL_PASSWORD') NOT NULL,
  `verification_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verification_device` VARCHAR(255) DEFAULT NULL,
  `verification_status` ENUM('pending','verified','failed','revoked') NOT NULL DEFAULT 'pending',
  `verified_by` CHAR(36) DEFAULT NULL COMMENT 'Technician/admin who verified if applicable',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `session_token` VARCHAR(255) DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `revoked_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_verification_student` (`student_id`),
  KEY `idx_verification_practical` (`practical_id`),
  KEY `idx_verification_method` (`verification_method`),
  KEY `idx_verification_status` (`verification_status`),
  KEY `idx_verification_timestamp` (`verification_timestamp`),
  CONSTRAINT `fk_verification_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_verification_practical` FOREIGN KEY (`practical_id`) REFERENCES `practicals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. student_practical_sessions: Track complete practical lifecycle per student
CREATE TABLE IF NOT EXISTS `student_practical_sessions` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` CHAR(36) NOT NULL,
  `practical_id` CHAR(36) NOT NULL,
  `attendance_verification_id` CHAR(36) DEFAULT NULL,
  `workflow_status` ENUM(
    'Scheduled',
    'Awaiting Verification',
    'Verified',
    'Ready To Start',
    'In Progress',
    'Practical Completed',
    'Datasheet Generated',
    'Datasheet Submitted',
    'Report Writing Open',
    'Report Submitted',
    'Graded'
  ) NOT NULL DEFAULT 'Scheduled',
  `verification_method` ENUM('RFID','BIOMETRIC','DYNAMIC_QR','TECHNICIAN_CODE','NFC') DEFAULT NULL,
  `verification_timestamp` DATETIME DEFAULT NULL,
  `verification_approved` TINYINT(1) DEFAULT 0,
  `practical_started_at` DATETIME DEFAULT NULL,
  `practical_completed_at` DATETIME DEFAULT NULL,
  `datasheet_generated_at` DATETIME DEFAULT NULL,
  `datasheet_submitted_at` DATETIME DEFAULT NULL,
  `datasheet_hash` VARCHAR(64) DEFAULT NULL,
  `datasheet_qr_token` VARCHAR(255) DEFAULT NULL,
  `report_started_at` DATETIME DEFAULT NULL,
  `report_submitted_at` DATETIME DEFAULT NULL,
  `report_id` CHAR(36) DEFAULT NULL,
  `grade` DECIMAL(5,2) DEFAULT NULL,
  `graded_by` CHAR(36) DEFAULT NULL,
  `graded_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_practical` (`student_id`, `practical_id`),
  KEY `idx_session_status` (`workflow_status`),
  KEY `idx_session_verification` (`verification_method`),
  KEY `idx_session_datasheet` (`datasheet_hash`),
  KEY `idx_session_qr` (`datasheet_qr_token`),
  CONSTRAINT `fk_session_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_session_practical` FOREIGN KEY (`practical_id`) REFERENCES `practicals`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_session_verification` FOREIGN KEY (`attendance_verification_id`) REFERENCES `attendance_verifications`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. datasheet_submissions: Immutable datasheet records with blockchain linkage
CREATE TABLE IF NOT EXISTS `datasheet_submissions` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` CHAR(36) NOT NULL,
  `practical_id` CHAR(36) NOT NULL,
  `session_id` CHAR(36) DEFAULT NULL,
  `pdf_filename` VARCHAR(255) NOT NULL,
  `pdf_path` VARCHAR(500) NOT NULL,
  `pdf_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 of PDF content for tamper evidence',
  `datasheet_data` JSON DEFAULT NULL COMMENT 'Full JSON snapshot of datasheet content',
  `verification_method` ENUM('RFID','BIOMETRIC','DYNAMIC_QR','TECHNICIAN_CODE','NFC') NOT NULL,
  `verification_timestamp` DATETIME DEFAULT NULL,
  `practical_start_time` DATETIME DEFAULT NULL,
  `practical_end_time` DATETIME DEFAULT NULL,
  `blockchain_hash` VARCHAR(64) DEFAULT NULL COMMENT 'Hash stored in blockchain_blocks',
  `blockchain_block_id` INT DEFAULT NULL,
  `qr_token` VARCHAR(255) NOT NULL COMMENT 'Unique token embedded in QR code',
  `qr_code_path` VARCHAR(500) DEFAULT NULL,
  `signature_hash` VARCHAR(64) DEFAULT NULL,
  `submission_status` ENUM('generated','submitted','verified','revoked') NOT NULL DEFAULT 'generated',
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  `revoked_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_practical` (`student_id`, `practical_id`),
  UNIQUE KEY `unique_qr_token` (`qr_token`),
  KEY `idx_datasheet_blockchain` (`blockchain_hash`),
  KEY `idx_datasheet_status` (`submission_status`),
  KEY `idx_datasheet_created` (`created_at`),
  CONSTRAINT `fk_datasheet_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_datasheet_practical` FOREIGN KEY (`practical_id`) REFERENCES `practicals`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_datasheet_session` FOREIGN KEY (`session_id`) REFERENCES `student_practical_sessions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. datasheet_qr_tokens: Track QR code validation tokens
CREATE TABLE IF NOT EXISTS `datasheet_qr_tokens` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `datasheet_id` CHAR(36) NOT NULL,
  `token` VARCHAR(255) NOT NULL COMMENT 'Unique verification token',
  `lab_id` CHAR(36) DEFAULT NULL,
  `practical_id` CHAR(36) DEFAULT NULL,
  `session_id` CHAR(36) DEFAULT NULL,
  `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `used_by_ip` VARCHAR(45) DEFAULT NULL,
  `verification_status` ENUM('active','used','expired','revoked') NOT NULL DEFAULT 'active',
  `blockchain_hash` VARCHAR(64) DEFAULT NULL,
  `tamper_attempts` INT DEFAULT 0,
  `last_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_token_datasheet` (`datasheet_id`),
  KEY `idx_token_status` (`verification_status`),
  CONSTRAINT `fk_qr_token_datasheet` FOREIGN KEY (`datasheet_id`) REFERENCES `datasheet_submissions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_token_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_qr_token_practical` FOREIGN KEY (`practical_id`) REFERENCES `practicals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Add workflow_status column to practicals table (if not exists)
SET @dbname = DATABASE();
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = @dbname 
               AND TABLE_NAME = 'practicals' 
               AND COLUMN_NAME = 'workflow_status');
SET @query = IF(@exists = 0, 
    'ALTER TABLE practicals ADD COLUMN workflow_status ENUM(''standard'',''strict'',''hybrid'') DEFAULT ''standard'' AFTER status',
    'SELECT ''Column workflow_status already exists''');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Add verification_window_minutes column to practicals
SET @exists2 = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = @dbname 
                AND TABLE_NAME = 'practicals' 
                AND COLUMN_NAME = 'verification_window_opens_minutes');
SET @query2 = IF(@exists2 = 0, 
    'ALTER TABLE practicals ADD COLUMN verification_window_opens_minutes INT DEFAULT 30 AFTER max_students',
    'SELECT ''Column verification_window_opens_minutes already exists''');
PREPARE stmt2 FROM @query2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @exists3 = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = @dbname 
                AND TABLE_NAME = 'practicals' 
                AND COLUMN_NAME = 'verification_window_closes_minutes');
SET @query3 = IF(@exists3 = 0, 
    'ALTER TABLE practicals ADD COLUMN verification_window_closes_minutes INT DEFAULT 20 AFTER verification_window_opens_minutes',
    'SELECT ''Column verification_window_closes_minutes already exists''');
PREPARE stmt3 FROM @query3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- 7. Add allowed_verification_methods column to practicals 
SET @exists4 = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = @dbname 
                AND TABLE_NAME = 'practicals' 
                AND COLUMN_NAME = 'allowed_verification_methods');
SET @query4 = IF(@exists4 = 0, 
    'ALTER TABLE practicals ADD COLUMN allowed_verification_methods VARCHAR(500) DEFAULT ''RFID,BIOMETRIC,DYNAMIC_QR,TECHNICIAN_CODE,NFC'' AFTER verification_window_closes_minutes',
    'SELECT ''Column allowed_verification_methods already exists''');
PREPARE stmt4 FROM @query4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- 8. Ensure blockchain_blocks has index on datasheet_hash
SET @exists5 = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = @dbname 
                AND TABLE_NAME = 'blockchain_blocks' 
                AND COLUMN_NAME = 'datasheet_reference');
SET @query5 = IF(@exists5 = 0, 
    'ALTER TABLE blockchain_blocks ADD COLUMN datasheet_reference VARCHAR(64) DEFAULT NULL AFTER hash, ADD KEY idx_blockchain_datasheet (datasheet_reference)',
    'SELECT ''Column datasheet_reference already exists''');
PREPARE stmt5 FROM @query5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

-- 9. Add datasheet_submitted column to reports table for workflow lock
SET @exists6 = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = @dbname 
                AND TABLE_NAME = 'reports' 
                AND COLUMN_NAME = 'datasheet_submitted');
SET @query6 = IF(@exists6 = 0, 
    'ALTER TABLE reports ADD COLUMN datasheet_submitted TINYINT(1) DEFAULT 0 AFTER status, ADD COLUMN datasheet_id CHAR(36) DEFAULT NULL AFTER datasheet_submitted',
    'SELECT ''Columns already exist''');
PREPARE stmt6 FROM @query6;
EXECUTE stmt6;
DEALLOCATE PREPARE stmt6;

-- 10. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_attendance_lookup ON attendance_verifications(student_id, practical_id, verification_status);
CREATE INDEX IF NOT EXISTS idx_session_lookup ON student_practical_sessions(student_id, practical_id, workflow_status);
CREATE INDEX IF NOT EXISTS idx_datasheet_lookup ON datasheet_submissions(student_id, practical_id, submission_status);
CREATE INDEX IF NOT EXISTS idx_qr_token_lookup ON datasheet_qr_tokens(token, verification_status);