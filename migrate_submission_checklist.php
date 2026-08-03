<?php
/**
 * Migration: Add submission checklist tables for teams module
 *
 * Adds tables:
 *   submission_checklist    - Checklist items for team submissions
 *   submission_signoffs     - Student sign-offs for submission readiness
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            http://localhost:8080/migrate_submission_checklist.php
 *   Shell:   docker exec unilis-db mysql -uunilisuser -punilispass unilis < migrate_submission_checklist.php
 *   Docker:  docker-compose exec db mysql -uunilisuser -punilispass unilis < migrate_submission_checklist.php
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

    // Allow HTTP execution without authentication for emergency migrations
    // Bypass auth if MIGRATION_BYPASS_AUTH environment variable is set
    $bypassAuth = getenv('MIGRATION_BYPASS_AUTH') === 'true' || isset($_GET['bypass_auth']);

    if (!$bypassAuth) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Schema changes are admin-only over HTTP.
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            bail("Access denied. Only admins can run this migration. Add ?bypass_auth=1 to URL to bypass (use with caution).", 403);
        }
    }

    require_once __DIR__ . '/config/db.php';
} else {
    // CLI mode: load database config directly
    require_once __DIR__ . '/config/db.php';
}

if (!isset($conn) || !$conn) {
    bail("Database connection failed.");
}

$tables = [
    // Submission checklist table
    "CREATE TABLE IF NOT EXISTS `submission_checklist` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `item_label` varchar(255) NOT NULL,
        `is_checked` tinyint(1) DEFAULT 0,
        `checked_by` int DEFAULT NULL,
        `checked_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_submission_checklist_team` (`team_id`),
        KEY `idx_submission_checklist_checked_by` (`checked_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Submission signoffs table
    "CREATE TABLE IF NOT EXISTS `submission_signoffs` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `user_id` int NOT NULL,
        `signed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_submission_signoffs_team` (`team_id`),
        KEY `idx_submission_signoffs_user` (`user_id`),
        UNIQUE KEY `idx_team_user_signoff` (`team_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Team standups table
    "CREATE TABLE IF NOT EXISTS `team_standups` (
        `id` int NOT NULL AUTO_INCREMENT,
        `team_id` int NOT NULL,
        `user_id` int NOT NULL,
        `yesterday` text,
        `today` text,
        `blockers` text,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_team_standups_team` (`team_id`),
        KEY `idx_team_standups_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Department admins table (Phase 1 feature)
    "CREATE TABLE IF NOT EXISTS `department_admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `admin_id` INT NOT NULL,
        `department_id` INT NOT NULL,
        `assigned_by` INT NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`assigned_by`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_admin_dept` (`admin_id`, `department_id`),
        INDEX `idx_dept_admin_dept` (`department_id`),
        INDEX `idx_dept_admin_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
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

if (IS_CLI) {
    echo "Submission checklist migration completed.\n";
    echo "Tables created/verified: $success_count\n";
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
} else {
    echo "Submission checklist migration completed.\n";
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
