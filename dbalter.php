<?php
/**
 * dbalter.php
 *
 * Displays raw courses and units tables exactly as stored in the DB.
 * Also provides actions: add single unit, bulk insert, undo, move.
 */

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available.');
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function isCli(): bool { return php_sapi_name() === 'cli'; }

function escape(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Raw DB fetchers ──────────────────────────────────────────────────────────

/** Every row from the courses table, ordered by id. */
function getAllCourses(mysqli $conn): array
{
    $rows = [];
    $res  = $conn->query('SELECT id, name, department_id, duration FROM courses ORDER BY id');
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

/** Every row from the units table, ordered by id. */
function getAllUnits(mysqli $conn): array
{
    $rows = [];
    $res  = $conn->query('SELECT id, name, code, course_id, year, semester FROM units ORDER BY id');
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

/** Units for a specific course, ordered by id. */
function getUnitsByCourse(mysqli $conn, int $courseId): array
{
    $rows = [];
    $stmt = $conn->prepare('SELECT id, name, code, course_id, year, semester FROM units WHERE course_id = ? ORDER BY id');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/** Unit count per course. */
function getUnitCounts(mysqli $conn): array
{
    $counts = [];
    $res = $conn->query('SELECT course_id, COUNT(*) as total FROM units GROUP BY course_id');
    while ($r = $res->fetch_assoc()) $counts[(int)$r['course_id']] = (int)$r['total'];
    return $counts;
}

/** Get course name by id, or null if not found. */
function getCourseNameById(mysqli $conn, int $id): ?string
{
    $stmt = $conn->prepare('SELECT name FROM courses WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? null;
}

// ─── B.Sc. Business Computing unit definitions ───────────────────────────────

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

// ─── Actions ──────────────────────────────────────────────────────────────────

function addSingleUnit(mysqli $conn, int $courseId, string $code, string $name, int $year, int $semester): array
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) {
        return ['status' => 'error', 'message' => "Course ID {$courseId} does not exist."];
    }
    $code = trim($code);
    $name = trim($name);
    if ($code === '' || $name === '') {
        return ['status' => 'error', 'message' => 'Unit code and name cannot be empty.'];
    }
    // Check duplicate
    $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->bind_param('is', $courseId, $code);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    if ($exists) {
        return ['status' => 'skipped', 'message' => "Unit \"{$code}\" already exists in \"{$courseName}\".",
                'course_id' => $courseId, 'course_name' => $courseName,
                'code' => $code, 'name' => $name, 'year' => $year, 'semester' => $semester];
    }
    // Insert
    $ins = $conn->prepare('INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)');
    $ins->bind_param('ssiii', $name, $code, $courseId, $year, $semester);
    $ins->execute();
    $ok    = $ins->affected_rows > 0;
    $newId = $conn->insert_id;
    $ins->close();
    if (!$ok) {
        return ['status' => 'error', 'message' => "Insert failed for unit \"{$code}\"."];
    }
    return ['status' => 'inserted', 'message' => "Unit \"{$code} — {$name}\" added to \"{$courseName}\".",
            'course_id' => $courseId, 'course_name' => $courseName, 'unit_id' => $newId,
            'code' => $code, 'name' => $name, 'year' => $year, 'semester' => $semester];
}

function insertBscBusinessComputingUnits(mysqli $conn, int $courseId): array
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) return ['error' => "Course ID {$courseId} not found."];
    $summary = ['course_id' => $courseId, 'course_name' => $courseName, 'inserted' => 0, 'skipped' => 0, 'failed' => 0];
    foreach (getBscBusinessComputingUnits() as [$code, $name, $year, $semester]) {
        $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
        $stmt->bind_param('is', $courseId, $code);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if ($exists) { $summary['skipped']++; continue; }
        $ins = $conn->prepare('INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)');
        $ins->bind_param('ssiii', $name, $code, $courseId, $year, $semester);
        $ins->execute();
        $summary[$ins->affected_rows > 0 ? 'inserted' : 'failed']++;
        $ins->close();
    }
    return $summary;
}

function removeWronglyInsertedUnits(mysqli $conn, int $courseId, int $maxLegitId): array
{
    $courseName = getCourseNameById($conn, $courseId);
    if ($courseName === null) return ['error' => "Course ID {$courseId} not found."];
    $stmt = $conn->prepare('DELETE FROM units WHERE course_id = ? AND id > ?');
    $stmt->bind_param('ii', $courseId, $maxLegitId);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    return ['course_id' => $courseId, 'course_name' => $courseName, 'deleted' => $deleted,
            'message' => $deleted > 0
                ? "{$deleted} wrongly inserted unit(s) removed from \"{$courseName}\"."
                : "Nothing to undo — no units with ID > {$maxLegitId} in \"{$courseName}\"."];
}

function moveBscBusinessComputingUnitsToCourse(mysqli $conn, int $from, int $to): array
{
    $fromName = getCourseNameById($conn, $from);
    $toName   = getCourseNameById($conn, $to);
    if (!$fromName || !$toName) return ['error' => 'Source or target course not found.'];
    $summary = ['source_course_id' => $from, 'source_course_name' => $fromName,
                'target_course_id' => $to,   'target_course_name' => $toName,
                'moved' => 0, 'duplicate' => 0, 'missing' => 0, 'failed' => 0];
    foreach (array_column(getBscBusinessComputingUnits(), 0) as $code) {
        $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
        $stmt->bind_param('is', $from, $code);
        $stmt->execute();
        $src = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$src) { $summary['missing']++; continue; }

        $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
        $stmt->bind_param('is', $to, $code);
        $stmt->execute();
        $stmt->store_result();
        $dup = $stmt->num_rows > 0;
        $stmt->close();
        if ($dup) { $summary['duplicate']++; continue; }

        $upd = $conn->prepare('UPDATE units SET course_id = ? WHERE course_id = ? AND code = ?');
        $upd->bind_param('iis', $to, $from, $code);
        $upd->execute();
        $summary[$upd->affected_rows > 0 ? 'moved' : 'failed']++;
        $upd->close();
    }
    return $summary;
}

