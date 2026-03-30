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
    title VARCHAR(255) NOT NULL,
    description TEXT,
    total_marks INT DEFAULT 0,
    due_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(unit_id), INDEX(lecturer_id),
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq','true_false','matching','short_answer','essay','file_upload') NOT NULL DEFAULT 'short_answer',
    marks INT DEFAULT 1,
    position INT NOT NULL DEFAULT 0,
    auto_grade TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(assessment_id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    match_pair VARCHAR(255) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    INDEX(question_id),
    FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS assessment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(8,2) DEFAULT NULL,
    graded TINYINT(1) DEFAULT 0,
    status ENUM('in_progress','submitted','graded','flagged') NOT NULL DEFAULT 'submitted',
    violations_json LONGTEXT DEFAULT NULL,
    graded_by INT DEFAULT NULL,
    graded_at DATETIME DEFAULT NULL,
    INDEX(assessment_id), INDEX(student_id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS submission_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option INT DEFAULT NULL,
    answer_text LONGTEXT,
    file_path VARCHAR(500) DEFAULT NULL,
    marks_awarded DECIMAL(8,2) DEFAULT NULL,
    is_correct TINYINT(1) DEFAULT NULL,
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
    INDEX idx_cm_unit (unit_id),
    INDEX idx_cm_lecturer (lecturer_id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS course_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    unit_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    lesson_number INT NOT NULL DEFAULT 1,
    position INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(module_id), INDEX idx_cl_unit (unit_id),
    FOREIGN KEY (module_id) REFERENCES course_modules(id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS lesson_content_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    block_type ENUM('text','image','video','audio','diagram','pdf') NOT NULL DEFAULT 'text',
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
    event_type ENUM('lesson_viewed','lesson_completed','quiz_score','assignment_score','cat_score','exam_score','lab_completed') NOT NULL,
    lesson_id INT DEFAULT NULL,
    assessment_id INT DEFAULT NULL,
    lab_id INT DEFAULT NULL,
    score DECIMAL(8,2) DEFAULT NULL,
    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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
    semester INT DEFAULT 1,
    academic_year VARCHAR(20) DEFAULT NULL,
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

$queries[] = "CREATE TABLE IF NOT EXISTS exam_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    student_id INT NOT NULL,
    violation_type VARCHAR(100) NOT NULL,
    details TEXT,
    occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(submission_id),
    INDEX(student_id)
) ENGINE=InnoDB";

$queries[] = "CREATE TABLE IF NOT EXISTS assessment_weights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    assessment_type VARCHAR(32) NOT NULL,
    weight_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_unit_lec_type (unit_id, lecturer_id, assessment_type)
) ENGINE=InnoDB";

echo "<h2>Checking / Creating Missing Tables</h2>";
foreach ($queries as $sql) {
    echo $conn->query($sql)
        ? "Table OK / Created successfully<br>"
        : "Error: " . $conn->error . "<br>";
}

/* ================================================================
   SECTION 2 — FIX EXISTING TABLES
   ================================================================ */

echo "<h2>Checking / Fixing Table Schemas</h2>";

// ── Helpers ───────────────────────────────────────────────────────

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
        // Ignore "duplicate" errors — already applied
        $errno = $conn->errno;
        if (in_array($errno, [1060, 1061, 1062, 1091])) {
            echo "✔ $label (already applied)<br>";
        } else {
            echo "✘ $label — " . $conn->error . "<br>";
        }
    }
}

// ── course_modules ────────────────────────────────────────────────
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

alter($conn,
    "UPDATE `course_modules` cm
     JOIN `lecturer_units` lu ON lu.unit_id = cm.unit_id
     SET cm.lecturer_id = lu.lecturer_id
     WHERE cm.lecturer_id = 0",
    "Backfilled course_modules.lecturer_id from lecturer_units for rows with 0"
);

// ── course_lessons ────────────────────────────────────────────────
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

// ── lesson_content_blocks ─────────────────────────────────────────
echo "<h3>lesson_content_blocks</h3>";

alter($conn,
    "ALTER TABLE `lesson_content_blocks`
        MODIFY COLUMN `block_type` ENUM('text','image','video','audio','diagram','pdf') NOT NULL DEFAULT 'text',
        MODIFY COLUMN `content` LONGTEXT",
    "Fixed block_type enum (audio/diagram) and content to LONGTEXT"
);

