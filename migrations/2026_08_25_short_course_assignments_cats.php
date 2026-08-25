<?php
/**
 * Migration: short-course Assignments & CATs
 * Adds lesson-level attachment and Assignment/CAT typing to
 * public_course_assessments, plus a submissions table for Assignment-type
 * (file/text) work distinct from the existing quiz-attempt table used by CATs.
 * Safe to run more than once.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo "Unauthorized.";
    exit;
}

header('Content-Type: text/plain');

function migColumnExists(mysqli $conn, string $table, string $column): bool
{
    $col = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    return $res && $res->num_rows > 0;
}

function migTableExists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");
    return $res && $res->num_rows > 0;
}

echo "Running: short-course Assignments & CATs migration\n\n";

// -- public_course_assessments: add lesson_id, type, time_limit_minutes,
//    submission_type, due_date ---------------------------------------------
$columnsToAdd = [
    'lesson_id'          => "ALTER TABLE public_course_assessments ADD COLUMN lesson_id INT NULL AFTER module_id",
    'type'                => "ALTER TABLE public_course_assessments ADD COLUMN type ENUM('assignment','cat') NOT NULL DEFAULT 'cat' AFTER title",
    'time_limit_minutes'  => "ALTER TABLE public_course_assessments ADD COLUMN time_limit_minutes INT NULL AFTER max_attempts",
    'submission_type'     => "ALTER TABLE public_course_assessments ADD COLUMN submission_type ENUM('file','text','both') NULL AFTER time_limit_minutes",
    'due_date'            => "ALTER TABLE public_course_assessments ADD COLUMN due_date DATETIME NULL AFTER submission_type",
];

foreach ($columnsToAdd as $col => $sql) {
    if (migColumnExists($conn, 'public_course_assessments', $col)) {
        echo "SKIP  public_course_assessments.{$col} already exists\n";
        continue;
    }
    if ($conn->query($sql)) {
        echo "OK    Added public_course_assessments.{$col}\n";
    } else {
        echo "FAIL  Adding public_course_assessments.{$col}: " . $conn->error . "\n";
    }
}

// -- FK: public_course_assessments.lesson_id -> public_course_lessons.id ----
$fkCheck = $conn->query("
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'public_course_assessments'
      AND CONSTRAINT_NAME = 'fk_pca_lesson'
");
if ($fkCheck && $fkCheck->num_rows > 0) {
    echo "SKIP  fk_pca_lesson already exists\n";
} else {
    $sql = "ALTER TABLE public_course_assessments
              ADD CONSTRAINT fk_pca_lesson FOREIGN KEY (lesson_id)
              REFERENCES public_course_lessons(id) ON DELETE CASCADE";
    if ($conn->query($sql)) {
        echo "OK    Added fk_pca_lesson foreign key\n";
    } else {
        echo "FAIL  Adding fk_pca_lesson: " . $conn->error . "\n";
    }
}

// -- external_assessment_submissions (Assignment-type file/text work) ------
if (migTableExists($conn, 'external_assessment_submissions')) {
    echo "SKIP  external_assessment_submissions already exists\n";
} else {
    $sql = "CREATE TABLE external_assessment_submissions (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        assessment_id INT NOT NULL,
        learner_id INT NOT NULL,
        file_path VARCHAR(500) NULL,
        text_answer TEXT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        grade DECIMAL(6,2) NULL,
        feedback TEXT NULL,
        graded_by INT NULL,
        graded_at DATETIME NULL,
        CONSTRAINT fk_eas_assessment FOREIGN KEY (assessment_id)
            REFERENCES public_course_assessments(id) ON DELETE CASCADE,
        CONSTRAINT fk_eas_learner FOREIGN KEY (learner_id)
            REFERENCES external_learners(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "OK    Created external_assessment_submissions\n";
    } else {
        echo "FAIL  Creating external_assessment_submissions: " . $conn->error . "\n";
    }
}

echo "\nDone.\n";
