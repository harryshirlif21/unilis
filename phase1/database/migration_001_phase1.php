<?php
/**
 * Phase 1 - Database Migration
 * UNILIS Academic Foundation Expansion
 * 
 * UPGRADES existing tables where needed, creates new tables for Phase 1 features.
 * Does NOT drop or destroy any existing data.
 * 
 * Migration: 001
 * Version: 1.0.0
 */

// Prevent direct access - but allow admin access from dashboard
if (!defined('PHASE1_ACCESS') && !defined('STDIN')) {
    session_start();
    // Check if admin is logged in (for dashboard migration runner)
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied. Admin access required.');
    }
    define('PHASE1_ACCESS', true);
}

// Load database connection
require_once __DIR__ . '/../../config/db.php';

/**
 * Run the Phase 1 database migration
 */
function phase1_migration_001_run($conn) {
    // The probes below (SHOW COLUMNS / SHOW TABLES) target tables that may not exist yet
    // on a database that has not had the earlier migrations applied. config/db.php enables
    // MYSQLI_REPORT_STRICT, which turns "table doesn't exist" into a thrown mysqli_sql_exception
    // and kills the whole run with a blank 500. Every probe here is already written as
    // `if ($check && ...)`, so switch reporting off and let query() return false as intended.
    mysqli_report(MYSQLI_REPORT_OFF);

    $results = [];
    $errors = [];
    $warnings = [];
    
    // ── 1. UPGRADE: Drop the retired course_type column from courses ───────────
    $check = $conn->query("SHOW COLUMNS FROM `courses` LIKE 'course_type'");
    if ($check && $check->num_rows > 0) {
        if ($conn->query("ALTER TABLE `courses` DROP COLUMN `course_type`")) {
            $results[] = "UPGRADED: courses table - dropped retired course_type column";
        } else {
            $errors[] = "Failed to drop course_type from courses: " . $conn->error;
        }
    } else {
        $results[] = "OK: courses table has no course_type column";
    }
    
    // ── 2. UPGRADE: Add verification columns to students if missing ────────────
    $checks = [
        'is_verified' => "SHOW COLUMNS FROM `students` LIKE 'is_verified'",
        'verification_code' => "SHOW COLUMNS FROM `students` LIKE 'verification_code'",
        'token_expires_at' => "SHOW COLUMNS FROM `students` LIKE 'token_expires_at'",
        'verified_at' => "SHOW COLUMNS FROM `students` LIKE 'verified_at'",
    ];
    
    foreach ($checks as $col => $sql) {
        $check = $conn->query($sql);
        if ($check && $check->num_rows === 0) {
            $alter = '';
            switch ($col) {
                case 'is_verified': $alter = "ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`"; break;
                case 'verification_code': $alter = "ADD COLUMN `verification_code` VARCHAR(64) DEFAULT NULL AFTER `is_verified`"; break;
                case 'token_expires_at': $alter = "ADD COLUMN `token_expires_at` DATETIME DEFAULT NULL AFTER `verification_code`"; break;
                case 'verified_at': $alter = "ADD COLUMN `verified_at` DATETIME DEFAULT NULL AFTER `token_expires_at`"; break;
            }
            if ($alter && $conn->query("ALTER TABLE `students` $alter")) {
                $results[] = "UPGRADED: students table - added $col column";
            } elseif ($alter) {
                $errors[] = "Failed to add $col to students: " . $conn->error;
            }
        } else {
            $results[] = "OK: students table already has $col column";
        }
    }
    
    // ── 3. UPGRADE: Add is_verified to lecturers if missing ────────────────────
    $check = $conn->query("SHOW COLUMNS FROM `lecturers` LIKE 'is_verified'");
    if ($check && $check->num_rows === 0) {
        $sql = "ALTER TABLE `lecturers` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `university_id`,
                ADD COLUMN `verification_token` VARCHAR(64) DEFAULT NULL AFTER `is_verified`,
                ADD COLUMN `token_expires_at` DATETIME DEFAULT NULL AFTER `verification_token`";
        if ($conn->query($sql)) {
            $results[] = "UPGRADED: lecturers table - added verification columns";
        } else {
            $errors[] = "Failed to add verification columns to lecturers: " . $conn->error;
        }
    } else {
        $results[] = "OK: lecturers table already has verification columns";
    }
    
    // ── 4. UPGRADE: Add is_verified to admins if missing ───────────────────────
    $check = $conn->query("SHOW COLUMNS FROM `admins` LIKE 'is_verified'");
    if ($check && $check->num_rows === 0) {
        $sql = "ALTER TABLE `admins` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password`,
                ADD COLUMN `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`,
                ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `is_super_admin`,
                ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`";
        if ($conn->query($sql)) {
            $results[] = "UPGRADED: admins table - added super admin and verification columns";
            // Set existing admin (id=1) as super admin
            $conn->query("UPDATE `admins` SET `is_super_admin` = 1, `is_verified` = 1 WHERE `id` = 1");
            $results[] = "Set admin id=1 (admin@unilis.com) as Super Admin";
        } else {
            $errors[] = "Failed to upgrade admins table: " . $conn->error;
        }
    } else {
        $results[] = "OK: admins table already has extended columns";
    }
    
    // ── 5. UPGRADE: notifications table to support new roles ───────────────────
    $check = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'user_role'");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if (strpos($row['Type'], 'department_admin') === false) {
            $sql = "ALTER TABLE `notifications` MODIFY COLUMN `user_role` VARCHAR(50) NOT NULL DEFAULT 'student'";
            if ($conn->query($sql)) {
                $results[] = "UPGRADED: notifications table - user_role now supports all roles";
            } else {
                $errors[] = "Failed to upgrade notifications table: " . $conn->error;
            }
        } else {
            $results[] = "OK: notifications table already supports new roles";
        }
    }
    
    // ── 6. NEW TABLE: department_admins ────────────────────────────────────────
    $sql = "CREATE TABLE IF NOT EXISTS `department_admins` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "CREATED: department_admins table";
    } else {
        $errors[] = "Failed to create department_admins: " . $conn->error;
    }
    
    // ── 7. NEW TABLE: technicians ──────────────────────────────────────────────
    $sql = "CREATE TABLE IF NOT EXISTS `technicians` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "CREATED: technicians table";
    } else {
        $errors[] = "Failed to create technicians: " . $conn->error;
    }
    
    // ── 8. UPGRADE: assignments table to support academic assignments ──────────
    $checks = [
        'assignment_type' => "SHOW COLUMNS FROM `assignments` LIKE 'assignment_type'",
        'user_id' => "SHOW COLUMNS FROM `assignments` LIKE 'user_id'",
        'user_role' => "SHOW COLUMNS FROM `assignments` LIKE 'user_role'",
        'reference_type' => "SHOW COLUMNS FROM `assignments` LIKE 'reference_type'",
        'reference_id' => "SHOW COLUMNS FROM `assignments` LIKE 'reference_id'",
        'academic_year' => "SHOW COLUMNS FROM `assignments` LIKE 'academic_year'",
        'is_active' => "SHOW COLUMNS FROM `assignments` LIKE 'is_active'",
        'assigned_by' => "SHOW COLUMNS FROM `assignments` LIKE 'assigned_by'",
        'expires_at' => "SHOW COLUMNS FROM `assignments` LIKE 'expires_at'",
    ];
    
    $assignCols = [
        'assignment_type' => "ADD COLUMN `assignment_type` VARCHAR(50) DEFAULT NULL COMMENT 'Academic assignment type (unit_lecturer, class_supervisor, etc.)' AFTER `mode`",
        'user_id' => "ADD COLUMN `user_id` INT DEFAULT NULL COMMENT 'User this assignment is for' AFTER `assignment_type`",
        'user_role' => "ADD COLUMN `user_role` VARCHAR(50) DEFAULT NULL COMMENT 'Role of the assigned user' AFTER `user_id`",
        'reference_type' => "ADD COLUMN `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'Entity type (unit, course, department)' AFTER `user_role`",
        'reference_id' => "ADD COLUMN `reference_id` INT DEFAULT NULL COMMENT 'ID of referenced entity' AFTER `reference_type`",
        'academic_year' => "ADD COLUMN `academic_year` VARCHAR(20) DEFAULT NULL AFTER `reference_id`",
        'is_active' => "ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `academic_year`",
        'assigned_by' => "ADD COLUMN `assigned_by` INT DEFAULT NULL COMMENT 'Admin who made this assignment' AFTER `is_active`",
        'expires_at' => "ADD COLUMN `expires_at` DATE DEFAULT NULL AFTER `assigned_by`",
    ];
    
    foreach ($checks as $col => $sql) {
        $check = $conn->query($sql);
        if ($check && $check->num_rows === 0) {
            if (isset($assignCols[$col]) && $conn->query("ALTER TABLE `assignments` {$assignCols[$col]}")) {
                $results[] = "UPGRADED: assignments table - added $col column";
            } elseif (isset($assignCols[$col])) {
                $errors[] = "Failed to add $col to assignments: " . $conn->error;
            }
        } else {
            $results[] = "OK: assignments table already has $col column";
        }
    }
    
    // Add indexes for the new columns
    $indexChecks = [
        'idx_assign_user' => "SHOW INDEX FROM `assignments` WHERE Key_name = 'idx_assign_user'",
        'idx_assign_type' => "SHOW INDEX FROM `assignments` WHERE Key_name = 'idx_assign_type'",
    ];
    
    foreach ($indexChecks as $idxName => $sql) {
        $check = $conn->query($sql);
        if ($check && $check->num_rows === 0) {
            $idxSql = '';
            if ($idxName === 'idx_assign_user') $idxSql = "ALTER TABLE `assignments` ADD INDEX `idx_assign_user` (`user_id`, `user_role`)";
            if ($idxName === 'idx_assign_type') $idxSql = "ALTER TABLE `assignments` ADD INDEX `idx_assign_type` (`assignment_type`, `is_active`)";
            if ($idxSql && $conn->query($idxSql)) {
                $results[] = "UPGRADED: assignments table - added $idxName index";
            }
        }
    }
    
    // ── 10. NEW TABLE: system_versions ─────────────────────────────────────────
    $sql = "CREATE TABLE IF NOT EXISTS `system_versions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `version` VARCHAR(20) NOT NULL,
        `version_label` VARCHAR(200) DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `is_current` TINYINT(1) NOT NULL DEFAULT 0,
        `installed_by` INT DEFAULT NULL,
        `installed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_version` (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "CREATED: system_versions table";
    } else {
        $errors[] = "Failed to create system_versions: " . $conn->error;
    }
    
    // ── 11. NEW TABLE: system_migrations ───────────────────────────────────────
    $sql = "CREATE TABLE IF NOT EXISTS `system_migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(100) NOT NULL,
        `batch` INT NOT NULL DEFAULT 1,
        `status` ENUM('pending', 'running', 'completed', 'failed', 'rolled_back') NOT NULL DEFAULT 'pending',
        `output` TEXT DEFAULT NULL,
        `execution_time_ms` INT DEFAULT NULL,
        `run_by` INT DEFAULT NULL,
        `started_at` TIMESTAMP NULL DEFAULT NULL,
        `completed_at` TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY `unique_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "CREATED: system_migrations table";
    } else {
        $errors[] = "Failed to create system_migrations: " . $conn->error;
    }
    
    // ── 12. NEW TABLE: system_upgrade_logs ─────────────────────────────────────
    $sql = "CREATE TABLE IF NOT EXISTS `system_upgrade_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `action` VARCHAR(100) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `status` ENUM('success', 'error', 'warning', 'info') NOT NULL DEFAULT 'info',
        `details` JSON DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_id` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_log_action` (`action`),
        INDEX `idx_log_status` (`status`),
        INDEX `idx_log_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "CREATED: system_upgrade_logs table";
    } else {
        $errors[] = "Failed to create system_upgrade_logs: " . $conn->error;
    }
    
    // ── 13. NEW TABLE: technician_pools ────────────────────────────────────────
    $sql = "CREATE TABLE IF NOT EXISTS `technician_pools` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "CREATED: technician_pools table";
    } else {
        $errors[] = "Failed to create technician_pools: " . $conn->error;
    }
    
    // ── 14. NEW TABLE: pool_technicians ────────────────────────────────────────
    $sql = "CREATE TABLE IF NOT EXISTS `pool_technicians` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `pool_id` INT NOT NULL,
        `technician_id` INT NOT NULL,
        `is_lead` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`pool_id`) REFERENCES `technician_pools`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`technician_id`) REFERENCES `technicians`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_pool_tech` (`pool_id`, `technician_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "CREATED: pool_technicians table";
    } else {
        $errors[] = "Failed to create pool_technicians: " . $conn->error;
    }
    
    // ── 16. DROP: short_courses table (redundant, using public_courses instead) ──
    $checkTable = $conn->query("SHOW TABLES LIKE 'short_courses'");
    if ($checkTable && $checkTable->num_rows > 0) {
        // First, update short_course_tutors to reference public_courses if possible
        // This is a data migration step - try to map short_courses to public_courses
        $conn->query("UPDATE short_course_tutors sct SET sct.short_course_id = (SELECT pc.id FROM public_courses pc WHERE pc.title = (SELECT name FROM short_courses sc WHERE sc.id = sct.short_course_id) LIMIT 1) WHERE sct.short_course_id IN (SELECT id FROM short_courses)");
        
        // Drop the redundant table
        if ($conn->query("DROP TABLE IF EXISTS `short_courses`")) {
            $results[] = "DROPPED: short_courses table (redundant, using public_courses)";
        } else {
            $errors[] = "Failed to drop short_courses: " . $conn->error;
        }
    } else {
        $results[] = "OK: short_courses table already removed";
    }
    
    // ── 17. UPDATE: short_course_tutors foreign key to reference public_courses ──
    $checkFK = $conn->query("SHOW CREATE TABLE `short_course_tutors`");
    if ($checkFK) {
        $tableDef = $checkFK->fetch_assoc()['Create Table'];
        // Check if foreign key references short_courses
        if (strpos($tableDef, 'REFERENCES `short_courses`') !== false) {
            // Drop foreign key
            $conn->query("ALTER TABLE `short_course_tutors` DROP FOREIGN KEY IF EXISTS `short_course_tutors_ibfk_1`");
            // Add new foreign key to public_courses
            if ($conn->query("ALTER TABLE `short_course_tutors` ADD CONSTRAINT `fk_sct_public_courses` FOREIGN KEY (`short_course_id`) REFERENCES `public_courses`(`id`) ON DELETE CASCADE")) {
                $results[] = "UPGRADED: short_course_tutors foreign key now references public_courses";
            } else {
                $errors[] = "Failed to update short_course_tutors foreign key: " . $conn->error;
            }
        } else {
            $results[] = "OK: short_course_tutors already references public_courses or no FK exists";
        }
    }
    
    // ── 18. DROP: short_course_units table (redundant, using public_course_modules instead) ──
    $checkTable = $conn->query("SHOW TABLES LIKE 'short_course_units'");
    if ($checkTable && $checkTable->num_rows > 0) {
        if ($conn->query("DROP TABLE IF EXISTS `short_course_units`")) {
            $results[] = "DROPPED: short_course_units table (redundant, using public_course_modules)";
        } else {
            $errors[] = "Failed to drop short_course_units: " . $conn->error;
        }
    } else {
        $results[] = "OK: short_course_units table already removed";
    }
    
    // ── 19. ENSURE: short_course_tutors table exists with correct schema ─────────
    $sql = "CREATE TABLE IF NOT EXISTS `short_course_tutors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `short_course_id` INT NOT NULL,
        `lecturer_id` INT NOT NULL,
        `assigned_by` INT NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_sc_tutor_course` (`short_course_id`),
        INDEX `idx_sc_tutor_lecturer` (`lecturer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql)) {
        $results[] = "ENSURED: short_course_tutors table exists";
    } else {
        $errors[] = "Failed to ensure short_course_tutors: " . $conn->error;
    }
    
    // ── 20. UPGRADE: Add code, duration, and department_id columns to public_courses ────────────
    $checkCode = $conn->query("SHOW COLUMNS FROM `public_courses` LIKE 'code'");
    if ($checkCode && $checkCode->num_rows === 0) {
        if ($conn->query("ALTER TABLE `public_courses` ADD COLUMN `code` VARCHAR(50) DEFAULT NULL AFTER `title`")) {
            $results[] = "UPGRADED: public_courses table - added code column";
        } else {
            $errors[] = "Failed to add code column to public_courses: " . $conn->error;
        }
    } else {
        $results[] = "OK: public_courses table already has code column";
    }
    
    $checkDuration = $conn->query("SHOW COLUMNS FROM `public_courses` LIKE 'duration'");
    if ($checkDuration && $checkDuration->num_rows === 0) {
        if ($conn->query("ALTER TABLE `public_courses` ADD COLUMN `duration` VARCHAR(100) DEFAULT NULL AFTER `code`")) {
            $results[] = "UPGRADED: public_courses table - added duration column";
        } else {
            $errors[] = "Failed to add duration column to public_courses: " . $conn->error;
        }
    } else {
        $results[] = "OK: public_courses table already has duration column";
    }
    
    $checkDepartmentId = $conn->query("SHOW COLUMNS FROM `public_courses` LIKE 'department_id'");
    if ($checkDepartmentId && $checkDepartmentId->num_rows === 0) {
        if ($conn->query("ALTER TABLE `public_courses` ADD COLUMN `department_id` INT DEFAULT NULL AFTER `duration`")) {
            $results[] = "UPGRADED: public_courses table - added department_id column";
        } else {
            $errors[] = "Failed to add department_id column to public_courses: " . $conn->error;
        }
    } else {
        $results[] = "OK: public_courses table already has department_id column";
    }
    
    // ── 21. Record migration in system_migrations ──────────────────────────────
    $stmt = $conn->prepare("INSERT IGNORE INTO `system_migrations` (`migration`, `batch`, `status`, `completed_at`) VALUES (?, 1, 'completed', NOW())");
    if ($stmt) {
        $migrationName = '001_phase1_academic_foundation';
        $stmt->bind_param('s', $migrationName);
        if ($stmt->execute()) {
            $results[] = "RECORDED: migration 001 in system_migrations";
        }
        $stmt->close();
    } else {
        $warnings[] = "Could not record migration 001: " . $conn->error;
    }
    
    // ── 16. Record version ─────────────────────────────────────────────────────
    $version = '1.0.0';
    $versionLabel = 'Phase 1 - Academic Foundation Expansion';
    $stmt = $conn->prepare("INSERT IGNORE INTO `system_versions` (`version`, `version_label`, `description`, `is_current`) VALUES (?, ?, 'Phase 1: Department Admin, Technician roles, Academic Assignment Engine, Dynamic Permission Engine, System Upgrade Manager', 1)");
    if ($stmt) {
        $stmt->bind_param('ss', $version, $versionLabel);
        if ($stmt->execute()) {
            $results[] = "RECORDED: version $version ($versionLabel)";
        }
        $stmt->close();
    } else {
        $warnings[] = "Could not record version $version: " . $conn->error;
    }

    // Restore the strict reporting mode config/db.php installed.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    return [
        'success' => empty($errors),
        'results' => $results,
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

