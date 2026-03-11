<?php
require_once 'config/db.php';

echo "<h1>Database Structure Viewer</h1>";

/* =========================
CREATE MISSING TABLES
========================= */

$queries = [];

/* =========================
ASSESSMENTS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS assessments (
id INT AUTO_INCREMENT PRIMARY KEY,
unit_id INT NOT NULL,
lecturer_id INT NOT NULL,
title VARCHAR(255) NOT NULL,
description TEXT,
total_marks INT DEFAULT 0,
due_date DATETIME,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
INDEX(unit_id),
INDEX(lecturer_id),
FOREIGN KEY (unit_id) REFERENCES units(id),
FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
) ENGINE=InnoDB";

/* =========================
ASSESSMENT QUESTIONS
========================= */

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

/* =========================
QUESTION OPTIONS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS question_options (
id INT AUTO_INCREMENT PRIMARY KEY,
question_id INT NOT NULL,
option_text VARCHAR(255) NOT NULL,
is_correct TINYINT(1) DEFAULT 0,
INDEX(question_id),
FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
) ENGINE=InnoDB";

/* =========================
ASSESSMENT SUBMISSIONS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS assessment_submissions (
id INT AUTO_INCREMENT PRIMARY KEY,
assessment_id INT NOT NULL,
student_id INT NOT NULL,
submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
score DECIMAL(5,2),
graded TINYINT(1) DEFAULT 0,
INDEX(assessment_id),
INDEX(student_id),
FOREIGN KEY (assessment_id) REFERENCES assessments(id),
FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
SUBMISSION ANSWERS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS submission_answers (
id INT AUTO_INCREMENT PRIMARY KEY,
submission_id INT NOT NULL,
question_id INT NOT NULL,
selected_option_id INT,
answer_text TEXT,
marks_awarded DECIMAL(5,2),
INDEX(submission_id),
INDEX(question_id),
FOREIGN KEY (submission_id) REFERENCES assessment_submissions(id),
FOREIGN KEY (question_id) REFERENCES assessment_questions(id)
) ENGINE=InnoDB";

/* =========================
COURSE MODULES
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS course_modules (
id INT AUTO_INCREMENT PRIMARY KEY,
course_id INT NOT NULL,
title VARCHAR(255) NOT NULL,
description TEXT,
position INT DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
INDEX(course_id),
FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB";

/* =========================
COURSE LESSONS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS course_lessons (
id INT AUTO_INCREMENT PRIMARY KEY,
module_id INT NOT NULL,
title VARCHAR(255) NOT NULL,
content TEXT,
position INT DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
INDEX(module_id),
FOREIGN KEY (module_id) REFERENCES course_modules(id)
) ENGINE=InnoDB";

/* =========================
LESSON CONTENT BLOCKS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS lesson_content_blocks (
id INT AUTO_INCREMENT PRIMARY KEY,
lesson_id INT NOT NULL,
block_type ENUM('text','image','video','quiz','code') DEFAULT 'text',
content TEXT,
position INT DEFAULT 0,
INDEX(lesson_id),
FOREIGN KEY (lesson_id) REFERENCES course_lessons(id)
) ENGINE=InnoDB";

/* =========================
LABS
========================= */

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

/* =========================
LAB SUBMISSIONS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS lab_submissions (
id INT AUTO_INCREMENT PRIMARY KEY,
lab_id INT NOT NULL,
student_id INT NOT NULL,
file_path VARCHAR(255),
submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
score DECIMAL(5,2),
INDEX(lab_id),
INDEX(student_id),
FOREIGN KEY (lab_id) REFERENCES labs(id),
FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
STUDENT PROGRESS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS student_progress (
id INT AUTO_INCREMENT PRIMARY KEY,
student_id INT NOT NULL,
unit_id INT NOT NULL,
progress_percent DECIMAL(5,2) DEFAULT 0,
last_accessed DATETIME,
INDEX(student_id),
INDEX(unit_id),
FOREIGN KEY (student_id) REFERENCES students(id),
FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB";

/* =========================
STUDENT UNIT ENROLLMENTS
========================= */

$queries[] = "CREATE TABLE IF NOT EXISTS student_unit_enrollments (
id INT AUTO_INCREMENT PRIMARY KEY,
student_id INT NOT NULL,
unit_id INT NOT NULL,
enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
INDEX(student_id),
INDEX(unit_id),
FOREIGN KEY (student_id) REFERENCES students(id),
FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB";

/* =========================
RUN CREATION
========================= */

echo "<h2>Checking / Creating Missing Tables</h2>";

foreach ($queries as $sql) {

    if ($conn->query($sql)) {
        echo "Table OK / Created successfully<br>";
    } else {
        echo "Error: " . $conn->error . "<br>";
    }
}

/* =========================
SHOW ALL TABLES
========================= */

$tables_result = $conn->query("SHOW TABLES");

while ($table_row = $tables_result->fetch_array()) {

    $table = $table_row[0];

    echo "<hr>";
    echo "<h2>Table: $table</h2>";

    /* SHOW COLUMNS */

    echo "<h3>Fields</h3>";

    $columns = $conn->query("SHOW COLUMNS FROM `$table`");

    echo "<table border='1' cellpadding='6' cellspacing='0'>
    <tr>
    <th>Field</th>
    <th>Type</th>
    <th>Null</th>
    <th>Key</th>
    <th>Default</th>
    <th>Extra</th>
    </tr>";

    while ($col = $columns->fetch_assoc()) {

        echo "<tr>
        <td>{$col['Field']}</td>
        <td>{$col['Type']}</td>
        <td>{$col['Null']}</td>
        <td>{$col['Key']}</td>
        <td>{$col['Default']}</td>
        <td>{$col['Extra']}</td>
        </tr>";
    }

    echo "</table>";

    /* SHOW FOREIGN KEYS */

    echo "<h3>Foreign Keys</h3>";

    $fk_query = "
    SELECT 
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME,
        CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '$table'
    AND REFERENCED_TABLE_NAME IS NOT NULL
    ";

    $fk_result = $conn->query($fk_query);

    if ($fk_result->num_rows > 0) {

        echo "<table border='1' cellpadding='6'>
        <tr>
        <th>Constraint</th>
        <th>Column</th>
        <th>References Table</th>
        <th>References Column</th>
        </tr>";

        while ($fk = $fk_result->fetch_assoc()) {

            echo "<tr>
            <td>{$fk['CONSTRAINT_NAME']}</td>
            <td>{$fk['COLUMN_NAME']}</td>
            <td>{$fk['REFERENCED_TABLE_NAME']}</td>
            <td>{$fk['REFERENCED_COLUMN_NAME']}</td>
            </tr>";
        }

        echo "</table>";

    } else {

        echo "No Foreign Keys";

    }

}
?>