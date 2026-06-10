<?php
require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available.');
}

function escape(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── All 66 units from SCT222.xls ─────────────────────────────────────────────

function getUnits(): array
{
    return [
        // Year 1 Semester 1
        ['BBC 2101', 'Business Studies for I.T.',                         1, 1],
        ['BIT 2103', 'Introduction to Computer Applications',             1, 1],
        ['BIT 2104', 'Introduction to Programming',                       1, 1],
        ['CILS 2101','Communication and Information Literacy Skills',      1, 1],
        ['HBC 2128', 'Introduction to Accounting 1',                      1, 1],
        ['HBC 2215', 'Essentials of Economics',                           1, 1],
        ['ICS 2109', 'Computer Operating Systems',                        1, 1],
        ['SMA 2104', 'Mathematics for Sciences',                          1, 1],
        ['SZL 2111', 'HIV/AIDS',                                          1, 1],
        // Year 1 Semester 2
        ['BBC 2102', 'Computer Networks, Design and Management',          1, 2],
        ['BBC 2103', 'Hardware Systems Support and Maintenance',          1, 2],
        ['BIT 2112', 'Systems Analysis and Design',                       1, 2],
        ['HBC 2107', 'Introduction to Accounting II',                     1, 2],
        ['HRD 2102', 'Development Studies and Social Ethics',             1, 2],
        ['ICS 2206', 'Introduction to Database Management Systems',       1, 2],
        ['SDS 2107', 'Algebra for Data Science',                          1, 2],
        ['STA 2100', 'Probability and Statistics I',                      1, 2],
        // Year 2 Semester 1
        ['BBC 2201', 'Enterprise Network Systems Administration and Management', 2, 1],
        ['BBC 2202', 'Web Development Fundamentals',                      2, 1],
        ['BIT 2214', 'Object-Oriented Analysis and Design',               2, 1],
        ['BIT 2223', 'Mobile and Wireless Computing',                     2, 1],
        ['HPS 2301', 'Operations Management',                             2, 1],
        ['HSC 2408', 'Innovation and Technology Transfer',                2, 1],
        ['ICS 2105', 'Data Structures and Algorithms',                    2, 1],
        ['SMA 2101', 'Calculus I',                                        2, 1],
        // Year 2 Semester 2
        ['BBC 2203', 'Software Engineering',                              2, 2],
        ['BBC 2204', 'Object-Oriented Programming',                       2, 2],
        ['BBC 2205', 'Cloud and Edge Computing',                          2, 2],
        ['BBC 2206', 'Introduction to Data Science',                      2, 2],
        ['BBC 2207', 'Design Thinking',                                   2, 2],
        ['BBC 2208', 'Business Computing Project in Industry',            2, 2],
        ['HBC 2202', 'Introduction to Financial Management',              2, 2],
        ['SMA 2100', 'Discrete Mathematics',                              2, 2],
        ['SMA 2102', 'Calculus II',                                       2, 2],
        // Year 3 Semester 1
        ['BBC 2301', 'Enterprise Web Application Development',            3, 1],
        ['BBC 2302', 'Principles of Data Analytics',                      3, 1],
        ['BBC 2303', 'Enterprise Resource Planning Systems',              3, 1],
        ['BBC 2304', 'Computer Graphics and Multimedia',                  3, 1],
        ['BIT 2319', 'Artificial Intelligence',                           3, 1],
        ['ICS 2301', 'Design and Analysis of Algorithms',                 3, 1],
        ['ICS 2404', 'Advanced Database Management System',               3, 1],
        ['STA 2200', 'Probability and Statistics II',                     3, 1],
        // Year 3 Semester 2
        ['BBC 2305', 'Machine Learning',                                  3, 2],
        ['BBC 2306', 'Information Analysis and Visualization',            3, 2],
        ['BBC 2307', 'Statistical Computing',                             3, 2],
        ['BIT 2122', 'Industrial Attachment',                             3, 2],
        ['BIT 2215', 'Software Project Management',                       3, 2],
        ['BIT 2301', 'Research Methodology',                              3, 2],
        ['BIT 2305', 'Human Computer Interactions',                       3, 2],
        ['BIT 2317', 'Fundamentals of Computer Security',                 3, 2],
        ['BIT 2320', 'Mobile Application Development',                    3, 2],
        // Year 4 Semester 1
        ['BBC 2401', 'Software Architectures',                            4, 1],
        ['BBC 2402', 'Embedded Systems and Internet of Things (IoT)',     4, 1],
        ['BBC 2403', 'Digital Marketing Communication',                   4, 1],
        ['BBC 2404', 'Deep Learning',                                     4, 1],
        ['BBC 2405', 'Business Data Mining and Warehousing',              4, 1],
        ['BBC 2406', 'Software Development Project',                      4, 1],
        ['BBC 2407', 'Animation and Augmented Reality',                   4, 1],
        ['BIT 2318', 'Information System Audit',                          4, 1],
        // Year 4 Semester 2
        ['BBC 2408', 'Business Decision Support Systems',                 4, 2],
        ['BBC 2409', 'Text Mining and Web Analytics',                     4, 2],
        ['BBC 2410', 'Business Analysis and Process Modeling',            4, 2],
        ['BIT 2313', 'Professional Issues in ICT',                        4, 2],
        ['BIT 2403', 'Business Development and Entrepreneurship',         4, 2],
        ['HBC 2209', 'Organizational Behaviour',                          4, 2],
        ['HBC 2401', 'Management Accounting',                             4, 2],
    ];
}

// ── DB helpers ────────────────────────────────────────────────────────────────

function courseExists(mysqli $conn, int $id): ?string
{
    $stmt = $conn->prepare('SELECT name FROM courses WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['name'] ?? null;
}

function bulkInsert(mysqli $conn, int $courseId): array
{
    $courseName = courseExists($conn, $courseId);
    if (!$courseName) {
        return ['error' => "Course ID {$courseId} not found."];
    }

    $summary = ['course_id' => $courseId, 'course_name' => $courseName,
                'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => []];

    foreach (getUnits() as [$code, $name, $year, $semester]) {
        // Check duplicate
        $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
        $stmt->bind_param('is', $courseId, $code);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $summary['skipped']++;
            $summary['rows'][] = ['code' => $code, 'name' => $name, 'year' => $year,
                                   'semester' => $semester, 'status' => 'skipped', 'id' => '-'];
            continue;
        }

        $stmt = $conn->prepare('INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiii', $name, $code, $courseId, $year, $semester);
        $stmt->execute();
        $ok    = $stmt->affected_rows > 0;
        $newId = $conn->insert_id;
        $stmt->close();

        $status = $ok ? 'inserted' : 'failed';
        $summary[$status]++;
        $summary['rows'][] = ['code' => $code, 'name' => $name, 'year' => $year,
                               'semester' => $semester, 'status' => $status, 'id' => $ok ? $newId : '-'];
    }

    return $summary;
}

function fetchCourses(mysqli $conn): array
{
    $rows = [];
    $res  = $conn->query('SELECT id, name, department_id, duration FROM courses ORDER BY id');
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function fetchUnitCounts(mysqli $conn): array
{
    $counts = [];
    $res = $conn->query('SELECT course_id, COUNT(*) AS total FROM units GROUP BY course_id');
    while ($r = $res->fetch_assoc()) $counts[(int)$r['course_id']] = (int)$r['total'];
    return $counts;
}

function fetchUnits(mysqli $conn, int $courseId): array
{
    $rows = [];
    $stmt = $conn->prepare('SELECT id, code, name, course_id, year, semester FROM units WHERE course_id = ? ORDER BY id');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

// ── New: DB Schema helpers ──────────────────────────────────────────────────

function getAllTables(mysqli $conn): array
{
    $tables = [];
    $res = $conn->query('SHOW TABLE STATUS');
    while ($r = $res->fetch_assoc()) {
        $tables[] = $r;
    }
    return $tables;
}

function getTableColumns(mysqli $conn, string $table): array
{
    $cols = [];
    $res = $conn->query("DESCRIBE `{$table}`");
    while ($r = $res->fetch_assoc()) {
        $cols[] = $r;
    }
    return $cols;
}

function getTableIndexes(mysqli $conn, string $table): array
{
    $indexes = [];
    $res = $conn->query("SHOW INDEX FROM `{$table}`");
    while ($r = $res->fetch_assoc()) {
        $indexes[] = $r;
    }
    return $indexes;
}

function getTableRowCount(mysqli $conn, string $table): int
{
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM `{$table}`");
    $row = $res->fetch_assoc();
    return (int)$row['cnt'];
}

function formatBytes(?string $bytes): string
{
    if ($bytes === null || $bytes === '') return '—';
    $b = (int)$bytes;
    if ($b >= 1073741824) return round($b / 1073741824, 1) . ' GiB';
    if ($b >= 1048576)    return round($b / 1048576, 1) . ' MiB';
    if ($b >= 1024)       return round($b / 1024, 1) . ' KiB';
    return $b . ' B';
}

// ── HTML ──────────────────────────────────────────────────────────────────────

function page(string $body, string $activeTab = 'schema'): void
{
    $tabs = [
        'schema' => 'DB Schema',
        'bulk'   => 'Bulk Insert',
    ];
    $tabLinks = '';
    foreach ($tabs as $key => $label) {
        $active = $key === $activeTab ? ' style="background:#1e3a5f;color:#fff;"' : '';
        $tabLinks .= '<a href="?tab=' . $key . '" class="tab"' . $active . '>' . escape($label) . '</a>';
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>dbalter — ' . escape($tabs[$activeTab] ?? '') . '</title>
        <style>
            *{box-sizing:border-box;margin:0;padding:0}
            body{font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;color:#111;padding:24px;}
            .container{max-width:1200px;margin:0 auto;}
            h1{margin:0 0 8px;font-size:22px;}
            .subtitle{color:#6b7280;font-size:13px;margin-bottom:16px;}
            .tabs{display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #e5e7eb;}
            .tab{display:inline-block;padding:8px 18px;font-size:13px;font-weight:600;
                 text-decoration:none;color:#374151;border-radius:6px 6px 0 0;margin-bottom:-2px;
                 border:1px solid transparent;transition:all .15s;}
            .tab:hover{background:#e5e7eb;}
            h2{margin:20px 0 8px;font-size:14px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;}
            table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:8px;}
            th{background:#1e3a5f;color:#fff;padding:7px 10px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.3px;}
            td{border-bottom:1px solid #e5e7eb;padding:6px 10px;}
            tr:hover td{background:#eff6ff;}
            .ins td{background:#dcfce7;} .skip td{background:#fef9c3;} .fail td{background:#fee2e2;}
            .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:20px;}
            .banner{padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:14px;}
            .ok{background:#dcfce7;color:#166534;border:1px solid #86efac;}
            .warn{background:#fef9c3;color:#92400e;border:1px solid #fde68a;}
            .err{background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;}
            button{background:#16a34a;color:#fff;padding:10px 24px;border:none;border-radius:6px;font-size:14px;cursor:pointer;font-weight:600;}
            button:hover{background:#15803d;}
            a{color:#2563eb;font-size:13px;}
            .stats{display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap;}
            .stat{padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;}
            .stat-green{background:#dcfce7;color:#166534;} .stat-yellow{background:#fef9c3;color:#92400e;}
            .stat-red{background:#fee2e2;color:#dc2626;} .stat-blue{background:#dbeafe;color:#1d4ed8;}
            /* Schema table styles */
            .tbl-card{background:#fff;border:1px solid #ddd;border-radius:8px;margin-bottom:20px;overflow:hidden;}
            .tbl-hdr{background:#1e3a5f;color:#fff;padding:10px 16px;font-size:14px;font-weight:600;
                     display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;}
            .tbl-hdr span{font-weight:400;opacity:.7;font-size:12px;}
            .tbl-meta{background:#f8fafc;padding:6px 16px;font-size:12px;color:#6b7280;border-bottom:1px solid #e5e7eb;
                      display:flex;gap:16px;flex-wrap:wrap;}
            .tbl-meta strong{color:#374151;}
            .schema-tbl{width:100%;border-collapse:collapse;font-size:13px;}
            .schema-tbl th{background:#f0f4f8;padding:7px 14px;text-align:left;color:#374151;font-size:11px;
                           text-transform:uppercase;letter-spacing:.4px;}
            .schema-tbl td{padding:6px 14px;border-bottom:1px solid #f0f0f0;}
            .schema-tbl tr:last-child td{border:none;}
            .pk{color:#b45309;font-weight:600;}
            .fk{color:#1d4ed8;}
            .null{color:#9ca3af;font-size:11px;}
            .key-icon{font-size:13px;}
            .index-badge{display:inline-block;background:#e0e7ff;color:#3730a3;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:4px;}
            .summary-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px;}
            .summary-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:14px 16px;text-align:center;}
            .summary-card .num{font-size:24px;font-weight:700;color:#1e3a5f;}
            .summary-card .lbl{font-size:12px;color:#6b7280;margin-top:2px;}
        </style>
    </head><body>
    <div class="container">
    <h1>dbalter</h1>
    <div class="subtitle">Database: unilis &nbsp;·&nbsp; ' . date('Y-m-d H:i:s') . '</div>
    <div class="tabs">' . $tabLinks . '</div>
    ' . $body . '
    </div></body></html>';
}

function schemaPage(mysqli $conn): string
{
    $tables = getAllTables($conn);
    $totalRows = 0;
    $totalSize = 0;

    // Summary stats
    $summaryRows = '';
    $tableCards = '';
    foreach ($tables as $t) {
        $tableName = $t['Name'];
        $engine    = $t['Engine'] ?? '?';
        $collation = $t['Collation'] ?? '?';
        $rowCount  = (int)$t['Rows'];
        $dataLen   = (int)$t['Data_length'];
        $indexLen  = (int)$t['Index_length'];
        $createTime = $t['Create_time'] ?? '?';
        $updateTime = $t['Update_time'] ?? '—';
        $tableComment = $t['Comment'] ?? '';

        $totalRows += $rowCount;
        $totalSize += $dataLen + $indexLen;

        // Get columns
        $cols = getTableColumns($conn, $tableName);

        $rows = '';
        foreach ($cols as $c) {
            $field   = escape($c['Field']);
            $type    = escape($c['Type']);
            $null    = $c['Null'];
            $key     = $c['Key'];
            $default = $c['Default'];
            $extra   = $c['Extra'] ?? '';

            $cls = '';
            $icon = '';
            if ($key === 'PRI') {
                $cls = 'pk';
                $icon = ' 🔑';
            } elseif ($key === 'UNI') {
                $cls = 'fk';
                $icon = ' ◆';
            } elseif ($key === 'MUL') {
                $cls = 'fk';
                $icon = ' ⌁';
            }

            $extraBadge = '';
            if ($extra) {
                $extraBadge = ' <span class="index-badge">' . escape($extra) . '</span>';
            }

            $rows .= '<tr>
                <td class="' . $cls . '">' . $field . $icon . $extraBadge . '</td>
                <td>' . $type . '</td>
                <td class="null">' . $null . '</td>
                <td>' . ($key ?: '—') . '</td>
                <td class="null">' . ($default === null ? 'NULL' : ($default === '' ? "''" : escape($default))) . '</td>
            </tr>';
        }

        $tableCards .= '<div class="tbl-card">
            <div class="tbl-hdr">
                ' . escape($tableName) . '
                <span>' . number_format($rowCount) . ' rows &nbsp;·&nbsp; ' . formatBytes((string)($dataLen + $indexLen)) . '</span>
            </div>
            <div class="tbl-meta">
                <span><strong>Engine:</strong> ' . escape($engine) . '</span>
                <span><strong>Collation:</strong> ' . escape($collation) . '</span>
                <span><strong>Created:</strong> ' . escape($createTime) . '</span>
                <span><strong>Updated:</strong> ' . escape($updateTime) . '</span>
                ' . ($tableComment ? '<span><strong>Comment:</strong> ' . escape($tableComment) . '</span>' : '') . '
            </div>
            <table class="schema-tbl">
                <tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>
                ' . $rows . '
            </table>
        </div>';
    }

    $summaryRows = '<div class="summary-grid">
        <div class="summary-card"><div class="num">' . count($tables) . '</div><div class="lbl">Tables</div></div>
        <div class="summary-card"><div class="num">' . number_format($totalRows) . '</div><div class="lbl">Total Rows</div></div>
        <div class="summary-card"><div class="num">' . formatBytes((string)$totalSize) . '</div><div class="lbl">Total Size (data + indexes)</div></div>
        <div class="summary-card"><div class="num">' . formatBytes((string)$totalSize) . '</div><div class="lbl">Data + Index</div></div>
    </div>';

    $html = $summaryRows;
    $html .= $tableCards;
    return $html;
}

function coursesTable(mysqli $conn): string
{
    $courses = fetchCourses($conn);
    $counts  = fetchUnitCounts($conn);
    $html = '<h2>courses</h2><table><thead><tr><th>id</th><th>name</th><th>department_id</th><th>duration</th><th>unit count</th></tr></thead><tbody>';
    foreach ($courses as $c) {
        $id = (int)$c['id'];
        $hl = $id === 6 ? ' style="background:#fef9c3;"' : '';
        $html .= '<tr' . $hl . '><td>' . $id . '</td><td>' . escape($c['name']) . '</td>'
            . '<td>' . escape($c['department_id']) . '</td><td>' . escape($c['duration']) . '</td>'
            . '<td><strong>' . ($counts[$id] ?? 0) . '</strong></td></tr>';
    }
    return $html . '</tbody></table>';
}

function unitsTable(mysqli $conn, int $courseId): string
{
    $units = fetchUnits($conn, $courseId);
    $html  = '<h2>units — course_id = ' . $courseId . ' (' . count($units) . ' rows)</h2>';
    $html .= '<table><thead><tr><th>id</th><th>code</th><th>name</th><th>course_id</th><th>year</th><th>semester</th></tr></thead><tbody>';
    foreach ($units as $u) {
        $html .= '<tr><td>' . escape((string)$u['id']) . '</td><td>' . escape($u['code']) . '</td>'
            . '<td>' . escape($u['name']) . '</td><td>' . escape((string)$u['course_id']) . '</td>'
            . '<td>' . escape((string)$u['year']) . '</td><td>' . escape((string)$u['semester']) . '</td></tr>';
    }
    return $html . '</tbody></table>';
}

// ── Main ──────────────────────────────────────────────────────────────────────

$TARGET_COURSE_ID = 6; // Bsc Business Computing
$tab = $_GET['tab'] ?? 'schema';

// ── Bulk Insert POST handler ──────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_insert') {

    $r = bulkInsert($conn, $TARGET_COURSE_ID);

    if (isset($r['error'])) {
        page('<h1>dbalter</h1><div class="banner err">✗ ' . escape($r['error']) . '</div><p><a href="dbalter.php">← Back</a></p>', 'bulk');
        exit;
    }

    $allSkipped = $r['inserted'] === 0 && $r['skipped'] > 0;
    $bannerClass = $r['failed'] > 0 ? 'err' : ($allSkipped ? 'warn' : 'ok');
    $msg = $allSkipped
        ? "All {$r['skipped']} units already exist — nothing inserted."
        : "Done. Inserted {$r['inserted']} unit(s) into \"{$r['course_name']}\".";

    $body  = '<div class="banner ' . $bannerClass . '">' . escape($msg) . '</div>';

    // Stats
    $body .= '<div class="stats">'
        . '<div class="stat stat-blue">Total: ' . count($r['rows']) . '</div>'
        . '<div class="stat stat-green">Inserted: ' . $r['inserted'] . '</div>'
        . '<div class="stat stat-yellow">Skipped: ' . $r['skipped'] . '</div>'
        . '<div class="stat stat-red">Failed: ' . $r['failed'] . '</div>'
        . '</div>';

    // Per-row result
    $body .= '<div class="box"><h2>Insert log</h2>';
    $body .= '<table><thead><tr><th>DB id</th><th>code</th><th>name</th><th>year</th><th>semester</th><th>status</th></tr></thead><tbody>';
    foreach ($r['rows'] as $row) {
        $cls = $row['status'] === 'inserted' ? 'ins' : ($row['status'] === 'skipped' ? 'skip' : 'fail');
        $body .= '<tr class="' . $cls . '"><td>' . escape((string)$row['id']) . '</td>'
            . '<td>' . escape($row['code']) . '</td><td>' . escape($row['name']) . '</td>'
            . '<td>' . $row['year'] . '</td><td>' . $row['semester'] . '</td>'
            . '<td><strong>' . $row['status'] . '</strong></td></tr>';
    }
    $body .= '</tbody></table></div>';

    // Live tables
    $body .= '<div class="box">' . coursesTable($conn) . '</div>';
    $body .= '<div class="box">' . unitsTable($conn, $TARGET_COURSE_ID) . '</div>';
    $body .= '<p><a href="dbalter.php">← Back</a></p>';

    page($body, 'bulk');
    exit;
}

// ── Render ────────────────────────────────────────────────────────────────────

if ($tab === 'schema') {
    $body = schemaPage($conn);
    page($body, 'schema');
} elseif ($tab === 'bulk') {
    // Default: show preview + form
    $previewUnits = getUnits();
    $body  = '<div class="box"><h2>Bulk Insert — Bsc Business Computing (course ID 6)</h2>';
    $body .= '<p style="font-size:13px;margin-bottom:14px;">This will insert all <strong>' . count($previewUnits) . ' units</strong> from SCT222.xls into course ID 6. Duplicates are skipped.</p>';
    $body .= '<form method="post"><button type="submit" name="action" value="bulk_insert">⬆ Insert All ' . count($previewUnits) . ' Units into Course ID 6</button></form>';
    $body .= '<div class="box"><h2 style="margin-top:16px;">Preview</h2>';
    $body .= '<table><thead><tr><th>code</th><th>name</th><th>year</th><th>semester</th></tr></thead><tbody>';
    foreach ($previewUnits as [$code, $name, $year, $semester]) {
        $body .= '<tr><td>' . escape($code) . '</td><td>' . escape($name) . '</td><td>' . $year . '</td><td>' . $semester . '</td></tr>';
    }
    $body .= '</tbody></table></div>';
    $body .= '<div class="box">' . coursesTable($conn) . '</div>';
    $body .= '<div class="box">' . unitsTable($conn, $TARGET_COURSE_ID) . '</div>';

    page($body, 'bulk');
}