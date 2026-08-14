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

if (PHP_SAPI === 'cli' || defined('STDIN')) {
    $result = phase1_migration_002_run($conn);
    foreach ($result['results'] as $message) {
        echo "OK: {$message}\n";
    }
    foreach ($result['errors'] as $message) {
        echo "ERROR: {$message}\n";
    }
    exit($result['success'] ? 0 : 1);
}
