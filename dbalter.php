<?php
/**
 * dbalter.php
 *
 * Display units table for course ID 7 (B.Sc. Business Computing) and the units to be added.
 * Safe to run repeatedly; it does not modify data unless insert action is triggered.
 */

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available.');
}

function isCli(): bool
{
    return php_sapi_name() === 'cli';
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getCourseNameById(mysqli $conn, int $courseId): ?string
{
    $stmt = $conn->prepare('SELECT name FROM courses WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $row['name'] ?? null;
}

function getExistingUnits(mysqli $conn, int $courseId): array
{
    $units = [];
    $stmt = $conn->prepare('SELECT id, name, code, year, semester FROM units WHERE course_id = ? ORDER BY year, semester, code');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    $stmt->close();
    return $units;
}

function getBscBusinessComputingUnits(): array
{
    return [
        ['BBC 2101', 'Business Studies for I.T.', 1, 1],
        ['BIT 2103', 'Introduction to Computer Applications', 1, 1],
        ['BIT 2104', 'Introduction to Programming', 1, 1],
        ['CILS 2101', 'Communication and Information Literacy Skills', 1, 1],
        ['HBC 2128', 'Introduction to Accounting 1', 1, 1],
        ['HBC 2215', 'Essentials of Economics', 1, 1],
        ['ICS 2109', 'Computer Operating Systems', 1, 1],
        ['SMA 2104', 'Mathematics for Sciences', 1, 1],
        ['SZL 2111', 'HIV/AIDS', 1, 1],
        ['BBC 2102', 'Computer Networks, Design and Management', 1, 2],
        ['BBC 2103', 'Hardware Systems Support and Maintenance', 1, 2],
        ['BIT 2112', 'Systems Analysis and Design', 1, 2],
        ['HBC 2107', 'Introduction to Accounting II', 1, 2],
        ['HRD 2102', 'Development Studies and Social Ethics', 1, 2],
        ['ICS 2206', 'Introduction to Database Management Systems', 1, 2],
        ['SDS 2107', 'Algebra for Data Science', 1, 2],
        ['STA 2100', 'Probability and Statistics I', 1, 2],
        ['BBC 2201', 'Enterprise Network Systems Administration and Management', 2, 1],
        ['BBC 2202', 'Web Development Fundamentals', 2, 1],
        ['BIT 2214', 'Object-Oriented Analysis and Design', 2, 1],
        ['BIT 2223', 'Mobile and Wireless Computing', 2, 1],
        ['HPS 2301', 'Operations Management', 2, 1],
        ['HSC 2408', 'Innovation and Technology Transfer', 2, 1],
        ['ICS 2105', 'Data Structures and Algorithms', 2, 1],
        ['SMA 2101', 'Calculus I', 2, 1],
        ['BBC 2203', 'Software Engineering', 2, 2],
        ['BBC 2204', 'Object-Oriented Programming', 2, 2],
        ['BBC 2205', 'Cloud and Edge Computing', 2, 2],
        ['BBC 2206', 'Introduction to Data Science', 2, 2],
        ['BBC 2207', 'Design Thinking', 2, 2],
        ['BBC 2208', 'Business Computing Project in Industry', 2, 2],
        ['HBC 2202', 'Introduction to Financial Management', 2, 2],
        ['SMA 2100', 'Discrete Mathematics', 2, 2],
        ['SMA 2102', 'Calculus II', 2, 2],
        ['BBC 2301', 'Enterprise Web Application Development', 3, 1],
        ['BBC 2302', 'Principles of Data Analytics', 3, 1],
        ['BBC 2303', 'Enterprise Resource Planning Systems', 3, 1],
        ['BBC 2304', 'Computer Graphics and Multimedia', 3, 1],
        ['BIT 2319', 'Artificial Intelligence', 3, 1],
        ['ICS 2301', 'Design and Analysis of Algorithms', 3, 1],
        ['ICS 2404', 'Advanced Database Management System', 3, 1],
        ['STA 2200', 'Probability and Statistics II', 3, 1],
        ['BBC 2305', 'Machine Learning', 3, 2],
        ['BBC 2306', 'Information Analysis and Visualization', 3, 2],
        ['BBC 2307', 'Statistical Computing', 3, 2],
        ['BIT 2122', 'Industrial Attachment', 3, 2],
        ['BIT 2215', 'Software Project Management', 3, 2],
        ['BIT 2301', 'Research Methodology', 3, 2],
        ['BIT 2305', 'Human Computer Interactions', 3, 2],
        ['BIT 2317', 'Fundamentals of Computer Security', 3, 2],
        ['BIT 2320', 'Mobile Application Development', 3, 2],
        ['BBC 2401', 'Software Architectures', 4, 1],
        ['BBC 2402', 'Embedded Systems and Internet of Things (IoT)', 4, 1],
        ['BBC 2403', 'Digital Marketing Communication', 4, 1],
        ['BBC 2404', 'Deep Learning', 4, 1],
        ['BBC 2405', 'Business Data Mining and Warehousing', 4, 1],
        ['BBC 2406', 'Software Development Project', 4, 1],
        ['BBC 2407', 'Animation and Augmented Reality', 4, 1],
        ['BIT 2318', 'Information System Audit', 4, 1],
        ['BBC 2408', 'Business Decision Support Systems', 4, 2],
        ['BBC 2409', 'Text Mining and Web Analytics', 4, 2],
        ['BBC 2410', 'Business Analysis and Process Modeling', 4, 2],
        ['BIT 2313', 'Professional Issues in ICT', 4, 2],
        ['BIT 2403', 'Business Development and Entrepreneurship', 4, 2],
        ['HBC 2209', 'Organizational Behaviour', 4, 2],
        ['HBC 2401', 'Management Accounting', 4, 2],
    ];
}

function insertUnit(mysqli $conn, int $courseId, string $code, string $name, int $year, int $semester): string
{
    $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->bind_param('is', $courseId, $code);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return 'skipped';
    }
    $stmt->close();

    $ins = $conn->prepare('INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)');
    $ins->bind_param('ssiii', $name, $code, $courseId, $year, $semester);
    $ins->execute();
    $ok = $ins->affected_rows > 0;
    $ins->close();
    return $ok ? 'inserted' : 'failed';
}

function insertBscBusinessComputingUnits(mysqli $conn, int $courseId): array
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) {
        return ['error' => 'Course id ' . $courseId . ' was not found.'];
    }

    $summary = [
        'course_id' => $courseId,
        'course_name' => $courseName,
        'inserted' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    foreach (getBscBusinessComputingUnits() as [$code, $name, $year, $semester]) {
        $result = insertUnit($conn, $courseId, $code, $name, $year, $semester);
        $summary[$result]++;
    }

    return $summary;
}