// ─── Action detection ─────────────────────────────────────────────────────────

function currentAction(): string
{
    if (isCli()) {
        global $argv;
        if (in_array('--add-single-unit',               $argv, true)) return 'add_single_unit';
        if (in_array('--insert-bsc-business-computing', $argv, true)) return 'insert_bsc_business_computing';
        if (in_array('--undo-course6',                  $argv, true)) return 'undo_course6';
        if (in_array('--move-bsc-business-computing',   $argv, true)) return 'move_units';
        return '';
    }
    return $_REQUEST['action'] ?? '';
}

// ─── HTML output ──────────────────────────────────────────────────────────────

function outputHtml(string $body): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<title>DB Inspector</title><style>'
        . 'body{font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;color:#111;margin:0;padding:24px;max-width:1200px;}'
        . 'h1{margin:0 0 4px;}h2{margin:20px 0 8px;font-size:16px;color:#374151;}'
        . 'section{margin-bottom:28px;padding:16px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.05);}'
        . 'table{width:100%;border-collapse:collapse;font-size:13px;}'
        . 'th{background:#1e3a5f;color:#fff;padding:8px 10px;text-align:left;position:sticky;top:0;}'
        . 'td{border-bottom:1px solid #e5e7eb;padding:7px 10px;vertical-align:top;}'
        . 'tr:nth-child(even) td{background:#f9fafb;}'
        . 'tr:hover td{background:#eff6ff;}'
        . '.badge{display:inline-block;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;}'
        . '.badge-blue{background:#dbeafe;color:#1d4ed8;}'
        . '.badge-green{background:#dcfce7;color:#15803d;}'
        . '.badge-yellow{background:#fef9c3;color:#92400e;}'
        . '.badge-red{background:#fee2e2;color:#dc2626;}'
        . '.action-box{margin-bottom:16px;padding:14px;border-radius:8px;}'
        . '.act-green{background:#f0fdf4;border:1px solid #86efac;}'
        . '.act-red{background:#fef2f2;border:1px solid #fca5a5;}'
        . '.act-blue{background:#eff6ff;border:1px solid #93c5fd;}'
        . '.act-gray{background:#f9fafb;border:1px solid #d1d5db;}'
        . 'input,select{width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;box-sizing:border-box;}'
        . 'label{font-weight:600;font-size:13px;}'
        . 'button{padding:9px 20px;border:none;border-radius:6px;font-size:14px;cursor:pointer;color:#fff;}'
        . '.btn-green{background:#16a34a;} .btn-blue{background:#2563eb;} .btn-red{background:#dc2626;} .btn-gray{background:#6b7280;}'
        . 'a{color:#2563eb;}'
        . '.highlight td{background:#fef9c3 !important;font-weight:600;}'
        . '.new-row td{background:#dcfce7 !important;font-weight:700;}'
        . '.meta{font-size:12px;color:#6b7280;margin-bottom:16px;}'
        . '</style></head><body>' . $body . '</body></html>';
}

