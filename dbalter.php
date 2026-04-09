<?php
/**
 * dbalter.php
 *
 * Manage units for B.Sc. Business Computing (course ID 7).
 * Displays the courses table, existing units, and provides action buttons.
 * Safe to view repeatedly; data is only modified when an action button is clicked.
 */

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available.');
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function isCli(): bool
{
    return php_sapi_name() === 'cli';
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Data fetchers ───────────────────────────────────────────────────────────

function getAllCourses(mysqli $conn): array
{
    $courses = [];
    $result = $conn->query('SELECT id, name, department_id, duration FROM courses ORDER BY id');
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    return $courses;
}

function getUnitCountByCourse(mysqli $conn): array
{
    $counts = [];
    $result = $conn->query('SELECT course_id, COUNT(*) as total FROM units GROUP BY course_id');
    while ($row = $result->fetch_assoc()) {
        $counts[(int)$row['course_id']] = (int)$row['total'];
    }
    return $counts;
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

// ─── B.Sc. Business Computing unit list ─────────────────────────────────────

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
        ['HBC 2209', 'Organisational Behaviour', 4, 2],
        ['HBC 2401', 'Management Accounting', 4, 2],
    ];
}

function getBscBusinessComputingUnitCodes(): array
{
    return array_column(getBscBusinessComputingUnits(), 0);
}

// ─── Actions ─────────────────────────────────────────────────────────────────

/**
 * Undo: delete any units in course $courseId whose id > $maxLegitId.
 * Those rows were inserted by mistake and don't belong to this course.
 */
function removeWronglyInsertedUnits(mysqli $conn, int $courseId, int $maxLegitId): array
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) {
        return ['error' => "Course ID {$courseId} not found."];
    }

    $stmt = $conn->prepare('DELETE FROM units WHERE course_id = ? AND id > ?');
    $stmt->bind_param('ii', $courseId, $maxLegitId);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    return [
        'course_id'   => $courseId,
        'course_name' => $courseName,
        'deleted'     => $deleted,
        'message'     => $deleted > 0
            ? "{$deleted} wrongly inserted unit(s) removed from \"{$courseName}\"."
            : "Nothing to undo — no units with ID > {$maxLegitId} found in \"{$courseName}\".",
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
        return ['error' => "Course ID {$courseId} not found."];
    }
    $summary = ['course_id' => $courseId, 'course_name' => $courseName, 'inserted' => 0, 'skipped' => 0, 'failed' => 0];
    foreach (getBscBusinessComputingUnits() as [$code, $name, $year, $semester]) {
        $summary[insertUnit($conn, $courseId, $code, $name, $year, $semester)]++;
    }
    return $summary;
}

function moveUnitToCourse(mysqli $conn, int $from, int $to, string $code): string
{
    $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->bind_param('is', $from, $code);
    $stmt->execute();
    $sourceUnit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$sourceUnit) return 'missing';

    $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->bind_param('is', $to, $code);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    if ($exists) return 'duplicate';

    $upd = $conn->prepare('UPDATE units SET course_id = ? WHERE course_id = ? AND code = ?');
    $upd->bind_param('iis', $to, $from, $code);
    $upd->execute();
    $moved = $upd->affected_rows > 0;
    $upd->close();
    return $moved ? 'moved' : 'failed';
}

function moveBscBusinessComputingUnitsToCourse(mysqli $conn, int $from, int $to): array
{
    $fromName = getCourseNameById($conn, $from);
    $toName   = getCourseNameById($conn, $to);
    if (!$fromName || !$toName) return ['error' => 'Source or target course not found.'];

    $summary = [
        'source_course_id' => $from, 'source_course_name' => $fromName,
        'target_course_id' => $to,   'target_course_name' => $toName,
        'moved' => 0, 'duplicate' => 0, 'missing' => 0, 'failed' => 0,
    ];
    foreach (getBscBusinessComputingUnitCodes() as $code) {
        $summary[moveUnitToCourse($conn, $from, $to, $code)]++;
    }
    return $summary;
}

// ─── Action detection ────────────────────────────────────────────────────────

