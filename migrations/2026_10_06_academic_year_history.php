<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'department_admin'], true)) {
    http_response_code(403);
    exit('Unauthorized.');
}

header('Content-Type: text/plain; charset=utf-8');

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

echo "Running academic-year history migration\n";

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
    if (!ayh_table_exists($conn, $table)) {
        echo "SKIP {$table}: table does not exist\n";
        continue;
    }

    if (!ayh_column_exists($conn, $table, 'academic_year')) {
        if (!$conn->query("ALTER TABLE `{$table}` ADD COLUMN academic_year VARCHAR(20) NULL")) {
            throw new RuntimeException("Unable to add {$table}.academic_year: " . $conn->error);
        }
        echo "OK added {$table}.academic_year\n";
    }

    if (ayh_column_exists($conn, $table, $dateColumn)) {
        $sql = "
            UPDATE `{$table}`
            SET academic_year = CONCAT(YEAR(`{$dateColumn}`), '/', YEAR(`{$dateColumn}`) + 1)
            WHERE academic_year IS NULL OR academic_year = ''
        ";
        if (!$conn->query($sql)) {
            throw new RuntimeException("Unable to backfill {$table}.academic_year: " . $conn->error);
        }
    }
}

$currentLabel = date('Y') . '/' . (date('Y') + 1);
if (ayh_table_exists($conn, 'academic_year_settings')) {
    $result = $conn->query("
        SELECT academic_year_label
        FROM academic_year_settings
        ORDER BY is_active DESC, updated_at DESC
        LIMIT 1
    ");
    $row = $result ? $result->fetch_assoc() : null;
    if ($row && preg_match('/^\d{4}\/\d{4}$/', (string)$row['academic_year_label'])) {
        $currentLabel = (string)$row['academic_year_label'];
    }
}

$currentStart = (int)substr($currentLabel, 0, 4);
$students = $conn->query("
    SELECT id, year_joined, year_of_study
    FROM students
    WHERE year_joined REGEXP '^[0-9]{4}$'
");
$historyInsert = $conn->prepare("
    INSERT IGNORE INTO student_academic_year_history (student_id, academic_year, year_of_study)
    VALUES (?, ?, ?)
");
while ($student = $students->fetch_assoc()) {
    $joinedYear = (int)$student['year_joined'];
    $latestStudyYear = max(1, (int)$student['year_of_study']);
    $maxStartYear = min($currentStart, $joinedYear + $latestStudyYear - 1);

    for ($startYear = $joinedYear; $startYear <= $maxStartYear; $startYear++) {
        $studentId = (int)$student['id'];
        $studyYear = $startYear - $joinedYear + 1;
        $label = $startYear . '/' . ($startYear + 1);
        $historyInsert->bind_param('isi', $studentId, $label, $studyYear);
        if (!$historyInsert->execute()) {
            throw new RuntimeException('Unable to initialize student year history: ' . $historyInsert->error);
        }
    }
}
$historyInsert->close();

echo "OK initialized academic-year history through {$currentLabel}\n";