if (!column_exists($conn, 'lesson_content_blocks', 'created_at')) {
    alter($conn, "ALTER TABLE `lesson_content_blocks` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP", "Added column created_at");
} else { echo "✔ created_at already exists<br>"; }

// ── assessments ───────────────────────────────────────────────────
echo "<h3>assessments</h3>";

if (!column_exists($conn, 'assessments', 'type')) {
    alter($conn, "ALTER TABLE `assessments` ADD COLUMN `type` ENUM('quiz','assignment','cat','exam') NOT NULL DEFAULT 'quiz' AFTER `lecturer_id`", "Added column type");
} else { echo "✔ type already exists<br>"; }

if (!column_exists($conn, 'assessments', 'is_published')) {
    alter($conn, "ALTER TABLE `assessments` ADD COLUMN `is_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `due_date`", "Added column is_published");
} else { echo "✔ is_published already exists<br>"; }

if (!column_exists($conn, 'assessments', 'pass_mark')) {
    alter($conn, "ALTER TABLE `assessments` ADD COLUMN `pass_mark` DECIMAL(5,2) DEFAULT 50.00 AFTER `total_marks`", "Added column pass_mark");
} else { echo "✔ pass_mark already exists<br>"; }

if (!column_exists($conn, 'assessments', 'time_limit_mins')) {
    alter($conn, "ALTER TABLE `assessments` ADD COLUMN `time_limit_mins` INT DEFAULT NULL AFTER `pass_mark`", "Added column time_limit_mins");
} else { echo "✔ time_limit_mins already exists<br>"; }

if (!column_exists($conn, 'assessments', 'instructions')) {
    alter($conn, "ALTER TABLE `assessments` ADD COLUMN `instructions` TEXT DEFAULT NULL AFTER `description`", "Added column instructions");
} else { echo "✔ instructions already exists<br>"; }

if (!column_exists($conn, 'assessments', 'module_id')) {
    alter($conn, "ALTER TABLE `assessments` ADD COLUMN `module_id` INT DEFAULT NULL AFTER `unit_id`", "Added column module_id");
} else { echo "✔ module_id already exists<br>"; }

if (!column_exists($conn, 'assessments', 'lesson_id')) {
    alter($conn, "ALTER TABLE `assessments` ADD COLUMN `lesson_id` INT DEFAULT NULL AFTER `module_id`", "Added column lesson_id");
} else { echo "✔ lesson_id already exists<br>"; }

// ── assessment_questions ──────────────────────────────────────────
echo "<h3>assessment_questions</h3>";

alter($conn,
    "ALTER TABLE `assessment_questions`
        MODIFY COLUMN `question_type`
            ENUM('mcq','true_false','matching','short_answer','essay','file_upload')
            NOT NULL DEFAULT 'short_answer',
        MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "Fixed question_type enum + created_at to DATETIME"
);

if (!column_exists($conn, 'assessment_questions', 'position')) {
    alter($conn, "ALTER TABLE `assessment_questions` ADD COLUMN `position` INT NOT NULL DEFAULT 0 AFTER `marks`", "Added column position");
} else { echo "✔ position already exists<br>"; }

if (!column_exists($conn, 'assessment_questions', 'auto_grade')) {
    alter($conn, "ALTER TABLE `assessment_questions` ADD COLUMN `auto_grade` TINYINT(1) NOT NULL DEFAULT 1 AFTER `position`", "Added column auto_grade");
} else { echo "✔ auto_grade already exists<br>"; }

// ── assessment_submissions ────────────────────────────────────────
echo "<h3>assessment_submissions</h3>";