function getBscBusinessComputingUnitCodes(): array
{
    return array_column(getBscBusinessComputingUnits(), 0);
}

function moveUnitToCourse(mysqli $conn, int $sourceCourseId, int $targetCourseId, string $code): string
{
    $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->bind_param('is', $sourceCourseId, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $sourceUnit = $result->fetch_assoc();
    $stmt->close();

    if (!$sourceUnit) {
        return 'missing';
    }

    $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->bind_param('is', $targetCourseId, $code);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return 'duplicate';
    }
    $stmt->close();

    $update = $conn->prepare('UPDATE units SET course_id = ? WHERE course_id = ? AND code = ?');
    $update->bind_param('iis', $targetCourseId, $sourceCourseId, $code);
    $update->execute();
    $moved = $update->affected_rows > 0;
    $update->close();

    return $moved ? 'moved' : 'failed';
}

function moveBscBusinessComputingUnitsToCourse(mysqli $conn, int $sourceCourseId, int $targetCourseId): array
{
    $sourceName = getCourseNameById($conn, $sourceCourseId);
    $targetName = getCourseNameById($conn, $targetCourseId);
    if ($sourceName === null || $targetName === null) {
        return ['error' => 'Source or target course id not found.'];
    }

    $summary = [
        'source_course_id' => $sourceCourseId,
        'source_course_name' => $sourceName,
        'target_course_id' => $targetCourseId,
        'target_course_name' => $targetName,
        'moved' => 0,
        'duplicate' => 0,
        'missing' => 0,
        'failed' => 0,
    ];

    foreach (getBscBusinessComputingUnitCodes() as $code) {
        $result = moveUnitToCourse($conn, $sourceCourseId, $targetCourseId, $code);
        $summary[$result]++;
    }

    return $summary;
}

function isInsertAction(): bool
{
    if (isCli()) {
        global $argv;
        return in_array('--insert-bsc-business-computing', $argv, true) || in_array('-i', $argv, true);
    }

    return ($_REQUEST['action'] ?? '') === 'insert_bsc_business_computing';
}

function isMoveAction(): bool
{
    if (isCli()) {
        global $argv;
        return in_array('--move-bsc-business-computing', $argv, true) || in_array('-m', $argv, true);
    }

    return ($_REQUEST['action'] ?? '') === 'move_units';
}

