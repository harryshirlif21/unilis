<?php
/**
 * Live Engagement Module - Database Installer
 * 
 * Creates all required tables for the Live Engagement Module.
 * Safe to run multiple times - checks if tables/columns exist before creating.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS') && !defined('LIVE_ENGAGEMENT_INSTALL')) {
    die('Direct access not permitted');
}

/**
 * Install all Live Engagement tables
 * 
 * @param mysqli $conn Database connection
 * @return array{success: bool, messages: string[]}
 */
function installLiveEngagementTables(mysqli $conn): array
{
    $messages = [];
    $success = true;

    try {
        // 1. live_sessions - Core session table
        $tableName = 'live_sessions';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `session_code` VARCHAR(20) NOT NULL UNIQUE,
                `course_id` INT UNSIGNED NULL,
                `unit_id` INT UNSIGNED NULL,
                `meeting_id` INT UNSIGNED NULL,
                `lecturer_id` INT UNSIGNED NOT NULL,
                `team_id` INT UNSIGNED NULL,
                `status` ENUM('scheduled','active','paused','ended') NOT NULL DEFAULT 'scheduled',
                `session_type` ENUM('presentation','whiteboard','poll','quiz','mixed') NOT NULL DEFAULT 'mixed',
                `allow_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
                `allow_recording` TINYINT(1) NOT NULL DEFAULT 0,
                `max_participants` INT UNSIGNED NULL,
                `scheduled_start` DATETIME NULL,
                `actual_start` DATETIME NULL,
                `actual_end` DATETIME NULL,
                `duration_minutes` INT UNSIGNED NULL,
                `passcode` VARCHAR(64) NULL,
                `is_template` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_session_code` (`session_code`),
                INDEX `idx_lecturer` (`lecturer_id`),
                INDEX `idx_course` (`course_id`),
                INDEX `idx_unit` (`unit_id`),
                INDEX `idx_meeting` (`meeting_id`),
                INDEX `idx_status` (`status`),
                INDEX `idx_team` (`team_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
            // Ensure columns exist
            ensureColumnsExist($conn, $tableName, [
                'description' => "TEXT NULL AFTER `title`",
                'team_id' => "INT UNSIGNED NULL AFTER `lecturer_id`",
                'allow_recording' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `allow_anonymous`",
                'is_template' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `passcode`",
            ], $messages);
        }

        // 2. live_presentations
        $tableName = 'live_presentations';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `file_path` VARCHAR(500) NULL,
                `file_type` ENUM('pdf','pptx','image','video','embed','html') NULL,
                `file_size` BIGINT UNSIGNED NULL,
                `original_filename` VARCHAR(255) NULL,
                `total_slides` INT UNSIGNED NOT NULL DEFAULT 0,
                `current_slide` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `allow_download` TINYINT(1) NOT NULL DEFAULT 0,
                `allow_annotations` TINYINT(1) NOT NULL DEFAULT 1,
                `presenter_notes` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 3. presentation_slides
        $tableName = 'presentation_slides';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `presentation_id` INT UNSIGNED NOT NULL,
                `slide_number` INT UNSIGNED NOT NULL,
                `image_path` VARCHAR(500) NULL,
                `content_html` LONGTEXT NULL,
                `notes` TEXT NULL,
                `duration_seconds` INT UNSIGNED NULL,
                `transition_type` VARCHAR(50) DEFAULT 'fade',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`presentation_id`) REFERENCES `live_presentations`(`id`) ON DELETE CASCADE,
                INDEX `idx_presentation` (`presentation_id`),
                INDEX `idx_slide_number` (`slide_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 4. live_participants
        $tableName = 'live_participants';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `display_name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(255) NULL,
                `role` ENUM('presenter','co_presenter','participant') NOT NULL DEFAULT 'participant',
                `joined_at` DATETIME NOT NULL,
                `left_at` DATETIME NULL,
                `duration_seconds` INT UNSIGNED NULL,
                `is_online` TINYINT(1) NOT NULL DEFAULT 0,
                `hand_raised` TINYINT(1) NOT NULL DEFAULT 0,
                `hand_raised_at` DATETIME NULL,
                `reaction` VARCHAR(50) NULL,
                `device_info` VARCHAR(255) NULL,
                `ip_address` VARCHAR(45) NULL,
                `attendance_recorded` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_online` (`is_online`),
                INDEX `idx_hand_raised` (`hand_raised`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 5. live_polls
        $tableName = 'live_polls';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `question` TEXT NOT NULL,
                `poll_type` ENUM('multiple_choice','true_false','yes_no','rating','likert','opinion') NOT NULL DEFAULT 'multiple_choice',
                `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
                `is_multiple_answer` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
                `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `time_limit_seconds` INT UNSIGNED NULL,
                `created_by` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `closed_at` DATETIME NULL,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_active` (`is_active`),
                INDEX `idx_order` (`display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 6. live_poll_options
        $tableName = 'live_poll_options';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `poll_id` INT UNSIGNED NOT NULL,
                `option_text` VARCHAR(500) NOT NULL,
                `option_value` VARCHAR(100) NULL,
                `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
                `color` VARCHAR(20) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`poll_id`) REFERENCES `live_polls`(`id`) ON DELETE CASCADE,
                INDEX `idx_poll` (`poll_id`),
                INDEX `idx_order` (`display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 7. live_poll_responses
        $tableName = 'live_poll_responses';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `poll_id` INT UNSIGNED NOT NULL,
                `option_id` INT UNSIGNED NULL,
                `user_id` INT UNSIGNED NULL,
                `session_participant_id` INT UNSIGNED NULL,
                `rating_value` INT NULL,
                `response_text` TEXT NULL,
                `responded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`poll_id`) REFERENCES `live_polls`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`option_id`) REFERENCES `live_poll_options`(`id`) ON DELETE SET NULL,
                INDEX `idx_poll` (`poll_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_participant` (`session_participant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 8. live_quizzes
        $tableName = 'live_quizzes';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `time_limit_minutes` INT UNSIGNED NULL,
                `passing_score` DECIMAL(5,2) NULL,
                `shuffle_questions` TINYINT(1) NOT NULL DEFAULT 0,
                `show_results` TINYINT(1) NOT NULL DEFAULT 1,
                `max_attempts` INT UNSIGNED NOT NULL DEFAULT 1,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 9. quiz_questions
        $tableName = 'quiz_questions';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `quiz_id` INT UNSIGNED NOT NULL,
                `question_text` TEXT NOT NULL,
                `question_type` ENUM('multiple_choice','true_false','short_answer','fill_blank','matching') NOT NULL DEFAULT 'multiple_choice',
                `points` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `explanation` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`quiz_id`) REFERENCES `live_quizzes`(`id`) ON DELETE CASCADE,
                INDEX `idx_quiz` (`quiz_id`),
                INDEX `idx_order` (`display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 10. quiz_answers
        $tableName = 'quiz_answers';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `question_id` INT UNSIGNED NOT NULL,
                `answer_text` VARCHAR(500) NOT NULL,
                `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
                `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE,
                INDEX `idx_question` (`question_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 11. live_wordcloud
        $tableName = 'live_wordcloud';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `prompt` VARCHAR(500) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `max_words` INT UNSIGNED NOT NULL DEFAULT 50,
                `min_word_length` INT UNSIGNED NOT NULL DEFAULT 2,
                `allow_profanity` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `closed_at` DATETIME NULL,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 12. live_open_responses
        $tableName = 'live_open_responses';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `prompt` TEXT NOT NULL,
                `response_type` ENUM('paragraph','short','single_line') NOT NULL DEFAULT 'short',
                `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
                `is_moderated` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `max_characters` INT UNSIGNED NULL,
                `created_by` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `closed_at` DATETIME NULL,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 13. live_notes
        $tableName = 'live_notes';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `slide_id` INT UNSIGNED NULL,
                `content` LONGTEXT NOT NULL,
                `is_shared` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_slide` (`slide_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 14. live_whiteboards
        $tableName = 'live_whiteboards';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL DEFAULT 'Whiteboard',
                `width` INT UNSIGNED NOT NULL DEFAULT 1920,
                `height` INT UNSIGNED NOT NULL DEFAULT 1080,
                `background_color` VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `is_collaborative` TINYINT(1) NOT NULL DEFAULT 0,
                `snapshot_path` VARCHAR(500) NULL,
                `created_by` INT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 15. whiteboard_objects
        $tableName = 'whiteboard_objects';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `whiteboard_id` INT UNSIGNED NOT NULL,
                `object_type` ENUM('path','rect','circle','line','text','image','arrow','highlight','eraser') NOT NULL,
                `object_data` JSON NOT NULL,
                `style_data` JSON NULL,
                `z_index` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`whiteboard_id`) REFERENCES `live_whiteboards`(`id`) ON DELETE CASCADE,
                INDEX `idx_whiteboard` (`whiteboard_id`),
                INDEX `idx_type` (`object_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 16. live_reports
        $tableName = 'live_reports';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `report_type` ENUM('attendance','participation','poll','quiz','presentation','comprehensive') NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `summary_data` JSON NULL,
                `generated_by` INT UNSIGNED NOT NULL,
                `file_path` VARCHAR(500) NULL,
                `file_format` ENUM('pdf','excel','csv','html') NULL,
                `is_auto_generated` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_type` (`report_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // 17. live_statistics
        $tableName = 'live_statistics';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `total_participants` INT UNSIGNED NOT NULL DEFAULT 0,
                `peak_concurrent` INT UNSIGNED NOT NULL DEFAULT 0,
                `avg_participation_minutes` DECIMAL(10,2) NULL,
                `total_polls_created` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_poll_responses` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_quiz_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_quiz_score` DECIMAL(10,2) NULL,
                `total_wordcloud_submissions` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_open_responses` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_hand_raises` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_reactions` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_whiteboard_actions` INT UNSIGNED NOT NULL DEFAULT 0,
                `engagement_score` DECIMAL(5,2) NULL,
                `snapshot_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_snapshot` (`snapshot_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // Add wordcloud_submissions table for storing actual word submissions
        $tableName = 'wordcloud_submissions';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `wordcloud_id` INT UNSIGNED NOT NULL,
                `word` VARCHAR(255) NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `session_participant_id` INT UNSIGNED NULL,
                `weight` INT UNSIGNED NOT NULL DEFAULT 1,
                `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`wordcloud_id`) REFERENCES `live_wordcloud`(`id`) ON DELETE CASCADE,
                INDEX `idx_wordcloud` (`wordcloud_id`),
                INDEX `idx_word` (`word`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // Add open_response_submissions table
        $tableName = 'open_response_submissions';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `open_response_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `session_participant_id` INT UNSIGNED NULL,
                `response_text` TEXT NOT NULL,
                `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
                `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`open_response_id`) REFERENCES `live_open_responses`(`id`) ON DELETE CASCADE,
                INDEX `idx_open_response` (`open_response_id`),
                INDEX `idx_approved` (`is_approved`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // Add quiz_attempts table
        $tableName = 'quiz_attempts';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `quiz_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `session_participant_id` INT UNSIGNED NULL,
                `score` DECIMAL(10,2) NULL,
                `total_points` DECIMAL(10,2) NULL,
                `percentage` DECIMAL(5,2) NULL,
                `started_at` DATETIME NOT NULL,
                `completed_at` DATETIME NULL,
                `time_taken_seconds` INT UNSIGNED NULL,
                `attempt_number` INT UNSIGNED NOT NULL DEFAULT 1,
                `status` ENUM('in_progress','completed','timed_out','abandoned') NOT NULL DEFAULT 'in_progress',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`quiz_id`) REFERENCES `live_quizzes`(`id`) ON DELETE CASCADE,
                INDEX `idx_quiz` (`quiz_id`),
                INDEX `idx_user` (`user_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // Add quiz_attempt_answers table
        $tableName = 'quiz_attempt_answers';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `attempt_id` INT UNSIGNED NOT NULL,
                `question_id` INT UNSIGNED NOT NULL,
                `answer_id` INT UNSIGNED NULL,
                `answer_text` TEXT NULL,
                `is_correct` TINYINT(1) NULL,
                `points_earned` DECIMAL(5,2) NULL,
                `answered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE,
                INDEX `idx_attempt` (`attempt_id`),
                INDEX `idx_question` (`question_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

        // Add live_reactions table
        $tableName = 'live_reactions';
        if (!tableExists($conn, $tableName)) {
            $sql = "CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `session_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `session_participant_id` INT UNSIGNED NULL,
                `reaction_type` VARCHAR(50) NOT NULL,
                `target_type` ENUM('slide','poll','quiz','whiteboard','general') NOT NULL DEFAULT 'general',
                `target_id` INT UNSIGNED NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`session_id`) REFERENCES `live_sessions`(`id`) ON DELETE CASCADE,
                INDEX `idx_session` (`session_id`),
                INDEX `idx_type` (`reaction_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created table: {$tableName}";
            } else {
                throw new Exception("Failed to create {$tableName}: " . $conn->error);
            }
        } else {
            $messages[] = "Table exists (skipped): {$tableName}";
        }

    } catch (Exception $e) {
        $success = false;
        $messages[] = 'ERROR: ' . $e->getMessage();
    }

    return [
        'success' => $success,
        'messages' => $messages
    ];
}

/**
 * Check if a table exists in the database
 * 
 * @param mysqli $conn
 * @param string $tableName
 * @return bool
 */
function tableExists(mysqli $conn, string $tableName): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    return $result && $result->num_rows > 0;
}

/**
 * Ensure specific columns exist in a table, adding them if missing
 * 
 * @param mysqli $conn
 * @param string $tableName
 * @param array $columns Column name => column definition
 * @param array &$messages Reference to messages array
 */
function ensureColumnsExist(mysqli $conn, string $tableName, array $columns, array &$messages): void
{
    $existingColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM `{$tableName}`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
    }

    foreach ($columns as $columnName => $definition) {
        if (!in_array($columnName, $existingColumns)) {
            $alterSql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$definition}";
            if ($conn->query($alterSql)) {
                $messages[] = "Added column `{$columnName}` to `{$tableName}`";
            } else {
                $messages[] = "Failed to add column `{$columnName}` to `{$tableName}`: " . $conn->error;
            }
        }
    }
}

// Run installer if accessed directly
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === basename(__FILE__)) {
    define('LIVE_ENGAGEMENT_INSTALL', true);
    require_once __DIR__ . '/../../config/db.php';
    
    header('Content-Type: application/json');
    $result = installLiveEngagementTables($conn);
    echo json_encode($result, JSON_PRETTY_PRINT);
}