<?php

function academic_year_history_current_label(mysqli $conn): string
{
    $table = $conn->query("SHOW TABLES LIKE 'academic_year_settings'");
    if ($table && $table->num_rows > 0) {
        $result = $conn->query("
            SELECT academic_year_label, start_date, end_date
            FROM academic_year_settings
            WHERE academic_year_label REGEXP '^[0-9]{4}/[0-9]{4}$'
            ORDER BY start_date DESC, id DESC
        ");
        $periods = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $today = date('Y-m-d');
        $label = academic_year_history_label_from_periods($today, $periods);
        if ($label !== null) {
            return $label;
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

function academic_year_history_label_from_periods(string $date, array $periods): ?string
{
    $date = substr($date, 0, 10);
    foreach ($periods as $period) {
        $label = (string)($period['academic_year_label'] ?? '');
        $startDate = (string)($period['start_date'] ?? '');
        $endDate = (string)($period['end_date'] ?? '');
        if (
            preg_match('/^\d{4}\/\d{4}$/', $label)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)
            && $startDate <= $date
            && $endDate >= $date
        ) {
            return $label;
        }
    }

    return null;
}

function academic_year_history_validate_period(string $label, string $startDate, string $endDate): ?string
{
    $startYear = academic_year_history_start_year($label);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    if (
        $startYear === null
        || !$start
        || $start->format('Y-m-d') !== $startDate
        || !$end
        || $end->format('Y-m-d') !== $endDate
        || $startDate > $endDate
    ) {
        return 'Enter a valid academic-year label and date range.';
    }
    if ((int)$start->format('Y') !== $startYear) {
        return 'The academic-year label must start with the calendar year of its start date.';
    }
    if ((int)$end->format('Y') > $startYear + 1) {
        return 'The academic-year end date cannot extend beyond the following calendar year.';
    }

    return null;
}

function academic_year_history_periods_overlap(
    string $startDate,
    string $endDate,
    string $existingStartDate,
    string $existingEndDate
): bool {
    return $startDate <= $existingEndDate && $endDate >= $existingStartDate;
}

function academic_year_history_study_year(int $registeredYear, string $academicYearLabel, int $courseDuration = 0): int
{
    $startYear = academic_year_history_start_year($academicYearLabel);
    if ($startYear === null || $registeredYear < 1900 || $registeredYear > $startYear) {
        return 1;
    }

    $studyYear = $startYear - $registeredYear + 1;
    return $courseDuration > 0 ? min($studyYear, $courseDuration) : $studyYear;
}

function academic_year_history_label_for_date(mysqli $conn, string $date): string
{
    $date = substr($date, 0, 10);
    $table = $conn->query("SHOW TABLES LIKE 'academic_year_settings'");
    if ($table && $table->num_rows > 0) {
        $result = $conn->query("
            SELECT academic_year_label, start_date, end_date
            FROM academic_year_settings
            ORDER BY start_date DESC, id DESC
        ");
        $periods = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $label = academic_year_history_label_from_periods($date, $periods);
        if ($label !== null) {
            return $label;
        }
    }

    $year = (int)substr($date, 0, 4);
    return academic_year_history_label($year > 0 ? $year : (int)date('Y'));
}

function academic_year_history_student_options(array $student, string $currentLabel, ?mysqli $conn = null): array
{
    $currentStart = academic_year_history_start_year($currentLabel);
    $joinedYear = (int)($student['year_joined'] ?? 0);
    $currentStudyYear = max(1, (int)($student['year_of_study'] ?? 1));

    if ($conn !== null) {
        $historyTable = $conn->query("SHOW TABLES LIKE 'student_academic_year_history'");
        if ($historyTable && $historyTable->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT academic_year, year_of_study
                FROM student_academic_year_history
                WHERE student_id = ? AND year_of_study <= ?
                ORDER BY academic_year DESC
            ");
            $studentId = (int)($student['id'] ?? 0);
            $stmt->bind_param('ii', $studentId, $currentStudyYear);
            $stmt->execute();
            $result = $stmt->get_result();
            $historyOptions = [];
            while ($row = $result->fetch_assoc()) {
                $yearStart = academic_year_history_start_year((string)$row['academic_year']);
                if ($yearStart !== null && $yearStart <= (int)$currentStart) {
                    $historyOptions[(string)$row['academic_year']] = (int)$row['year_of_study'];
                }
            }
            $stmt->close();
            if ($historyOptions !== []) {
                $historyOptions[$currentLabel] = max($currentStudyYear, $historyOptions[$currentLabel] ?? 1);
                krsort($historyOptions, SORT_STRING);
                return $historyOptions;
            }
        }
    }

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

    $options[$currentLabel] = max($currentStudyYear, $options[$currentLabel] ?? 1);
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