function renderUnitsInfoText(mysqli $conn, int $courseId): void
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) {
        echo "Course ID {$courseId} not found.\n";
        return;
    }

    echo "Course: {$courseName} (ID: {$courseId})\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

    $existingUnits = getExistingUnits($conn, $courseId);
    echo "Existing Units in Database:\n";
    if (empty($existingUnits)) {
        echo "(No units found for this course)\n";
    } else {
        echo "ID | Code     | Year | Sem | Name\n";
        echo str_repeat('-', 80) . "\n";
        foreach ($existingUnits as $unit) {
            printf("%-2s | %-8s | %-4s | %-3s | %s\n", $unit['id'], $unit['code'], $unit['year'], $unit['semester'], $unit['name']);
        }
    }
    echo "\n";

    $unitsToAdd = getBscBusinessComputingUnits();
    echo "Units to be Added:\n";
    echo "Code     | Year | Sem | Name\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($unitsToAdd as [$code, $name, $year, $semester]) {
        printf("%-8s | %-4s | %-3s | %s\n", $code, $year, $semester, $name);
    }
    echo "\n";
}

function renderUnitsInfoHtml(mysqli $conn, int $courseId): string
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) {
        return '<h1>Course Not Found</h1><p>Course ID ' . escape((string)$courseId) . ' was not found.</p>';
    }

    $html = '<h1>Units for ' . escape($courseName) . ' (ID: ' . escape((string)$courseId) . ')</h1>';
    $html .= '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';

    $existingUnits = getExistingUnits($conn, $courseId);
    $html .= '<section><h2>Existing Units in Database</h2>';
    if (empty($existingUnits)) {
        $html .= '<p>(No units found for this course)</p>';
    } else {
        $html .= '<table><thead><tr><th>ID</th><th>Code</th><th>Year</th><th>Semester</th><th>Name</th></tr></thead><tbody>';
        foreach ($existingUnits as $unit) {
            $html .= '<tr>' .
                '<td>' . escape((string)$unit['id']) . '</td>' .
                '<td>' . escape($unit['code']) . '</td>' .
                '<td>' . escape((string)$unit['year']) . '</td>' .
                '<td>' . escape((string)$unit['semester']) . '</td>' .
                '<td>' . escape($unit['name']) . '</td>' .
            '</tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</section>';

    $unitsToAdd = getBscBusinessComputingUnits();
    $html .= '<section><h2>Units to be Added</h2>';
    $html .= '<table><thead><tr><th>Code</th><th>Year</th><th>Semester</th><th>Name</th></tr></thead><tbody>';
    foreach ($unitsToAdd as [$code, $name, $year, $semester]) {
        $html .= '<tr>' .
            '<td>' . escape($code) . '</td>' .
            '<td>' . escape((string)$year) . '</td>' .
            '<td>' . escape((string)$semester) . '</td>' .
            '<td>' . escape($name) . '</td>' .
        '</tr>';
    }
    $html .= '</tbody></table></section>';

    $html .= '<section><h2>Actions</h2>';

    $html .= '<div style="margin-bottom:16px;">' .
        '<h3 style="margin:0 0 6px;">Insert Units into Course ID 7</h3>' .
        '<p style="margin:0 0 10px;">Inserts all B.Sc. Business Computing units directly into course ID 7. Safe to run repeatedly — existing units are skipped.</p>' .
        '<form method="post">' .
        '<button type="submit" name="action" value="insert_bsc_business_computing" style="background:#2563eb;color:#fff;padding:10px 24px;border:none;border-radius:6px;font-size:15px;cursor:pointer;">&#10003; Insert Units into Course ID 7</button>' .
        '</form>' .
        '</div>';

    $html .= '<div>' .
        '<h3 style="margin:0 0 6px;">Move Units from Course ID 6 → 7</h3>' .
        '<p style="margin:0 0 10px;">Moves units with B.Sc. Business Computing codes from course ID 6 to course ID 7. Only use if units are mistakenly assigned to course 6.</p>' .
        '<form method="post">' .
        '<button type="submit" name="action" value="move_units" style="background:#dc2626;color:#fff;padding:10px 24px;border:none;border-radius:6px;font-size:15px;cursor:pointer;">&#8594; Move Units to Course ID 7</button>' .
        '</form>' .
        '</div>';

    $html .= '</section>';

    return $html;
}

function renderActionResultText(array $summary): void
{
    if (isset($summary['error'])) {
        echo "ERROR: " . $summary['error'] . "\n";
        return;
    }

    echo "Course ID: {$summary['course_id']}\n";
    echo "Course name: {$summary['course_name']}\n";
    echo "Inserted units: {$summary['inserted']}\n";
    echo "Skipped units: {$summary['skipped']}\n";
    echo "Failed units: {$summary['failed']}\n";
}

function renderActionResultHtml(array $summary): string
{
    if (isset($summary['error'])) {
        return '<h1>Insert Units Failed</h1><p>' . escape($summary['error']) . '</p>' .
            '<p><a href="dbalter.php" style="color:#2563eb;">&#8592; Back</a></p>';
    }

    $allSkipped = $summary['inserted'] === 0 && $summary['skipped'] > 0;
    $status = $allSkipped
        ? '<p style="color:#b45309;background:#fef9c3;padding:10px;border-radius:6px;">&#9888; All units already exist — nothing new was inserted.</p>'
        : '<p style="color:#166534;background:#dcfce7;padding:10px;border-radius:6px;">&#10003; Units inserted successfully.</p>';

    return '<h1>Insert Units Result</h1>' .
        $status .
        '<p><strong>Course ID:</strong> ' . escape((string)$summary['course_id']) . '<br>' .
        '<strong>Course name:</strong> ' . escape($summary['course_name']) . '<br>' .
        '<strong>Inserted:</strong> ' . escape((string)$summary['inserted']) . '<br>' .
        '<strong>Skipped (already exist):</strong> ' . escape((string)$summary['skipped']) . '<br>' .
        '<strong>Failed:</strong> ' . escape((string)$summary['failed']) . '</p>' .
        '<p><a href="dbalter.php" style="color:#2563eb;">&#8592; Back</a></p>';
}

function renderMoveResultText(array $summary): void
{
    if (isset($summary['error'])) {
        echo "ERROR: " . $summary['error'] . "\n";
        return;
    }

    echo "Source course: {$summary['source_course_name']} (ID: {$summary['source_course_id']})\n";
    echo "Target course: {$summary['target_course_name']} (ID: {$summary['target_course_id']})\n";
    echo "Moved units: {$summary['moved']}\n";
    echo "Duplicates skipped: {$summary['duplicate']}\n";
    echo "Missing from source: {$summary['missing']}\n";
    echo "Failed moves: {$summary['failed']}\n";
}

function renderMoveResultHtml(array $summary): string
{
    if (isset($summary['error'])) {
        return '<h1>Move Units Failed</h1><p>' . escape($summary['error']) . '</p>' .
            '<p><a href="dbalter.php" style="color:#2563eb;">&#8592; Back</a></p>';
    }

    return '<h1>Move Units Result</h1>' .
        '<p><strong>Source course:</strong> ' . escape($summary['source_course_name']) . ' (ID: ' . escape((string)$summary['source_course_id']) . ')<br>' .
        '<strong>Target course:</strong> ' . escape($summary['target_course_name']) . ' (ID: ' . escape((string)$summary['target_course_id']) . ')<br>' .
        '<strong>Moved:</strong> ' . escape((string)$summary['moved']) . '<br>' .
        '<strong>Duplicates skipped:</strong> ' . escape((string)$summary['duplicate']) . '<br>' .
        '<strong>Missing from source:</strong> ' . escape((string)$summary['missing']) . '<br>' .
        '<strong>Failed:</strong> ' . escape((string)$summary['failed']) . '</p>' .
        '<p><a href="dbalter.php" style="color:#2563eb;">&#8592; Back</a></p>';
}

function outputHtml(string $html): void
{
    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<title>Units Inspector</title>';
    echo '<style>';
    echo 'body{font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;color:#111;margin:0;padding:24px;}';
    echo 'h1,h2,h3{margin:0 0 12px;}';
    echo 'section{margin-bottom:32px;padding:16px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.05);}';
    echo 'table{width:100%;border-collapse:collapse;margin-top:12px;}';
    echo 'th,td{border:1px solid #d6d8db;padding:8px;text-align:left;vertical-align:top;}';
    echo 'th{background:#f0f2f5;font-weight:600;}';
    echo 'p{margin:.5em 0;}';
    echo 'a{color:#2563eb;}';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo $html;
    echo '</body>';
    echo '</html>';
}

$courseId = 7;
$sourceCourseId = 6;
$targetCourseId = 7;

if (isMoveAction()) {
    $result = moveBscBusinessComputingUnitsToCourse($conn, $sourceCourseId, $targetCourseId);
    if (isCli()) {
        renderMoveResultText($result);
    } else {
        outputHtml(renderMoveResultHtml($result));
    }
    exit;
}

if (isInsertAction()) {
    $result = insertBscBusinessComputingUnits($conn, $courseId);
    if (isCli()) {
        renderActionResultText($result);
    } else {
        outputHtml(renderActionResultHtml($result));
    }
    exit;
}

if (isCli()) {
    renderUnitsInfoText($conn, $courseId);
} else {
    outputHtml(renderUnitsInfoHtml($conn, $courseId));
}