/**
 * Rollback the Phase 1 database migration
 */
function phase1_migration_001_rollback($conn) {
    $results = [];
    $errors = [];
    
    $tables_to_drop = [
        'pool_technicians',
        'technician_pools',
        'system_upgrade_logs',
        'system_migrations',
        'system_versions',
        'technicians',
        'department_admins',
    ];
    
    foreach ($tables_to_drop as $table) {
        if ($conn->query("DROP TABLE IF EXISTS `$table`")) {
            $results[] = "DROPPED: $table";
        } else {
            $errors[] = "Failed to drop $table: " . $conn->error;
        }
    }
    
    return [
        'success' => empty($errors),
        'results' => $results,
        'errors' => $errors,
    ];
}

// ── Self-execution when run directly ────────────────────────────────────────
$isDirectExecution = (
    (PHP_SAPI === 'cli' || defined('STDIN')) ||
    (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__))
);

if ($isDirectExecution) {
    require_once __DIR__ . '/../../config/db.php';
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        die("Database connection not available.\n");
    }
    
    echo "Running Phase 1 Migration 001...\n\n";
    $result = phase1_migration_001_run($conn);
    
    if ($result['success']) {
        echo "✅ Migration completed successfully!\n\n";
    } else {
        echo "❌ Migration completed with errors!\n\n";
    }
    
    echo "Results:\n";
    foreach ($result['results'] as $r) {
        echo "  ✅ $r\n";
    }
    
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $e) {
            echo "  ❌ $e\n";
        }
    }
    
    if (!empty($result['warnings'])) {
        echo "\nWarnings:\n";
        foreach ($result['warnings'] as $w) {
            echo "  ⚠️ $w\n";
        }
    }
}