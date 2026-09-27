<?php
/**
 * Migration: Results / Transcript feature (Grading Setup, weighted grades, retakes)
 *
 * Adds the schema the Results/Transcript feature needs on top of the existing
 * UNILIS schema. Everything is idempotent (safe to run more than once).
 *
 * NOTES ON MAPPING:
 *  - There is no `student_unit_enrollments` / `assessments` / `assessment_submissions`
 *    / `assessment_weights` table in the live DB. Enrollment actually lives in
 *    `student_units` (student_id, unit_id); the existing assessment code
 *    (lecturer/assessment_builder.php, student/take_assessment.php,
 *    lecturer/ajax/save_weights.php) targets `assessments`, `assessment_submissions`
 *    and `assessment_weights`. So:
 *      - `student_units` is THE enrollment table and gets the columns below.
 *      - `assessments` + `assessment_submissions` are created to match the schema
 *        the existing assessment code already expects (the "newer" system).
 *      - `assessment_weights` is created with a shared (unit_id, assessment_type)
 *        UNIQUE key + a default_total_marks column.
 *      - `unit_grading_settings` holds the per-unit pass threshold.
 *      - `unit_retakes.enrollment_id` references `student_units.id`.
 */

define('MIGRATION_ACCESS', true);
require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/plain; charset=utf-8');

function rtr_col_exists(mysqli $conn, string $table, string $col): bool {
    $c = $conn->real_escape_string($col);
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return $res && $res->num_rows > 0;
}

function rtr_table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");
    return $res && $res->num_rows > 0;
}

echo "=== Results / Transcript migration ===\n";
$ok = true;

/* -------------------------------------------------------------------------
 * 1) Extend `student_units` (the enrollment table) with academic-year,
 *    semester, attempt number and outcome status.
 * ---------------------------------------------------------------------- */
if (rtr_table_exists($conn, 'student_units')) {
    $suCols = [
        'academic_year'  => "ALTER TABLE student_units ADD COLUMN academic_year VARCHAR(20) AFTER unit_id",
        'semester'       => "ALTER TABLE student_units ADD COLUMN semester INT NULL AFTER academic_year",
        'attempt_number' => "ALTER TABLE student_units ADD COLUMN attempt_number INT NOT NULL DEFAULT 1 AFTER semester",
        'status'         => "ALTER TABLE student_units ADD COLUMN status ENUM('in_progress','passed','failed') NOT NULL DEFAULT 'in_progress' AFTER attempt_number",
    ];
    foreach ($suCols as $col => $sql) {
        if (rtr_col_exists($conn, 'student_units', $col)) { echo "  SKIP  student_units.{$col}\n"; continue; }
        if ($conn->query($sql)) { echo "  OK    added student_units.{$col}\n"; }
        else { $ok = false; echo "  FAIL  student_units.{$col}: " . $conn->error . "\n"; }
    }
    $idx = $conn->query("SHOW INDEX FROM student_units WHERE Key_name = 'idx_su_unit'");
    if ($idx && $idx->num_rows === 0) {

/* -------------------------------------------------------------------------
 * 2) `assessments` — header of the newer assessment system. Matches the
 *    columns used by save_assessment.php / submit_assessment.php.
 * ---------------------------------------------------------------------- */
if (!rtr_table_exists($conn, 'assessments')) {
    $sql = "CREATE TABLE assessments (
        id              INT  NOT NULL AUTO_INCREMENT PRIMARY KEY,
        unit_id         INT  NOT NULL,
        lecturer_id     INT  NULL,
        module_id       INT  NULL,
        lesson_id       INT  NULL,
        title           VARCHAR(255) NOT NULL,
        type            ENUM('quiz','assignment','cat','exam') NOT NULL DEFAULT 'assignment',
        instructions    TEXT NULL,
        time_limit_mins INT  NULL,
        total_marks     INT  NULL,
        pass_mark       DECIMAL(5,2) NULL,
        due_date        DATETIME NULL,
        is_published    TINYINT(1) NOT NULL DEFAULT 0,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_as_unit (unit_id),
        KEY idx_as_lecturer (lecturer_id),
        CONSTRAINT fk_as_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
        CONSTRAINT fk_as_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if ($conn->query($sql)) { echo "  OK    created table assessments\n"; }
    else { $ok = false; echo "  FAIL  assessments: " . $conn->error . "\n"; }
} else {
    echo "  SKIP  assessments already exists\n";
}

/* -------------------------------------------------------------------------
 * 3) `assessment_submissions` — per-student scored submissions for an
 *    assessment. `score` is a percentage (0-100) as written by the existing
 *    student/ajax/submit_assessment.php.
 * ---------------------------------------------------------------------- */
if (!rtr_table_exists($conn, 'assessment_submissions')) {
    $sql = "CREATE TABLE assessment_submissions (
        id             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        assessment_id  INT NOT NULL,
        student_id     INT NOT NULL,
        score          DECIMAL(6,2) NULL,
        status         ENUM('submitted','graded','flagged') NOT NULL DEFAULT 'submitted',
        graded         TINYINT(1) NOT NULL DEFAULT 0,
        violations_json TEXT NULL,
        submitted_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        graded_at      TIMESTAMP NULL,
        UNIQUE KEY uq_assess_student (assessment_id, student_id),
        KEY idx_asub_student (student_id),
        CONSTRAINT fk_asub_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
        CONSTRAINT fk_asub_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if ($conn->query($sql)) { echo "  OK    created table assessment_submissions\n"; }
    else { $ok = false; echo "  FAIL  assessment_submissions: " . $conn->error . "\n"; }
} else {
    echo "  SKIP  assessment_submissions already exists\n";
}

        if ($conn->query("ALTER TABLE student_units ADD INDEX idx_su_unit (unit_id)")) echo "  OK    index student_units.unit_id\n";
    }
} else {
    $ok = false;
    echo "  FAIL  student_units table not found (should exist)\n";
}

