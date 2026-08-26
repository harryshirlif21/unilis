<?php
/**
 * Migration: Lesson topics, subtopics & per-topic reading progress.
 *
 * Adds a real hierarchy under each lesson so authors can break a lesson
 * ("topic") into ordered topics & subtopics, and track reading progress per
 * topic for every learner, plus count how many learners finished each topic.
 *
 *  - public_course_lesson_topics      : id, lesson_id, parent_id (nullable,
 *                                       >0 = subtopic), title, content_html,
 *                                       position
 *  - external_lesson_topic_progress   : per-learner per-topic read marker
 *
 * Idempotent and safe to run more than once. Run as admin from the browser, or
 * via CLI: php migrations/2026_08_26_lesson_topics_reading.php --cli
 */
session_start();
require_once __DIR__ . '/../config/db.php';

$isCli = in_array('--cli', $argv ?? [], true);
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

if (!$isCli && !$isAdmin) {
    http_response_code(403);
    exit('Unauthorized.');
}
header('Content-Type: text/plain; charset=utf-8');

function mCol(mysqli $c, string $t, string $col): bool {
    $n = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $n && $n->num_rows > 0;
}
function mTable(mysqli $c, string $t): bool {
    $n = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $n && $n->num_rows > 0;
}

echo "Running: lesson topics & reading progress migration\n\n";

// ---- public_course_lesson_topics ----
if (mTable($conn, 'public_course_lesson_topics')) {
    echo "SKIP  public_course_lesson_topics already exists\n";
} else {
    $sql = "CREATE TABLE public_course_lesson_topics (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        lesson_id INT NOT NULL,
        parent_id INT NULL,
        title VARCHAR(220) NOT NULL,
        content_html MEDIUMTEXT NULL,
        position INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_plt_lesson FOREIGN KEY (lesson_id)
            REFERENCES public_course_lessons(id) ON DELETE CASCADE,
        CONSTRAINT fk_plt_parent FOREIGN KEY (parent_id)
            REFERENCES public_course_lesson_topics(id) ON DELETE CASCADE,
        KEY idx_plt_lesson (lesson_id),
        KEY idx_plt_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    echo ($conn->query($sql) ? "OK    Created public_course_lesson_topics\n"
                             : "FAIL  public_course_lesson_topics: " . $conn->error . "\n");
}

// ---- external_lesson_topic_progress ----
if (mTable($conn, 'external_lesson_topic_progress')) {
    echo "SKIP  external_lesson_topic_progress already exists\n";
} else {
    $sql = "CREATE TABLE external_lesson_topic_progress (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        learner_id INT NOT NULL,
        topic_id INT NOT NULL,
        completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ltp (learner_id, topic_id),
        CONSTRAINT fk_ltp_learner FOREIGN KEY (learner_id)
            REFERENCES external_learners(id) ON DELETE CASCADE,
        CONSTRAINT fk_ltp_topic FOREIGN KEY (topic_id)
            REFERENCES public_course_lesson_topics(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    echo ($conn->query($sql) ? "OK    Created external_lesson_topic_progress\n"
                             : "FAIL  external_lesson_topic_progress: " . $conn->error . "\n");
}

echo "\nDone.\n";