alter($conn,
    "ALTER TABLE `assessment_submissions`
        MODIFY COLUMN `score` DECIMAL(8,2) DEFAULT NULL,
        MODIFY COLUMN `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    "Fixed score precision to DECIMAL(8,2) + submitted_at to DATETIME"
);

if (!column_exists($conn, 'assessment_submissions', 'status')) {
    alter($conn, "ALTER TABLE `assessment_submissions` ADD COLUMN `status` ENUM('submitted','graded','flagged') NOT NULL DEFAULT 'submitted' AFTER `graded`", "Added column status");
} else { 
    // Fix enum if it has wrong values
    $check = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assessment_submissions' AND COLUMN_NAME = 'status'");
    if ($check && $row = $check->fetch_assoc()) {
        if (strpos($row['COLUMN_TYPE'], 'in_progress') !== false) {
            alter($conn, "ALTER TABLE `assessment_submissions` MODIFY COLUMN `status` ENUM('submitted','graded','flagged') NOT NULL DEFAULT 'submitted'", "Fixed status enum values");
        } else {
            echo "✔ status already exists<br>";
        }
    }
}

if (!column_exists($conn, 'assessment_submissions', 'violations_json')) {
    alter($conn, "ALTER TABLE `assessment_submissions` ADD COLUMN `violations_json` LONGTEXT DEFAULT NULL AFTER `status`", "Added column violations_json");
} else { echo "✔ violations_json already exists<br>"; }

if (!column_exists($conn, 'assessment_submissions', 'graded_by')) {
    alter($conn, "ALTER TABLE `assessment_submissions` ADD COLUMN `graded_by` INT DEFAULT NULL AFTER `violations_json`", "Added column graded_by");
} else { echo "✔ graded_by already exists<br>"; }

if (!column_exists($conn, 'assessment_submissions', 'graded_at')) {
    alter($conn, "ALTER TABLE `assessment_submissions` ADD COLUMN `graded_at` DATETIME DEFAULT NULL AFTER `graded_by`", "Added column graded_at");
} else { echo "✔ graded_at already exists<br>"; }

// ── question_options ──────────────────────────────────────────────
echo "<h3>question_options</h3>";

alter($conn,
    "ALTER TABLE `question_options` MODIFY COLUMN `option_text` TEXT NOT NULL",
    "Fixed option_text to TEXT"
);

if (!column_exists($conn, 'question_options', 'match_pair')) {
    alter($conn, "ALTER TABLE `question_options` ADD COLUMN `match_pair` VARCHAR(255) DEFAULT NULL AFTER `is_correct`", "Added column match_pair");
} else { echo "✔ match_pair already exists<br>"; }

if (!column_exists($conn, 'question_options', 'position')) {
    alter($conn, "ALTER TABLE `question_options` ADD COLUMN `position` INT NOT NULL DEFAULT 0 AFTER `match_pair`", "Added column position");
} else { echo "✔ position already exists<br>"; }

// ── submission_answers ────────────────────────────────────────────
echo "<h3>submission_answers</h3>";

if (!column_exists($conn, 'submission_answers', 'selected_option') &&
     column_exists($conn, 'submission_answers', 'selected_option_id')) {
    alter($conn, "ALTER TABLE `submission_answers` CHANGE `selected_option_id` `selected_option` INT DEFAULT NULL", "Renamed selected_option_id → selected_option");
} else { echo "✔ selected_option column OK<br>"; }

alter($conn,
    "ALTER TABLE `submission_answers` MODIFY COLUMN `answer_text` LONGTEXT",
    "Fixed answer_text to LONGTEXT"
);

if (!column_exists($conn, 'submission_answers', 'file_path')) {
    alter($conn, "ALTER TABLE `submission_answers` ADD COLUMN `file_path` VARCHAR(500) DEFAULT NULL AFTER `answer_text`", "Added column file_path");
} else { echo "✔ file_path already exists<br>"; }

if (!column_exists($conn, 'submission_answers', 'is_correct')) {
    alter($conn, "ALTER TABLE `submission_answers` ADD COLUMN `is_correct` TINYINT(1) DEFAULT NULL AFTER `marks_awarded`", "Added column is_correct");
} else { echo "✔ is_correct already exists<br>"; }

// ── student_progress ──────────────────────────────────────────────
echo "<h3>student_progress</h3>";

if (!column_exists($conn, 'student_progress', 'event_type')) {
    alter($conn,
        "ALTER TABLE `student_progress` ADD COLUMN `event_type`
            ENUM('lesson_viewed','lesson_completed','quiz_score','assignment_score','cat_score','exam_score','lab_completed')
            NOT NULL DEFAULT 'lesson_viewed' AFTER `unit_id`",
        "Added column event_type"
    );
} else { echo "✔ event_type already exists<br>"; }

if (!column_exists($conn, 'student_progress', 'lesson_id')) {
    alter($conn, "ALTER TABLE `student_progress` ADD COLUMN `lesson_id` INT DEFAULT NULL AFTER `event_type`", "Added column lesson_id");
} else { echo "✔ lesson_id already exists<br>"; }

if (!column_exists($conn, 'student_progress', 'assessment_id')) {
    alter($conn, "ALTER TABLE `student_progress` ADD COLUMN `assessment_id` INT DEFAULT NULL AFTER `lesson_id`", "Added column assessment_id");
} else { echo "✔ assessment_id already exists<br>"; }

if (!column_exists($conn, 'student_progress', 'lab_id')) {
    alter($conn, "ALTER TABLE `student_progress` ADD COLUMN `lab_id` INT DEFAULT NULL AFTER `assessment_id`", "Added column lab_id");
} else { echo "✔ lab_id already exists<br>"; }

if (!column_exists($conn, 'student_progress', 'score')) {
    alter($conn, "ALTER TABLE `student_progress` ADD COLUMN `score` DECIMAL(8,2) DEFAULT NULL AFTER `lab_id`", "Added column score");
} else { echo "✔ score already exists<br>"; }

if (!column_exists($conn, 'student_progress', 'completed_at')) {
    alter($conn, "ALTER TABLE `student_progress` ADD COLUMN `completed_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `score`", "Added column completed_at");
} else { echo "✔ completed_at already exists<br>"; }

