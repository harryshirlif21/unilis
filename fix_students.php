<?php
require_once 'config/db.php';

/*
 Updated DB migration script for Interactive Assignments & Notes feature.
 - Creates / updates interactive_* tables safely (if not exists or missing columns)
 - Fixes duplicate/overlapping columns (drops `type` from interactive_questions)
 - Adds support for file uploads for questions and answers, marks_awarded, option points
 - Preserves existing data and avoids destructive operations where possible

 USAGE: run this once from the application environment where config/db.php provides $conn (mysqli)
*/

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' LIMIT 1");
    return ($res && $res->num_rows > 0);
}

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
    return ($res && $res->num_rows > 0);
}

function getColumnType($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) return $row['COLUMN_TYPE'];
    return null;
}

echo "<h1>Interactive feature DB migration</h1>";

/* ------------------
   Create interactive_assignments
   ------------------ */
if (!tableExists($conn, 'interactive_assignments')) {
    $sql = "CREATE TABLE interactive_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lecturer_id INT NOT NULL,
        unit_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        due_date DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
        FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($sql)) echo "<p>Table <strong>interactive_assignments</strong> created.</p>";
    else echo "<p style='color:red;'>Error creating interactive_assignments: " . htmlspecialchars($conn->error) . "</p>";
} else {
    echo "<p>Table <strong>interactive_assignments</strong> already exists.</p>";
}

/* ------------------
   Create interactive_questions
   ------------------ */
if (!tableExists($conn, 'interactive_questions')) {
    $sql = "CREATE TABLE interactive_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        interactive_assignment_id INT NOT NULL,
        question_text TEXT NOT NULL,
        question_type ENUM('multiple_choice','true_false','short_answer','essay','image_based') NOT NULL DEFAULT 'short_answer',
        points INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        media_url VARCHAR(255) NULL,
        file_url VARCHAR(255) NULL,
        FOREIGN KEY (interactive_assignment_id) REFERENCES interactive_assignments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($sql)) echo "<p>Table <strong>interactive_questions</strong> created.</p>";
    else echo "<p style='color:red;'>Error creating interactive_questions: " . htmlspecialchars($conn->error) . "</p>";
} else {
    echo "<p>Table <strong>interactive_questions</strong> already exists. Checking columns...</p>";

    // Drop duplicate 'type' column if present
    if (columnExists($conn, 'interactive_questions', 'type')) {
        $sql = "ALTER TABLE interactive_questions DROP COLUMN `type`";
        if ($conn->query($sql)) echo "<p>Dropped column <strong>type</strong> from interactive_questions.</p>";
        else echo "<p style='color:red;'>Error dropping column 'type': " . htmlspecialchars($conn->error) . "</p>";
    }

    // Ensure question_type has desired enum values
    $colType = getColumnType($conn, 'interactive_questions', 'question_type');
    if ($colType === null) {
        // column missing -> add
        $sql = "ALTER TABLE interactive_questions ADD question_type ENUM('multiple_choice','true_false','short_answer','essay','image_based') NOT NULL DEFAULT 'short_answer' AFTER question_text";
        if ($conn->query($sql)) echo "<p>Added column <strong>question_type</strong> to interactive_questions.</p>";
        else echo "<p style='color:red;'>Error adding question_type: " . htmlspecialchars($conn->error) . "</p>";
    } else {
        // attempt to modify enum to include the desired values (best effort)
        if (stripos($colType, 'multiple_choice') === false || stripos($colType, 'image_based') === false) {
            $sql = "ALTER TABLE interactive_questions MODIFY question_type ENUM('multiple_choice','true_false','short_answer','essay','image_based') NOT NULL DEFAULT 'short_answer'";
            if ($conn->query($sql)) echo "<p>Modified <strong>question_type</strong> enum on interactive_questions.</p>";
            else echo "<p style='color:orange;'>Could not modify question_type enum (may contain existing values not compatible): " . htmlspecialchars($conn->error) . "</p>";
        } else {
            echo "<p><strong>question_type</strong> already includes required values.</p>";
        }
    }

    // Ensure media_url exists
    if (!columnExists($conn, 'interactive_questions', 'media_url')) {
        $sql = "ALTER TABLE interactive_questions ADD media_url VARCHAR(255) NULL AFTER created_at";
        if ($conn->query($sql)) echo "<p>Added column <strong>media_url</strong> to interactive_questions.</p>";
    }

    // Ensure file_url exists
    if (!columnExists($conn, 'interactive_questions', 'file_url')) {
        $sql = "ALTER TABLE interactive_questions ADD file_url VARCHAR(255) NULL AFTER media_url";
        if ($conn->query($sql)) echo "<p>Added column <strong>file_url</strong> to interactive_questions.</p>";
    }

    // Ensure points column exists
    if (!columnExists($conn, 'interactive_questions', 'points')) {
        $sql = "ALTER TABLE interactive_questions ADD points INT NOT NULL DEFAULT 1 AFTER question_type";
        if ($conn->query($sql)) echo "<p>Added column <strong>points</strong> to interactive_questions.</p>";
    }
}

/* ------------------
   Create interactive_options
   ------------------ */
if (!tableExists($conn, 'interactive_options')) {
    $sql = "CREATE TABLE interactive_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        option_text VARCHAR(255) NOT NULL,
        is_correct TINYINT(1) DEFAULT 0,
        points INT DEFAULT 0,
        FOREIGN KEY (question_id) REFERENCES interactive_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($sql)) echo "<p>Table <strong>interactive_options</strong> created.</p>";
    else echo "<p style='color:red;'>Error creating interactive_options: " . htmlspecialchars($conn->error) . "</p>";
} else {
    echo "<p>Table <strong>interactive_options</strong> already exists. Ensuring 'points' column exists...</p>";
    if (!columnExists($conn, 'interactive_options', 'points')) {
        $sql = "ALTER TABLE interactive_options ADD points INT DEFAULT 0 AFTER is_correct";
        if ($conn->query($sql)) echo "<p>Added <strong>points</strong> to interactive_options.</p>";
    }
}