/* -------------------------------------------------------------------------
 * 4) `assessment_weights` — per-unit, per-type weighting + a default_total_marks
 *    used ONLY to convert legacy `submissions.marks` (which carry no total) to a
 *    percentage. One row per (unit_id, assessment_type), shared across lecturers.
 * ---------------------------------------------------------------------- */
if (!rtr_table_exists($conn, 'assessment_weights')) {
    $sql = "CREATE TABLE assessment_weights (
        id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        unit_id             INT NOT NULL,
        assessment_type     VARCHAR(32) NOT NULL,
        weight_percent      DECIMAL(5,2) NOT NULL DEFAULT 0,
        default_total_marks INT NULL,
        created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_unit_type (unit_id, assessment_type),
        CONSTRAINT fk_aw_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if ($conn->query($sql)) { echo "  OK    created table assessment_weights\n"; }
    else { $ok = false; echo "  FAIL  assessment_weights: " . $conn->error . "\n"; }
} else {
    // Existing table may still carry the old per-lecturer unique key; enforce the
    // shared (unit_id, assessment_type) uniqueness if it is missing.
    $uq = $conn->query("SHOW INDEX FROM assessment_weights WHERE Key_name = 'uq_unit_type'");
    if (!$uq || $uq->num_rows === 0) {
        $dropOld = null;
        foreach (($conn->query("SHOW INDEX FROM assessment_weights") ?: []) as $ix) {
            if (in_array($ix['Key_name'], ['uq_unit_lec_type', 'PRIMARY'], true)) continue;
            if (substr($ix['Key_name'], 0, 3) === 'uq_') { $dropOld = $ix['Key_name']; }
        }
        if ($dropOld) { $conn->query("ALTER TABLE assessment_weights DROP INDEX `$dropOld`"); }
        if ($conn->query("ALTER TABLE assessment_weights ADD UNIQUE KEY uq_unit_type (unit_id, assessment_type)")) {
            echo "  OK    enforced shared UNIQUE (unit_id, assessment_type) on assessment_weights\n";
        } else { $ok = false; echo "  FAIL  unique key on assessment_weights: " . $conn->error . "\n"; }
    } else {
        echo "  SKIP  assessment_weights unique key already present\n";
    }
    if (!rtr_col_exists($conn, 'assessment_weights', 'default_total_marks')) {
        if ($conn->query("ALTER TABLE assessment_weights ADD COLUMN default_total_marks INT NULL AFTER weight_percent")) {
            echo "  OK    added assessment_weights.default_total_marks\n";
        } else { $ok = false; echo "  FAIL  assessment_weights.default_total_marks: " . $conn->error . "\n"; }
    }
}



/* -------------------------------------------------------------------------
 * 5) `unit_grading_settings` — pass threshold per unit.
 * ---------------------------------------------------------------------- */
if (!rtr_table_exists($conn, 'unit_grading_settings')) {
    $sql = "CREATE TABLE unit_grading_settings (
        id                      INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        unit_id                 INT NOT NULL UNIQUE,
        pass_threshold_percent  DECIMAL(5,2) NOT NULL DEFAULT 40.00,
        created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_ugs_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if ($conn->query($sql)) { echo "  OK    created table unit_grading_settings\n"; }
    else { $ok = false; echo "  FAIL  unit_grading_settings: " . $conn->error . "\n"; }
} else {
    echo "  SKIP  unit_grading_settings already exists\n";
}

/* -------------------------------------------------------------------------
 * 6) `unit_retakes` — supplementary exam-style retake within the same
 *    enrollment. enrollment_id -> student_units.id (the enrollment table).
 * ---------------------------------------------------------------------- */
if (!rtr_table_exists($conn, 'unit_retakes')) {
    $sql = "CREATE TABLE unit_retakes (
        id                INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        enrollment_id     INT NOT NULL,
        total_marks       INT NOT NULL,
        marks_obtained    DECIMAL(6,2) NULL,
        status            ENUM('pending','graded') NOT NULL DEFAULT 'pending',
        created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        graded_at         TIMESTAMP NULL,
        UNIQUE KEY uq_retake_enr (enrollment_id),
        CONSTRAINT fk_retake_enr FOREIGN KEY (enrollment_id) REFERENCES student_units(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if ($conn->query($sql)) { echo "  OK    created table unit_retakes\n"; }
    else { $ok = false; echo "  FAIL  unit_retakes: " . $conn->error . "\n"; }
} else {
    echo "  SKIP  unit_retakes already exists\n";
}

if ($ok) {
    echo "\n✓ Migration completed successfully.\n";
} else {
    echo "\n✗ Migration finished WITH ERRORS (see above).\n";
    exit(1);
}
