<?php
/**
 * One-off migration: create the technicians system tables.
 *
 * Adds tables backing the Phase 1 technician role:
 *   technicians          - Technician accounts with department linkage
 *   department_admins    - Department admin assignments (admins linked to departments)
 *   technician_pools     - Technician pools per department
 *   pool_technicians     - Technicians assigned to pools
 *
 * Also upgrades the admins table to add department_id if missing.
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            http://localhost:8080/migrate_technicians_system.php
 *   Shell:   docker exec unilis-db mysql -uunilisuser -punilispass unilis < migrate_technicians_system.php
 *
 * Only the admin role may run it over HTTP. Safe to run more than once:
 * existing tables are left alone.
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

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $escapedTable = $conn->real_escape_string($table);
    $escapedColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");
    if (!$result) {
        return false;
    }
    return $result->num_rows > 0;
}

function tableExists(mysqli $conn, string $table): bool
{
    $escapedTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$escapedTable}'");
    return $result ? $result->num_rows > 0 : false;
}

$tables = [
    // Technicians table
    "CREATE TABLE IF NOT EXISTS `technicians` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `staff_id` VARCHAR(50) NOT NULL UNIQUE,
        `name` VARCHAR(200) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `phone` VARCHAR(20) DEFAULT NULL,
        `password` VARCHAR(255) NOT NULL,
        `department_id` INT DEFAULT NULL,
        `university_id` INT DEFAULT NULL,
        `specialization` VARCHAR(255) DEFAULT NULL,
        `qualification` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
        `verification_token` VARCHAR(64) DEFAULT NULL,
        `token_expires_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`university_id`) REFERENCES `universities`(`id`) ON DELETE SET NULL,
        INDEX `idx_tech_dept` (`department_id`),
        INDEX `idx_tech_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Department admins table
    "CREATE TABLE IF NOT EXISTS `department_admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `admin_id` INT NOT NULL,
        `department_id` INT NOT NULL,
        `assigned_by` INT NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`assigned_by`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_admin_dept` (`admin_id`, `department_id`),
        INDEX `idx_dept_admin_dept` (`department_id`),
        INDEX `idx_dept_admin_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Technician pools table
    "CREATE TABLE IF NOT EXISTS `technician_pools` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(200) NOT NULL,
        `department_id` INT NOT NULL,
        `description` TEXT DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
        INDEX `idx_pool_dept` (`department_id`),
        INDEX `idx_pool_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Pool technicians table
    "CREATE TABLE IF NOT EXISTS `pool_technicians` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `pool_id` INT NOT NULL,
        `technician_id` INT NOT NULL,
        `is_lead` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`pool_id`) REFERENCES `technician_pools`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`technician_id`) REFERENCES `technicians`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_pool_tech` (`pool_id`, `technician_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$errors = [];
$success_count = 0;

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        $errors[] = $conn->error;
    } else {
        $success_count++;
    }
}

// Add department_id to admins table if it doesn't exist
if (!columnExists($conn, 'admins', 'department_id')) {
    $placementColumn = columnExists($conn, 'admins', 'is_super_admin') ? 'is_super_admin' : 'email';
    $alterSql = "ALTER TABLE `admins` ADD COLUMN `department_id` INT DEFAULT NULL AFTER `{$placementColumn}`";
    if (!$conn->query($alterSql)) {
        $errors[] = $conn->error;
    } else {
        $success_count++;
    }
}

// Add department_id to lecturers table if it doesn't exist (needed for supervisor search)
if (!columnExists($conn, 'lecturers', 'department_id')) {
    $alterSql = "ALTER TABLE `lecturers` ADD COLUMN `department_id` INT DEFAULT NULL AFTER `university_id`";
    if (!$conn->query($alterSql)) {
        $errors[] = $conn->error;
    } else {
        $success_count++;
    }
}

// Ensure team_supervisors supervisor_type enum includes 'admin' (for department admin supervisors)
if (tableExists($conn, 'team_supervisors') && columnExists($conn, 'team_supervisors', 'supervisor_type')) {
    $alterEnumSql = "ALTER TABLE `team_supervisors` MODIFY COLUMN `supervisor_type` enum('lecturer','technician','admin') NOT NULL DEFAULT 'lecturer'";
    if (!$conn->query($alterEnumSql)) {
        $errors[] = $conn->error;
    } else {
        $success_count++;
    }
}

if (IS_CLI) {
    echo "Migration completed.\n";
    echo "Tables created/verified: $success_count\n";
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
} else {
    echo "Technicians system migration completed.\n";
    echo "Tables created/verified: $success_count\n";
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    } else {
        echo "\nAll tables created successfully.";
    }
}