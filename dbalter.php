<?php
session_start();
require_once __DIR__ . "/config/db.php";

/**
 * Display table structure
 */
function display_table_structure($conn, $table_name) {
    echo "<h3>Structure</h3>";

    $res = $conn->query("DESCRIBE `$table_name`");
    $rows = $res->fetch_all(MYSQLI_ASSOC);

    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>
            </tr>";

    foreach ($rows as $r) {
        echo "<tr>
                <td>{$r['Field']}</td>
                <td>{$r['Type']}</td>
                <td>{$r['Null']}</td>
                <td>{$r['Key']}</td>
                <td>{$r['Default']}</td>
                <td>{$r['Extra']}</td>
              </tr>";
    }

    echo "</table>";
}

/**
 * Display foreign key relationships of table
 */
function display_table_relationships($conn, $db_name, $table_name) {
    echo "<h3>Foreign Key Relationships</h3>";

    $sql = "
        SELECT 
            k.COLUMN_NAME,
            k.REFERENCED_TABLE_NAME,
            k.REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE k
        WHERE 
            k.TABLE_SCHEMA = '$db_name'
            AND k.TABLE_NAME = '$table_name'
            AND k.REFERENCED_TABLE_NAME IS NOT NULL;
    ";

    $res = $conn->query($sql);

    if ($res->num_rows === 0) {
        echo "<p>No foreign keys</p>";
        return;
    }

    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>Column</th>
                <th>References Table</th>
                <th>References Column</th>
            </tr>";

    while ($row = $res->fetch_assoc()) {
        echo "<tr>
                <td>{$row['COLUMN_NAME']}</td>
                <td>{$row['REFERENCED_TABLE_NAME']}</td>
                <td>{$row['REFERENCED_COLUMN_NAME']}</td>
              </tr>";
    }

    echo "</table>";
}

/**
 * Display table data
 */
function display_table_data($conn, $table_name) {
    echo "<h3>Data</h3>";

    $res = $conn->query("SELECT * FROM `$table_name`");

    if ($res->num_rows === 0) {
        echo "<p>No data</p>";
        return;
    }

    $rows = $res->fetch_all(MYSQLI_ASSOC);

    echo "<pre>";
    print_r($rows);
    echo "</pre>";
}

/**
 * Display entire table info
 */
function display_table_info($conn, $db_name, $table_name) {
    echo "<hr>";
    echo "<h2>Table: $table_name</h2>";

    display_table_structure($conn, $table_name);
    display_table_relationships($conn, $db_name, $table_name);
    display_table_data($conn, $table_name);
}

// Fetch tables
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$tables_res = $conn->query("SHOW TABLES");

$tables = [];
while ($row = $tables_res->fetch_row()) {
    $tables[] = $row[0];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Inspection: <?= htmlspecialchars($db_name) ?></title>
</head>
<body>
    <h1>Database: <?= htmlspecialchars($db_name) ?></h1>
    <p>Total tables: <?= count($tables) ?></p>

    <?php
    foreach ($tables as $table) {
        display_table_info($conn, $db_name, $table);
    }
    $conn->close();
    ?>
</body>
</html>
