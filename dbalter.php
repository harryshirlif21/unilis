<?php
/**
 * dbalter.php  —  v2 (fixed after DB audit)
 * ==========================================
 * Inserts the 4 JKUAT IT-school courses + their 181 units.
 *
 * FIXES vs v1
 * -----------
 * 1. The 4 courses did NOT exist in the DB at all — this script inserts them
 *    first (into `courses`, linked to department_id=2 "information technology"
 *    at JKUAT, university_id=7).  Already-present courses are skipped.
 * 2. Course lookup now uses the exact names inserted here, so name-mismatch
 *    "Course NOT FOUND" errors can no longer happen.
 * 3. Schema confirmed: units(id, name, code, course_id, year, semester) — matches.
 *    Longest unit name is 57 chars — safe inside VARCHAR(100).
 *
 * HOW TO USE
 * ----------
 * 1. Place this file in your project root (same level as config/).
 * 2. Open in browser  OR  run:  php dbalter.php
 * 3. Safe to re-run — existing courses/units are skipped, nothing duplicated.
 * 4. DELETE this file from the server when done.
 */

require_once __DIR__ . '/config/db.php';   // provides $conn (mysqli)

// ─── Config ──────────────────────────────────────────────────────────────────
// Department id=2 = "information technology" at JKUAT (university_id=7) in your DB
const DEPT_ID = 2;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getOrCreateCourse(mysqli $conn, string $name, int $deptId, int $duration): array {
    $stmt = $conn->prepare("SELECT id FROM courses WHERE name = ? AND department_id = ? LIMIT 1");
    $stmt->bind_param('si', $name, $deptId);
    $stmt->execute();
    $stmt->bind_result($id);
    $found = $stmt->fetch();
    $stmt->close();

    if ($found) {
        return ['id' => (int)$id, 'status' => 'exists'];
    }

    $ins = $conn->prepare("INSERT INTO courses (name, department_id, duration) VALUES (?, ?, ?)");
    $ins->bind_param('sii', $name, $deptId, $duration);
    $ins->execute();
    $newId = (int)$conn->insert_id;
    $ins->close();
    return ['id' => $newId, 'status' => 'inserted'];
}

function insertUnit(mysqli $conn, int $courseId, string $code, string $name, int $year, int $semester): string {
    $chk = $conn->prepare("SELECT id FROM units WHERE course_id = ? AND code = ? LIMIT 1");
    $chk->bind_param('is', $courseId, $code);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); return 'skipped'; }
    $chk->close();

    $ins = $conn->prepare("INSERT INTO units (name, code, course_id, year, semester) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param('ssiii', $name, $code, $courseId, $year, $semester);
    $ins->execute();
    $ok = $ins->affected_rows > 0;
    $ins->close();
    return $ok ? 'inserted' : 'failed';
}

// ─── Course definitions ───────────────────────────────────────────────────────
// [ display_name => duration_years ]
$courseDefs = [
    'Certificate in Information Technology' => 1,
    'Diploma in Information Technology'     => 2,
    'B.Sc. Information Technology'          => 4,
    'B.Sc. Business Computing'              => 4,
];

function normalizeSearchName(string $name): string {
    $clean = preg_replace('/[^a-z0-9 ]+/i', ' ', $name);
    $clean = preg_replace('/\s+/', ' ', trim($clean));
    return '%' . strtolower($clean) . '%';
}

