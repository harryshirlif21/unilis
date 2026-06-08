<?php
require_once __DIR__ . '/config/db.php';

$mysqli = getSmartLabConnection();
if (!$mysqli) {
    die('Unable to connect to Smart Lab database.');
}

function newUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function fetchOne(mysqli $db, string $sql, array $params = []): ?array {
    $stmt = $db->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function runQuery(mysqli $db, string $sql, array $params = []): bool {
    $stmt = $db->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    return $stmt->execute();
}

function createOrFetchLab(mysqli $db): array {
    $labCode = 'lab-chem-002';
    $existing = fetchOne($db, 'SELECT * FROM labs WHERE lab_code = ? LIMIT 1', [$labCode]);
    if ($existing) {
        return $existing;
    }

    $labId = newUuid();
    $sql = "INSERT INTO labs (id, lab_code, name, description, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())";
    runQuery($db, $sql, [$labId, $labCode, 'Chemistry Practice Lab', 'Chemistry practicals for experimental workflows']);
    return fetchOne($db, 'SELECT * FROM labs WHERE id = ? LIMIT 1', [$labId]);
}

function createOrFetchLecturer(mysqli $db, string $email, string $regNumber, string $fullName, ?string $labId): array {
    $existing = fetchOne($db, 'SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    if ($existing) {
        return $existing;
    }

    $lecturerId = newUuid();
    $passwordHash = password_hash('Chemistry123!', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (id, reg_number, full_name, email, password, role, lab_id, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'lecturer', ?, 1, NOW(), NOW())";
    runQuery($db, $sql, [$lecturerId, $regNumber, $fullName, $email, $passwordHash, $labId]);
    return fetchOne($db, 'SELECT * FROM users WHERE id = ? LIMIT 1', [$lecturerId]);
}

function createPracticalIfMissing(mysqli $db, array $lab, array $lecturer, array $practicalSpec): array {
    $existing = fetchOne($db, 'SELECT * FROM practicals WHERE title = ? AND scheduled_date = ? LIMIT 1', [$practicalSpec['title'], $practicalSpec['scheduled_date']]);
    if ($existing) {
        return $existing;
    }

    $practicalId = newUuid();
    $sql = "INSERT INTO practicals (id, title, lab_id, lecturer_id, created_at, description, course_code, scheduled_date, start_time, end_time, max_students, required_equipment, required_chemicals, safety_notes, status, updated_at)
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())";
    runQuery($db, $sql, [
        $practicalId,
        $practicalSpec['title'],
        $lab['id'],
        $lecturer['id'],
        $practicalSpec['description'],
        $practicalSpec['course_code'],
        $practicalSpec['scheduled_date'],
        $practicalSpec['start_time'],
        $practicalSpec['end_time'],
        (string)$practicalSpec['max_students'],
        $practicalSpec['required_equipment'],
        $practicalSpec['required_chemicals'],
        $practicalSpec['safety_notes']
    ]);

    return fetchOne($db, 'SELECT * FROM practicals WHERE id = ? LIMIT 1', [$practicalId]);
}

function createClosedSessionIfMissing(mysqli $db, array $practical): array {
    $existing = fetchOne($db, 'SELECT * FROM lab_sessions WHERE practical_id = ? LIMIT 1', [$practical['id']]);
    if ($existing) {
        return $existing;
    }

    $sessionId = newUuid();
    $sql = "INSERT INTO lab_sessions (id, practical_id, lab_id, started_at, status)
            VALUES (?, ?, ?, CONCAT(?, ' ', ?), 'closed')";
    $startedAt = $practical['scheduled_date'];
    runQuery($db, $sql, [$sessionId, $practical['id'], $practical['lab_id'], $startedAt, $practical['start_time'] ?: '09:00:00']);

    return fetchOne($db, 'SELECT * FROM lab_sessions WHERE id = ? LIMIT 1', [$sessionId]);
}

function enrollStudentsForPractical(mysqli $db, array $session, array $practical): int {
    $students = [];
    $result = $db->query("SELECT id, reg_number, full_name FROM users WHERE role = 'student' AND is_active = 1 LIMIT 10");
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $inserted = 0;
    foreach ($students as $student) {
        $exists = fetchOne($db, 'SELECT * FROM student_practicals WHERE student_id = ? AND practical_id = ? LIMIT 1', [$student['id'], $practical['id']]);
        if ($exists) {
            continue;
        }

        runQuery($db, 'INSERT INTO student_practicals (student_id, practical_id, enrolled_at, status) VALUES (?, ?, NOW(), "completed")', [$student['id'], $practical['id']]);
        $inserted++;
    }

    return $inserted;
}

function createNotebookForStudent(mysqli $db, array $session, array $student, array $practical): ?array {
    $existing = fetchOne($db, 'SELECT * FROM notebooks WHERE student_id = ? AND session_id = ? LIMIT 1', [$student['id'], $session['id']]);
    if ($existing) {
        return $existing;
    }

    $notebookId = newUuid();
    $title = 'Chemistry Practical Notebook';
    $content = "Student notebook created for {$practical['title']} on {$practical['scheduled_date']}.\n\nObservations:\n- Experiment setup established.\n- Measurements recorded.\n- Analysis pending.";

    runQuery($db, 'INSERT INTO notebooks (id, session_id, student_id, title, content, version, status, created_at, updated_at, created_by, creator_role) VALUES (?, ?, ?, ?, ?, 1, "submitted", NOW(), NOW(), ?, "student")', [$notebookId, $session['id'], $student['id'], $title, $content, $student['id']]);
    return fetchOne($db, 'SELECT * FROM notebooks WHERE id = ? LIMIT 1', [$notebookId]);
}

$lab = createOrFetchLab($mysqli);
$lecturer = createOrFetchLecturer($mysqli, 'chemistry-lecturer@unilis.edu', 'LECT-CHM-001', 'Dr. Chemistry', $lab['id']);

$practicals = [
    [
        'title' => 'Stoichiometry and Titration Analysis',
        'description' => 'Determine concentration using acid-base titration and analyze stoichiometric ratios.',
        'course_code' => 'CHEM201',
        'scheduled_date' => date('Y-m-d', strtotime('+5 days')),
        'start_time' => '09:00:00',
        'end_time' => '11:30:00',
        'max_students' => 24,
        'required_equipment' => 'Burettes, pipettes, volumetric flasks, indicators',
        'required_chemicals' => 'Hydrochloric acid, sodium hydroxide, methyl orange',
        'safety_notes' => 'Wear gloves, goggles and laboratory coat. Handle acids with care.'
    ],
    [
        'title' => 'Qualitative Analysis of Common Ions',
        'description' => 'Perform systematic qualitative tests to identify cations and anions in unknown samples.',
        'course_code' => 'CHEM202',
        'scheduled_date' => date('Y-m-d', strtotime('+7 days')),
        'start_time' => '13:00:00',
        'end_time' => '15:30:00',
        'max_students' => 24,
        'required_equipment' => 'Test tubes, droppers, spotting tile, heating source',
        'required_chemicals' => 'Silver nitrate, hydrochloric acid, sodium carbonate solutions',
        'safety_notes' => 'Use eye protection and rinse spills immediately. Do not inhale reagent vapours.'
    ]
];

$createdPracticals = [];
foreach ($practicals as $spec) {
    $createdPracticals[] = createPracticalIfMissing($mysqli, $lab, $lecturer, $spec);
}

$report = [];
foreach ($createdPracticals as $practical) {
    $session = createClosedSessionIfMissing($mysqli, $practical);
    $added = enrollStudentsForPractical($mysqli, $session, $practical);
    $report[] = sprintf('Practical "%s" ready with session %s and %d student enrollments', $practical['title'], $session['id'], $added);

    $students = [];
    $result = $mysqli->query('SELECT id, full_name FROM users WHERE role = "student" AND is_active = 1 LIMIT 5');
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    foreach ($students as $student) {
        createNotebookForStudent($mysqli, $session, $student, $practical);
    }
}

echo "Chemistry practical setup complete.\n";
foreach ($report as $line) {
    echo "- {$line}\n";
}

exit(0);
