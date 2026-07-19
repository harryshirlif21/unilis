<?php
/**
 * Live Engagement database setup
 *
 * Open this file once in the browser (or run it with PHP) to create every
 * database table used by the Live Engagement module. It is safe to run again:
 * existing tables and columns are left in place.
 *
 * IMPORTANT: Delete or rename this file after setup on a production server.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    // setup_database.php is in modules/live-engagement/, so the root config is
    // two directory levels above this file's directory.
    require_once __DIR__ . '/../../config/db.php';

    define('LIVE_ENGAGEMENT_INSTALL', true);
    require_once __DIR__ . '/database/install.php';

    $result = installLiveEngagementTables($conn);
    $messages = $result['messages'];

    if ($result['success']) {
        $guestUsersSql = "CREATE TABLE IF NOT EXISTS `le_guest_users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `organisation` VARCHAR(255) NOT NULL,
            `role` VARCHAR(100) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login_at` DATETIME NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_le_guest_users_email` (`email`),
            KEY `idx_le_guest_users_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $conn->query($guestUsersSql);
        $messages[] = 'Created or verified table: le_guest_users';

        $conn->query("CREATE TABLE IF NOT EXISTS `live_engagement_migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `description` VARCHAR(500) NULL,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $messages[] = 'Created or verified table: live_engagement_migrations';

        $columns = [
            'live_sessions' => [
                'avg_rating' => 'DECIMAL(3,2) NULL AFTER `is_template`',
                'rating_count' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `avg_rating`',
            ],
            'live_presentations' => [
                'metadata' => 'JSON NULL AFTER `presenter_notes`',
            ],
            'live_participants' => [
                'notes' => 'TEXT NULL AFTER `attendance_recorded`',
            ],
            'live_polls' => [
                'display_config' => 'JSON NULL AFTER `time_limit_seconds`',
            ],
            'live_quizzes' => [
                'display_config' => 'JSON NULL AFTER `max_attempts`',
            ],
            'live_wordcloud' => [
                'stop_words' => 'JSON NULL AFTER `allow_profanity`',
            ],
            'live_reports' => [
                'scheduled_at' => 'DATETIME NULL AFTER `is_auto_generated`',
                'generated_at' => 'DATETIME NULL AFTER `scheduled_at`',
            ],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $definition) {
                $check = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
                if ($check && $check->num_rows === 0) {
                    $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
                    $messages[] = "Added column: {$table}.{$column}";
                }
            }
        }

        $indexes = [
            ['live_poll_responses', 'idx_responded_at', 'responded_at'],
            ['quiz_attempts', 'idx_completed_at', 'completed_at'],
            ['live_reactions', 'idx_reaction_created', 'created_at'],
        ];
        foreach ($indexes as [$table, $index, $column]) {
            $check = $conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '" . $conn->real_escape_string($index) . "'");
            if ($check && $check->num_rows === 0) {
                $conn->query("CREATE INDEX `{$index}` ON `{$table}` (`{$column}`)");
                $messages[] = "Added index: {$index}";
            }
        }
    }

    echo json_encode([
        'success' => $result['success'],
        'messages' => $messages,
        'next_step' => $result['success'] ? 'Delete setup_database.php after confirming setup.' : 'Review the error above and run this page again.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'messages' => ['ERROR: ' . $error->getMessage()],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
