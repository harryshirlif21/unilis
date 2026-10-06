<?php
require_once __DIR__ . '/../includes/academic_year_history.php';

$periods = [
    ['academic_year_label' => '2024/2025', 'start_date' => '2024-09-01', 'end_date' => '2025-08-31'],
    ['academic_year_label' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-08-31'],
];

$assertSame = static function ($expected, $actual, string $case): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
};

$assertSame('2024/2025', academic_year_history_label_from_periods('2025-08-31', $periods), 'End-date boundary');
$assertSame('2025/2026', academic_year_history_label_from_periods('2025-09-01', $periods), 'Start-date boundary');
$assertSame(null, academic_year_history_label_from_periods('2026-09-01', $periods), 'Date outside configured periods');
$assertSame(true, academic_year_history_periods_overlap('2025-08-31', '2025-09-30', '2025-09-01', '2026-08-31'), 'Overlapping periods');
$assertSame(false, academic_year_history_periods_overlap('2025-09-01', '2026-08-31', '2024-09-01', '2025-08-31'), 'Adjacent periods');
$assertSame(null, academic_year_history_validate_period('2025/2026', '2025-09-01', '2026-08-31'), 'Valid progression period');
$assertSame('The academic-year label must start with the calendar year of its start date.', academic_year_history_validate_period('2024/2025', '2025-09-01', '2026-08-31'), 'Mismatched label and start');
$assertSame('Enter a valid academic-year label and date range.', academic_year_history_validate_period('2025/2027', '2025-09-01', '2027-08-31'), 'Invalid academic-year label');
$assertSame(1, academic_year_history_study_year(2025, '2025/2026'), 'First year');
$assertSame(3, academic_year_history_study_year(2023, '2025/2026'), 'Study-year progression');
$assertSame(4, academic_year_history_study_year(2020, '2025/2026', 4), 'Course-duration cap');
$assertSame(1, academic_year_history_study_year(2026, '2025/2026'), 'Registration after academic-year start');

echo "Academic-year progression tests passed.\n";
