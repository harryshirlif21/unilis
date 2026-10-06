<?php
session_start();
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
ini_set('zlib.output_compression', '0');
set_time_limit(0);
ignore_user_abort(true);
ob_implicit_flush(true);
while (ob_get_level() > 0) {
    ob_end_flush();
}

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'department_admin'], true)) {
    http_response_code(403);
    exit('Unauthorized.');
}

function ayh_report(string $message): void
{
    echo $message . "\n";
    flush();
}

function ayh_report_fatal_error(): void
{
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    error_log(sprintf(
        'Academic-year history migration fatal error: %s in %s:%d',
        $error['message'],
        $error['file'],
        $error['line']
    ));
    http_response_code(500);
    ayh_report('FAILED: A fatal PHP error interrupted the migration. Check the PHP error log for details.');
}

register_shutdown_function('ayh_report_fatal_error');

function ayh_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function ayh_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

ayh_report('Starting academic-year history migration.');

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/academic_year_history.php';

    ayh_report('Preparing content-history tables.');
    if (!$conn->query("
    CREATE TABLE IF NOT EXISTS learning_content_versions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        content_type VARCHAR(32) NOT NULL,
        content_id INT NOT NULL,
        unit_id INT NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        version_number INT NOT NULL,
        snapshot_json LONGTEXT NOT NULL,
        edited_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_content_version (content_type, content_id, version_number),
        KEY idx_content_year (content_type, content_id, academic_year),
        KEY idx_unit_year (unit_id, academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ")) {
        throw new RuntimeException('Unable to create learning_content_versions: ' . $conn->error);
    }

    if (!$conn->query("
    CREATE TABLE IF NOT EXISTS student_academic_year_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        year_of_study INT NOT NULL,
        recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_academic_year (student_id, academic_year),
        KEY idx_student_history (student_id, academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ")) {
        throw new RuntimeException('Unable to create student_academic_year_history: ' . $conn->error);
    }

    $contentTables = [
        'notes' => 'uploaded_at',
        'classnotes' => 'uploaded_at',
        'assignments' => 'created_at',
        'interactive_assignments' => 'created_at',
        'attendance_sessions' => 'created_at',
    ];

    foreach ($contentTables as $table => $dateColumn) {
        ayh_report("Checking {$table}.");
        if (!ayh_table_exists($conn, $table)) {
            ayh_report("SKIP {$table}: table does not exist.");
            continue;
        }

        if (!ayh_column_exists($conn, $table, 'academic_year')) {
            if (!$conn->query("ALTER TABLE `{$table}` ADD COLUMN academic_year VARCHAR(20) NULL")) {
                throw new RuntimeException("Unable to add {$table}.academic_year: " . $conn->error);
            }
            ayh_report("Added {$table}.academic_year.");
        }

        if (!ayh_column_exists($conn, $table, $dateColumn)) {
            ayh_report("SKIP {$table}: date column {$dateColumn} does not exist.");
            continue;
        }
        if (!ayh_column_exists($conn, $table, 'id')) {
            ayh_report("SKIP {$table}: primary-key column id does not exist.");
            continue;
        }

        $yearForDate = "CONCAT(YEAR(`{$dateColumn}`), '/', YEAR(`{$dateColumn}`) + 1)";
        if (ayh_table_exists($conn, 'academic_year_settings')) {
            $yearForDate = "COALESCE((
                SELECT ays.academic_year_label
                FROM academic_year_settings ays
                WHERE ays.start_date <= DATE(`{$dateColumn}`)
                  AND ays.end_date >= DATE(`{$dateColumn}`)
                  AND ays.academic_year_label REGEXP '^[0-9]{4}/[0-9]{4}$'
                ORDER BY ays.start_date DESC, ays.id DESC
                LIMIT 1
            ), {$yearForDate})";
        }

        $bounds = $conn->query("SELECT COALESCE(MAX(id), 0) AS max_id FROM `{$table}`")->fetch_assoc();
        $maxId = (int)($bounds['max_id'] ?? 0);
        $batchSize = 5000;
        for ($startId = 0; $startId < $maxId; $startId += $batchSize) {
            $endId = min($startId + $batchSize, $maxId);
            $sql = "
                UPDATE `{$table}`
                SET academic_year = {$yearForDate}
                WHERE id > {$startId}
                  AND id <= {$endId}
                  AND `{$dateColumn}` IS NOT NULL
                  AND NOT (academic_year <=> {$yearForDate})
            ";
            if (!$conn->query($sql)) {
                throw new RuntimeException("Unable to backfill {$table}.academic_year (IDs {$startId}-{$endId}): " . $conn->error);
            }
            ayh_report("Backfilled {$table} records through ID {$endId} of {$maxId}.");
        }
        ayh_report("Completed {$table}.");
    }

    ayh_report('Building student academic-year history.');
    $currentLabel = academic_year_history_current_label($conn);
    $today = date('Y-m-d');
    $academicYears = [];
    if (ayh_table_exists($conn, 'academic_year_settings')) {
        $periodStmt = $conn->prepare("
            SELECT academic_year_label, start_date, end_date
            FROM academic_year_settings
            WHERE start_date <= ?
              AND academic_year_label REGEXP '^[0-9]{4}/[0-9]{4}$'
            ORDER BY start_date ASC, id ASC
        ");
        $periodStmt->bind_param('s', $today);
        $periodStmt->execute();
        $periodResult = $periodStmt->get_result();
        while ($period = $periodResult->fetch_assoc()) {
            $academicYears[(string)$period['academic_year_label']] = $period;
        }
        $periodStmt->close();
    }

    $currentStart = (int)substr($currentLabel, 0, 4);
    $students = $conn->query("
        SELECT s.id, s.year_joined, s.year_of_study, c.duration
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        WHERE s.year_joined REGEXP '^[0-9]{4}$'
    ");
    $hasConfiguredAcademicYears = $academicYears !== [];
    $historyInsert = $conn->prepare("
        INSERT INTO student_academic_year_history (student_id, academic_year, year_of_study)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE year_of_study = VALUES(year_of_study)
    ");
    $studentCount = 0;
    while ($student = $students->fetch_assoc()) {
        $joinedYear = (int)$student['year_joined'];
        $latestStudyYear = max(1, (int)$student['year_of_study']);
        $duration = (int)($student['duration'] ?? 0);
        if ($duration > 0) {
            $latestStudyYear = min($latestStudyYear, $duration);
        }
        $maxStartYear = min($currentStart, $joinedYear + $latestStudyYear - 1);

        $studentAcademicYears = $academicYears;
        if (!$hasConfiguredAcademicYears) {
            for ($startYear = $joinedYear; $startYear <= $maxStartYear; $startYear++) {
                $label = academic_year_history_label($startYear);
                $studentAcademicYears[$label] = ['academic_year_label' => $label];
            }
        } else {
            for ($startYear = $joinedYear; $startYear <= $maxStartYear; $startYear++) {
                $label = academic_year_history_label($startYear);
                if (!isset($studentAcademicYears[$label])) {
                    $studentAcademicYears[$label] = ['academic_year_label' => $label];
                }
            }
        }

        foreach ($studentAcademicYears as $label => $period) {
            $startYear = academic_year_history_start_year((string)$label);
            if ($startYear === null || $startYear < $joinedYear || $startYear > $maxStartYear) {
                continue;
            }
            $studentId = (int)$student['id'];
            $studyYear = academic_year_history_study_year($joinedYear, (string)$label, $duration);
            $historyInsert->bind_param('isi', $studentId, $label, $studyYear);
            if (!$historyInsert->execute()) {
                throw new RuntimeException('Unable to initialize student year history: ' . $historyInsert->error);
            }
        }
        $studentCount++;
        if ($studentCount % 500 === 0) {
            ayh_report("Processed {$studentCount} students.");
        }
    }
    $historyInsert->close();
    $students->free();

    ayh_report("Processed {$studentCount} students.");
    ayh_report("SUCCESS: Academic-year history migration completed through {$currentLabel}.");
} catch (Throwable $e) {
    error_log(sprintf(
        'Academic-year history migration failed: %s in %s:%d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    http_response_code(500);
    ayh_report('FAILED: ' . $e->getMessage());
}