if (!column_exists($conn, 'student_progress', 'created_at')) {
    alter($conn, "ALTER TABLE `student_progress` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `completed_at`", "Added column created_at");
} else { echo "✔ created_at already exists<br>"; }

// ── THE FIX: add unique key only if it does NOT already exist ─────
if (!index_exists($conn, 'student_progress', 'uq_progress_event')) {
    alter($conn,
        "ALTER TABLE `student_progress`
            ADD UNIQUE KEY `uq_progress_event` (`student_id`, `unit_id`, `lesson_id`, `event_type`)",
        "Added unique key uq_progress_event"
    );
} else {
    echo "✔ uq_progress_event already exists<br>";
}

// ── students ─────────────────────────────────────────────────────
// students table uses reg_no (not registration_number)
echo "<h3>students</h3>";

if (!column_exists($conn, 'students', 'reg_no')) {
    alter($conn, "ALTER TABLE `students` ADD COLUMN `reg_no` VARCHAR(50) DEFAULT NULL AFTER `id`", "Added column reg_no");
} else { echo "✔ reg_no already exists<br>"; }

// ── student_unit_enrollments ──────────────────────────────────────
echo "<h3>student_unit_enrollments</h3>";

if (!column_exists($conn, 'student_unit_enrollments', 'semester')) {
    alter($conn, "ALTER TABLE `student_unit_enrollments` ADD COLUMN `semester` INT NOT NULL DEFAULT 1 AFTER `unit_id`", "Added column semester");
} else { echo "✔ semester already exists<br>"; }

if (!column_exists($conn, 'student_unit_enrollments', 'academic_year')) {
    alter($conn, "ALTER TABLE `student_unit_enrollments` ADD COLUMN `academic_year` VARCHAR(20) DEFAULT NULL AFTER `semester`", "Added column academic_year");
} else { echo "✔ academic_year already exists<br>"; }

// ── notifications ──────────────────────────────────────────────────
echo "<h3>notifications</h3>";

