-- Team marks awarded by lecturers (group or individual)
CREATE TABLE IF NOT EXISTS `team_marks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `team_id` int NOT NULL,
  `student_id` int DEFAULT NULL COMMENT 'NULL for team-wide marks',
  `awarded_by` int NOT NULL COMMENT 'lecturer id',
  `mark` decimal(6,2) NOT NULL,
  `max_mark` decimal(6,2) NOT NULL DEFAULT 100.00,
  `mark_type` enum('team','individual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `component` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `awarded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_marks_team` (`team_id`),
  KEY `idx_team_marks_student` (`student_id`),
  KEY `idx_team_marks_awarded_by` (`awarded_by`),
  CONSTRAINT `fk_team_marks_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_marks_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_marks_lecturer` FOREIGN KEY (`awarded_by`) REFERENCES `lecturers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
