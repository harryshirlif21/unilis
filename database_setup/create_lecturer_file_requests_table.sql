-- Create lecturer_file_requests table
CREATE TABLE IF NOT EXISTS `lecturer_file_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `lecturer_id` int(11) NOT NULL,
    `unit_id` int(11) NOT NULL,
    `student_id` int(11) NOT NULL,
    `request_title` varchar(255) NOT NULL,
    `request_description` text DEFAULT NULL,
    `file_type` varchar(50) DEFAULT 'document',
    `status` enum('pending','approved','rejected') DEFAULT 'pending',
    `uploaded_file_path` varchar(255) DEFAULT NULL,
    `uploaded_at` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `lecturer_id` (`lecturer_id`),
    KEY `unit_id` (`unit_id`),
    KEY `student_id` (`student_id`),
    KEY `status` (`status`),
    FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