function renderCoursesTable(mysqli $conn, array $highlightIds = []): string
{
    $courses = getAllCourses($conn);
    $counts  = getUnitCounts($conn);

    $html = '<section><h2>&#128218; courses table <span class="meta" style="font-weight:normal;">(' . count($courses) . ' rows)</span></h2>'
          . '<table><thead><tr><th>id</th><th>name</th><th>department_id</th><th>duration</th><th>unit_count</th></tr></thead><tbody>';
    foreach ($courses as $c) {
        $id  = (int)$c['id'];
        $cls = in_array($id, $highlightIds) ? ' class="highlight"' : '';
        $html .= '<tr' . $cls . '>'
            . '<td>' . escape((string)$id) . '</td>'
            . '<td>' . escape($c['name']) . '</td>'
            . '<td>' . escape((string)$c['department_id']) . '</td>'
            . '<td>' . escape((string)$c['duration']) . '</td>'
            . '<td><span class="badge badge-blue">' . ($counts[$id] ?? 0) . '</span></td>'
            . '</tr>';
    }
    return $html . '</tbody></table></section>';
}

function renderUnitsTable(mysqli $conn, ?int $filterCourseId = null, int $highlightUnitId = 0): string
{
    $units  = $filterCourseId !== null ? getUnitsByCourse($conn, $filterCourseId) : getAllUnits($conn);
    $title  = $filterCourseId !== null
        ? "&#128218; units table &mdash; course_id = {$filterCourseId} <span class=\"meta\" style=\"font-weight:normal;\">(" . count($units) . " rows)</span>"
        : "&#128218; units table <span class=\"meta\" style=\"font-weight:normal;\">(" . count($units) . " rows)</span>";

    $html = '<section><h2>' . $title . '</h2>'
          . '<table><thead><tr><th>id</th><th>code</th><th>name</th><th>course_id</th><th>year</th><th>semester</th></tr></thead><tbody>';
    foreach ($units as $u) {
        $cls = (int)$u['id'] === $highlightUnitId ? ' class="new-row"' : '';
        $html .= '<tr' . $cls . '>'
            . '<td>' . escape((string)$u['id']) . '</td>'
            . '<td>' . escape($u['code']) . '</td>'
            . '<td>' . escape($u['name']) . '</td>'
            . '<td>' . escape((string)$u['course_id']) . '</td>'
            . '<td>' . escape((string)$u['year']) . '</td>'
            . '<td>' . escape((string)$u['semester']) . '</td>'
            . '</tr>';
    }
    return $html . '</tbody></table></section>';
}

