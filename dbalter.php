<?php
/**
 * dbalter.php
 *
 * Display database tables and schema information.
 * Starts output with the `courses` table, then shows all other tables.
 * Safe to run repeatedly; it does not modify data.
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

function fetchTables(mysqli $conn): array
{
    $tables = [];
    $result = $conn->query('SHOW TABLES');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $tables[] = $row[0];
        }
        $result->free();
    }
    return $tables;
}

function getRowCount(mysqli $conn, string $table): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM `{$table}`");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = 0;
    if ($row = $result->fetch_assoc()) {
        $count = (int)$row['total'];
    }
    $stmt->close();
    return $count;
}

function getTableColumns(mysqli $conn, string $table): array
{
    $columns = [];
    $stmt = $conn->prepare("DESCRIBE `{$table}`");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    $stmt->close();
    return $columns;
}

function getSampleRows(mysqli $conn, string $table, int $limit = 10): array
{
    $rows = [];
    $stmt = $conn->prepare("SELECT * FROM `{$table}` LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function formatValue($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }
    return (string)$value;
}

function renderDatabaseInfoText(mysqli $conn): void
{
    $databaseName = $conn->query('SELECT DATABASE()')->fetch_row()[0] ?? 'unknown';
    echo "Database: {$databaseName}\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

    $tables = fetchTables($conn);
    if (empty($tables)) {
        echo "No tables found in database.\n";
        return;
    }

    $priority = 'courses';
    if (in_array($priority, $tables, true)) {
        $tables = array_merge([$priority], array_values(array_diff($tables, [$priority])));
    }

    echo "Tables found: " . count($tables) . "\n";
    echo "  " . implode(', ', $tables) . "\n\n";

    foreach ($tables as $table) {
        try {
            $rowCount = getRowCount($conn, $table);
        } catch (Throwable $e) {
            echo "Failed to count rows for {$table}: " . $e->getMessage() . "\n\n";
            continue;
        }

        echo "════════════════════════════════════════════════════════════════════════\n";
        echo "Table: {$table}\n";
        echo "Rows : {$rowCount}\n";
        echo "════════════════════════════════════════════════════════════════════════\n";

        $columns = getTableColumns($conn, $table);
        if (empty($columns)) {
            echo "(No column metadata available)\n\n";
        } else {
            $format = "%-22s | %-20s | %-6s | %-3s | %-10s | %-10s\n";
            printf($format, 'Field', 'Type', 'Null', 'Key', 'Default', 'Extra');
            echo str_repeat('-', 82) . "\n";
            foreach ($columns as $column) {
                printf(
                    $format,
                    $column['Field'],
                    $column['Type'],
                    $column['Null'],
                    $column['Key'],
                    $column['Default'] === null ? 'NULL' : $column['Default'],
                    $column['Extra']
                );
            }
            echo "\n";
        }

        if ($rowCount > 0) {
            $rows = getSampleRows($conn, $table, 10);
            if (empty($rows)) {
                echo "(No sample rows to display)\n\n";
            } else {
                echo implode(' | ', array_keys($rows[0])) . "\n";
                echo str_repeat('-', max(80, strlen(implode(' | ', array_keys($rows[0]))))) . "\n";
                foreach ($rows as $row) {
                    echo implode(' | ', array_map(fn($value) => formatValue($value), $row)) . "\n";
                }
                echo "\n";
            }
        } else {
            echo "(Table contains no rows)\n\n";
        }
    }
}

function renderDatabaseInfoHtml(mysqli $conn): string
{
    $databaseName = escape($conn->query('SELECT DATABASE()')->fetch_row()[0] ?? 'unknown');
    $html = '';
    $html .= "<h1>Database Inspector</h1>";
    $html .= "<p><strong>Database:</strong> {$databaseName}<br><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";

    $tables = fetchTables($conn);
    if (empty($tables)) {
        return $html . '<p>No tables found in database.</p>';
    }

    $priority = 'courses';
    if (in_array($priority, $tables, true)) {
        $tables = array_merge([$priority], array_values(array_diff($tables, [$priority])));
    }

    $html .= "<p><strong>Tables found:</strong> " . count($tables) . "</p>";
    $html .= "<p>" . implode(', ', array_map('escape', $tables)) . "</p>";

    foreach ($tables as $table) {
        try {
            $rowCount = getRowCount($conn, $table);
        } catch (Throwable $e) {
            $html .= "<section><h2>Table: " . escape($table) . "</h2><p>Failed to count rows: " . escape($e->getMessage()) . "</p></section>";
            continue;
        }

        $html .= "<section>";
        $html .= "<h2>Table: " . escape($table) . "</h2>";
        $html .= "<p><strong>Rows:</strong> " . $rowCount . "</p>";

        $columns = getTableColumns($conn, $table);
        if (empty($columns)) {
            $html .= '<p>(No column metadata available)</p>';
        } else {
            $html .= '<table><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead><tbody>';
            foreach ($columns as $column) {
                $html .= '<tr>' .
                    '<td>' . escape($column['Field']) . '</td>' .
                    '<td>' . escape($column['Type']) . '</td>' .
                    '<td>' . escape($column['Null']) . '</td>' .
                    '<td>' . escape($column['Key']) . '</td>' .
                    '<td>' . escape($column['Default'] === null ? 'NULL' : $column['Default']) . '</td>' .
                    '<td>' . escape($column['Extra']) . '</td>' .
                '</tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($rowCount > 0) {
            $rows = getSampleRows($conn, $table, 10);
            if (empty($rows)) {
                $html .= '<p>(No sample rows to display)</p>';
            } else {
                $html .= '<table><thead><tr>';
                foreach (array_keys($rows[0]) as $header) {
                    $html .= '<th>' . escape($header) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    foreach ($row as $value) {
                        $html .= '<td>' . escape(formatValue($value)) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
            }
        } else {
            $html .= '<p>(Table contains no rows)</p>';
        }

        $html .= '</section>';
    }

    return $html;
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

function isInsertAction(): bool
{
    if (isCli()) {
        global $argv;
        return in_array('--insert-bsc-business-computing', $argv, true) || in_array('-i', $argv, true);
    }

    return ($_REQUEST['action'] ?? '') === 'insert_bsc_business_computing';
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
        return '<h1>Insert Units Failed</h1><p>' . escape($summary['error']) . '</p>';
    }

    return '<h1>Insert Units Result</h1>' .
        '<p><strong>Course ID:</strong> ' . escape((string)$summary['course_id']) . '<br>' .
        '<strong>Course name:</strong> ' . escape($summary['course_name']) . '<br>' .
        '<strong>Inserted units:</strong> ' . escape((string)$summary['inserted']) . '<br>' .
        '<strong>Skipped units:</strong> ' . escape((string)$summary['skipped']) . '<br>' .
        '<strong>Failed units:</strong> ' . escape((string)$summary['failed']) . '</p>';
}

function outputHtml(string $html): void
{
    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<title>DB Inspector</title>';
    echo '<style>';
    echo 'body{font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;color:#111;margin:0;padding:24px;}';
    echo 'h1,h2{margin:0 0 12px;}';
    echo 'section{margin-bottom:32px;padding:16px;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.05);}';
    echo 'table{width:100%;border-collapse:collapse;margin-top:12px;}';
    echo 'th,td{border:1px solid #d6d8db;padding:8px;text-align:left;vertical-align:top;}';
    echo 'th{background:#f0f2f5;font-weight:600;}';
    echo 'p{margin:.5em 0;}';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo $html;
    echo '</body>';
    echo '</html>';
}

if (isInsertAction()) {
    $result = insertBscBusinessComputingUnits($conn, 6);
    if (isCli()) {
        renderActionResultText($result);
    } else {
        outputHtml(renderActionResultHtml($result));
    }
    exit;
}

if (isCli()) {
    renderDatabaseInfoText($conn);
} else {
    outputHtml(renderDatabaseInfoHtml($conn));
}
