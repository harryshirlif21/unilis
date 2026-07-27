<?php
/**
 * One-off migration: create the team_membership_requests table.
 *
 * Fixes "Delete team error: Table 'unilis.team_membership_requests' doesn't exist",
 * raised by teams/api/delete_team.php and by the leave/removal request endpoints.
 *
 * The definition is taken verbatim from the original
 * database_setup/create_team_membership_requests_table.sql, recovered from git
 * history (commit d241a17^) after SQL files were removed from the repository.
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            https://unilis.jhubafrica.com/migrate_team_membership_requests.php
 *   Shell:   docker compose exec app php migrate_team_membership_requests.php
 *
 * Only the admin role may run it over HTTP — lecturers and students get a 403,
 * so deploying it does not hand a schema-altering endpoint to every logged-in
 * user. Safe to run more than once: it creates nothing if the table exists.
 *
 * Delete this file once the migration has been applied.
 */

define('IS_CLI', PHP_SAPI === 'cli');
const TABLE = 'team_membership_requests';

/**
 * Report a failure the way the current caller can see it, then stop.
 * STDERR does not exist under the web SAPI, so it cannot be used unconditionally.
 */
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
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        bail(
            "Forbidden: only an admin may run database migrations.\n\n"
            . "Log in at /login.php as an admin, then reload this page.\n"
            . "Or run it from the shell:\n"
            . "  docker compose exec app php migrate_team_membership_requests.php",
            403
        );
    }
}

require_once __DIR__ . '/config/db.php';

$ddl = <<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

function tableExists(mysqli $conn, string $table): bool
{
    // SHOW TABLES reports zero rows for a missing table instead of throwing,
    // which a SELECT would do under the strict error mode config/db.php sets.
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $exists = $result->num_rows > 0;
    $result->free();

    return $exists;
}

try {
    echo "=== team_membership_requests migration ===\n";
    echo 'Database: ' . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "\n";
    echo 'Ran by:   ' . (IS_CLI ? 'CLI' : 'admin user ' . (int)$_SESSION['user_id']) . "\n\n";

    // The foreign keys target these tables, so a missing one would fail the CREATE
    // with a confusing errno 150 rather than naming the real problem.
    foreach (['teams', 'students', 'lecturers'] as $required) {
        if (!tableExists($conn, $required)) {
            bail("Cannot continue: referenced table '$required' does not exist.");
        }
    }

    if (tableExists($conn, TABLE)) {
        echo 'Table ' . TABLE . " already exists - nothing to do.\n";
        exit(0);
    }

    echo 'Creating table ' . TABLE . " ...\n";
    $conn->query($ddl);

    if (!tableExists($conn, TABLE)) {
        bail('CREATE reported success but the table is still missing.');
    }

    echo "Created. Columns:\n";
    $columns = $conn->query('SHOW COLUMNS FROM `' . TABLE . '`');
    while ($column = $columns->fetch_assoc()) {
        printf("  %-24s %s\n", $column['Field'], $column['Type']);
    }
    $columns->free();

    echo "\nDone. Deleting teams and handling membership requests should now work.\n";
    echo "Remember to delete this file.\n";
    exit(0);
} catch (Throwable $e) {
    bail('Migration failed: ' . $e->getMessage());
}
