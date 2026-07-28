<?php
/**
 * One-off migration: guest access to meetings.
 *
 * Until now a meeting could only be joined by a lecturer who owns it or a
 * student enrolled in its unit. Both checks are joins against internal tables,
 * so anyone outside the university - an external learner working through /learn,
 * an industry speaker, a parent at an open day - had no way in at all.
 *
 * WHAT THIS ADDS
 *
 * Four columns on `meetings` and one table.
 *
 *   guest_access    off by default. Guest access is opt-in per meeting: a
 *                   lecture about student coursework should not become public
 *                   because the feature shipped.
 *   guest_token     the unguessable part of the public link. Separate from
 *                   meetings.id, so the link cannot be found by counting up
 *                   from 1, and so revoking a leaked link is a matter of
 *                   generating a new token rather than moving the meeting.
 *   guest_passcode  optional second factor, stored in the clear. It is a
 *                   shared secret the host reads out or pastes into an
 *                   invitation, not a credential belonging to one person, and
 *                   the host has to be able to see it to share it. Hashing it
 *                   would only stop the host from doing their job.
 *   guest_listed    whether the session also appears on /learn/live.php for
 *                   signed-in external learners. Separate from guest_access
 *                   because "anyone with the link" and "advertised to every
 *                   registered learner" are different decisions, and only the
 *                   host knows which one they meant.
 *
 * WHY GUESTS GET THEIR OWN TABLE
 *
 * A guest is not a `students` row and not necessarily an `external_learners`
 * row either - most are somebody who clicked a link and typed a name. They
 * still have to be recorded, because a host needs to know who was in the room,
 * so they get a table of their own keyed by meeting.
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            https://unilis.jhubafrica.com/migrate_meeting_guests.php
 *   Shell:   docker compose exec app php migrate_meeting_guests.php
 *
 * Only the admin role may run it over HTTP. Safe to run more than once.
 *
 * Delete this file once the migration has been applied.
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

    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        bail(
            "Forbidden: only an admin may run database migrations.\n\n"
            . "Log in at /login.php as an admin, then reload this page.\n"
            . "Or run it from the shell:\n"
            . "  docker compose exec app php migrate_meeting_guests.php",
            403
        );
    }
}

require_once __DIR__ . '/config/db.php';

function tableExists(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $exists = $result->num_rows > 0;
    $result->free();

    return $exists;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $result = $conn->query(
        "SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE '"
        . $conn->real_escape_string($column) . "'"
    );
    $exists = $result->num_rows > 0;
    $result->free();

    return $exists;
}

function indexExists(mysqli $conn, string $table, string $index): bool
{
    $result = $conn->query(
        "SHOW INDEX FROM `" . str_replace('`', '', $table) . "` WHERE Key_name = '"
        . $conn->real_escape_string($index) . "'"
    );
    $exists = $result->num_rows > 0;
    $result->free();

    return $exists;
}

$columns = [
    'guest_access' => "ADD COLUMN `guest_access` tinyint(1) NOT NULL DEFAULT 0 "
        . "COMMENT 'Whether anyone holding the guest link may join.'",
    'guest_listed' => "ADD COLUMN `guest_listed` tinyint(1) NOT NULL DEFAULT 0 "
        . "COMMENT 'Whether the session is also advertised on /learn/live.php.'",
    'guest_token' => "ADD COLUMN `guest_token` varchar(64) DEFAULT NULL "
        . "COMMENT 'Unguessable path segment of the public join link.'",
    'guest_passcode' => "ADD COLUMN `guest_passcode` varchar(32) DEFAULT NULL "
        . "COMMENT 'Optional shared passcode, stored in the clear so the host can read it out.'",
];

$guestsTable = <<<SQL
CREATE TABLE IF NOT EXISTS `meeting_guests` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `meeting_id` int NOT NULL,
  `learner_id` int DEFAULT NULL COMMENT 'Set when the guest was signed in to /learn; NULL for an anonymous guest.',
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'As typed on the join page, or taken from the learner account.',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Random per-join secret held in the guest cookie session; how a refresh finds its own row again instead of creating a second one.',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IPv6 fits in 45 characters.',
  UNIQUE KEY `uniq_session` (`session_key`),
  KEY `idx_meeting` (`meeting_id`, `joined_at`),
  KEY `idx_learner` (`learner_id`),
  CONSTRAINT `fk_mg_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    echo "=== meeting guest access migration ===\n";
    echo 'Database: ' . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "\n";
    echo 'Ran by:   ' . (IS_CLI ? 'CLI' : 'admin user ' . (int)$_SESSION['user_id']) . "\n\n";

    if (!tableExists($conn, 'meetings')) {
        bail("The `meetings` table does not exist, so there is nothing to add guest access to.");
    }

    // Columns first: meeting_guests has a foreign key into meetings, and the
    // guest link is useless without somewhere to record who used it.
    $added = [];
    foreach ($columns as $column => $clause) {
        if (columnExists($conn, 'meetings', $column)) {
            echo str_pad('meetings.' . $column, 34) . "already exists - skipped\n";
            continue;
        }
        $added[] = $clause;
        echo str_pad('meetings.' . $column, 34) . "adding\n";
    }

    if ($added) {
        // One ALTER for all of them: on a large table each ALTER is a rebuild,
        // and four rebuilds cost four times as much as one.
        $conn->query('ALTER TABLE `meetings` ' . implode(', ', $added));
    }

    // A leaked or shared token must identify exactly one meeting, so this is a
    // correctness constraint rather than an optimisation.
    if (!indexExists($conn, 'meetings', 'uniq_guest_token')) {
        echo str_pad('meetings.uniq_guest_token', 34) . "adding\n";
        $conn->query('ALTER TABLE `meetings` ADD UNIQUE KEY `uniq_guest_token` (`guest_token`)');
    } else {
        echo str_pad('meetings.uniq_guest_token', 34) . "already exists - skipped\n";
    }

    if (tableExists($conn, 'meeting_guests')) {
        echo str_pad('meeting_guests', 34) . "already exists - skipped\n";
    } else {
        echo str_pad('meeting_guests', 34) . "creating ... ";
        $conn->query($guestsTable);

        if (!tableExists($conn, 'meeting_guests')) {
            echo "\n";
            bail("CREATE reported success but 'meeting_guests' is still missing.");
        }
        echo "done\n";
    }

    // No foreign key from meeting_guests.learner_id to external_learners: that
    // table is created by a different migration which may not have run, and a
    // guest does not need an account. The column is indexed instead, and a
    // deleted learner leaves a guest row whose name is still the right answer to
    // "who was in the room".
    if (!tableExists($conn, 'external_learners')) {
        echo "\nNote: external_learners does not exist yet, so /learn/live.php will not\n"
           . "      list sessions until migrate_external_learners.php has been run.\n"
           . "      Guest links work regardless.\n";
    }

    echo "\nDone. Lecturers can now open guest access from the Guests button on a\n";
    echo "meeting, at /lecturer/meeting_access.php?meeting_id=<id>.\n";
    echo "Remember to delete this file.\n";
    exit(0);
} catch (Throwable $e) {
    bail('Migration failed: ' . $e->getMessage());
}
