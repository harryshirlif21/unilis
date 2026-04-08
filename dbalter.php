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

if (isCli()) {
    renderDatabaseInfoText($conn);
} else {
    outputHtml(renderDatabaseInfoHtml($conn));
}
