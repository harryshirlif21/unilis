<?php
/**
 * Ensures team_marks table exists (used by marks awarding and exports).
 */
function ensure_team_marks_table(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS `team_marks` (
            `id` int NOT NULL AUTO_INCREMENT,
            `team_id` int NOT NULL,
            `student_id` int DEFAULT NULL,
            `awarded_by` int NOT NULL,
            `mark` decimal(6,2) NOT NULL,
            `max_mark` decimal(6,2) NOT NULL DEFAULT 100.00,
            `mark_type` enum('team','individual') NOT NULL,
            `component` varchar(255) NOT NULL,
            `notes` text,
            `awarded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_team_marks_team` (`team_id`),
            KEY `idx_team_marks_student` (`student_id`),
            KEY `idx_team_marks_awarded_by` (`awarded_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Failed to ensure team_marks table: ' . $conn->error);
    }

    $ensured = true;
}