function currentAction(): string
{
    if (isCli()) {
        global $argv;
        if (in_array('--undo-course6',                  $argv, true)) return 'undo_course6';
        if (in_array('--insert-bsc-business-computing', $argv, true)) return 'insert_bsc_business_computing';
        if (in_array('--move-bsc-business-computing',   $argv, true)) return 'move_units';
        return '';
    }
    return $_REQUEST['action'] ?? '';
}

// ─── HTML renderers ──────────────────────────────────────────────────────────

function renderCoursesTableHtml(mysqli $conn): string
{
    $courses = getAllCourses($conn);
    $counts  = getUnitCountByCourse($conn);

    $html = '<section><h2>Courses Table</h2>';
    $html .= '<table><thead><tr><th>ID</th><th>Name</th><th>Department ID</th><th>Duration (yrs)</th><th>Unit Count</th></tr></thead><tbody>';
    foreach ($courses as $c) {
        $id        = (int)$c['id'];
        $units     = $counts[$id] ?? 0;
        $highlight = ($id === 6 || $id === 7) ? ' style="background:#fffbeb;"' : '';
        $html .= '<tr' . $highlight . '>' .
            '<td><strong>' . escape((string)$id) . '</strong></td>' .
            '<td>' . escape($c['name']) . '</td>' .
            '<td>' . escape((string)$c['department_id']) . '</td>' .
            '<td>' . escape((string)$c['duration']) . '</td>' .
            '<td><strong>' . $units . '</strong></td>' .
            '</tr>';
    }
    $html .= '</tbody></table></section>';
    return $html;
}

function renderUnitsInfoHtml(mysqli $conn, int $courseId): string
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) {
        return '<h1>Course Not Found</h1><p>Course ID ' . escape((string)$courseId) . ' not found.</p>';
    }

    $html  = '<h1>Units Manager &mdash; ' . escape($courseName) . ' (ID&nbsp;' . escape((string)$courseId) . ')</h1>';
    $html .= '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';

    // Courses table
    $html .= renderCoursesTableHtml($conn);

    // Existing units for course 7
    $existingUnits = getExistingUnits($conn, $courseId);
    $html .= '<section><h2>Current Units in Course ID ' . escape((string)$courseId) . ' (' . count($existingUnits) . ' total)</h2>';
    if (empty($existingUnits)) {
        $html .= '<p style="color:#b45309;">(No units found for this course)</p>';
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

    // Units to be added preview
    $unitsToAdd = getBscBusinessComputingUnits();
    $html .= '<section><h2>Units to be Inserted (preview &mdash; ' . count($unitsToAdd) . ' total)</h2>';
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

    // Actions
    $html .= '<section><h2>Actions</h2>';

    // Undo
    $html .= '<div style="margin-bottom:20px;padding:14px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;">'
        . '<h3 style="margin:0 0 6px;color:#dc2626;">&#8617; Undo &mdash; Remove Wrongly Inserted Units from Course ID 6</h3>'
        . '<p style="margin:0 0 10px;">Deletes units added to <strong>B.Sc. Information Technology (course 6)</strong> by mistake — '
        . 'specifically any unit with ID &gt; 215. The original 65 units (IDs 152&ndash;215) are <strong>not touched</strong>.</p>'
        . '<form method="post">'
        . '<button type="submit" name="action" value="undo_course6" '
        . 'style="background:#dc2626;color:#fff;padding:10px 24px;border:none;border-radius:6px;font-size:15px;cursor:pointer;">'
        . '&#8617; Undo Wrong Inserts on Course ID 6</button>'
        . '</form></div>';

    // Insert
    $html .= '<div style="margin-bottom:20px;padding:14px;background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;">'
        . '<h3 style="margin:0 0 6px;color:#1d4ed8;">&#10003; Insert Units into Course ID 7</h3>'
        . '<p style="margin:0 0 10px;">Inserts all B.Sc. Business Computing units into course ID 7. Safe to run repeatedly — existing units are skipped.</p>'
        . '<form method="post">'
        . '<button type="submit" name="action" value="insert_bsc_business_computing" '
        . 'style="background:#2563eb;color:#fff;padding:10px 24px;border:none;border-radius:6px;font-size:15px;cursor:pointer;">'
        . '&#10003; Insert Units into Course ID 7</button>'
        . '</form></div>';

    // Move
    $html .= '<div style="padding:14px;background:#f9fafb;border:1px solid #d1d5db;border-radius:8px;">'
        . '<h3 style="margin:0 0 6px;">&#8594; Move Units from Course ID 6 &rarr; 7</h3>'
        . '<p style="margin:0 0 10px;">Moves units with B.Sc. Business Computing codes from course 6 to course 7. Only use if units are mistakenly in course 6.</p>'
        . '<form method="post">'
        . '<button type="submit" name="action" value="move_units" '
        . 'style="background:#6b7280;color:#fff;padding:10px 24px;border:none;border-radius:6px;font-size:15px;cursor:pointer;">'
        . '&#8594; Move Units to Course ID 7</button>'
        . '</form></div>';

    $html .= '</section>';
    return $html;
}

