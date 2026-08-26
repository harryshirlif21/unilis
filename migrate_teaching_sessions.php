<?php
/**
 * One-off migration: guided teaching sessions.
 *
 * Adds the tutor "Teach" layer on top of the existing public course content.
 * It stores ONLY the teaching session and the current course position - it does
 * NOT duplicate any course, module, lesson, activity or assessment content.
 * Those all continue to live in `public_courses`, `public_course_modules`,
 * `public_course_lessons` and `public_course_assessments`.
 *
 * HOW TO RUN
 *   Browser: log in as an admin, then open
 *            https://unilis.jhubafrica.com/migrate_teaching_sessions.php
 *   Shell:   docker compose exec app php migrate_teaching_sessions.php
 *
 * Only the admin role may run it over HTTP. Safe to run more than once.
 * Delete this file once the migration has been applied.
 */

define('IS_CLI', PHP_SAPI === 'cli');

function teachmake_bail(string $message, int $httpStatus = 500): void
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

    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        bailmake(
            "Forbidden: only an admin may run database migrations.\n\n"
            . "Log in at /login.php as an admin, then reload this page.\n"
            . "Or run it from the shell:\n"
            . "  docker compose exec app php migrate_teaching_sessions.php",
            403
        );
    }
}

require_once __DIR__ . '/config/db.php';

$tables = [

    // One row per guided session. tutor_id is a lecturers.id (the UNILIS user
    // id of the person running Teach mode). status: 'started' | 'ended'.
    'teaching_sessions' => <<<SQL
CREATE TABLE IF NOT EXISTS `teaching_sessions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `course_id` int NOT NULL,
  `tutor_id` int NOT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'started',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  KEY `idx_ts_course_status` (`course_id`, `status`),
  KEY `idx_ts_tutor` (`tutor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // The single live "where am I" pointer for a session. One row per session,
    // updated as the tutor navigates. No course content is stored here.
    'teaching_session_positions' => <<<SQL
CREATE TABLE IF NOT EXISTS `teaching_session_positions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `teaching_session_id` int NOT NULL,
  `current_module_id` int NOT NULL DEFAULT 0,
  `current_lesson_id` int NOT NULL DEFAULT 0,
  `current_content_position` int NOT NULL DEFAULT 0,
  `open_assessment_id` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_session_position` (`teaching_session_id`),
  CONSTRAINT `fk_tsp_session` FOREIGN KEY (`teaching_session_id`)
    REFERENCES `teaching_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

function teachTableExists(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

try {
    echo "=== guided teaching sessions migration ===\n";
    echo 'Database: ' . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "\n";
    echo 'Ran by:   ' . (IS_CLI ? 'CLI' : 'admin user ' . (int)$_SESSION['user_id']) . "\n\n";

    $created = 0;
    $skipped = 0;

    foreach ($tables as $table => $ddl) {
        if (teachTableExists($conn, $table)) {
            echo str_pad($table, 30) . "already exists - skipped\n";
            $skipped++;
            continue;
        }
        echo str_pad($table, 30) . "creating ... ";
        $conn->query($ddl);
        if (!teachTableExists($conn, $table)) {
            echo "\n";
            bailmake("CREATE reported success but '$table' is still missing.");
        }
        echo "done\n";
        $created++;
    }

    echo "\n$created created, $skipped already present.\n";
    echo "\nDone. Tutors can now open the Teach flow from the Short Courses catalogue.\n";
    echo "Remember to delete this file.\n";
    exit(0);
} catch (Throwable $e) {
    bailmake('Migration failed: ' . $e->getMessage());
}