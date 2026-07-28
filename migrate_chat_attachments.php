<?php
/**
 * One-off migration: let chat messages carry a file attachment.
 *
 * Adds four columns to chat_messages and relaxes body to allow an empty string,
 * so a message can be a file on its own with no accompanying text.
 *
 * Run this in addition to migrate_chat_system.php. It is separate because that
 * migration may already have been applied, in which case its CREATE TABLE will
 * not run again and the new columns would never appear. Fresh installs get the
 * columns straight from migrate_chat_system.php and this becomes a no-op.
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            https://unilis.jhubafrica.com/migrate_chat_attachments.php
 *   Shell:   docker compose exec app php migrate_chat_attachments.php
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
            . "  docker compose exec app php migrate_chat_attachments.php",
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

// attachment_path holds a path relative to uploads/chat/, never an absolute one
// and never a URL: the file is served through chat/api/file.php after a
// membership check, so the stored value must not be resolvable by a browser.
$columns = [
    'attachment_path' => "ADD COLUMN `attachment_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relative to uploads/chat/. Served only via chat/api/file.php.'",
    'attachment_name' => "ADD COLUMN `attachment_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original filename, for display and download.'",
    'attachment_size' => "ADD COLUMN `attachment_size` int unsigned DEFAULT NULL",
    'attachment_mime' => "ADD COLUMN `attachment_mime` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL",
];

try {
    echo "=== chat attachments migration ===\n";
    echo 'Database: ' . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "\n";
    echo 'Ran by:   ' . (IS_CLI ? 'CLI' : 'admin user ' . (int)$_SESSION['user_id']) . "\n\n";

    if (!tableExists($conn, 'chat_messages')) {
        bail(
            "Cannot continue: chat_messages does not exist.\n"
            . "Run migrate_chat_system.php first."
        );
    }

    $added = 0;
    $present = 0;

    foreach ($columns as $column => $clause) {
        if (columnExists($conn, 'chat_messages', $column)) {
            echo str_pad($column, 20) . "already exists - skipped\n";
            $present++;
            continue;
        }

        echo str_pad($column, 20) . "adding ... ";
        $conn->query("ALTER TABLE `chat_messages` $clause");

        if (!columnExists($conn, 'chat_messages', $column)) {
            echo "\n";
            bail("ALTER reported success but '$column' is still missing.");
        }

        echo "done\n";
        $added++;
    }

    echo "\n$added added, $present already present.\n";

    // A file-only message has no text. The column is NOT NULL, so the empty
    // string is what gets stored - no schema change needed, but worth asserting
    // that nothing upstream made it NOT NULL with a minimum length.
    $bodyNullable = $conn->query("SHOW COLUMNS FROM `chat_messages` LIKE 'body'")->fetch_assoc();
    echo "\nbody column: " . ($bodyNullable['Type'] ?? '?')
        . ' (' . ($bodyNullable['Null'] === 'YES' ? 'nullable' : 'NOT NULL, empty string used for file-only messages') . ")\n";

    $uploadDir = __DIR__ . '/uploads/chat';
    if (!is_dir($uploadDir)) {
        if (@mkdir($uploadDir, 0755, true)) {
            echo "Created upload directory: uploads/chat/\n";
        } else {
            echo "WARNING: could not create uploads/chat/ - create it manually and make it writable.\n";
        }
    } else {
        echo "Upload directory already present: uploads/chat/\n";
    }

    if (is_dir($uploadDir) && !is_writable($uploadDir)) {
        echo "WARNING: uploads/chat/ is not writable by the web server.\n";
    }

    echo "\nDone. Attachments can now be sent from the chat composer.\n";
    echo "Remember to delete this file.\n";
    exit(0);
} catch (Throwable $e) {
    bail('Migration failed: ' . $e->getMessage());
}