function fetchCourseById(mysqli $conn, int $courseId): ?array {
    $stmt = $conn->prepare('SELECT id, name, department_id FROM courses WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $course;
}

function findDuplicateCourses(mysqli $conn, string $searchTerm, int $departmentId, int $excludeId): array {
    $like = normalizeSearchName($searchTerm);
    $stmt = $conn->prepare(
        'SELECT c.id, c.name, (SELECT COUNT(*) FROM units u WHERE u.course_id = c.id) AS units_count
         FROM courses c
         WHERE c.department_id = ? AND LOWER(c.name) LIKE ? AND c.id != ?
         ORDER BY units_count DESC, c.id ASC'
    );
    $stmt->bind_param('isi', $departmentId, $like, $excludeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
    return $courses;
}

function reassignUnits(mysqli $conn, int $targetCourseId, array $sourceCourseIds): int {
    if (empty($sourceCourseIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($sourceCourseIds), '?'));
    $types = str_repeat('i', count($sourceCourseIds) + 1);
    $sql = "UPDATE units SET course_id = ? WHERE course_id IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    $params = array_merge([$targetCourseId], $sourceCourseIds);
    $bindNames = [];
    $bindNames[] = &$types;
    foreach ($params as $key => $value) {
        $bindNames[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindNames);
    $stmt->execute();
    $moved = $stmt->affected_rows;
    $stmt->close();
    return $moved;
}

function cleanupEmptyDuplicateCourses(mysqli $conn, array $courseIds): array {
    if (empty($courseIds)) {
        return ['deleted' => 0, 'kept' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    $types = str_repeat('i', count($courseIds));
    $sql = "DELETE FROM courses WHERE id IN ($placeholders)
            AND NOT EXISTS (SELECT 1 FROM units u WHERE u.course_id = courses.id)
            AND NOT EXISTS (SELECT 1 FROM students s WHERE s.course_id = courses.id)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    $bindNames = [];
    $bindNames[] = &$types;
    foreach ($courseIds as $key => $value) {
        $bindNames[] = &$courseIds[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindNames);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    return ['deleted' => $deleted, 'kept' => count($courseIds) - $deleted];
}

$requestedAction = $_REQUEST['action'] ?? null;
if ($requestedAction === 'reassign_units') {
    $targetCourseId = intval($_REQUEST['target_course_id'] ?? 0);
    $searchTerm = trim((string)($_REQUEST['search_term'] ?? ''));

    if ($targetCourseId <= 0) {
        exit('Error: target_course_id is required.');
    }

    $targetCourse = fetchCourseById($conn, $targetCourseId);
    if (!$targetCourse) {
        exit('Error: target course not found.');
    }

    if ($searchTerm === '') {
        $searchTerm = $targetCourse['name'];
    }

    $duplicates = findDuplicateCourses($conn, $searchTerm, (int)$targetCourse['department_id'], $targetCourseId);
    if (empty($duplicates)) {
        exit('No duplicate courses found matching "' . htmlspecialchars($searchTerm) . '" excluding target course.');
    }

    $duplicateIds = array_column($duplicates, 'id');
    $coursesAffected = array_map(fn($row) => sprintf('[id=%d] %s (%d units)', $row['id'], $row['name'], $row['units_count']), $duplicates);

    $conn->begin_transaction();
    try {
        $movedCount = reassignUnits($conn, $targetCourseId, $duplicateIds);
        $cleanup = cleanupEmptyDuplicateCourses($conn, $duplicateIds);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        exit('Transaction failed: ' . $e->getMessage());
    }

    $output = "Reassign Units Report:\n";
    $output .= "Target course: {$targetCourse['name']} (id={$targetCourseId})\n";
    $output .= "Search term: {$searchTerm}\n";
    $output .= "Duplicate courses affected:\n" . implode("\n", $coursesAffected) . "\n";
    $output .= "Units moved: {$movedCount}\n";
    $output .= "Duplicate courses deleted: {$cleanup['deleted']}\n";
    $output .= "Duplicate courses kept (non-empty / student-linked): {$cleanup['kept']}\n";
    $output .= "\nMigration complete.\n";

    if (php_sapi_name() !== 'cli') {
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
    } else {
        echo $output;
    }
    exit;
}

// ─── Curriculum data ─────────────────────────────────────────────────────────
// [ course_name => [ [code, name, year, semester], ... ] ]

$curriculum = [

    // ═══════════════════════════════════════════════════════════════════════
    // Certificate in Information Technology  (SCT021)
    // ═══════════════════════════════════════════════════════════════════════
    'Certificate in Information Technology' => [
        // Year 1 – Semester 1
        ['CIT 0101', 'Introduction to Computer Systems',              1, 1],
        ['CIT 0102', 'Elementary Computer Mathematics',               1, 1],
        ['CIT 0104', 'Computer Applications',                         1, 1],
        ['CIT 0105', 'Introduction to Databases and Data Gathering',  1, 1],
        ['CIT 0106', 'Introduction to Internet Applications',         1, 1],
        ['CIT 0107', 'Principles of Electronics',                     1, 1],
        ['CIT 0110', 'Introduction to Computer Operating Systems',    1, 1],
        ['TDH 1100', 'Introduction to HIV/AIDS',                      1, 1],
        // Year 1 – Semester 2
        ['CIT 0103', 'Principles of Programming Languages',           1, 2],
        ['CIT 0108', 'Elementary Computer Support and Maintenance',   1, 2],
        ['CIT 0109', 'Networking Skills and Technologies',            1, 2],
        ['CIT 0111', 'Entrepreneurship and Life Skills',              1, 2],
        ['CIT 0112', 'Introduction to Application Programming',       1, 2],
        ['CIT 0113', 'Trade Project',                                 1, 2],
        ['HRD 0101', 'Communication Skills',                          1, 2],
    ],

    // ═══════════════════════════════════════════════════════════════════════
    // Diploma in Information Technology  (SCT121)
    // ═══════════════════════════════════════════════════════════════════════
    'Diploma in Information Technology' => [
        // Year 1 – Semester 1
        ['DIT 0106', 'Basic Mathematics for IT',                       1, 1],
        ['HRD 0101', 'Communication Skills',                           1, 1],
        ['DIT 0103', 'Computer Operating Systems',                     1, 1],
        ['DIT 0102', 'Introduction to Computer Applications',          1, 1],
        ['DIT 0101', 'Introduction to Computers',                      1, 1],
        ['DIT 0108', 'Introduction to Database Management Systems',    1, 1],
        ['TDH 1100', 'Introduction to HIV/AIDS',                       1, 1],
        // Year 1 – Semester 2
        ['DIT 0206', 'Analogue Electronics',                           1, 2],
        ['CED 0217', 'Entrepreneurship Skills',                        1, 2],
        ['DIT 0207', 'Introduction to Programming and Algorithms',     1, 2],
        ['DIT 0204', 'Network Essentials',                             1, 2],
        ['DIT 0205', 'Principles of Computer Support and Maintenance', 1, 2],
        ['DIT 0208', 'Programming Desktop Applications',               1, 2],
        ['DIT 0209', 'Web Design and Development I',                   1, 2],
        // Year 2 – Semester 1
        ['DIT 0313', 'Advanced Computer Operating Systems',            2, 1],
        ['DIT 0311', 'Cloud Computing',                                2, 1],
        ['DIT 0309', 'Desktop Publishing',                             2, 1],
        ['DIT 0306', 'ICT Project I',                                  2, 1],
        ['DIT 0308', 'Industrial Attachment',                          2, 1],
        ['DIT 0312', 'Introduction to Cybersecurity',                  2, 1],
        ['DIT 0301', 'Object-Oriented Programming I',                  2, 1],
        ['DIT 0310', 'System Analysis and Design',                     2, 1],
        // Year 2 – Semester 2  (core)
        ['DIT 0421', 'ICT Project II',                                 2, 2],
        ['DIT 0419', 'Object-Oriented Analysis and Design',            2, 2],
        ['DIT 0422', 'Professional Issues in Information Technology',  2, 2],
        ['DIT 0420', 'Software Engineering',                           2, 2],
        // Year 2 – Semester 2  (electives)
        ['DIT 0425', 'Advanced Computer Support and Upgrading',        2, 2],
        ['DIT 0424', 'Advanced Event-Driven Programming',              2, 2],
        ['DIT 0423', 'Advanced Object-Oriented Programming',           2, 2],
        ['DIT 0417', 'Computer Animation',                             2, 2],
        ['DIT 0429', 'Computer Graphics',                              2, 2],
        ['DIT 0407', 'Database Management Systems',                    2, 2],
        ['DIT 0427', 'Digital Photography and Video Editing',          2, 2],
        ['DIT 0426', 'Mobile Computing',                               2, 2],
        ['DIT 0428', 'Multi-Media Applications',                       2, 2],
        ['DIT 0408', 'Network Design and Administration',              2, 2],
    ],

    // ═══════════════════════════════════════════════════════════════════════
    // B.Sc. Information Technology  (SCT221)
    // ═══════════════════════════════════════════════════════════════════════
    'B.Sc. Information Technology' => [
        // Year 1 – Semester 1
        ['CILS 2101', 'Communication and Information Literacy Skills', 1, 1],
        ['ICS 2109',  'Computer Operating Systems',                    1, 1],
        ['BBC 2105',  'Essentials of Economics',                       1, 1],
        ['BBC 2104',  'Hardware Systems Support and Maintenance',      1, 1],
        ['SZL 2111',  'HIV/AIDS',                                      1, 1],
        ['HBC 2128',  'Introduction to Accounting 1',                  1, 1],
        ['BIT 2103',  'Introduction to Computer Applications',         1, 1],
        ['BIT 2104',  'Introduction to Programming',                   1, 1],
        ['SMA 2104',  'Mathematics for Sciences',                      1, 1],
        // Year 1 – Semester 2
        ['SDS 2107',  'Algebra for Data Science',                      1, 2],
        ['ICS 2200',  'Analogue Electronics',                          1, 2],
        ['BIT 2212',  'Business Systems Modelling',                    1, 2],
        ['BIT 2225',  'Cloud Computing',                               1, 2],
        ['BIT 2123',  'Computer Network, Design and Management',       1, 2],
        ['HRD 2102',  'Development Studies and Social Ethics',         1, 2],
        ['SMA 2100',  'Discrete Mathematics',                          1, 2],
        ['BIT 2112',  'Systems Analysis and Design',                   1, 2],
        // Year 2 – Semester 1
        ['SMA 2101',  'Calculus I',                                    2, 1],
        ['BIT 2324',  'Geographical Information Systems',              2, 1],
        ['ICS 2206',  'Introduction to Database Management Systems',   2, 1],
        ['BIT 2223',  'Mobile and Wireless Computing',                 2, 1],
        ['ICS 2104',  'Object Oriented Programming I',                 2, 1],
        ['BIT 2214',  'Object-Oriented Analysis and Design',           2, 1],
        ['ICS 2302',  'Software Engineering I',                        2, 1],
        ['ICS 2203',  'Web Application Development I',                 2, 1],
        // Year 2 – Semester 2
        ['BIT 2118',  'Application Programming I',                     2, 2],
        ['SMA 2102',  'Calculus II',                                   2, 2],
        ['ICS 2105',  'Data Structures and Algorithms',                2, 2],
        ['ICS 2205',  'Digital Logic',                                 2, 2],
        ['BIT 2122',  'Industrial Attachment',                         2, 2],
        ['BIT 2204',  'Network Systems Administration',                2, 2],
        ['ICS 2201',  'Object Oriented Programming II',                2, 2],
        ['STA 2100',  'Probability and Statistics I',                  2, 2],
        ['BIT 2207',  'Web Design and Development II',                 2, 2],
        // Year 3 – Semester 1
        ['ICS 2404',  'Advanced Database Management System',           3, 1],
        ['BIT 2203',  'Advanced Programming',                          3, 1],
        ['BIT 2323',  'Application Programming II',                    3, 1],
        ['BIT 2111',  'Computer Aided Design',                         3, 1],
        ['ICS 2301',  'Design and Analysis of Algorithms',             3, 1],
        ['BIT 2320',  'Mobile Application Development',                3, 1],
        ['BIT 2321',  'Software Engineering II',                       3, 1],
        // Year 3 – Semester 2
        ['BIT 2319',  'Artificial Intelligence',                       3, 2],
        ['BIT 2328',  'Cryptography and Blockchain Applications',      3, 2],
        ['BIT 2326',  'Internet of Things (IoT) and Embedded Systems', 3, 2],
        ['BIT 2327',  'Introduction to Cyber Security',                3, 2],
        ['STA 2200',  'Probability and Statistics II',                 3, 2],
        ['BIT 2301',  'Research Methodology',                          3, 2],
        ['BIT 2215',  'Software Project Management',                   3, 2],
        ['ICS 2305',  'Systems Programming',                           3, 2],
        // Year 4 – Semester 1
        ['BIT 2317',  'Computer Systems Security',                     4, 1],
        ['ICS 2403',  'Distributed Systems',                           4, 1],
        ['BIT 2210',  'Fundamentals of Business Intelligence',         4, 1],
        ['BIT 2305',  'Human Computer Interactions',                   4, 1],
        ['HSC 2408',  'Innovation and Technology Transfer',            4, 1],
        ['BIT 2400',  'Introduction to Functional Programming',        4, 1],
        ['ICS 2405',  'Knowledge Based Systems',                       4, 1],
        ['BIT 2303',  'Research Project',                              4, 1],
        // Year 4 – Semester 2
        ['BIT 2401',  'Advanced Business Intelligence',                4, 2],
        ['BIT 2402',  'Enterprise Systems Applications and Architecture', 4, 2],
        ['HRD 2401',  'Entrepreneurship Skills',                       4, 2],
        ['BIT 2318',  'Information System Audit',                      4, 2],
        ['ICS 2303',  'Multimedia Systems and Applications',           4, 2],
        ['HBC 2112',  'Principles of Marketing',                       4, 2],
        ['BIT 2313',  'Professional Issues in ICT',                    4, 2],
    ],

    // ═══════════════════════════════════════════════════════════════════════
    // B.Sc. Business Computing  (SCT222)
    // ═══════════════════════════════════════════════════════════════════════
    'B.Sc. Business Computing' => [
        // Year 1 – Semester 1
        ['BBC 2101',  'Business Studies for I.T.',                     1, 1],
        ['BIT 2103',  'Introduction to Computer Applications',         1, 1],
        ['BIT 2104',  'Introduction to Programming',                   1, 1],
        ['CILS 2101', 'Communication and Information Literacy Skills', 1, 1],
        ['HBC 2128',  'Introduction to Accounting 1',                  1, 1],
        ['HBC 2215',  'Essentials of Economics',                       1, 1],
        ['ICS 2109',  'Computer Operating Systems',                    1, 1],
        ['SMA 2104',  'Mathematics for Sciences',                      1, 1],
        ['SZL 2111',  'HIV/AIDS',                                      1, 1],
        // Year 1 – Semester 2
        ['BBC 2102',  'Computer Networks, Design and Management',      1, 2],
        ['BBC 2103',  'Hardware Systems Support and Maintenance',      1, 2],
        ['BIT 2112',  'Systems Analysis and Design',                   1, 2],
        ['HBC 2107',  'Introduction to Accounting II',                 1, 2],
        ['HRD 2102',  'Development Studies and Social Ethics',         1, 2],
        ['ICS 2206',  'Introduction to Database Management Systems',   1, 2],
        ['SDS 2107',  'Algebra for Data Science',                      1, 2],
        ['STA 2100',  'Probability and Statistics I',                  1, 2],
        // Year 2 – Semester 1
        ['BBC 2201',  'Enterprise Network Systems Administration and Management', 2, 1],
        ['BBC 2202',  'Web Development Fundamentals',                  2, 1],
        ['BIT 2214',  'Object-Oriented Analysis and Design',           2, 1],
        ['BIT 2223',  'Mobile and Wireless Computing',                 2, 1],
        ['HPS 2301',  'Operations Management',                         2, 1],
        ['HSC 2408',  'Innovation and Technology Transfer',            2, 1],
        ['ICS 2105',  'Data Structures and Algorithms',                2, 1],
        ['SMA 2101',  'Calculus I',                                    2, 1],
        // Year 2 – Semester 2
        ['BBC 2203',  'Software Engineering',                          2, 2],
        ['BBC 2204',  'Object-Oriented Programming',                   2, 2],
        ['BBC 2205',  'Cloud and Edge Computing',                      2, 2],
        ['BBC 2206',  'Introduction to Data Science',                  2, 2],
        ['BBC 2207',  'Design Thinking',                               2, 2],
        ['BBC 2208',  'Business Computing Project in Industry',        2, 2],
        ['HBC 2202',  'Introduction to Financial Management',          2, 2],
        ['SMA 2100',  'Discrete Mathematics',                          2, 2],
        ['SMA 2102',  'Calculus II',                                   2, 2],
        // Year 3 – Semester 1
        ['BBC 2301',  'Enterprise Web Application Development',        3, 1],
        ['BBC 2302',  'Principles of Data Analytics',                  3, 1],
        ['BBC 2303',  'Enterprise Resource Planning Systems',          3, 1],
        ['BBC 2304',  'Computer Graphics and Multimedia',              3, 1],
        ['BIT 2319',  'Artificial Intelligence',                       3, 1],
        ['ICS 2301',  'Design and Analysis of Algorithms',             3, 1],
        ['ICS 2404',  'Advanced Database Management System',           3, 1],
        ['STA 2200',  'Probability and Statistics II',                 3, 1],
        // Year 3 – Semester 2
        ['BBC 2305',  'Machine Learning',                              3, 2],
        ['BBC 2306',  'Information Analysis and Visualization',        3, 2],
        ['BBC 2307',  'Statistical Computing',                         3, 2],
        ['BIT 2122',  'Industrial Attachment',                         3, 2],
        ['BIT 2215',  'Software Project Management',                   3, 2],
        ['BIT 2301',  'Research Methodology',                          3, 2],
        ['BIT 2305',  'Human Computer Interactions',                   3, 2],
        ['BIT 2317',  'Fundamentals of Computer Security',             3, 2],
        ['BIT 2320',  'Mobile Application Development',                3, 2],
        // Year 4 – Semester 1
        ['BBC 2401',  'Software Architectures',                        4, 1],
        ['BBC 2402',  'Embedded Systems and Internet of Things (IoT)', 4, 1],
        ['BBC 2403',  'Digital Marketing Communication',               4, 1],
        ['BBC 2404',  'Deep Learning',                                 4, 1],
        ['BBC 2405',  'Business Data Mining and Warehousing',          4, 1],
        ['BBC 2406',  'Software Development Project',                  4, 1],
        ['BBC 2407',  'Animation and Augmented Reality',               4, 1],
        ['BIT 2318',  'Information System Audit',                      4, 1],
        // Year 4 – Semester 2
        ['BBC 2408',  'Business Decision Support Systems',             4, 2],
        ['BBC 2409',  'Text Mining and Web Analytics',                 4, 2],
        ['BBC 2410',  'Business Analysis and Process Modeling',        4, 2],
        ['BIT 2313',  'Professional Issues in ICT',                    4, 2],
        ['BIT 2403',  'Business Development and Entrepreneurship',     4, 2],
        ['HBC 2209',  'Organizational Behaviour',                      4, 2],
        ['HBC 2401',  'Management Accounting',                         4, 2],
    ],

]; // end $curriculum

// ─── Run ─────────────────────────────────────────────────────────────────────

$totals = ['courses_inserted' => 0, 'courses_existed' => 0,
           'units_inserted'   => 0, 'units_skipped'   => 0, 'units_failed' => 0];
$log = [];

foreach ($curriculum as $courseName => $units) {
    $duration = $courseDefs[$courseName];
    $result   = getOrCreateCourse($conn, $courseName, DEPT_ID, $duration);
    $courseId = $result['id'];

    if ($result['status'] === 'inserted') {
        $totals['courses_inserted']++;
        $log[] = "📗 Course CREATED (id=$courseId): \"$courseName\"";
    } else {
        $totals['courses_existed']++;
        $log[] = "📘 Course exists  (id=$courseId): \"$courseName\"";
    }

    foreach ($units as [$code, $name, $year, $semester]) {
        $r = insertUnit($conn, $courseId, $code, $name, $year, $semester);
        $totals["units_{$r}"]++;
        $icon  = match($r) { 'inserted' => '  ✅', 'skipped' => '  ⚠️ ', default => '  ❌' };
        $log[] = "$icon [$r] Y{$year}S{$semester} | $code | $name";
    }
}

// ─── Output ──────────────────────────────────────────────────────────────────

$isCli = php_sapi_name() === 'cli';
if (!$isCli) { echo '<pre style="font-family:monospace;font-size:13px;background:#1e1e1e;color:#d4d4d4;padding:20px;border-radius:8px;">'; }

echo "╔══════════════════════════════════════════════════╗\n";
echo "║       UNILIS – Course + Unit Insertion v2        ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

foreach ($log as $line) { echo $line . "\n"; }

echo "\n═══════════════════════ SUMMARY ═══════════════════\n";
echo "  Courses created : {$totals['courses_inserted']}\n";
echo "  Courses existed : {$totals['courses_existed']}\n";
echo "  ✅ Units inserted: {$totals['units_inserted']}\n";
echo "  ⚠️  Units skipped : {$totals['units_skipped']} (already existed)\n";
echo "  ❌ Units failed  : {$totals['units_failed']}\n";
echo "═══════════════════════════════════════════════════\n";
echo "\n⚠️  DELETE this file from the server when done!\n";

if (!$isCli) { echo '</pre>'; }