function renderMainPage(mysqli $conn): string
{
    $html  = '<h1>DB Inspector</h1>';
    $html .= '<p class="meta">Generated: ' . date('Y-m-d H:i:s') . ' &nbsp;|&nbsp; Showing live data from the database.</p>';

    // Courses table — raw
    $html .= renderCoursesTable($conn, [6, 7]);

    // Units table — course 7 only
    $html .= renderUnitsTable($conn, 7);

    // ── Actions ──
    $html .= '<section><h2>Actions</h2>';

    // Add single unit
    $html .= '<div class="action-box act-green">'
        . '<h3 style="margin:0 0 10px;color:#15803d;">&#43; Add a Single Unit to Course ID 7</h3>'
        . '<form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:680px;">'
        . '<input type="hidden" name="action" value="add_single_unit">'
        . '<label style="grid-column:1/3;">Unit Code<br><input type="text" name="unit_code" value="BBC 2101" required></label>'
        . '<label style="grid-column:1/3;">Unit Name<br><input type="text" name="unit_name" value="Business Studies for I.T." required></label>'
        . '<label>Year<br><select name="unit_year">'
        . '<option value="1" selected>Year 1</option><option value="2">Year 2</option><option value="3">Year 3</option><option value="4">Year 4</option>'
        . '</select></label>'
        . '<label>Semester<br><select name="unit_semester">'
        . '<option value="1" selected>Semester 1</option><option value="2">Semester 2</option>'
        . '</select></label>'
        . '<div style="grid-column:1/3;margin-top:4px;"><button type="submit" class="btn-green">&#43; Add Unit</button></div>'
        . '</form></div>';

    // Bulk insert
    $html .= '<div class="action-box act-blue">'
        . '<h3 style="margin:0 0 6px;color:#1d4ed8;">&#10003; Bulk Insert All B.Sc. Business Computing Units into Course ID 7</h3>'
        . '<p style="margin:0 0 10px;font-size:13px;">Inserts all 66 defined units. Skips any that already exist.</p>'
        . '<form method="post"><button type="submit" name="action" value="insert_bsc_business_computing" class="btn-blue">&#10003; Bulk Insert into Course ID 7</button></form>'
        . '</div>';

    // Undo
    $html .= '<div class="action-box act-red">'
        . '<h3 style="margin:0 0 6px;color:#dc2626;">&#8617; Undo &mdash; Remove Wrongly Inserted Units from Course ID 6</h3>'
        . '<p style="margin:0 0 10px;font-size:13px;">Deletes units in course 6 with ID &gt; 215. Original units (IDs 152&ndash;215) are untouched.</p>'
        . '<form method="post"><button type="submit" name="action" value="undo_course6" class="btn-red">&#8617; Undo Wrong Inserts on Course 6</button></form>'
        . '</div>';

    // Move
    $html .= '<div class="action-box act-gray">'
        . '<h3 style="margin:0 0 6px;">&#8594; Move B.Sc. Business Computing Units from Course 6 &rarr; 7</h3>'
        . '<p style="margin:0 0 10px;font-size:13px;">Only use if units are mistakenly assigned to course 6.</p>'
        . '<form method="post"><button type="submit" name="action" value="move_units" class="btn-gray">&#8594; Move Units to Course 7</button></form>'
        . '</div>';

    $html .= '</section>';
    return $html;
}

// ─── Result pages ─────────────────────────────────────────────────────────────

function resultPage(mysqli $conn, string $title, string $banner, string $details, int $highlightUnitId = 0): string
{
    return '<h1>' . $title . '</h1>'
        . $banner . $details
        . renderCoursesTable($conn, [6, 7])
        . renderUnitsTable($conn, 7, $highlightUnitId)
        . '<p><a href="dbalter.php">&#8592; Back</a></p>';
}

function banner(string $msg, string $type): string
{
    $map = ['success' => ['#166534','#dcfce7','&#10003;'],
            'warning' => ['#92400e','#fef9c3','&#9888;'],
            'error'   => ['#dc2626','#fee2e2','&#10007;']];
    [$c, $bg, $icon] = $map[$type] ?? $map['error'];
    return '<p style="color:' . $c . ';background:' . $bg . ';padding:10px;border-radius:6px;margin-bottom:12px;">'
        . $icon . ' ' . escape($msg) . '</p>';
}

// ─── Main ─────────────────────────────────────────────────────────────────────

$courseId          = 7;
$sourceCourseId    = 6;
$targetCourseId    = 7;
$course6MaxLegitId = 215;

