CREATE TABLE IF NOT EXISTS `attendance_verifications` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` CHAR(36) NOT NULL,
  `practical_id` CHAR(36) NOT NULL,
  `verification_method` ENUM('RFID','BIOMETRIC','DYNAMIC_QR','TECHNICIAN_CODE','NFC','EMAIL_PASSWORD') NOT NULL,
  `verification_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verification_device` VARCHAR(255) DEFAULT NULL,
  `verification_status` ENUM('pending','verified','failed','revoked') NOT NULL DEFAULT 'pending',
  `verified_by` CHAR(36) DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `student_practical_sessions` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` CHAR(36) NOT NULL,
  `practical_id` CHAR(36) NOT NULL,
  `attendance_verification_id` CHAR(36) DEFAULT NULL,
  `workflow_status` ENUM('Scheduled','Awaiting Verification','Verified','Ready To Start','In Progress','Practical Completed','Datasheet Generated','Datasheet Submitted','Report Writing Open','Report Submitted','Graded') NOT NULL DEFAULT 'Scheduled',
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

CREATE TABLE IF NOT EXISTS `datasheet_submissions` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `student_id` CHAR(36) NOT NULL,
  `practical_id` CHAR(36) NOT NULL,
  `session_id` CHAR(36) DEFAULT NULL,
  `pdf_filename` VARCHAR(255) NOT NULL,
  `pdf_path` VARCHAR(500) NOT NULL,
  `pdf_hash` VARCHAR(64) NOT NULL,
  `datasheet_data` JSON DEFAULT NULL,
  `verification_method` ENUM('RFID','BIOMETRIC','DYNAMIC_QR','TECHNICIAN_CODE','NFC') NOT NULL,
  `verification_timestamp` DATETIME DEFAULT NULL,
  `practical_start_time` DATETIME DEFAULT NULL,
  `practical_end_time` DATETIME DEFAULT NULL,
  `blockchain_hash` VARCHAR(64) DEFAULT NULL,
  `blockchain_block_id` INT DEFAULT NULL,
  `qr_token` VARCHAR(255) NOT NULL,
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

CREATE TABLE IF NOT EXISTS `datasheet_qr_tokens` (
  `id` CHAR(36) NOT NULL DEFAULT (UUID()),
  `datasheet_id` CHAR(36) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
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

-- ============================================================================
-- Schema Alterations
-- NOTE: ADD COLUMN IF NOT EXISTS is MariaDB-specific and NOT supported by MySQL 8.0.
-- Column existence is pre-checked in run_migration.php before executing these
-- ALTER statements, so plain ADD COLUMN is used here.
-- ============================================================================

ALTER TABLE `practicals` ADD COLUMN `workflow_status` ENUM('standard','strict','hybrid') DEFAULT 'standard' AFTER `status`;
ALTER TABLE `practicals` ADD COLUMN `verification_window_opens_minutes` INT DEFAULT 30 AFTER `max_students`;
ALTER TABLE `practicals` ADD COLUMN `verification_window_closes_minutes` INT DEFAULT 20 AFTER `verification_window_opens_minutes`;
ALTER TABLE `practicals` ADD COLUMN `allowed_verification_methods` VARCHAR(500) DEFAULT 'RFID,BIOMETRIC,DYNAMIC_QR,TECHNICIAN_CODE,NFC' AFTER `verification_window_closes_minutes`;

ALTER TABLE `blockchain_blocks` ADD COLUMN `datasheet_reference` VARCHAR(64) DEFAULT NULL AFTER `hash`;
CREATE INDEX `idx_blockchain_datasheet` ON `blockchain_blocks`(`datasheet_reference`);

ALTER TABLE `reports` ADD COLUMN `datasheet_submitted` TINYINT(1) DEFAULT 0 AFTER `status`;
ALTER TABLE `reports` ADD COLUMN `datasheet_id` CHAR(36) DEFAULT NULL AFTER `datasheet_submitted`;

CREATE INDEX `idx_attendance_lookup` ON `attendance_verifications`(`student_id`, `practical_id`, `verification_status`);
CREATE INDEX `idx_session_lookup` ON `student_practical_sessions`(`student_id`, `practical_id`, `workflow_status`);
CREATE INDEX `idx_datasheet_lookup` ON `datasheet_submissions`(`student_id`, `practical_id`, `submission_status`);
CREATE INDEX `idx_qr_token_lookup` ON `datasheet_qr_tokens`(`token`, `verification_status`);