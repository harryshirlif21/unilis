<?php
require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available.');
}

function escape(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Add one unit ──────────────────────────────────────────────────────────────

function addUnit(mysqli $conn, int $courseId, string $code, string $name, int $year, int $semester): array
{
    // 1. Does the course exist?
    $stmt = $conn->prepare('SELECT name FROM courses WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$course) {
        return ['status' => 'error', 'message' => "Course ID {$courseId} not found."];
    }

    // 2. Does the unit already exist?
    $stmt = $conn->prepare('SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1');
    $stmt->bind_param('is', $courseId, $code);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        return ['status' => 'skipped', 'message' => "Unit \"{$code}\" already exists in \"{$course['name']}\"."];
    }

    // 3. Insert
    $stmt = $conn->prepare('INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssiii', $name, $code, $courseId, $year, $semester);
    $stmt->execute();
    $newId = $conn->insert_id;
    $stmt->close();

    return [
        'status'  => 'inserted',
        'message' => "Unit \"{$code} — {$name}\" added to \"{$course['name']}\" with ID {$newId}.",
        'unit_id' => $newId,
    ];
}

// ── Fetch tables ──────────────────────────────────────────────────────────────

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

// ── HTML helpers ──────────────────────────────────────────────────────────────

function coursesTable(mysqli $conn): string
{
    $courses = fetchCourses($conn);
    $counts  = fetchUnitCounts($conn);

    $html = '<h2>courses</h2><table><thead><tr><th>id</th><th>name</th><th>department_id</th><th>duration</th><th>units</th></tr></thead><tbody>';
    foreach ($courses as $c) {
        $id  = (int)$c['id'];
        $hl  = $id === 6 ? ' style="background:#fef9c3;"' : '';
        $html .= '<tr' . $hl . '>'
            . '<td>' . $id . '</td>'
            . '<td>' . escape($c['name']) . '</td>'
            . '<td>' . escape($c['department_id']) . '</td>'
            . '<td>' . escape($c['duration']) . '</td>'
            . '<td>' . ($counts[$id] ?? 0) . '</td>'
            . '</tr>';
    }
    return $html . '</tbody></table>';
}

function unitsTable(mysqli $conn, int $courseId, int $highlightId = 0): string
{
    $units = fetchUnits($conn, $courseId);
    $html  = '<h2>units &mdash; course_id = ' . $courseId . ' (' . count($units) . ' rows)</h2>';
    $html .= '<table><thead><tr><th>id</th><th>code</th><th>name</th><th>course_id</th><th>year</th><th>semester</th></tr></thead><tbody>';
    foreach ($units as $u) {
        $hl = (int)$u['id'] === $highlightId ? ' style="background:#dcfce7;font-weight:700;"' : '';
        $html .= '<tr' . $hl . '>'
            . '<td>' . escape((string)$u['id']) . '</td>'
            . '<td>' . escape($u['code']) . '</td>'
            . '<td>' . escape($u['name']) . '</td>'
            . '<td>' . escape((string)$u['course_id']) . '</td>'
            . '<td>' . escape((string)$u['year']) . '</td>'
            . '<td>' . escape((string)$u['semester']) . '</td>'
            . '</tr>';
    }
    return $html . '</tbody></table>';
}

function page(string $body): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>dbalter</title><style>'
        . 'body{font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;color:#111;padding:24px;max-width:1000px;margin:0 auto;}'
        . 'h1{margin:0 0 16px;}h2{margin:24px 0 8px;font-size:15px;color:#374151;}'
        . 'table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:24px;}'
        . 'th{background:#1e3a5f;color:#fff;padding:8px 10px;text-align:left;}'
        . 'td{border-bottom:1px solid #e5e7eb;padding:7px 10px;}'
        . 'tr:hover td{background:#eff6ff;}'
        . '.box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:20px;}'
        . '.banner{padding:10px 14px;border-radius:6px;margin-bottom:14px;font-weight:600;}'
        . '.ok{background:#dcfce7;color:#166534;} .warn{background:#fef9c3;color:#92400e;} .err{background:#fee2e2;color:#dc2626;}'
        . 'input,select{padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;width:100%;box-sizing:border-box;}'
        . 'label{font-size:13px;font-weight:600;display:block;margin-bottom:4px;}'
        . '.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:600px;}'
        . '.full{grid-column:1/3;}'
        . 'button{background:#16a34a;color:#fff;padding:9px 22px;border:none;border-radius:6px;font-size:14px;cursor:pointer;margin-top:4px;}'
        . 'a{color:#2563eb;font-size:13px;}'
        . '</style></head><body>' . $body . '</body></html>';
}

// ── Main ──────────────────────────────────────────────────────────────────────

$TARGET_COURSE_ID = 6; // Bsc Business Computing

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_unit') {
    $code     = trim($_POST['code']     ?? '');
    $name     = trim($_POST['name']     ?? '');
    $year     = (int)($_POST['year']     ?? 1);
    $semester = (int)($_POST['semester'] ?? 1);

    $result = addUnit($conn, $TARGET_COURSE_ID, $code, $name, $year, $semester);

    $bannerClass = match($result['status']) { 'inserted' => 'ok', 'skipped' => 'warn', default => 'err' };
    $icon        = match($result['status']) { 'inserted' => '✓', 'skipped' => '⚠', default => '✗' };
    $highlightId = (int)($result['unit_id'] ?? 0);

    $body  = '<h1>dbalter</h1>';
    $body .= '<div class="banner ' . $bannerClass . '">' . $icon . ' ' . escape($result['message']) . '</div>';
    $body .= '<div class="box">' . coursesTable($conn) . '</div>';
    $body .= '<div class="box">' . unitsTable($conn, $TARGET_COURSE_ID, $highlightId) . '</div>';
    $body .= '<p><a href="dbalter.php">← Back</a></p>';

    page($body);
    exit;
}

// Default: show form + tables
$body  = '<h1>dbalter</h1>';
$body .= '<div class="box"><h2>Add a unit to Bsc Business Computing (ID 6)</h2>'
    . '<form method="post"><input type="hidden" name="action" value="add_unit">'
    . '<div class="grid">'
    . '<div class="full"><label>Unit Code<input type="text" name="code" value="BBC 2101" required></label></div>'
    . '<div class="full"><label>Unit Name<input type="text" name="name" value="Business Studies for I.T." required></label></div>'
    . '<div><label>Year<select name="year"><option value="1" selected>1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option></select></label></div>'
    . '<div><label>Semester<select name="semester"><option value="1" selected>1</option><option value="2">2</option></select></label></div>'
    . '<div class="full"><button type="submit">+ Add Unit</button></div>'
    . '</div></form></div>';
$body .= '<div class="box">' . coursesTable($conn) . '</div>';
$body .= '<div class="box">' . unitsTable($conn, $TARGET_COURSE_ID) . '</div>';

page($body);