/* ------------------
   Create interactive_submissions
   ------------------ */
if (!tableExists($conn, 'interactive_submissions')) {
    $sql = "CREATE TABLE interactive_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        assignment_id INT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        score DECIMAL(6,2) NULL,
        graded TINYINT(1) DEFAULT 0,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (assignment_id) REFERENCES interactive_assignments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($sql)) echo "<p>Table <strong>interactive_submissions</strong> created.</p>";
    else echo "<p style='color:red;'>Error creating interactive_submissions: " . htmlspecialchars($conn->error) . "</p>";
} else {
    echo "<p>Table <strong>interactive_submissions</strong> already exists. Ensuring 'score' has appropriate type...</p>";
    $colType = getColumnType($conn, 'interactive_submissions', 'score');
    if ($colType === null) {
        $sql = "ALTER TABLE interactive_submissions ADD score DECIMAL(6,2) NULL AFTER submitted_at";
        if ($conn->query($sql)) echo "<p>Added column <strong>score</strong> to interactive_submissions.</p>";
    } else {
        // if score exists but not decimal, attempt modify
        if (stripos($colType, 'decimal') === false) {
            $sql = "ALTER TABLE interactive_submissions MODIFY score DECIMAL(6,2) NULL";
            if ($conn->query($sql)) echo "<p>Modified <strong>score</strong> to DECIMAL(6,2).</p>";
        }
    }
}

/* ------------------
   Create interactive_answers
   ------------------ */
if (!tableExists($conn, 'interactive_answers')) {
    $sql = "CREATE TABLE interactive_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        question_id INT NOT NULL,
        option_id INT NULL,
        answer_text TEXT NULL,
        is_correct TINYINT(1) NULL,
        answer_audio VARCHAR(255) NULL,
        answer_media VARCHAR(255) NULL,
        marks_awarded DECIMAL(6,2) NULL,
        FOREIGN KEY (submission_id) REFERENCES interactive_submissions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES interactive_questions(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES interactive_options(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($sql)) echo "<p>Table <strong>interactive_answers</strong> created.</p>";
    else echo "<p style='color:red;'>Error creating interactive_answers: " . htmlspecialchars($conn->error) . "</p>";
} else {
    echo "<p>Table <strong>interactive_answers</strong> already exists. Ensuring columns exist...</p>";
    $wanted = [
        'option_id' => "INT NULL",
        'answer_text' => "TEXT NULL",
        'is_correct' => "TINYINT(1) NULL",
        'answer_audio' => "VARCHAR(255) NULL",
        'answer_media' => "VARCHAR(255) NULL",
        'marks_awarded' => "DECIMAL(6,2) NULL"
    ];
    foreach ($wanted as $col => $type) {
        if (!columnExists($conn, 'interactive_answers', $col)) {
            $sql = "ALTER TABLE interactive_answers ADD $col $type";
            if ($conn->query($sql)) echo "<p>Added column <strong>$col</strong> to interactive_answers.</p>";
            else echo "<p style='color:red;'>Error adding $col: " . htmlspecialchars($conn->error) . "</p>";
        }
    }
}

/* ------------------
   Preserve existing classnotes & recordings tables (already part of your script)
   If they don't exist, create them (safe creation)
   ------------------ */
if (!tableExists($conn, 'recordings')) {
    $sql = "CREATE TABLE recordings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        lecturer_id INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
        FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($sql)) echo "<p>Table <strong>recordings</strong> created.</p>";
}

if (!tableExists($conn, 'classnotes')) {
    $sql = "CREATE TABLE classnotes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_id INT NOT NULL,
        lecturer_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        file_path VARCHAR(255) NULL,
        media_type ENUM('pdf','ppt','excel','video','other','image') DEFAULT 'other',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
        FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($sql)) echo "<p>Table <strong>classnotes</strong> created.</p>";
}

if (!tableExists($conn, 'student_classnotes_progress')) {
    $sql = "CREATE TABLE student_classnotes_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        classnote_id INT NOT NULL,
        status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
        last_accessed TIMESTAMP NULL,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (classnote_id) REFERENCES classnotes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if ($conn->query($sql)) echo "<p>Table <strong>student_classnotes_progress</strong> created.</p>";
}

/* ------------------
   Optional: safe cleanup order for selective deletes (NOT executed automatically)
   This section only prints recommended SQL for clearing non-critical tables while preserving students & lecturers.
   ------------------ */

echo "<h2>Migration complete.</h2>";
echo "<p>The interactive tables have been created/updated. Please review the output above for any warnings.</p>";

echo "<p><strong>Important notes:</strong></p><ul>";
echo "<li>Altering ENUM types can fail if existing values are incompatible. If you see warnings about enum modification, inspect existing rows in <code>interactive_questions.question_type</code> and adjust them manually before re-running.</li>";
echo "<li>Back up your database before running destructive operations. This script avoids drops where possible but will drop the old 'type' column from interactive_questions if it exists.</li>";
echo "<li>File upload handlers and filesystem storage for <code>media_url</code>, <code>file_url</code>, and <code>answer_media</code> must be implemented in your application logic. Database columns only store paths.</li>";
echo "</ul>";

?>