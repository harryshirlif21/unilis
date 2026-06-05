-- Add deadline extension columns to report_deadlines (2026-06-05)
-- Prefer running: php smart-lab/migrations/add_report_deadline_extension_columns.php
-- Skip any statement that fails with "Duplicate column name".

ALTER TABLE report_deadlines ADD COLUMN extended TINYINT(1) DEFAULT 0 AFTER deadline_date;
ALTER TABLE report_deadlines ADD COLUMN extended_until DATETIME DEFAULT NULL AFTER extended;
ALTER TABLE report_deadlines ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
