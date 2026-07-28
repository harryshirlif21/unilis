<?php
/**
 * One-off migration: external learners, the public course catalogue, their
 * progress, and certificates.
 *
 * Creates the schema behind /learn - the public side of the ICLM, open to people
 * who are not enrolled students or staff.
 *
 * WHY A SEPARATE IDENTITY TABLE
 *
 * External learners are not rows in `students`. A student id carries a course,
 * a year of study, unit enrolments and a place in class lists, course groups and
 * chat; an external learner has none of those. Flagging them inside `students`
 * would have put them in every roster and every course group unless each query
 * remembered to exclude them, and one forgotten filter is a privacy incident.
 * They get their own table and their own login instead.
 *
 * The cost is that anything serving both audiences has to carry an identity
 * pair rather than a bare id - the same (id, role) discipline the chat module
 * already uses, and for the same reason: ids from different tables collide.
 *
 * WHY A SEPARATE CATALOGUE
 *
 * public_courses is not `units`. Units belong to a degree programme, carry a
 * year and semester, and their material is written for enrolled students.
 * Catalogue courses are self-contained and self-paced. Sharing one table would
 * have meant every unit query growing an "and is it public?" clause.
 *
 * HOW TO RUN
 *
 *   Browser: log in as an admin, then open
 *            https://unilis.jhubafrica.com/migrate_external_learners.php
 *   Shell:   docker compose exec app php migrate_external_learners.php
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
            . "  docker compose exec app php migrate_external_learners.php",
            403
        );
    }
}

require_once __DIR__ . '/config/db.php';

$tables = [

    // Mirrors the columns students.php relies on for verification
    // (verification_code / token_expires_at / is_verified) so the existing
    // mail-a-token-and-check-it flow reads the same way on both sides.
    'external_learners' => <<<SQL
CREATE TABLE IF NOT EXISTS `external_learners` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organisation` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `reset_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL,
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_verification` (`verification_code`),
  KEY `idx_reset` (`reset_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // slug is the public URL segment, so it is unique and generated from the
    // title. is_published gates visibility: a half-written course must not
    // appear in the catalogue.
    'public_courses' => <<<SQL
CREATE TABLE IF NOT EXISTS `public_courses` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `cover_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` enum('beginner','intermediate','advanced') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'beginner',
  `estimated_hours` decimal(5,1) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `certificate_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `pass_mark` tinyint unsigned NOT NULL DEFAULT '70' COMMENT 'Percentage each assessment must reach to count as passed.',
  `created_by_lecturer_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_published` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    'public_course_modules' => <<<SQL
CREATE TABLE IF NOT EXISTS `public_course_modules` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `course_id` int NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  KEY `idx_course` (`course_id`, `position`),
  CONSTRAINT `fk_pcm_course` FOREIGN KEY (`course_id`) REFERENCES `public_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    'public_course_lessons' => <<<SQL
CREATE TABLE IF NOT EXISTS `public_course_lessons` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `module_id` int NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_minutes` int unsigned DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  KEY `idx_module` (`module_id`, `position`),
  CONSTRAINT `fk_pcl_module` FOREIGN KEY (`module_id`) REFERENCES `public_course_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // module_id NULL means a course-level assessment (a final exam) rather than
    // one attached to a single module.
    'public_course_assessments' => <<<SQL
CREATE TABLE IF NOT EXISTS `public_course_assessments` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `course_id` int NOT NULL,
  `module_id` int DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `pass_mark` tinyint unsigned DEFAULT NULL COMMENT 'Overrides public_courses.pass_mark when set.',
  `max_attempts` int unsigned NOT NULL DEFAULT '0' COMMENT '0 = unlimited.',
  `position` int NOT NULL DEFAULT '0',
  KEY `idx_course` (`course_id`, `position`),
  KEY `idx_module` (`module_id`),
  CONSTRAINT `fk_pca_course` FOREIGN KEY (`course_id`) REFERENCES `public_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    'public_course_questions' => <<<SQL
CREATE TABLE IF NOT EXISTS `public_course_questions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `assessment_id` int NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('single','multiple','true_false','short_text') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `options` json DEFAULT NULL COMMENT 'Choice list for single/multiple; null for text answers.',
  `correct_answer` text COLLATE utf8mb4_unicode_ci,
  `marks` int unsigned NOT NULL DEFAULT '1',
  `position` int NOT NULL DEFAULT '0',
  KEY `idx_assessment` (`assessment_id`, `position`),
  CONSTRAINT `fk_pcq_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `public_course_assessments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    'external_enrollments' => <<<SQL
CREATE TABLE IF NOT EXISTS `external_enrollments` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `learner_id` int NOT NULL,
  `course_id` int NOT NULL,
  `enrolled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `last_seen_lesson_id` int DEFAULT NULL COMMENT 'Resume point.',
  UNIQUE KEY `uniq_enrolment` (`learner_id`, `course_id`),
  KEY `idx_course` (`course_id`),
  CONSTRAINT `fk_ee_learner` FOREIGN KEY (`learner_id`) REFERENCES `external_learners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ee_course` FOREIGN KEY (`course_id`) REFERENCES `public_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // One row per lesson a learner has finished. The unique key makes "mark
    // complete" idempotent, so a double-click cannot inflate progress.
    'external_lesson_progress' => <<<SQL
CREATE TABLE IF NOT EXISTS `external_lesson_progress` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `learner_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `completed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_progress` (`learner_id`, `lesson_id`),
  KEY `idx_lesson` (`lesson_id`),
  CONSTRAINT `fk_elp_learner` FOREIGN KEY (`learner_id`) REFERENCES `external_learners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_elp_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `public_course_lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // Attempts are kept rather than overwritten, so a retake does not erase the
    // history and "best attempt" stays computable.
    'external_assessment_attempts' => <<<SQL
CREATE TABLE IF NOT EXISTS `external_assessment_attempts` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `learner_id` int NOT NULL,
  `assessment_id` int NOT NULL,
  `score` decimal(6,2) NOT NULL DEFAULT '0.00',
  `max_score` decimal(6,2) NOT NULL DEFAULT '0.00',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `passed` tinyint(1) NOT NULL DEFAULT '0',
  `answers` json DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_learner_assessment` (`learner_id`, `assessment_id`),
  KEY `idx_passed` (`learner_id`, `passed`),
  CONSTRAINT `fk_eaa_learner` FOREIGN KEY (`learner_id`) REFERENCES `external_learners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eaa_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `public_course_assessments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

    // serial is what appears on the certificate; verification_code is what a
    // third party types into the public checker. They are separate so the
    // printed serial can be human-friendly without being guessable.
    'certificates' => <<<SQL
CREATE TABLE IF NOT EXISTS `certificates` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `learner_id` int NOT NULL,
  `course_id` int NOT NULL,
  `serial` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `learner_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Snapshot: the name as printed, so a later rename does not rewrite history.',
  `course_title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Snapshot, for the same reason.',
  `final_percentage` decimal(5,2) DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  UNIQUE KEY `uniq_serial` (`serial`),
  UNIQUE KEY `uniq_verification` (`verification_code`),
  UNIQUE KEY `uniq_award` (`learner_id`, `course_id`),
  CONSTRAINT `fk_cert_learner` FOREIGN KEY (`learner_id`) REFERENCES `external_learners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cert_course` FOREIGN KEY (`course_id`) REFERENCES `public_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

function tableExists(mysqli $conn, string $table): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    $exists = $result->num_rows > 0;
    $result->free();

    return $exists;
}

try {
    echo "=== external learners migration ===\n";
    echo 'Database: ' . $conn->query('SELECT DATABASE()')->fetch_row()[0] . "\n";
    echo 'Ran by:   ' . (IS_CLI ? 'CLI' : 'admin user ' . (int)$_SESSION['user_id']) . "\n\n";

    $created = 0;
    $skipped = 0;

    // Order matters: the foreign keys point backwards up this list.
    foreach ($tables as $table => $ddl) {
        if (tableExists($conn, $table)) {
            echo str_pad($table, 30) . "already exists - skipped\n";
            $skipped++;
            continue;
        }

        echo str_pad($table, 30) . "creating ... ";
        $conn->query($ddl);

        if (!tableExists($conn, $table)) {
            echo "\n";
            bail("CREATE reported success but '$table' is still missing.");
        }

        echo "done\n";
        $created++;
    }

    echo "\n$created created, $skipped already present.\n";

    echo "\nDone. External learners can register at /learn/register.php\n";
    echo "The catalogue is empty until courses are published.\n";
    echo "Remember to delete this file.\n";
    exit(0);
} catch (Throwable $e) {
    bail('Migration failed: ' . $e->getMessage());
}
