<?php
require_once 'config/db.php';

echo "<h1>Database Structure Viewer</h1>";

/* ================================================================
   SECTION 1 — CREATE MISSING TABLES
   ================================================================ */

$queries = [];

$queries[] = "CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    module_id INT DEFAULT NULL,
    lesson_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    type ENUM('quiz','assignment','cat','exam') NOT NULL,
    instructions TEXT,
    time_limit_mins INT DEFAULT NULL,
    total_marks INT NOT NULL DEFAULT 0,
    pass_mark INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    due_date DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(unit_id), INDEX(lecturer_id),
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice','true_false','short_answer','essay') DEFAULT 'short_answer',
    marks INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(assessment_id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    INDEX(question_id),
    FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS assessment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(5,2),
    graded TINYINT(1) DEFAULT 0,
    INDEX(assessment_id), INDEX(student_id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS submission_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT,
    answer_text TEXT,
    marks_awarded DECIMAL(5,2),
    INDEX(submission_id), INDEX(question_id),
    FOREIGN KEY (submission_id) REFERENCES assessment_submissions(id),
    FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS course_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    position INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(unit_id), INDEX(lecturer_id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS course_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    unit_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    lesson_number INT NOT NULL DEFAULT 1,
    position INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(module_id), INDEX(unit_id),
    FOREIGN KEY (module_id) REFERENCES course_modules(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS lesson_content_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    block_type ENUM('text','image','video','audio','diagram') NOT NULL DEFAULT 'text',
    content LONGTEXT,
    position INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(lesson_id),
    FOREIGN KEY (lesson_id) REFERENCES course_lessons(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT,
    due_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(unit_id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS lab_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(5,2),
    INDEX(lab_id), INDEX(student_id),
    FOREIGN KEY (lab_id) REFERENCES labs(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS student_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    unit_id INT NOT NULL,
    progress_percent DECIMAL(5,2) DEFAULT 0,
    last_accessed DATETIME,
    INDEX(student_id), INDEX(unit_id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS student_unit_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    unit_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(student_id), INDEX(unit_id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS course_outlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    description TEXT,
    outline LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_unit_lecturer (unit_id, lecturer_id)
) ENGINE=InnoDB";

// ── NEW: exam_violations (missing on live server) ──────────────
$queries[] = "CREATE TABLE IF NOT EXISTS exam_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    student_id INT NOT NULL,
    violation_type VARCHAR(100) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    INDEX(submission_id), INDEX(student_id)
) ENGINE=InnoDB";

echo "<h2>Checking / Creating Missing Tables</h2>";
foreach ($queries as $sql) {
    echo $conn->query($sql) ? "Table OK / Created successfully<br>" : "Error: " . $conn->error . "<br>";
}

/* ================================================================
   SECTION 2 — FIX EXISTING TABLE SCHEMAS
   ================================================================ */

echo "<h2>Checking / Fixing Table Schemas</h2>";

function column_exists($conn, $table, $column) {
    $r = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = '$table'
          AND COLUMN_NAME  = '$column' LIMIT 1");
    return $r && $r->num_rows > 0;
}

function index_exists($conn, $table, $index) {
    $r = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
          AND INDEX_NAME = '$index' LIMIT 1");
    return $r && $r->num_rows > 0;
}

function alter($conn, $sql, $label) {
    if ($conn->query($sql)) {
        echo "✔ $label<br>";
    } else {
        echo "✘ $label — " . $conn->error . "<br>";
    }
}

/* ── assessments ─────────────────────────────────────────────────
   Online (wrong): missing module_id, lesson_id, type, instructions,
                   time_limit_mins, pass_mark, is_published
                   Has extra 'description' column not in localhost
   Correct:        full schema matching localhost                   */

echo "<h3>assessments</h3>";

// Drop 'description' column (exists online, not in localhost schema)
if (column_exists($conn, 'assessments', 'description')) {
    alter($conn, "ALTER TABLE `assessments` DROP COLUMN `description`", "Dropped column description");
} else { echo "✔ description already absent<br>"; }

// Add module_id
if (!column_exists($conn, 'assessments', 'module_id')) {
    alter($conn,
        "ALTER TABLE `assessments` ADD COLUMN `module_id` INT DEFAULT NULL AFTER `lecturer_id`",
        "Added column module_id"
    );
} else { echo "✔ module_id already exists<br>"; }

// Add lesson_id
if (!column_exists($conn, 'assessments', 'lesson_id')) {
    alter($conn,
        "ALTER TABLE `assessments` ADD COLUMN `lesson_id` INT DEFAULT NULL AFTER `module_id`",
        "Added column lesson_id"
    );
} else { echo "✔ lesson_id already exists<br>"; }

// Add type
if (!column_exists($conn, 'assessments', 'type')) {
    alter($conn,
        "ALTER TABLE `assessments` ADD COLUMN `type` ENUM('quiz','assignment','cat','exam') NOT NULL AFTER `lesson_id`",
        "Added column type"
    );
} else { echo "✔ type already exists<br>"; }

// Add instructions
if (!column_exists($conn, 'assessments', 'instructions')) {
    alter($conn,
        "ALTER TABLE `assessments` ADD COLUMN `instructions` TEXT DEFAULT NULL AFTER `type`",
        "Added column instructions"
    );
} else { echo "✔ instructions already exists<br>"; }

// Add time_limit_mins
if (!column_exists($conn, 'assessments', 'time_limit_mins')) {
    alter($conn,
        "ALTER TABLE `assessments` ADD COLUMN `time_limit_mins` INT DEFAULT NULL AFTER `instructions`",
        "Added column time_limit_mins"
    );
} else { echo "✔ time_limit_mins already exists<br>"; }

// Add pass_mark (after total_marks)
if (!column_exists($conn, 'assessments', 'pass_mark')) {
    alter($conn,
        "ALTER TABLE `assessments` ADD COLUMN `pass_mark` INT NOT NULL DEFAULT 0 AFTER `total_marks`",
        "Added column pass_mark"
    );
} else { echo "✔ pass_mark already exists<br>"; }

// Add is_published (after pass_mark)
if (!column_exists($conn, 'assessments', 'is_published')) {
    alter($conn,
        "ALTER TABLE `assessments` ADD COLUMN `is_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pass_mark`",
        "Added column is_published"
    );
} else { echo "✔ is_published already exists<br>"; }

// Fix due_date to DATETIME (online may have DATE type)
alter($conn,
    "ALTER TABLE `assessments` MODIFY COLUMN `due_date` DATETIME DEFAULT NULL",
    "Ensured due_date is DATETIME"
);

// Fix created_at to DATETIME (online has TIMESTAMP)
alter($conn,
    "ALTER TABLE `assessments` MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "Ensured created_at is DATETIME"
);

/* ── course_modules ──────────────────────────────────────────────*/
echo "<h3>course_modules</h3>";

$fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_modules'
    AND COLUMN_NAME = 'course_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
if ($fk && $fk->num_rows > 0) {
    $fk_name = $fk->fetch_assoc()['CONSTRAINT_NAME'];
    alter($conn, "ALTER TABLE `course_modules` DROP FOREIGN KEY `$fk_name`", "Dropped FK $fk_name on course_id");
}

if (column_exists($conn, 'course_modules', 'course_id')) {
    alter($conn, "ALTER TABLE `course_modules` DROP COLUMN `course_id`", "Dropped column course_id");
} else { echo "✔ course_id already absent<br>"; }

if (column_exists($conn, 'course_modules', 'description')) {
    alter($conn, "ALTER TABLE `course_modules` DROP COLUMN `description`", "Dropped column description");
} else { echo "✔ description already absent<br>"; }

if (!column_exists($conn, 'course_modules', 'unit_id')) {
    alter($conn, "ALTER TABLE `course_modules` ADD COLUMN `unit_id` INT NOT NULL DEFAULT 0 AFTER `id`", "Added column unit_id");
} else { echo "✔ unit_id already exists<br>"; }

if (!column_exists($conn, 'course_modules', 'lecturer_id')) {
    alter($conn, "ALTER TABLE `course_modules` ADD COLUMN `lecturer_id` INT NOT NULL DEFAULT 0 AFTER `unit_id`", "Added column lecturer_id");
} else { echo "✔ lecturer_id already exists<br>"; }

if (!index_exists($conn, 'course_modules', 'idx_cm_unit')) {
    alter($conn, "ALTER TABLE `course_modules` ADD INDEX `idx_cm_unit` (`unit_id`)", "Added index idx_cm_unit");
} else { echo "✔ idx_cm_unit already exists<br>"; }

if (!index_exists($conn, 'course_modules', 'idx_cm_lecturer')) {
    alter($conn, "ALTER TABLE `course_modules` ADD INDEX `idx_cm_lecturer` (`lecturer_id`)", "Added index idx_cm_lecturer");
} else { echo "✔ idx_cm_lecturer already exists<br>"; }

/* ── course_lessons ──────────────────────────────────────────────*/
echo "<h3>course_lessons</h3>";

if (column_exists($conn, 'course_lessons', 'content')) {
    alter($conn, "ALTER TABLE `course_lessons` DROP COLUMN `content`", "Dropped column content");
} else { echo "✔ content already absent<br>"; }

if (!column_exists($conn, 'course_lessons', 'unit_id')) {
    alter($conn, "ALTER TABLE `course_lessons` ADD COLUMN `unit_id` INT NOT NULL DEFAULT 0 AFTER `module_id`", "Added column unit_id");
} else { echo "✔ unit_id already exists<br>"; }

if (!column_exists($conn, 'course_lessons', 'lesson_number')) {
    alter($conn, "ALTER TABLE `course_lessons` ADD COLUMN `lesson_number` INT NOT NULL DEFAULT 1 AFTER `title`", "Added column lesson_number");
} else { echo "✔ lesson_number already exists<br>"; }

if (!index_exists($conn, 'course_lessons', 'idx_cl_unit')) {
    alter($conn, "ALTER TABLE `course_lessons` ADD INDEX `idx_cl_unit` (`unit_id`)", "Added index idx_cl_unit");
} else { echo "✔ idx_cl_unit already exists<br>"; }

/* ── lesson_content_blocks ───────────────────────────────────────*/
echo "<h3>lesson_content_blocks</h3>";

alter($conn,
    "ALTER TABLE `lesson_content_blocks`
        MODIFY COLUMN `block_type` ENUM('text','image','video','audio','diagram') NOT NULL DEFAULT 'text',
        MODIFY COLUMN `content` LONGTEXT",
    "Fixed block_type enum (audio/diagram) and content to LONGTEXT"
);

if (!column_exists($conn, 'lesson_content_blocks', 'created_at')) {
    alter($conn, "ALTER TABLE `lesson_content_blocks` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP", "Added column created_at");
} else { echo "✔ created_at already exists<br>"; }

echo "<h3>All fixes applied ✔</h3>";

/* ================================================================
   SECTION 3 — SHOW ALL TABLES
   ================================================================ */

$tables_result = $conn->query("SHOW TABLES");
while ($table_row = $tables_result->fetch_array()) {
    $table = $table_row[0];
    echo "<hr><h2>Table: $table</h2>";

    echo "<h3>Fields</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM `$table`");
    echo "<table border='1' cellpadding='6' cellspacing='0'>
        <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr>
            <td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td>
            <td>{$col['Key']}</td><td>{$col['Default']}</td><td>{$col['Extra']}</td>
        </tr>";
    }
    echo "</table>";

    echo "<h3>Foreign Keys</h3>";
    $fk_result = $conn->query("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    if ($fk_result->num_rows > 0) {
        echo "<table border='1' cellpadding='6'>
            <tr><th>Constraint</th><th>Column</th><th>References Table</th><th>References Column</th></tr>";
        while ($fk = $fk_result->fetch_assoc()) {
            echo "<tr>
                <td>{$fk['CONSTRAINT_NAME']}</td><td>{$fk['COLUMN_NAME']}</td>
                <td>{$fk['REFERENCED_TABLE_NAME']}</td><td>{$fk['REFERENCED_COLUMN_NAME']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "No Foreign Keys";
    }
}
?>