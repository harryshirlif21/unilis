-- Create team_membership_requests table for handling removal/leave requests
CREATE TABLE IF NOT EXISTS `team_membership_requests` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `team_id` int NOT NULL,
  `student_id` int NOT NULL,
  `requested_by` int NOT NULL,
  `request_type` enum('leave', 'remove') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'leave' COMMENT 'leave = student requests to leave, remove = team lead removes member',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending', 'approved', 'rejected', 'cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by_lecturer` int DEFAULT NULL,
  `approved_by_team_lead` int DEFAULT NULL,
  `lecturer_approval_at` datetime DEFAULT NULL,
  `team_lead_approval_at` datetime DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by_lecturer`) REFERENCES `lecturers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by_team_lead`) REFERENCES `students`(`id`) ON DELETE SET NULL,
  KEY `idx_team_status` (`team_id`, `status`),
  KEY `idx_student_status` (`student_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
