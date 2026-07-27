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
 * RUN FROM THE CLI INSIDE THE APP CONTAINER:
 *
 *     docker compose exec app php migrate_team_membership_requests.php
 *
 * It refuses to run over HTTP, so deploying it does not expose a schema-altering
 * endpoint. Safe to run more than once: it creates nothing if the table exists.
 *
 * Delete this file once the migration has been applied.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "This migration only runs from the command line.\n";
    echo "Use: docker compose exec app php migrate_team_membership_requests.php\n";
    exit(1);
}

require_once __DIR__ . '/config/db.php';

const TABLE = 'team_membership_requests';

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
    echo "Database: " . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "\n";

    // The foreign keys target these tables, so a missing one would fail the CREATE
    // with a confusing errno 150 rather than naming the real problem.
    foreach (['teams', 'students', 'lecturers'] as $required) {
        if (!tableExists($conn, $required)) {
            fwrite(STDERR, "Cannot continue: referenced table '$required' does not exist.\n");
            exit(1);
        }
    }

    if (tableExists($conn, TABLE)) {
        echo "Table '" . TABLE . "' already exists - nothing to do.\n";
        exit(0);
    }

    echo "Creating table '" . TABLE . "' ...\n";
    $conn->query($ddl);

    if (!tableExists($conn, TABLE)) {
        fwrite(STDERR, "CREATE reported success but the table is still missing.\n");
        exit(1);
    }

    echo "Created. Columns:\n";
    $columns = $conn->query('SHOW COLUMNS FROM `' . TABLE . '`');
    while ($column = $columns->fetch_assoc()) {
        printf("  %-24s %s\n", $column['Field'], $column['Type']);
    }
    $columns->free();

    echo "\nDone. Deleting team memberships and requests should now work.\n";
    echo "Remember to delete this file.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
