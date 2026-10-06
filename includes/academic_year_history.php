<?php

function academic_year_history_current_label(mysqli $conn): string
{
    $table = $conn->query("SHOW TABLES LIKE 'academic_year_settings'");
    if ($table && $table->num_rows > 0) {
        $result = $conn->query("
            SELECT academic_year_label
            FROM academic_year_settings
            ORDER BY is_active DESC, updated_at DESC
            LIMIT 1
        ");
        $row = $result ? $result->fetch_assoc() : null;
        if ($row && preg_match('/^\d{4}\/\d{4}$/', (string)$row['academic_year_label'])) {
            return (string)$row['academic_year_label'];
        }
    }

    $year = (int)date('Y');
    return $year . '/' . ($year + 1);
}

function academic_year_history_start_year(string $label): ?int
{
    if (!preg_match('/^(\d{4})\/(\d{4})$/', $label, $matches)) {
        return null;
    }

    $start = (int)$matches[1];
    return (int)$matches[2] === $start + 1 ? $start : null;
}

function academic_year_history_label(int $startYear): string
{
    return $startYear . '/' . ($startYear + 1);
}

function academic_year_history_student_options(array $student, string $currentLabel): array
{
    $currentStart = academic_year_history_start_year($currentLabel);
    $joinedYear = (int)($student['year_joined'] ?? 0);
    $currentStudyYear = max(1, (int)($student['year_of_study'] ?? 1));

    if (!$currentStart || $joinedYear < 1900 || $joinedYear > $currentStart) {
        return [$currentLabel => $currentStudyYear];
    }

    $options = [];
    for ($startYear = $joinedYear; $startYear <= $currentStart; $startYear++) {
        $studyYear = $startYear - $joinedYear + 1;
        if ($studyYear <= $currentStudyYear) {
            $options[academic_year_history_label($startYear)] = $studyYear;
        }
    }

    $options[$currentLabel] = $currentStudyYear;
    krsort($options, SORT_STRING);
    return $options;
}

function academic_year_history_revision(
    mysqli $conn,
    string $contentType,
    int $contentId,
    int $unitId,
    string $academicYear,
    int $editedBy,
    array $snapshot
): void {
    $stmt = $conn->prepare("
        INSERT INTO learning_content_versions
            (content_type, content_id, unit_id, academic_year, version_number, snapshot_json, edited_by)
        VALUES (
            ?, ?, ?, ?,
            (
                SELECT COALESCE(MAX(version_number), 0) + 1
                FROM learning_content_versions
                WHERE content_type = ? AND content_id = ?
            ),
            ?, ?
        )
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare content revision: ' . $conn->error);
    }

    $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode content revision.');
    }
    $stmt->bind_param(
        'siissisi',
        $contentType,
        $contentId,
        $unitId,
        $academicYear,
        $contentType,
        $contentId,
        $json,
        $editedBy
    );
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to save content revision: ' . $error);
    }
    $stmt->close();
}
