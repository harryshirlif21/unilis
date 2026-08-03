<?php
/**
 * Undo Migration: Remove submission checklist tables for teams module
 *
 * Drops tables that were created by migrate_submission_checklist.php:
 *   submission_checklist    - Checklist items for team submissions
 *   submission_signoffs     - Student sign-offs for submission readiness
 *   team_standups          - Daily standup notes for teams
 *   department_admins       - Department admin assignments
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            http://localhost:8080/undo_submission_checklist_migration.php
 *   Shell:   docker exec unilis-db mysql -uunilisuser -punilispass unilis < undo_submission_checklist_migration.php
 *   Docker:  docker-compose exec db mysql -uunilisuser -punilispass unilis < undo_submission_checklist_migration.php
 *
 * Only the admin role may run it over HTTP.
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

$tables_to_drop = [
    'submission_checklist',
    'submission_signoffs',
    'team_standups',
    'department_admins'
];

$errors = [];
$dropped_count = 0;
$dropped_tables = [];

foreach ($tables_to_drop as $table_name) {
    // Check if table exists first
    $check_result = $conn->query("SHOW TABLES LIKE '$table_name'");
    
    if ($check_result && $check_result->num_rows > 0) {
        // Drop foreign key constraints first if they exist
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        $drop_sql = "DROP TABLE IF EXISTS `$table_name`";
        if (!$conn->query($drop_sql)) {
            $errors[] = "Failed to drop $table_name: " . $conn->error;
        } else {
            $dropped_count++;
            $dropped_tables[] = $table_name;
        }
        
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    } else {
        // Table doesn't exist, skip it
        $dropped_count++;
        $dropped_tables[] = $table_name . " (already absent)";
    }
}

if (IS_CLI) {
    echo "Submission checklist migration undo completed.\n";
    echo "Tables dropped/verified: $dropped_count\n";
    if (!empty($dropped_tables)) {
        echo "Tables dropped:\n";
        foreach ($dropped_tables as $table) {
            echo "  ✓ $table\n";
        }
    }
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
} else {
    echo "Submission checklist migration undo completed.\n";
    echo "Tables dropped/verified: $dropped_count\n";
    if (!empty($dropped_tables)) {
        echo "<h3>Tables Dropped:</h3>\n";
        echo "<ul>\n";
        foreach ($dropped_tables as $table) {
            echo "<li style='color: green;'>✓ $table</li>\n";
        }
        echo "</ul>\n";
    }
    if (!empty($errors)) {
        echo "\n<h3>Errors:</h3>\n";
        echo "<ul>\n";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>$error</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: green; font-weight: bold;'>All tables dropped successfully!</p>\n";
    }
}
