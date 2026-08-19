<?php
require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available.');
}

function escape(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── DB Schema helpers ────────────────────────────────────────────────────────

function getDbName(mysqli $conn): string
{
    $res = $conn->query('SELECT DATABASE() AS db');
    $row = $res->fetch_assoc();
    return $row['db'] ?? '';
}

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

/**
 * Every foreign key in the database, both outgoing (this table's column
 * references another table) and — derived from the same result set —
 * incoming (which other tables point at this one). Reading this straight
 * out of information_schema means it's always accurate to the live schema,
 * never hand-maintained.
 *
 * Returns:
 * [
 *   'outgoing' => [ table_name => [ [column, constraint, ref_table, ref_column, on_update, on_delete], ... ] ],
 *   'incoming' => [ ref_table_name => [ [table, column, constraint, ref_column, on_update, on_delete], ... ] ],
 *   'flat'     => [ [table, column, constraint, ref_table, ref_column, on_update, on_delete], ... ]  // for a DB-wide list
 * ]
 */
function getAllForeignKeys(mysqli $conn, string $dbName): array
{
    $outgoing = [];
    $incoming = [];
    $flat     = [];

    $stmt = $conn->prepare(
        "SELECT
            kcu.TABLE_NAME        AS table_name,
            kcu.COLUMN_NAME       AS column_name,
            kcu.CONSTRAINT_NAME   AS constraint_name,
            kcu.REFERENCED_TABLE_NAME  AS ref_table,
            kcu.REFERENCED_COLUMN_NAME AS ref_column,
            rc.UPDATE_RULE        AS on_update,
            rc.DELETE_RULE        AS on_delete
         FROM information_schema.KEY_COLUMN_USAGE kcu
         JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
           ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
          AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
         WHERE kcu.TABLE_SCHEMA = ?
           AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME"
    );
    $stmt->bind_param('s', $dbName);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $table  = $r['table_name'];
        $refTbl = $r['ref_table'];

        $outgoing[$table][] = [
            'column'     => $r['column_name'],
            'constraint' => $r['constraint_name'],
            'ref_table'  => $refTbl,
            'ref_column' => $r['ref_column'],
            'on_update'  => $r['on_update'],
            'on_delete'  => $r['on_delete'],
        ];

        $incoming[$refTbl][] = [
            'table'      => $table,
            'column'     => $r['column_name'],
            'constraint' => $r['constraint_name'],
            'ref_column' => $r['ref_column'],
            'on_update'  => $r['on_update'],
            'on_delete'  => $r['on_delete'],
        ];

        $flat[] = [
            'table'      => $table,
            'column'     => $r['column_name'],
            'constraint' => $r['constraint_name'],
            'ref_table'  => $refTbl,
            'ref_column' => $r['ref_column'],
            'on_update'  => $r['on_update'],
            'on_delete'  => $r['on_delete'],
        ];
    }
    $stmt->close();

    return ['outgoing' => $outgoing, 'incoming' => $incoming, 'flat' => $flat];
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

function page(string $body): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>dbalter — DB Schema</title>
        <style>
            *{box-sizing:border-box;margin:0;padding:0}
            body{font-family:Segoe UI,Arial,sans-serif;background:#f4f5f7;color:#111;padding:24px;}
            .container{max-width:1200px;margin:0 auto;}
            h1{margin:0 0 8px;font-size:22px;}
            .subtitle{color:#6b7280;font-size:13px;margin-bottom:16px;}
            h2{margin:20px 0 8px;font-size:14px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;}
            table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:8px;}
            th{background:#1e3a5f;color:#fff;padding:7px 10px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.3px;}
            td{border-bottom:1px solid #e5e7eb;padding:6px 10px;}
            tr:hover td{background:#eff6ff;}
            .box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:20px;}
            a{color:#2563eb;font-size:13px;}
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
            .fk-badge{display:inline-block;background:#dbeafe;color:#1d4ed8;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:4px;}
            .summary-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px;}
            .summary-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:14px 16px;text-align:center;}
            .summary-card .num{font-size:24px;font-weight:700;color:#1e3a5f;}
            .summary-card .lbl{font-size:12px;color:#6b7280;margin-top:2px;}
            /* FK section styles */
            .fk-section{padding:10px 16px 16px;}
            .fk-section h4{font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#6b7280;margin-bottom:6px;}
            .fk-list{list-style:none;font-size:12.5px;}
            .fk-list li{padding:5px 0;border-bottom:1px dashed #eee;}
            .fk-list li:last-child{border-bottom:none;}
            .fk-arrow{color:#9ca3af;margin:0 4px;}
            .rule-badge{display:inline-block;font-size:10px;padding:1px 5px;border-radius:3px;margin-left:5px;background:#f3f4f6;color:#4b5563;}
            .rule-cascade{background:#fee2e2;color:#b91c1c;}
            .empty-note{color:#9ca3af;font-size:12px;font-style:italic;padding:4px 0;}
        </style>
    </head><body>
    <div class="container">
    <h1>dbalter</h1>
    <div class="subtitle">Database: unilis &nbsp;·&nbsp; ' . date('Y-m-d H:i:s') . '</div>
    ' . $body . '
    </div></body></html>';
}

function ruleBadge(string $rule): string
{
    $cls = in_array(strtoupper($rule), ['CASCADE', 'SET NULL']) ? ' rule-cascade' : '';
    return '<span class="rule-badge' . $cls . '">' . escape($rule) . '</span>';
}

/**
 * A DB-wide table of every foreign key relationship, for a fast top-level
 * scan before drilling into individual table cards.
 */
function fkOverviewTable(array $flatFks): string
{
    if (empty($flatFks)) {
        return '<div class="box"><h2>Foreign Keys</h2><p class="empty-note">No foreign key constraints found in this database.</p></div>';
    }

    $rows = '';
    foreach ($flatFks as $fk) {
        $rows .= '<tr>'
            . '<td><strong>' . escape($fk['table']) . '</strong></td>'
            . '<td>' . escape($fk['column']) . '</td>'
            . '<td class="fk-arrow">→</td>'
            . '<td><strong>' . escape($fk['ref_table']) . '</strong></td>'
            . '<td>' . escape($fk['ref_column']) . '</td>'
            . '<td>' . ruleBadge($fk['on_update']) . ' upd</td>'
            . '<td>' . ruleBadge($fk['on_delete']) . ' del</td>'
            . '<td class="null">' . escape($fk['constraint']) . '</td>'
            . '</tr>';
    }

    return '<div class="box">
        <h2>Foreign Keys (' . count($flatFks) . ' across the database)</h2>
        <table>
            <thead><tr>
                <th>Table</th><th>Column</th><th></th><th>References Table</th><th>References Column</th>
                <th>On Update</th><th>On Delete</th><th>Constraint</th>
            </tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>
    </div>';
}

function schemaPage(mysqli $conn): string
{
    $dbName = getDbName($conn);
    $tables = getAllTables($conn);
    $fks    = getAllForeignKeys($conn, $dbName);

    $totalRows = 0;
    $totalSize = 0;

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

        $cols = getTableColumns($conn, $tableName);
        $outgoingForTable = $fks['outgoing'][$tableName] ?? [];
        $incomingForTable = $fks['incoming'][$tableName] ?? [];

        // Index outgoing FKs by column name so the column list can flag them inline.
        $fkByColumn = [];
        foreach ($outgoingForTable as $fk) {
            $fkByColumn[$fk['column']] = $fk;
        }

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

            // If this column is a real foreign key (from information_schema,
            // not just a MUL-indexed column), show exactly what it points to.
            $fkBadge = '';
            if (isset($fkByColumn[$c['Field']])) {
                $target = $fkByColumn[$c['Field']];
                $fkBadge = ' <span class="fk-badge">FK → ' . escape($target['ref_table']) . '.' . escape($target['ref_column']) . '</span>';
            }

            $rows .= '<tr>
                <td class="' . $cls . '">' . $field . $icon . $extraBadge . $fkBadge . '</td>
                <td>' . $type . '</td>
                <td class="null">' . $null . '</td>
                <td>' . ($key ?: '—') . '</td>
                <td class="null">' . ($default === null ? 'NULL' : ($default === '' ? "''" : escape($default))) . '</td>
            </tr>';
        }

        // Outgoing FKs section: what this table's columns reference.
        $outgoingHtml = '';
        if (!empty($outgoingForTable)) {
            $items = '';
            foreach ($outgoingForTable as $fk) {
                $items .= '<li><strong>' . escape($fk['column']) . '</strong>'
                    . '<span class="fk-arrow">→</span>'
                    . escape($fk['ref_table']) . '.' . escape($fk['ref_column'])
                    . ruleBadge($fk['on_update']) . ' upd'
                    . ruleBadge($fk['on_delete']) . ' del'
                    . '</li>';
            }
            $outgoingHtml = '<div class="fk-section"><h4>References (outgoing foreign keys)</h4><ul class="fk-list">' . $items . '</ul></div>';
        }

        // Incoming FKs section: which other tables point at this one.
        $incomingHtml = '';
        if (!empty($incomingForTable)) {
            $items = '';
            foreach ($incomingForTable as $fk) {
                $items .= '<li>' . escape($fk['table']) . '.' . escape($fk['column'])
                    . '<span class="fk-arrow">→</span>'
                    . '<strong>' . escape($tableName) . '.' . escape($fk['ref_column']) . '</strong>'
                    . ruleBadge($fk['on_update']) . ' upd'
                    . ruleBadge($fk['on_delete']) . ' del'
                    . '</li>';
            }
            $incomingHtml = '<div class="fk-section"><h4>Referenced by (incoming foreign keys)</h4><ul class="fk-list">' . $items . '</ul></div>';
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
            ' . $outgoingHtml . $incomingHtml . '
        </div>';
    }

    $summaryGrid = '<div class="summary-grid">
        <div class="summary-card"><div class="num">' . count($tables) . '</div><div class="lbl">Tables</div></div>
        <div class="summary-card"><div class="num">' . number_format($totalRows) . '</div><div class="lbl">Total Rows</div></div>
        <div class="summary-card"><div class="num">' . count($fks['flat']) . '</div><div class="lbl">Foreign Keys</div></div>
        <div class="summary-card"><div class="num">' . formatBytes((string)$totalSize) . '</div><div class="lbl">Data + Index</div></div>
    </div>';

    $html = $summaryGrid;
    $html .= fkOverviewTable($fks['flat']);
    $html .= $tableCards;
    return $html;
}

// ── Main ──────────────────────────────────────────────────────────────────────

$body = schemaPage($conn);
page($body);