function renderUndoResultHtml(array $summary): string
{
    if (isset($summary['error'])) {
        return '<h1>Undo Failed</h1><p style="color:#dc2626;">' . escape($summary['error']) . '</p>'
            . '<p><a href="dbalter.php">&#8592; Back</a></p>';
    }
    $color = $summary['deleted'] > 0 ? '#166534' : '#92400e';
    $bg    = $summary['deleted'] > 0 ? '#dcfce7'  : '#fef9c3';
    $icon  = $summary['deleted'] > 0 ? '&#10003;' : '&#9888;';
    return '<h1>Undo Result</h1>'
        . '<p style="color:' . $color . ';background:' . $bg . ';padding:10px;border-radius:6px;">' . $icon . ' ' . escape($summary['message']) . '</p>'
        . '<p><strong>Course ID:</strong> ' . escape((string)$summary['course_id']) . '<br>'
        . '<strong>Course:</strong> ' . escape($summary['course_name']) . '<br>'
        . '<strong>Units deleted:</strong> ' . escape((string)$summary['deleted']) . '</p>'
        . '<p><a href="dbalter.php">&#8592; Back</a></p>';
}

function renderInsertResultHtml(array $summary): string
{
    if (isset($summary['error'])) {
        return '<h1>Insert Failed</h1><p style="color:#dc2626;">' . escape($summary['error']) . '</p>'
            . '<p><a href="dbalter.php">&#8592; Back</a></p>';
    }
    $allSkipped = $summary['inserted'] === 0 && $summary['skipped'] > 0;
    $status = $allSkipped
        ? '<p style="color:#92400e;background:#fef9c3;padding:10px;border-radius:6px;">&#9888; All units already exist — nothing new inserted.</p>'
        : '<p style="color:#166534;background:#dcfce7;padding:10px;border-radius:6px;">&#10003; Units inserted successfully.</p>';
    return '<h1>Insert Units Result</h1>' . $status
        . '<p><strong>Course ID:</strong> ' . escape((string)$summary['course_id']) . '<br>'
        . '<strong>Course:</strong> ' . escape($summary['course_name']) . '<br>'
        . '<strong>Inserted:</strong> ' . escape((string)$summary['inserted']) . '<br>'
        . '<strong>Skipped (already exist):</strong> ' . escape((string)$summary['skipped']) . '<br>'
        . '<strong>Failed:</strong> ' . escape((string)$summary['failed']) . '</p>'
        . '<p><a href="dbalter.php">&#8592; Back</a></p>';
}

function renderMoveResultHtml(array $summary): string
{
    if (isset($summary['error'])) {
        return '<h1>Move Failed</h1><p style="color:#dc2626;">' . escape($summary['error']) . '</p>'
            . '<p><a href="dbalter.php">&#8592; Back</a></p>';
    }
    return '<h1>Move Units Result</h1>'
        . '<p><strong>From:</strong> ' . escape($summary['source_course_name']) . ' (ID ' . escape((string)$summary['source_course_id']) . ')<br>'
        . '<strong>To:</strong> ' . escape($summary['target_course_name']) . ' (ID ' . escape((string)$summary['target_course_id']) . ')<br>'
        . '<strong>Moved:</strong> ' . escape((string)$summary['moved']) . '<br>'
        . '<strong>Duplicates skipped:</strong> ' . escape((string)$summary['duplicate']) . '<br>'
        . '<strong>Missing from source:</strong> ' . escape((string)$summary['missing']) . '<br>'
        . '<strong>Failed:</strong> ' . escape((string)$summary['failed']) . '</p>'
        . '<p><a href="dbalter.php">&#8592; Back</a></p>';
}

