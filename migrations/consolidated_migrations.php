<?php
// migrations/consolidated_migrations.php

function run_all_migrations() {
    ob_start();

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo "Forbidden: only an admin may run database migrations.";
        return ob_get_clean();
    }

    require_once __DIR__ . '/../config/db.php';
    if (!isset($conn) || !$conn instanceof mysqli) {
        die('Database connection not available');
    }
    
    require_once __DIR__ . '/../phase1/database/migration_001_phase1.php';
    require_once __DIR__ . '/migrate_unique_unit_assignment.php';

    $GLOBALS['log'] = [];

    // --- Helper functions ---
    function tableExists(mysqli $conn, string $table): bool {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        return $result && $result->num_rows > 0;
    }

    function columnExists(mysqli $conn, string $table, string $column): bool {
        $result = $conn->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE '" . $conn->real_escape_string($column) . "'");
        return $result && $result->num_rows > 0;
    }
    
    function run_sql(mysqli $conn, string $label, string $sql) {
        try {
            if ($conn->query($sql)) {
                $GLOBALS['log'][] = ['label' => $label, 'status' => 'ok', 'msg' => 'Executed successfully.'];
            } else {
                $GLOBALS['log'][] = ['label' => $label, 'status' => 'err', 'msg' => $conn->error];
            }
        } catch (Exception $e) {
            $GLOBALS['log'][] = ['label' => $label, 'status' => 'err', 'msg' => $e->getMessage()];
        }
    }
    
    function skip($label, $reason = 'already exists') {
        $GLOBALS['log'][] = ['label' => $label, 'status' => 'skip', 'msg' => $reason];
    }

    // --- Migration Functions ---

    function migrate_attendance(mysqli $conn) {
        if (!tableExists($conn, 'attendance')) {
            run_sql($conn, 'CREATE TABLE attendance', "
                CREATE TABLE `attendance` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `student_id` VARCHAR(36) NOT NULL,
                    `practical_id` VARCHAR(36) NOT NULL,
                    `verification_method` ENUM('qr','rfid','fingerprint','admin_code', 'manual') DEFAULT 'qr',
                    `marked_at` DATETIME DEFAULT NOW(),
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_student` (`student_id`),
                    INDEX `idx_practical` (`practical_id`),
                    UNIQUE KEY `unique_attendance` (`student_id`, `practical_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            skip('CREATE TABLE attendance');
        }
    }

    function migrate_public_presentations(mysqli $conn) {
        if (!tableExists($conn, 'public_presentations')) {
            run_sql($conn, 'CREATE TABLE public_presentations', "
                CREATE TABLE `public_presentations` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `presentation_id` INT UNSIGNED NOT NULL, `share_token` VARCHAR(64) NOT NULL UNIQUE,
                    `created_by` INT UNSIGNED NOT NULL, `access_count` INT UNSIGNED DEFAULT 0, `expires_at` DATETIME NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_presentation_id` (`presentation_id`), INDEX `idx_share_token` (`share_token`), INDEX `idx_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else { skip('CREATE TABLE public_presentations'); }

        if (tableExists($conn, 'live_presentations') && !columnExists($conn, 'live_presentations', 'is_public')) {
            run_sql($conn, 'ALTER TABLE live_presentations ADD is_public', "ALTER TABLE live_presentations ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0");
        } else { skip('ALTER TABLE live_presentations ADD is_public', 'Table not found or column exists'); }
    }

    function migrate_chat_system(mysqli $conn) {
        $chat_tables = [
            'chat_conversations' => "CREATE TABLE `chat_conversations` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `group_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL, `type` enum('direct','team','course','course_year','unit_announce') COLLATE utf8mb4_unicode_ci NOT NULL, `title` varchar(255), `team_id` int, `course_id` int, `unit_id` int, `year_of_study` int NOT NULL DEFAULT '0', `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, `last_message_at` datetime, `members_synced_at` datetime, UNIQUE KEY `uniq_group_key` (`group_key`)) ENGINE=InnoDB",
            'chat_participants' => "CREATE TABLE `chat_participants` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `conversation_id` int NOT NULL, `user_id` int NOT NULL, `user_role` enum('student','lecturer') COLLATE utf8mb4_unicode_ci NOT NULL, `can_post` tinyint(1) NOT NULL DEFAULT '1', `last_read_message_id` int NOT NULL DEFAULT '0', `muted` tinyint(1) NOT NULL DEFAULT '0', `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `uniq_member` (`conversation_id`, `user_id`, `user_role`), CONSTRAINT `fk_chat_participants_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE) ENGINE=InnoDB",
            'chat_messages' => "CREATE TABLE `chat_messages` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `conversation_id` int NOT NULL, `sender_id` int NOT NULL, `sender_role` enum('student','lecturer') COLLATE utf8mb4_unicode_ci NOT NULL, `body` text COLLATE utf8mb4_unicode_ci NOT NULL, `is_instruction` tinyint(1) NOT NULL DEFAULT '0', `attachment_path` varchar(500), `attachment_name` varchar(255), `attachment_size` int unsigned, `attachment_mime` varchar(150), `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, `deleted_at` datetime, CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE) ENGINE=InnoDB",
            'chat_instructions' => "CREATE TABLE `chat_instructions` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `message_id` int NOT NULL, `lecturer_id` int NOT NULL, `target_type` enum('unit','course','course_year') COLLATE utf8mb4_unicode_ci NOT NULL, `target_id` int NOT NULL, `year_of_study` int NOT NULL DEFAULT '0', `recipient_count` int NOT NULL DEFAULT '0', `email_requested` tinyint(1) NOT NULL DEFAULT '0', `emails_sent` int NOT NULL DEFAULT '0', `emails_failed` int NOT NULL DEFAULT '0', `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `fk_chat_instructions_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE) ENGINE=InnoDB"
        ];
        foreach ($chat_tables as $table => $sql) {
            if (!tableExists($conn, $table)) { run_sql($conn, "CREATE TABLE $table", $sql); }
            else { skip("CREATE TABLE $table"); }
        }
    }
    
    function migrate_chat_attachments(mysqli $conn) {
        $columns = ['attachment_path' => "ADD COLUMN `attachment_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL", 'attachment_name' => "ADD COLUMN `attachment_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL", 'attachment_size' => "ADD COLUMN `attachment_size` int unsigned DEFAULT NULL", 'attachment_mime' => "ADD COLUMN `attachment_mime` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL"];
        if(tableExists($conn, 'chat_messages')) {
            foreach ($columns as $column => $clause) {
                if (!columnExists($conn, 'chat_messages', $column)) { run_sql($conn, "ALTER TABLE chat_messages ADD $column", "ALTER TABLE `chat_messages` $clause");}
                else { skip("ALTER TABLE chat_messages ADD $column"); }
            }
        } else { skip("Migration: chat_attachments", "chat_messages table not found"); }
    }

    function migrate_external_learners(mysqli $conn) {
        $tables = [
            'external_learners' => "CREATE TABLE `external_learners` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` varchar(120), `email` varchar(190) NOT NULL UNIQUE, `password` varchar(255)) ENGINE=InnoDB",
            'public_courses' => "CREATE TABLE `public_courses` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `slug` varchar(190) NOT NULL UNIQUE, `title` varchar(200), `is_published` tinyint(1) DEFAULT 0) ENGINE=InnoDB",
            'public_course_modules' => "CREATE TABLE `public_course_modules` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `course_id` int, `title` varchar(200)) ENGINE=InnoDB",
            'public_course_lessons' => "CREATE TABLE `public_course_lessons` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `module_id` int, `title` varchar(200)) ENGINE=InnoDB",
            'public_course_assessments' => "CREATE TABLE `public_course_assessments` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `course_id` int, `title` varchar(200)) ENGINE=InnoDB",
            'public_course_questions' => "CREATE TABLE `public_course_questions` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `assessment_id` int) ENGINE=InnoDB",
            'external_enrollments' => "CREATE TABLE `external_enrollments` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `learner_id` int, `course_id` int, UNIQUE KEY (`learner_id`, `course_id`)) ENGINE=InnoDB",
            'external_lesson_progress' => "CREATE TABLE `external_lesson_progress` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `learner_id` int, `lesson_id` int, UNIQUE KEY (`learner_id`, `lesson_id`)) ENGINE=InnoDB",
            'external_assessment_attempts' => "CREATE TABLE `external_assessment_attempts` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `learner_id` int, `assessment_id` int) ENGINE=InnoDB",
            'certificates' => "CREATE TABLE `certificates` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `learner_id` int, `course_id` int, UNIQUE KEY (`learner_id`, `course_id`)) ENGINE=InnoDB"
        ];
        foreach($tables as $table => $sql) {
            if(!tableExists($conn, $table)) { run_sql($conn, "CREATE TABLE $table", $sql); }
            else { skip("CREATE TABLE $table"); }
        }
    }
    
    function migrate_meeting_guests(mysqli $conn) {
        $columns = ['guest_access' => "ADD COLUMN `guest_access` tinyint(1) NOT NULL DEFAULT 0", 'guest_listed' => "ADD COLUMN `guest_listed` tinyint(1) NOT NULL DEFAULT 0", 'guest_token' => "ADD COLUMN `guest_token` varchar(64) DEFAULT NULL", 'guest_passcode' => "ADD COLUMN `guest_passcode` varchar(32) DEFAULT NULL"];
        if(tableExists($conn, 'meetings')) {
            $added = [];
            foreach ($columns as $column => $clause) {
                if (!columnExists($conn, 'meetings', $column)) { $added[] = $clause; }
                else { skip("ALTER TABLE meetings ADD $column"); }
            }
            if($added) { run_sql($conn, 'ALTER TABLE meetings (guest columns)', 'ALTER TABLE `meetings` ' . implode(', ', $added)); }
        } else { skip("Migration: meeting_guests columns", "meetings table not found"); }
        if(!tableExists($conn, 'meeting_guests')) {
            run_sql($conn, 'CREATE TABLE meeting_guests', "CREATE TABLE `meeting_guests` (`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY, `meeting_id` int, `name` varchar(120), `session_key` varchar(64) UNIQUE, CONSTRAINT `fk_mg_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE) ENGINE=InnoDB");
        } else { skip('CREATE TABLE meeting_guests'); }
    }

    function migrate_standalone_meetings(mysqli $conn) {
        if (!tableExists($conn, 'meetings')) {
            skip('Migration: standalone meetings', 'meetings table not found');
            return;
        }
        $column = $conn->query("SHOW COLUMNS FROM meetings LIKE 'unit_id'");
        $definition = $column ? $column->fetch_assoc() : null;
        if ($definition && strtoupper((string)$definition['Null']) !== 'YES') {
            run_sql($conn, 'ALTER TABLE meetings allow no unit', 'ALTER TABLE `meetings` MODIFY `unit_id` INT NULL');
        } else {
            skip('ALTER TABLE meetings allow no unit');
        }
    }
    
    function migrate_submission_checklist(mysqli $conn) {
        $tables = [
            'submission_checklist' => "CREATE TABLE `submission_checklist` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int NOT NULL, `item_label` varchar(255), PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'submission_signoffs' => "CREATE TABLE `submission_signoffs` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int NOT NULL, `user_id` int NOT NULL, UNIQUE KEY (`team_id`, `user_id`), PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_standups' => "CREATE TABLE `team_standups` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int NOT NULL, `user_id` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'department_admins' => "CREATE TABLE `department_admins` (`id` INT AUTO_INCREMENT PRIMARY KEY, `admin_id` INT NOT NULL, `department_id` INT NOT NULL, UNIQUE KEY (`admin_id`, `department_id`)) ENGINE=InnoDB"
        ];
        foreach($tables as $table => $sql) {
            if(!tableExists($conn, $table)) { run_sql($conn, "CREATE TABLE $table", $sql); }
            else { skip("CREATE TABLE $table"); }
        }
    }
    
    function migrate_teams_system(mysqli $conn) {
        $tables = [
            'teams' => "CREATE TABLE `teams` (`id` int NOT NULL AUTO_INCREMENT, `title` varchar(255), `unit_id` int, PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_members' => "CREATE TABLE `team_members` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int, `student_id` int, UNIQUE KEY (`team_id`, `student_id`), PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_files' => "CREATE TABLE `team_files` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int, `filepath` varchar(500), `uploader_id` int, PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_activity_log' => "CREATE TABLE `team_activity_log` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int, `user_id` int, PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_tasks' => "CREATE TABLE `team_tasks` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int, `title` varchar(255), PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_submissions' => "CREATE TABLE `team_submissions` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int, PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_marks' => "CREATE TABLE `team_marks` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int, `mark` decimal(6,2), PRIMARY KEY (`id`)) ENGINE=InnoDB",
            'team_supervisors' => "CREATE TABLE `team_supervisors` (`id` int NOT NULL AUTO_INCREMENT, `team_id` int, `lecturer_id` int, supervisor_type enum('lecturer','technician','admin') NOT NULL DEFAULT 'lecturer', UNIQUE KEY (`team_id`, `lecturer_id`), PRIMARY KEY (`id`)) ENGINE=InnoDB",
        ];
        foreach($tables as $table => $sql) {
            if(!tableExists($conn, $table)) { run_sql($conn, "CREATE TABLE $table", $sql); }
            else { skip("CREATE TABLE $table"); }
        }
        if(tableExists($conn, 'admins') && !columnExists($conn, 'admins', 'department_id')) { run_sql($conn, 'ALTER TABLE admins ADD department_id', "ALTER TABLE `admins` ADD COLUMN `department_id` INT DEFAULT NULL"); } 
        else { skip("ALTER TABLE admins ADD department_id", "Table not found or column exists"); }
        
        if(tableExists($conn, 'team_supervisors')) { run_sql($conn, 'ALTER TABLE team_supervisors MODIFY supervisor_type', "ALTER TABLE `team_supervisors` MODIFY COLUMN `supervisor_type` enum('lecturer','technician','admin') NOT NULL DEFAULT 'lecturer'"); }
    }

    function migrate_rfid(mysqli $conn) {
        if (!tableExists($conn, 'rfid_cards')) { run_sql($conn, 'CREATE TABLE rfid_cards', "CREATE TABLE `rfid_cards` (`id` INT AUTO_INCREMENT PRIMARY KEY, `uid` VARCHAR(100) NOT NULL UNIQUE) ENGINE=InnoDB"); }
        else { skip('CREATE TABLE rfid_cards'); }
        if (!tableExists($conn, 'rfid_access_log')) { run_sql($conn, 'CREATE TABLE rfid_access_log', "CREATE TABLE `rfid_access_log` (`id` INT AUTO_INCREMENT PRIMARY KEY, `uid` VARCHAR(100) NOT NULL) ENGINE=InnoDB"); }
        else { skip('CREATE TABLE rfid_access_log'); }
        if (tableExists($conn, 'student_practicals') && !columnExists($conn, 'student_practicals', 'verified')) { run_sql($conn, 'ALTER TABLE student_practicals ADD verified', "ALTER TABLE student_practicals ADD COLUMN verified TINYINT(1) DEFAULT 0"); }
        else { skip('ALTER TABLE student_practicals ADD verified', 'Table not found or column exists'); }
        if (tableExists($conn, 'student_practicals') && !columnExists($conn, 'student_practicals', 'started_at')) { run_sql($conn, 'ALTER TABLE student_practicals ADD started_at', "ALTER TABLE student_practicals ADD COLUMN started_at TIMESTAMP NULL"); }
        else { skip('ALTER TABLE student_practicals ADD started_at', 'Table not found or column exists'); }
    }
    
    function fix_collations(mysqli $conn) {
        if(tableExists($conn, 'notifications')) {
             run_sql($conn, 'Fix notifications collation', "ALTER TABLE notifications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        } else {
            skip('Fix notifications collation', 'notifications table not found');
        }
    }
    
    function fix_notifications_columns(mysqli $conn) {
        if(tableExists($conn, 'notifications')) {
            if (!columnExists($conn, 'notifications', 'user_id')) { run_sql($conn, 'ALTER TABLE notifications ADD user_id', "ALTER TABLE notifications ADD COLUMN user_id INT DEFAULT NULL"); }
            else { skip('ALTER TABLE notifications ADD user_id'); }
            if (!columnExists($conn, 'notifications', 'user_role')) { run_sql($conn, 'ALTER TABLE notifications ADD user_role', "ALTER TABLE notifications ADD COLUMN user_role ENUM('student','lecturer','admin') DEFAULT NULL"); }
            else { skip('ALTER TABLE notifications ADD user_role'); }
        } else {
            skip('Fix notifications columns', 'notifications table not found');
        }
    }

    function migrate_note_status(mysqli $conn) {
        if(tableExists($conn, 'notes') && !columnExists($conn, 'notes', 'status')) {
            run_sql($conn, 'ALTER TABLE notes ADD status', "ALTER TABLE notes ADD COLUMN status ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active'");
            run_sql($conn, 'UPDATE notes SET status', "UPDATE notes SET status = 'active' WHERE status IS NULL OR status = ''");
        } else {
            skip('ALTER TABLE notes ADD status', 'Table not found or column exists');
        }
    }

    // --- Run all migrations ---
    echo "<h1>Running All Migrations...</h1>";
    
    migrate_attendance($conn);
    migrate_public_presentations($conn);
    migrate_chat_system($conn);
    migrate_chat_attachments($conn);
    migrate_external_learners($conn);
    migrate_meeting_guests($conn);
    migrate_standalone_meetings($conn);
    migrate_submission_checklist($conn);
    migrate_teams_system($conn);
    migrate_rfid($conn);
    fix_collations($conn);
    fix_notifications_columns($conn);
    migrate_note_status($conn);
    $GLOBALS['log'][] = migrate_unique_unit_assignment($conn);

    // --- Run Phase 1 Migration ---
    echo "<h1>Running Phase 1 Migrations...</h1>";
    try {
        $phase1_result = phase1_migration_001_run($conn);
    } catch (Throwable $e) {
        // Never let one migration abort the batch — report it in the log instead.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $phase1_result = [
            'results'  => [],
            'errors'   => [$e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'],
            'warnings' => [],
        ];
    }
    foreach ($phase1_result['results'] as $result) {
        $GLOBALS['log'][] = ['label' => 'Phase 1: ' . $result, 'status' => 'ok', 'msg' => ''];
    }
    foreach ($phase1_result['errors'] as $error) {
        $GLOBALS['log'][] = ['label' => 'Phase 1: ' . $error, 'status' => 'err', 'msg' => ''];
    }
    foreach ($phase1_result['warnings'] as $warning) {
        $GLOBALS['log'][] = ['label' => 'Phase 1: ' . $warning, 'status' => 'skip', 'msg' => ''];
    }

    // --- Display log ---
    $log = $GLOBALS['log'];
    echo '<style>table{width:100%;border-collapse:collapse;font-size:13px} th{background:#1e3a5f;color:#fff;padding:8px 14px;text-align:left} td{padding:7px 14px;border-bottom:1px solid #f0f0f0} .ok{color:#166534;font-weight:600}.skip{color:#92400e}.err{color:#dc2626;font-weight:600}.info{color:#1d4ed8}</style>';
    echo '<table><tr><th>Item</th><th>Status</th><th>Message</th></tr>';
    foreach($log as $r) {
        echo '<tr><td>'.htmlspecialchars($r['label']).'</td><td class="'.$r['status'].'">'.$r['status'].'</td><td>'.htmlspecialchars($r['msg']).'</td></tr>';
    }
    echo '</table>';

    foreach($log as $r) {
        if ($r['status'] === 'err') {
            http_response_code(500);
            break;
        }
    }

    return ob_get_clean();
}
?>