if (!column_exists($conn, 'notifications', 'notes_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD COLUMN `notes_id` INT DEFAULT NULL AFTER `created_at`", "Added column notes_id");
} else { echo "✔ notes_id already exists<br>"; }

if (!column_exists($conn, 'notifications', 'assignment_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD COLUMN `assignment_id` INT DEFAULT NULL AFTER `notes_id`", "Added column assignment_id");
} else { echo "✔ assignment_id already exists<br>"; }

if (!column_exists($conn, 'notifications', 'interactive_assignment_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD COLUMN `interactive_assignment_id` INT DEFAULT NULL AFTER `assignment_id`", "Added column interactive_assignment_id");
} else { echo "✔ interactive_assignment_id already exists<br>"; }

if (!column_exists($conn, 'notifications', 'meeting_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD COLUMN `meeting_id` INT DEFAULT NULL AFTER `interactive_assignment_id`", "Added column meeting_id");
} else { echo "✔ meeting_id already exists<br>"; }

if (!column_exists($conn, 'notifications', 'attendance_session_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD COLUMN `attendance_session_id` INT DEFAULT NULL AFTER `meeting_id`", "Added column attendance_session_id");
} else { echo "✔ attendance_session_id already exists<br>"; }

// Add foreign keys for notifications
$fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'notes_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
if (!$fk || $fk->num_rows === 0) {
    alter($conn, "ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_notes` FOREIGN KEY (`notes_id`) REFERENCES `notes`(`id`)", "Added FK fk_notifications_notes");
} else { echo "✔ FK fk_notifications_notes already exists<br>"; }

$fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'assignment_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
if (!$fk || $fk->num_rows === 0) {
    alter($conn, "ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_assignments` FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`)", "Added FK fk_notifications_assignments");
} else { echo "✔ FK fk_notifications_assignments already exists<br>"; }

$fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'interactive_assignment_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
if (!$fk || $fk->num_rows === 0) {
    alter($conn, "ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_interactive_assignments` FOREIGN KEY (`interactive_assignment_id`) REFERENCES `interactive_assignments`(`id`)", "Added FK fk_notifications_interactive_assignments");
} else { echo "✔ FK fk_notifications_interactive_assignments already exists<br>"; }

$fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'meeting_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
if (!$fk || $fk->num_rows === 0) {
    alter($conn, "ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_meetings` FOREIGN KEY (`meeting_id`) REFERENCES `meetings`(`id`)", "Added FK fk_notifications_meetings");
} else { echo "✔ FK fk_notifications_meetings already exists<br>"; }

$fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'attendance_session_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1");
if (!$fk || $fk->num_rows === 0) {
    alter($conn, "ALTER TABLE `notifications` ADD CONSTRAINT `fk_notif_session` FOREIGN KEY (`attendance_session_id`) REFERENCES `attendance_sessions`(`id`)", "Added FK fk_notif_session");
} else { echo "✔ FK fk_notif_session already exists<br>"; }

// Add indexes
if (!index_exists($conn, 'notifications', 'idx_notes_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD INDEX `idx_notes_id` (`notes_id`)", "Added index idx_notes_id");
} else { echo "✔ idx_notes_id already exists<br>"; }

if (!index_exists($conn, 'notifications', 'idx_assignment_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD INDEX `idx_assignment_id` (`assignment_id`)", "Added index idx_assignment_id");
} else { echo "✔ idx_assignment_id already exists<br>"; }

if (!index_exists($conn, 'notifications', 'idx_interactive_assignment_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD INDEX `idx_interactive_assignment_id` (`interactive_assignment_id`)", "Added index idx_interactive_assignment_id");
} else { echo "✔ idx_interactive_assignment_id already exists<br>"; }

if (!index_exists($conn, 'notifications', 'idx_meeting_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD INDEX `idx_meeting_id` (`meeting_id`)", "Added index idx_meeting_id");
} else { echo "✔ idx_meeting_id already exists<br>"; }

if (!index_exists($conn, 'notifications', 'idx_attendance_session_id')) {
    alter($conn, "ALTER TABLE `notifications` ADD INDEX `idx_attendance_session_id` (`attendance_session_id`)", "Added index idx_attendance_session_id");
} else { echo "✔ idx_attendance_session_id already exists<br>"; }

// ── submissions ────────────────────────────────────────────────────
echo "<h3>submissions (Optional fixes)</h3>";

if (column_exists($conn, 'submissions', 'answer_audio')) {
    echo "✔ answer_audio column exists<br>";
} else {
    echo "✔ answer_audio not required in submissions<br>";
}

// ── team_submissions ───────────────────────────────────────────────
echo "<h3>team_submissions (Status alignment)</h3>";

$check = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_submissions' AND COLUMN_NAME = 'lecturer_status'");
if ($check && $row = $check->fetch_assoc()) {
    if (strpos($row['COLUMN_TYPE'], 'enum') !== false) {
        echo "✔ lecturer_status enum exists<br>";
    }
}

echo "<h2>All fixes applied ✔</h2>";

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