function outputHtml(string $html): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Units Manager</title><style>';
    echo 'body{font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;color:#111;margin:0;padding:24px;max-width:1100px;}';
    echo 'h1,h2,h3{margin:0 0 12px;}';
    echo 'section{margin-bottom:28px;padding:16px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.05);}';
    echo 'table{width:100%;border-collapse:collapse;margin-top:10px;font-size:14px;}';
    echo 'th,td{border:1px solid #d6d8db;padding:7px 10px;text-align:left;vertical-align:top;}';
    echo 'th{background:#f0f2f5;font-weight:600;}';
    echo 'tr:hover td{background:#f8fafc;}';
    echo 'p{margin:.5em 0;}a{color:#2563eb;}';
    echo '</style></head><body>';
    echo $html;
    echo '</body></html>';
}

// ─── Main ────────────────────────────────────────────────────────────────────

$courseId          = 7;
$sourceCourseId    = 6;
$targetCourseId    = 7;
$course6MaxLegitId = 215; // original B.Sc. IT units are IDs 152–215

switch (currentAction()) {

    case 'undo_course6':
        $result = removeWronglyInsertedUnits($conn, $sourceCourseId, $course6MaxLegitId);
        if (isCli()) {
            echo isset($result['error'])
                ? "ERROR: {$result['error']}\n"
                : "{$result['message']}\nDeleted: {$result['deleted']}\n";
        } else {
            outputHtml(renderUndoResultHtml($result));
        }
        break;

    case 'insert_bsc_business_computing':
        $result = insertBscBusinessComputingUnits($conn, $courseId);
        if (isCli()) {
            echo isset($result['error'])
                ? "ERROR: {$result['error']}\n"
                : "Inserted: {$result['inserted']}  Skipped: {$result['skipped']}  Failed: {$result['failed']}\n";
        } else {
            outputHtml(renderInsertResultHtml($result));
        }
        break;

    case 'move_units':
        $result = moveBscBusinessComputingUnitsToCourse($conn, $sourceCourseId, $targetCourseId);
        if (isCli()) {
            echo isset($result['error'])
                ? "ERROR: {$result['error']}\n"
                : "Moved: {$result['moved']}  Duplicate: {$result['duplicate']}  Missing: {$result['missing']}  Failed: {$result['failed']}\n";
        } else {
            outputHtml(renderMoveResultHtml($result));
        }
        break;

    default:
        if (isCli()) {
            $courses = getAllCourses($conn);
            $counts  = getUnitCountByCourse($conn);
            echo "Courses:\n";
            printf("%-4s %-45s %-6s %-8s\n", 'ID', 'Name', 'Dept', 'Units');
            echo str_repeat('-', 70) . "\n";
            foreach ($courses as $c) {
                printf("%-4s %-45s %-6s %-8s\n", $c['id'], $c['name'], $c['department_id'], $counts[(int)$c['id']] ?? 0);
            }
            echo "\n";
            $units = getExistingUnits($conn, $courseId);
            echo "Units for course ID {$courseId} (" . count($units) . " total):\n";
            printf("%-4s %-10s %-4s %-4s %s\n", 'ID', 'Code', 'Yr', 'Sem', 'Name');
            echo str_repeat('-', 80) . "\n";
            foreach ($units as $u) {
                printf("%-4s %-10s %-4s %-4s %s\n", $u['id'], $u['code'], $u['year'], $u['semester'], $u['name']);
            }
        } else {
            outputHtml(renderUnitsInfoHtml($conn, $courseId));
        }
        break;
}