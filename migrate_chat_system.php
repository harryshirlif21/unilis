<?php
/**
 * One-off migration: create the chat system tables.
 *
 * Adds four tables backing the chat module in chat/:
 *   chat_conversations  - a direct thread, an auto-synced group, or a unit
 *                         announcement channel
 *   chat_participants   - who is in a conversation, and how far they have read
 *   chat_messages       - the messages themselves
 *   chat_instructions   - delivery record for lecturer instructions (who was
 *                         emailed, and whether it succeeded)
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            https://unilis.jhubafrica.com/migrate_chat_system.php
 *   Shell:   docker compose exec app php migrate_chat_system.php
 *
 * Only the admin role may run it over HTTP - lecturers and students get a 403,
 * so deploying it does not hand a schema-altering endpoint to every logged-in
 * user. Safe to run more than once: existing tables are left alone.
 *
 * Delete this file once the migration has been applied.
 */

define('IS_CLI', PHP_SAPI === 'cli');

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
            . "  docker compose exec app php migrate_chat_system.php",
            403
        );
    }
}

require_once __DIR__ . '/config/db.php';

/**
 * Every user reference in this schema is (id, role). Student ids and lecturer
 * ids come from separate auto-increment sequences, so student 7 and lecturer 7
 * are different people - an id on its own is ambiguous. This mirrors how the
 * notifications table scopes rows with user_id + user_role.
 *
 * That is also why there are no foreign keys onto students/lecturers here: a
 * single column cannot reference two tables. Membership is instead rebuilt from
 * the source of truth (team_members, students.course_id, enrollments) by
 * chat/includes/chat_groups.php, so a deleted user simply stops being synced in.
 */
$tables = [

    // group_key is the idempotency anchor for the whole module. Every
    // conversation has one, so "create it if it isn't there" is a single
    // INSERT ... ON DUPLICATE KEY UPDATE rather than a read-then-write race:
    //   dm:student:7|student:12   (participant keys sorted, so either
    //                              direction of a DM resolves to one thread)
    //   team:12
    //   course:3:all
    //   course:3:y2
    //   unit:44:announce
    //
    // year_of_study is NOT NULL DEFAULT 0 (0 = whole course) on purpose: MySQL
    // treats NULLs as distinct in a unique index, so a nullable year column
    // would happily allow duplicate whole-course groups.
    'chat_conversations' => <<<SQL
CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `group_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Canonical identity, e.g. team:12 or course:3:y2. Makes sync idempotent.',
  `type` enum('direct','team','course','course_year','unit_announce') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Display name for groups; NULL for direct threads, which are named after the other person.',
  `team_id` int DEFAULT NULL,
  `course_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `year_of_study` int NOT NULL DEFAULT '0' COMMENT '0 = whole course (not year-scoped).',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_message_at` datetime DEFAULT NULL COMMENT 'Denormalised so the conversation list can sort without touching chat_messages.',
  `members_synced_at` datetime DEFAULT NULL COMMENT 'Last membership rebuild; throttles resync on poll.',
  UNIQUE KEY `uniq_group_key` (`group_key`),
  KEY `idx_team` (`team_id`),
  KEY `idx_course` (`course_id`, `year_of_study`),
  KEY `idx_unit` (`unit_id`),
  KEY `idx_recent` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // last_read_message_id drives unread counts with one indexed comparison
    // instead of a per-user read-receipt row per message.
    // can_post is denormalised from the conversation type: students are
    // read-only in unit_announce threads, everyone can post everywhere else.
    'chat_participants' => <<<SQL
CREATE TABLE IF NOT EXISTS `chat_participants` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_role` enum('student','lecturer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `can_post` tinyint(1) NOT NULL DEFAULT '1',
  `last_read_message_id` int NOT NULL DEFAULT '0',
  `muted` tinyint(1) NOT NULL DEFAULT '0',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_member` (`conversation_id`, `user_id`, `user_role`),
  KEY `idx_user` (`user_id`, `user_role`),
  CONSTRAINT `fk_chat_participants_conversation`
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // idx_poll covers the hot query: "messages in this conversation after id N".
    'chat_messages' => <<<SQL
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `sender_role` enum('student','lecturer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_instruction` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Lecturer instruction: pinned styling, notification fan-out, optional email.',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  KEY `idx_poll` (`conversation_id`, `id`),
  KEY `idx_sender` (`sender_id`, `sender_role`),
  CONSTRAINT `fk_chat_messages_conversation`
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // Separate from chat_messages so a failed mail run is diagnosable after the
    // fact: the message is still delivered in-app even when email bounces.
    'chat_instructions' => <<<SQL
CREATE TABLE IF NOT EXISTS `chat_instructions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `message_id` int NOT NULL,
  `lecturer_id` int NOT NULL,
  `target_type` enum('unit','course','course_year') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int NOT NULL COMMENT 'unit_id or course_id depending on target_type.',
  `year_of_study` int NOT NULL DEFAULT '0',
  `recipient_count` int NOT NULL DEFAULT '0',
  `email_requested` tinyint(1) NOT NULL DEFAULT '0',
  `emails_sent` int NOT NULL DEFAULT '0',
  `emails_failed` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_message` (`message_id`),
  KEY `idx_lecturer` (`lecturer_id`, `created_at`),
  CONSTRAINT `fk_chat_instructions_message`
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

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
    echo "=== chat system migration ===\n";
    echo 'Database: ' . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "\n";
    echo 'Ran by:   ' . (IS_CLI ? 'CLI' : 'admin user ' . (int)$_SESSION['user_id']) . "\n\n";

    // Membership is rebuilt from these, so chat is useless without them. Fail
    // naming the missing table rather than leaving empty groups to debug later.
    foreach (['students', 'lecturers', 'units', 'courses'] as $required) {
        if (!tableExists($conn, $required)) {
            bail("Cannot continue: required table '$required' does not exist.");
        }
    }

    // Not fatal: a deployment without teams still gets course groups and DMs.
    if (!tableExists($conn, 'teams') || !tableExists($conn, 'team_members')) {
        echo "Note: teams/team_members missing - team groups will be skipped.\n\n";
    }

    $created = 0;
    $skipped = 0;

    foreach ($tables as $table => $ddl) {
        if (tableExists($conn, $table)) {
            echo str_pad($table, 22) . "already exists - skipped\n";
            $skipped++;
            continue;
        }

        echo str_pad($table, 22) . "creating ... ";
        $conn->query($ddl);

        if (!tableExists($conn, $table)) {
            echo "\n";
            bail("CREATE reported success but '$table' is still missing.");
        }

        echo "done\n";
        $created++;
    }

    echo "\n$created created, $skipped already present.\n";

    if ($created > 0) {
        echo "\nColumns created:\n";
        foreach (array_keys($tables) as $table) {
            echo "\n  $table\n";
            $columns = $conn->query('SHOW COLUMNS FROM `' . $table . '`');
            while ($column = $columns->fetch_assoc()) {
                printf("    %-22s %s\n", $column['Field'], $column['Type']);
            }
            $columns->free();
        }
    }

    echo "\nDone. Chat is available at /chat/views/chat.php for students and lecturers.\n";
    echo "Groups are created on demand the first time a user opens chat.\n";
    echo "Remember to delete this file.\n";
    exit(0);
} catch (Throwable $e) {
    bail('Migration failed: ' . $e->getMessage());
}
