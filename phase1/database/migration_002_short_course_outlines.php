<?php
/**
 * Migration 002 — short-course outlines.
 *
 * Adds independent outline fields for courses, modules, and lessons. The
 * migration is idempotent, so it can safely be run more than once.
 */

if (!defined('PHASE1_ACCESS') && PHP_SAPI !== 'cli' && !defined('STDIN')) {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        exit('Admin access required.');
    }
}

require_once __DIR__ . '/../../config/db.php';

function phase1_migration_002_run(mysqli $conn): array
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $results = [];
    $errors = [];
    $tables = ['public_courses', 'public_course_modules', 'public_course_lessons'];

    foreach ($tables as $table) {
        $exists = $conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$exists || $exists->num_rows === 0) {
            $errors[] = "Required table {$table} does not exist.";
            continue;
        }

        $column = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'outline'");
        if ($column && $column->num_rows > 0) {
            $results[] = "OK: {$table}.outline already exists";
            continue;
        }

        if ($conn->query("ALTER TABLE `{$table}` ADD COLUMN `outline` TEXT NULL")) {
            $results[] = "ADDED: {$table}.outline";
        } else {
            $errors[] = "Failed to add {$table}.outline: " . $conn->error;
        }
    }

    // Sponsorship belongs to the course itself. Keep every field nullable so
    // existing and non-sponsored courses remain valid without backfilling.
    $sponsorColumns = [
        'is_sponsored' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'sponsor_name' => 'VARCHAR(255) NULL',
        'sponsor_details' => 'TEXT NULL',
        'sponsor_logo' => 'VARCHAR(500) NULL',
    ];

    $courseTable = $conn->query("SHOW TABLES LIKE 'public_courses'");
    if (!$courseTable || $courseTable->num_rows === 0) {
        $errors[] = 'Required table public_courses does not exist.';
    } else {
        foreach ($sponsorColumns as $columnName => $definition) {
            $column = $conn->query("SHOW COLUMNS FROM `public_courses` LIKE '{$columnName}'");
            if ($column && $column->num_rows > 0) {
                $results[] = "OK: public_courses.{$columnName} already exists";
                continue;
            }
            if ($conn->query("ALTER TABLE `public_courses` ADD COLUMN `{$columnName}` {$definition}")) {
                $results[] = "ADDED: public_courses.{$columnName}";
            } else {
                $errors[] = "Failed to add public_courses.{$columnName}: " . $conn->error;
            }
        }
    }

    $migrationTable = $conn->query("SHOW TABLES LIKE 'system_migrations'");
    if ($migrationTable && $migrationTable->num_rows > 0 && empty($errors)) {
        $name = '002_short_course_outlines';
        $stmt = $conn->prepare("INSERT IGNORE INTO system_migrations (migration, batch, status, completed_at) VALUES (?, 2, 'completed', NOW())");
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
            $results[] = 'RECORDED: migration 002 in system_migrations';
        }
    }

    return ['success' => empty($errors), 'results' => $results, 'errors' => $errors];
}

// Run when launched directly from the Admin Dashboard as well as from the CLI.
// When this file is included by another migration runner it only exposes the
// function, avoiding a duplicate run.
$isDirectExecution = (
    PHP_SAPI === 'cli' || defined('STDIN') ||
    (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__)
);

if ($isDirectExecution) {
    $result = phase1_migration_002_run($conn);
    if (PHP_SAPI !== 'cli' && !defined('STDIN')) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    foreach ($result['results'] as $message) {
        echo "{$message}\n";
    }
    foreach ($result['errors'] as $message) {
        echo "ERROR: {$message}\n";
    }
    if (PHP_SAPI === 'cli' || defined('STDIN')) {
        exit($result['success'] ? 0 : 1);
    }
}
