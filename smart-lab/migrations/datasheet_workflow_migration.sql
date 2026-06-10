-- Lab Datasheet PDF Workflow Migration
-- Date: 2026-06-08

-- Ensure reports table has graded_by column
ALTER TABLE reports ADD COLUMN IF NOT EXISTS graded_by VARCHAR(36) DEFAULT NULL;

-- Create datasheets table for PDF management
CREATE TABLE IF NOT EXISTS datasheets (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  student_id CHAR(36) NOT NULL,
  practical_id CHAR(36) NOT NULL,
  report_id CHAR(36) DEFAULT NULL,
  pdf_filename VARCHAR(255) NOT NULL,
  pdf_path VARCHAR(500) NOT NULL,
  signature_hash VARCHAR(64) NOT NULL,
  qr_code_data TEXT NOT NULL,
  qr_code_path VARCHAR(500) DEFAULT NULL,
  authentication_method ENUM('biometric', 'rfid', 'qrcode', 'auth_code', 'password') DEFAULT 'password',
  approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  approved_by CHAR(36) DEFAULT NULL,
  approved_at TIMESTAMP NULL DEFAULT NULL,
  status ENUM('generated', 'submitted', 'verified', 'archived') DEFAULT 'generated',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_student_practical (student_id, practical_id),
  KEY idx_status (status),
  KEY idx_approval (approval_status),
  KEY idx_created (created_at),
  CONSTRAINT fk_datasheets_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_datasheets_practical FOREIGN KEY (practical_id) REFERENCES practicals(id) ON DELETE CASCADE,
  CONSTRAINT fk_datasheets_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create datasheet_readings table for experiment data
CREATE TABLE IF NOT EXISTS datasheet_readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  datasheet_id CHAR(36) NOT NULL,
  trial_number INT NOT NULL,
  measurement VARCHAR(255) DEFAULT NULL,
  units VARCHAR(50) DEFAULT NULL,
  observation TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_datasheet (datasheet_id),
  CONSTRAINT fk_readings_datasheet FOREIGN KEY (datasheet_id) REFERENCES datasheets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create chemistry_practicals table for prefilled practicals
CREATE TABLE IF NOT EXISTS chemistry_practicals (
  id CHAR(36) NOT NULL DEFAULT (UUID()),
  practical_id CHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  course_id CHAR(36) DEFAULT NULL,
  scheduled_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  lab_number VARCHAR(50) NOT NULL,
  experiment_name VARCHAR(255) NOT NULL,
  experiment_description TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_practical_ref (practical_id),
  CONSTRAINT fk_chem_practical FOREIGN KEY (practical_id) REFERENCES practicals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create chemistry_practical_readings template
CREATE TABLE IF NOT EXISTS chemistry_practical_readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chemistry_practical_id CHAR(36) NOT NULL,
  trial_number INT NOT NULL,
  measurement_label VARCHAR(255) DEFAULT NULL,
  units VARCHAR(50) DEFAULT NULL,
  observation_label VARCHAR(255) DEFAULT NULL,
  display_order INT DEFAULT 0,
  CONSTRAINT fk_reading_template FOREIGN KEY (chemistry_practical_id) REFERENCES chemistry_practicals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample chemistry practicals
INSERT IGNORE INTO chemistry_practicals (
  id, practical_id, title, course_id, scheduled_date, 
  start_time, end_time, lab_number, experiment_name, experiment_description
) VALUES (
  UUID(), UUID(), 'Chemistry Practical 1', NULL, '2026-06-10',
  '10:00:00', '16:00:00', 'Lab 1', 'Acid-Base Titration',
  'Students will conduct acid-base titration to determine the concentration of an unknown acid solution using a standardized base.'
);

INSERT IGNORE INTO chemistry_practicals (
  id, practical_id, title, course_id, scheduled_date,
  start_time, end_time, lab_number, experiment_name, experiment_description
) VALUES (
  UUID(), UUID(), 'Chemistry Practical 2', NULL, '2026-06-10',
  '10:00:00', '16:00:00', 'Lab 2', 'Rate of Reaction',
  'Students will investigate the effect of temperature and catalyst on the rate of reaction between hydrogen peroxide and potassium iodide.'
);

-- Create indexes for performance
CREATE INDEX idx_datasheet_student ON datasheets(student_id);
CREATE INDEX idx_datasheet_practical ON datasheets(practical_id);
CREATE INDEX idx_datasheet_signature ON datasheets(signature_hash);
