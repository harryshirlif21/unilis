<?php
// ============================================================
// UNILIS SmartLab - One-Time Database Setup Script
// Run once at: https://unilis.jhubafrica.com/smart-lab/setup_db.php
// DELETE THIS FILE immediately after successful setup!
// ============================================================

$host = 'unilis-db';
$user = 'root';
$pass = 'rootpass';

$tables = [];
$errors = [];

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database and user
    $pdo->exec("CREATE DATABASE IF NOT EXISTS unilis_smartlab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER IF NOT EXISTS 'lab_admin'@'%' IDENTIFIED BY 'lab_password'");
    $pdo->exec("GRANT ALL PRIVILEGES ON unilis_smartlab.* TO 'lab_admin'@'%'");
    $pdo->exec("FLUSH PRIVILEGES");
    $pdo->exec("USE unilis_smartlab");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // ── SCHEMA ──────────────────────────────────────────────

    $statements = [

        // roles
        "CREATE TABLE IF NOT EXISTS `roles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL,
            `description` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // labs
        "CREATE TABLE IF NOT EXISTS `labs` (
            `id` char(36) NOT NULL,
            `name` varchar(150) NOT NULL,
            `lab_code` varchar(20) NOT NULL,
            `type` enum('physics','chemistry','engineering','clinical','computer','general') NOT NULL,
            `building` varchar(100) DEFAULT NULL,
            `room_number` varchar(30) DEFAULT NULL,
            `max_capacity` int(11) DEFAULT 30,
            `current_count` int(11) DEFAULT 0,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // users
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` varchar(36) NOT NULL,
            `full_name` varchar(150) NOT NULL,
            `reg_number` varchar(50) NOT NULL,
            `email` varchar(150) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('admin','lecturer','technician','student') NOT NULL DEFAULT 'student',
            `lab_id` varchar(36) DEFAULT NULL,
            `biometric_hash` varchar(255) DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `reg_number` (`reg_number`),
            UNIQUE KEY `email` (`email`),
            KEY `fk_users_lab` (`lab_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // approvals
        "CREATE TABLE IF NOT EXISTS `approvals` (
            `id` varchar(36) NOT NULL,
            `document_type` enum('notebook','report') NOT NULL,
            `document_id` varchar(36) NOT NULL,
            `reviewer_id` varchar(36) NOT NULL,
            `action` enum('approved','rejected','revision_requested') NOT NULL,
            `comments` text DEFAULT NULL,
            `signature_hash` varchar(255) DEFAULT NULL,
            `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `reviewer_id` (`reviewer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // assets
        "CREATE TABLE IF NOT EXISTS `assets` (
            `id` varchar(36) NOT NULL,
            `asset_code` varchar(50) NOT NULL,
            `name` varchar(200) NOT NULL,
            `type` enum('equipment','chemical','consumable','instrument') NOT NULL,
            `lab_id` varchar(36) DEFAULT NULL,
            `quantity` decimal(10,2) DEFAULT 1.00,
            `unit` varchar(30) DEFAULT NULL,
            `status` enum('available','in_use','maintenance','disposed','in_transit') DEFAULT 'available',
            `serial_number` varchar(100) DEFAULT NULL,
            `purchase_date` date DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `description` text DEFAULT NULL,
            `location` varchar(100) DEFAULT NULL,
            `unit_price` decimal(10,2) DEFAULT 0.00,
            `min_quantity` int(11) DEFAULT 5,
            `warranty_expiry` date DEFAULT NULL,
            `safety_notes` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `lab_id` (`lab_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // asset_transactions
        "CREATE TABLE IF NOT EXISTS `asset_transactions` (
            `id` varchar(36) NOT NULL,
            `asset_id` varchar(36) NOT NULL,
            `action` enum('registered','issued','returned','transferred','disposed','usage_logged') NOT NULL,
            `user_id` varchar(36) NOT NULL,
            `lab_id` varchar(36) DEFAULT NULL,
            `target_lab_id` varchar(36) DEFAULT NULL,
            `quantity` decimal(10,2) DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `asset_id` (`asset_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // audit_logs
        "CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` varchar(36) DEFAULT NULL,
            `action` varchar(200) NOT NULL,
            `module` varchar(100) DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` varchar(500) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // blockchain_blocks
        "CREATE TABLE IF NOT EXISTS `blockchain_blocks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `block_index` int(11) NOT NULL,
            `timestamp` datetime NOT NULL,
            `block_data` longtext NOT NULL,
            `previous_hash` varchar(64) NOT NULL,
            `hash` varchar(64) NOT NULL,
            `nonce` int(11) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // lab_requests
        "CREATE TABLE IF NOT EXISTS `lab_requests` (
            `id` varchar(36) NOT NULL,
            `requester_id` varchar(36) NOT NULL,
            `requesting_lab` varchar(36) NOT NULL,
            `target_lab` varchar(36) DEFAULT NULL,
            `asset_id` varchar(36) DEFAULT NULL,
            `asset_name` varchar(200) DEFAULT NULL,
            `quantity` decimal(10,2) DEFAULT NULL,
            `purpose` text DEFAULT NULL,
            `status` enum('pending','approved','rejected','fulfilled') DEFAULT 'pending',
            `approved_by` varchar(36) DEFAULT NULL,
            `approved_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `requester_id` (`requester_id`),
            KEY `requesting_lab` (`requesting_lab`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // practicals
        "CREATE TABLE IF NOT EXISTS `practicals` (
            `id` char(36) NOT NULL,
            `title` varchar(200) NOT NULL,
            `description` text DEFAULT NULL,
            `lab_id` char(36) NOT NULL,
            `lecturer_id` varchar(36) NOT NULL,
            `scheduled_date` date DEFAULT NULL,
            `duration_hours` decimal(4,1) DEFAULT NULL,
            `max_students` int(11) DEFAULT 30,
            `status` enum('draft','published','completed','cancelled') DEFAULT 'draft',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `fk_prac_lab` (`lab_id`),
            KEY `fk_prac_lecturer` (`lecturer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // lab_sessions
        "CREATE TABLE IF NOT EXISTS `lab_sessions` (
            `id` char(36) NOT NULL,
            `practical_id` char(36) NOT NULL,
            `lab_id` char(36) NOT NULL,
            `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `status` enum('open','closed') DEFAULT 'open',
            `confirmation_code` varchar(20) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `practical_id` (`practical_id`),
            KEY `lab_id` (`lab_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // lab_session_students
        "CREATE TABLE IF NOT EXISTS `lab_session_students` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `session_id` char(36) NOT NULL,
            `student_id` varchar(36) NOT NULL,
            `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `session_id` (`session_id`),
            KEY `student_id` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // notebooks
        "CREATE TABLE IF NOT EXISTS `notebooks` (
            `id` varchar(36) NOT NULL,
            `student_id` varchar(36) NOT NULL,
            `session_id` char(36) NOT NULL,
            `title` varchar(200) DEFAULT NULL,
            `content` longtext DEFAULT NULL,
            `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
            `submitted_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `fk_nb_student_id` (`student_id`),
            KEY `fk_nb_session_id` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // notebook_versions
        "CREATE TABLE IF NOT EXISTS `notebook_versions` (
            `id` varchar(36) NOT NULL,
            `notebook_id` varchar(36) NOT NULL,
            `content` longtext DEFAULT NULL,
            `version` int(11) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `notebook_id` (`notebook_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // reports
        "CREATE TABLE IF NOT EXISTS `reports` (
            `id` varchar(36) NOT NULL,
            `notebook_id` varchar(36) DEFAULT NULL,
            `student_id` varchar(36) NOT NULL,
            `practical_id` char(36) NOT NULL,
            `title` varchar(200) DEFAULT NULL,
            `content` longtext DEFAULT NULL,
            `file_path` varchar(500) DEFAULT NULL,
            `status` enum('draft','submitted','graded','rejected') DEFAULT 'draft',
            `grade` decimal(5,2) DEFAULT NULL,
            `feedback` text DEFAULT NULL,
            `submitted_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `notebook_id` (`notebook_id`),
            KEY `student_id` (`student_id`),
            KEY `practical_id` (`practical_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // report_deadlines
        "CREATE TABLE IF NOT EXISTS `report_deadlines` (
            `id` varchar(36) NOT NULL,
            `practical_id` char(36) NOT NULL,
            `student_id` varchar(36) DEFAULT NULL,
            `deadline_date` datetime NOT NULL,
            `extended` tinyint(1) DEFAULT 0,
            `created_by` varchar(36) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `created_by` (`created_by`),
            KEY `idx_practical_student` (`practical_id`,`student_id`),
            KEY `idx_student_id` (`student_id`),
            KEY `idx_deadline_date` (`deadline_date`),
            KEY `idx_status` (`extended`,`deadline_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // practical_requests
        "CREATE TABLE IF NOT EXISTS `practical_requests` (
            `id` varchar(36) NOT NULL,
            `student_id` varchar(36) NOT NULL,
            `practical_id` char(36) NOT NULL,
            `preferred_lab` varchar(36) DEFAULT NULL,
            `reason` text DEFAULT NULL,
            `status` enum('pending','approved','rejected') DEFAULT 'pending',
            `reviewed_by` varchar(36) DEFAULT NULL,
            `reviewed_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `preferred_lab` (`preferred_lab`),
            KEY `idx_student_id` (`student_id`),
            KEY `idx_practical_id` (`practical_id`),
            KEY `idx_status` (`status`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // student_practicals
        "CREATE TABLE IF NOT EXISTS `student_practicals` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `student_id` varchar(36) NOT NULL,
            `practical_id` char(36) NOT NULL,
            `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_enrollment` (`student_id`,`practical_id`),
            KEY `practical_id` (`practical_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // student_sessions
        "CREATE TABLE IF NOT EXISTS `student_sessions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `session_id` char(36) NOT NULL,
            `student_id` varchar(36) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `session_id` (`session_id`),
            KEY `student_id` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── SEED DATA ────────────────────────────────────────

        // roles
        "INSERT IGNORE INTO `roles` (`id`, `name`, `description`) VALUES
            (1, 'admin', 'System Administrator'),
            (2, 'lecturer', 'Lecturer'),
            (3, 'technician', 'Lab Technician'),
            (4, 'student', 'Student')",

        // labs
        "INSERT IGNORE INTO `labs` (`id`, `name`, `lab_code`, `type`, `building`, `room_number`, `max_capacity`, `current_count`, `is_active`, `created_at`) VALUES
            ('lab-chem-001', 'Chemistry Laboratory B', 'CHEM-B', 'chemistry', 'Science Block', '205', 25, 0, 1, '2026-03-25 08:14:29'),
            ('lab-clin-001', 'Clinical Skills Lab', 'CLIN-A', 'clinical', 'Health Sciences', '301', 15, 0, 1, '2026-03-25 08:14:29'),
            ('lab-eng-001', 'Engineering Workshop', 'ENG-W', 'engineering', 'Engineering Block', 'G01', 20, 0, 1, '2026-03-25 08:14:29'),
            ('lab-phy-001', 'Physics Laboratory A', 'PHY-A', 'physics', 'Science Block', '101', 30, 0, 1, '2026-03-25 08:14:29')",

        // blockchain genesis block
        "INSERT IGNORE INTO `blockchain_blocks` (`id`, `block_index`, `timestamp`, `block_data`, `previous_hash`, `hash`, `nonce`, `created_at`) VALUES
            (1, 0, '2026-03-25 16:01:48', '{\"event\":\"Genesis\",\"system\":\"UNILIS SmartLab\"}', '0', 'c648605732ac1d81ff62ea9e3482b3d0d92d32180ec35595ecc50fd27691f64e', 0, '2026-03-25 15:01:48')",

        // re-enable FK checks
        "SET FOREIGN_KEY_CHECKS = 1",
    ];

    // Execute all statements
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $errors[] = htmlspecialchars(substr(trim($sql), 0, 80)) . ' → ' . $e->getMessage();
        }
    }

    // Show results
    $tables = $pdo->query("SHOW TABLES FROM unilis_smartlab")->fetchAll(PDO::FETCH_COLUMN);

    echo "<!DOCTYPE html><html><head><title>SmartLab DB Setup</title>
    <style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:20px}
    .ok{color:green}.err{color:red}.warn{background:#fff3cd;padding:15px;border-radius:8px}</style></head><body>";

    echo "<h2 class='ok'>✅ Setup Complete — " . count($tables) . " tables created</h2>";
    echo "<h3>Tables:</h3><ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";

    if ($errors) {
        echo "<h3>⚠️ Errors (" . count($errors) . "):</h3><ul class='err'>";
        foreach ($errors as $e) echo "<li>$e</li>";
        echo "</ul>";
    }

    echo "<div class='warn'><strong>⚠️ SECURITY: Delete this file immediately!</strong><br>
    Run: <code>git rm smart-lab/setup_db.php && git commit -m 'security: remove setup script' && git push</code></div>";
    echo "</body></html>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Failed: " . $e->getMessage() . "</h2>";
}