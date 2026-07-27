<?php
/**
 * Schema probes for the Chat module.
 *
 * This deployment's schema varies: some installs enrol students through
 * student_unit_enrollments, older ones through student_units, and students.
 * is_verified is not present everywhere. Rather than hard-coding one shape and
 * failing loudly on the others, chat resolves what exists once per request -
 * the same defensive approach includes/notifications.php and config/meeting.php
 * already take.
 */

/**
 * Whether a table exists. Cached per request.
 */
function chat_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    // SHOW TABLES returns zero rows for a missing table; a SELECT would throw
    // under the strict mysqli error mode config/db.php sets.
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $cache[$table] = $result->num_rows > 0;
    $result->free();

    return $cache[$table];
}

/**
 * Whether a column exists on a table. Cached per request.
 */
function chat_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!chat_table_exists($conn, $table)) {
        return $cache[$key] = false;
    }

    $result = $conn->query(
        "SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE '"
        . $conn->real_escape_string($column) . "'"
    );
    $cache[$key] = $result->num_rows > 0;
    $result->free();

    return $cache[$key];
}

/**
 * Whether every chat table the module needs has been created.
 * Views use this to point the user at the migration instead of throwing.
 */
function chat_schema_ready(mysqli $conn): bool
{
    foreach (['chat_conversations', 'chat_participants', 'chat_messages', 'chat_instructions'] as $table) {
        if (!chat_table_exists($conn, $table)) {
            return false;
        }
    }

    return true;
}

/**
 * Name of the table mapping students to units, or null when neither exists.
 *
 * student_unit_enrollments is the current one - student/my_units.php writes to
 * it, so it is what a student's enrolment actually means today. student_units
 * is the older name and is still read by a handful of pages, so it is accepted
 * as a fallback for installs that never migrated.
 */
function chat_enrollment_table(mysqli $conn): ?string
{
    static $resolved = false;
    static $table = null;

    if ($resolved) {
        return $table;
    }

    foreach (['student_unit_enrollments', 'student_units'] as $candidate) {
        if (chat_table_exists($conn, $candidate)) {
            $table = $candidate;
            break;
        }
    }

    $resolved = true;

    return $table;
}

/**
 * SQL fragment restricting a students query to verified accounts, or an empty
 * string where the column does not exist.
 *
 * $alias is interpolated into SQL, so it must be a literal from the caller and
 * never request input; it is validated here rather than trusted.
 */
function chat_verified_student_clause(mysqli $conn, string $alias = 's'): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) {
        throw new InvalidArgumentException('Invalid table alias: ' . $alias);
    }

    return chat_column_exists($conn, 'students', 'is_verified')
        ? " AND {$alias}.is_verified = 1"
        : '';
}

/**
 * Whether the teams feature is present. Team groups are skipped without it.
 */
function chat_teams_available(mysqli $conn): bool
{
    return chat_table_exists($conn, 'teams') && chat_table_exists($conn, 'team_members');
}
