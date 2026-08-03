<?php
/**
 * One-off migration: create the teams system tables.
 *
 * Adds tables backing the teams module:
 *   teams                    - Team information
 *   team_members             - Team membership with roles
 *   team_files               - File uploads for teams
 *   team_activity_log        - Activity tracking
 *   team_tasks               - Task management
 *   team_submissions         - Assessment submissions
 *   team_marks               - Marking system
 *   team_supervisors         - Supervisor assignments (lecturers/technicians)
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            http://localhost:8080/migrate_teams_system.php
 *   Shell:   docker exec unilis-db mysql -uunilisuser -punilispass unilis < migrate_teams_system.php
 *
 * Only the admin role may run it over HTTP. Safe to run more than once:
 * existing tables are left alone.
 */

define('IS_CLI', PHP_SAPI === 'cli');

function bail(string $message, int $httpStatus = 500): void
{
    if (IS_CLI) {
        fwrite(STDERR, $message . "\n");
    } else {
        http_response_code($httpStatus);
        echo $message . "\n";
    }
    exit(1);
}

if (!IS_CLI) {
    header('Content-Type: text/plain; charset=utf-8');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Schema changes are admin-only over HTTP.
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        bail("Access denied. Only admins can run this migration.", 403);
    }

    require_once __DIR__ . '/config/db.php';
} else {
    // CLI mode: load database config directly
    require_once __DIR__ . '/config/db.php';
}

if (!isset($conn) || !$conn) {
    bail("Database connection failed.");
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $escapedTable = $conn->real_escape_string($table);
    $escapedColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");
    if (!$result) {
        return false;
    }
    return $result->num_rows > 0;
}

$tables = [
    // Teams table
    "CREATE TABLE IF NOT EXISTS `teams` (
        `id` int NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `unit_id` int NOT NULL,
        `course_id` int NOT NULL DEFAULT 0,
        `status` enum('active','locked','archived') DEFAULT 'active',
        `assessment_type` varchar(100),
        `description` text,
        `submission_mode` varchar(50) DEFAULT 'standard',
        `max_members` int NOT NULL DEFAULT 15,
        `created_by` int NOT NULL DEFAULT 0,
        `year` int NOT NULL DEFAULT 1,
        `min_supervisors` int NOT NULL DEFAULT 2,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_teams_unit` (`unit_id`),
        KEY `idx_teams_course` (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team members table
    "CREATE TABLE IF NOT EXISTS `team_members` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `student_id` int NOT NULL,
        `role` enum('leader','member','frontend_developer','backend_developer','machine_learning','ui_ux_designer','data_analyst','tester','researcher','presenter','other') DEFAULT 'member',
        `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_team_member` (`team_id`, `student_id`),
        KEY `idx_team_members_student` (`student_id`),
        KEY `idx_team_members_team` (`team_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team files table
    "CREATE TABLE IF NOT EXISTS `team_files` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `filepath` varchar(500) NOT NULL,
        `original_name` varchar(255) NOT NULL,
        `mime_type` varchar(100),
        `file_size` bigint,
        `uploader_id` int NOT NULL,
        `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_team_files_team` (`team_id`),
        KEY `idx_team_files_uploader` (`uploader_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team activity log table
    "CREATE TABLE IF NOT EXISTS `team_activity_log` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `user_id` int NOT NULL,
        `action_type` varchar(50) NOT NULL,
        `action_detail` text,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_team_activity_team` (`team_id`),
        KEY `idx_team_activity_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team tasks table
    "CREATE TABLE IF NOT EXISTS `team_tasks` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `title` varchar(255) NOT NULL,
        `description` text,
        `status` enum('pending','in_progress','completed') DEFAULT 'pending',
        `assigned_to` int,
        `due_date` date,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_team_tasks_team` (`team_id`),
        KEY `idx_team_tasks_assigned` (`assigned_to`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team submissions table
    "CREATE TABLE IF NOT EXISTS `team_submissions` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `assessment_id` int,
        `submission_type` varchar(50) DEFAULT 'assignment',
        `file_path` varchar(500),
        `file_name` varchar(255),
        `version` int DEFAULT 1,
        `submitted_by` int NOT NULL,
        `submitted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_team_submissions_team` (`team_id`),
        KEY `idx_team_submissions_assessment` (`assessment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team marks table
    "CREATE TABLE IF NOT EXISTS `team_marks` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team supervisors table
    "CREATE TABLE IF NOT EXISTS `team_supervisors` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `lecturer_id` int NOT NULL,
        `supervisor_type` enum('lecturer','technician','admin') NOT NULL DEFAULT 'lecturer',
        `is_primary` boolean DEFAULT FALSE,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `requested_by` int,
        `requested_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `approved_by` int,
        `approved_at` timestamp NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_team_lecturer` (`team_id`, `lecturer_id`),
        KEY `idx_team_supervisors_team` (`team_id`),
        KEY `idx_team_supervisors_lecturer` (`lecturer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Alter supervisor_type enum to include admin (for existing tables)
    "ALTER TABLE `team_supervisors` MODIFY COLUMN `supervisor_type` enum('lecturer','technician','admin') NOT NULL DEFAULT 'lecturer'",

    // Add department_id column to admins table if it doesn't exist
    "ALTER TABLE `admins` ADD COLUMN `department_id` INT DEFAULT NULL AFTER `email`"
];

$errors = [];
$success_count = 0;

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        $errors[] = $conn->error;
    } else {
        $success_count++;
    }
}

if (!columnExists($conn, 'admins', 'department_id')) {
    $placementColumn = columnExists($conn, 'admins', 'is_super_admin') ? 'is_super_admin' : 'email';
    $alterSql = "ALTER TABLE `admins` ADD COLUMN `department_id` INT DEFAULT NULL AFTER `{$placementColumn}`";
    if (!$conn->query($alterSql)) {
        $errors[] = $conn->error;
    } else {
        $success_count++;
    }
}

if (columnExists($conn, 'team_supervisors', 'supervisor_type')) {
    $alterEnumSql = "ALTER TABLE `team_supervisors` MODIFY COLUMN `supervisor_type` enum('lecturer','technician','admin') NOT NULL DEFAULT 'lecturer'";
    if (!$conn->query($alterEnumSql)) {
        $errors[] = $conn->error;
    } else {
        $success_count++;
    }
}

if (IS_CLI) {
    echo "Migration completed.\n";
    echo "Tables created/verified: $success_count\n";
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
} else {
    echo "Teams system migration completed.\n";
    echo "Tables created/verified: $success_count\n";
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    } else {
        echo "\nAll tables created successfully.";
    }
}