switch (currentAction()) {

    case 'add_single_unit':
        $code     = trim($_POST['unit_code']     ?? '');
        $name     = trim($_POST['unit_name']     ?? '');
        $year     = (int)($_POST['unit_year']     ?? 1);
        $semester = (int)($_POST['unit_semester'] ?? 1);
        $r = addSingleUnit($conn, $courseId, $code, $name, $year, $semester);

        if ($r['status'] === 'error') {
            outputHtml(resultPage($conn, 'Add Unit — Error', banner($r['message'], 'error'), ''));
        } elseif ($r['status'] === 'skipped') {
            $details = '<p><strong>Course ID:</strong> ' . escape((string)$r['course_id'])
                . ' &nbsp; <strong>Course:</strong> ' . escape($r['course_name']) . '<br>'
                . '<strong>Code:</strong> ' . escape($r['code'])
                . ' &nbsp; <strong>Year:</strong> ' . escape((string)$r['year'])
                . ' &nbsp; <strong>Semester:</strong> ' . escape((string)$r['semester']) . '</p>';
            outputHtml(resultPage($conn, 'Add Unit — Skipped', banner($r['message'], 'warning'), $details));
        } else {
            $details = '<p><strong>Course ID:</strong> ' . escape((string)$r['course_id'])
                . ' &nbsp; <strong>Course:</strong> ' . escape($r['course_name']) . '<br>'
                . '<strong>New Unit ID:</strong> <span class="badge badge-green">' . escape((string)$r['unit_id']) . '</span>'
                . ' &nbsp; <strong>Code:</strong> ' . escape($r['code'])
                . ' &nbsp; <strong>Year:</strong> ' . escape((string)$r['year'])
                . ' &nbsp; <strong>Semester:</strong> ' . escape((string)$r['semester']) . '</p>';
            outputHtml(resultPage($conn, 'Add Unit — Success', banner($r['message'], 'success'), $details, (int)$r['unit_id']));
        }
        break;

    case 'insert_bsc_business_computing':
        $r = insertBscBusinessComputingUnits($conn, $courseId);
        if (isset($r['error'])) {
            outputHtml(resultPage($conn, 'Bulk Insert — Error', banner($r['error'], 'error'), ''));
        } else {
            $allSkipped = $r['inserted'] === 0 && $r['skipped'] > 0;
            $msg  = $allSkipped ? 'All units already exist — nothing new inserted.' : "Inserted {$r['inserted']} unit(s) into \"{$r['course_name']}\".";
            $type = $allSkipped ? 'warning' : 'success';
            $details = '<p><strong>Course ID:</strong> ' . escape((string)$r['course_id'])
                . ' &nbsp; <strong>Course:</strong> ' . escape($r['course_name']) . '<br>'
                . '<strong>Inserted:</strong> ' . $r['inserted']
                . ' &nbsp; <strong>Skipped:</strong> ' . $r['skipped']
                . ' &nbsp; <strong>Failed:</strong> ' . $r['failed'] . '</p>';
            outputHtml(resultPage($conn, 'Bulk Insert Result', banner($msg, $type), $details));
        }
        break;

    case 'undo_course6':
        $r = removeWronglyInsertedUnits($conn, $sourceCourseId, $course6MaxLegitId);
        if (isset($r['error'])) {
            outputHtml(resultPage($conn, 'Undo — Error', banner($r['error'], 'error'), ''));
        } else {
            $type    = $r['deleted'] > 0 ? 'success' : 'warning';
            $details = '<p><strong>Course ID:</strong> ' . escape((string)$r['course_id'])
                . ' &nbsp; <strong>Course:</strong> ' . escape($r['course_name']) . '<br>'
                . '<strong>Units deleted:</strong> <span class="badge badge-red">' . $r['deleted'] . '</span></p>';
            outputHtml(resultPage($conn, 'Undo Result', banner($r['message'], $type), $details));
        }
        break;

    case 'move_units':
        $r = moveBscBusinessComputingUnitsToCourse($conn, $sourceCourseId, $targetCourseId);
        if (isset($r['error'])) {
            outputHtml(resultPage($conn, 'Move — Error', banner($r['error'], 'error'), ''));
        } else {
            $msg     = "Moved {$r['moved']} unit(s) from \"{$r['source_course_name']}\" to \"{$r['target_course_name']}\".";
            $type    = $r['moved'] > 0 ? 'success' : 'warning';
            $details = '<p><strong>From:</strong> ' . escape($r['source_course_name']) . ' (ID ' . $r['source_course_id'] . ')<br>'
                . '<strong>To:</strong> ' . escape($r['target_course_name']) . ' (ID ' . $r['target_course_id'] . ')<br>'
                . '<strong>Moved:</strong> ' . $r['moved']
                . ' &nbsp; <strong>Duplicates:</strong> ' . $r['duplicate']
                . ' &nbsp; <strong>Missing:</strong> ' . $r['missing']
                . ' &nbsp; <strong>Failed:</strong> ' . $r['failed'] . '</p>';
            outputHtml(resultPage($conn, 'Move Result', banner($msg, $type), $details));
        }
        break;

    default:
        outputHtml(renderMainPage($conn));
        break;
}