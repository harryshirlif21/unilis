<?php
require_once 'config/db.php';

echo "<h1>Database Structure Viewer</h1>";

/* =========================
GET ALL TABLES
========================= */

$tables_result = $conn->query("SHOW TABLES");

while ($table_row = $tables_result->fetch_array()) {

    $table = $table_row[0];

    echo "<hr>";
    echo "<h2>Table: $table</h2>";

    /* =========================
    SHOW COLUMNS
    ========================= */

    echo "<h3>Fields</h3>";

    $columns = $conn->query("SHOW COLUMNS FROM `$table`");

    echo "<table border='1' cellpadding='6' cellspacing='0'>
    <tr>
    <th>Field</th>
    <th>Type</th>
    <th>Null</th>
    <th>Key</th>
    <th>Default</th>
    <th>Extra</th>
    </tr>";

    while ($col = $columns->fetch_assoc()) {

        echo "<tr>
        <td>{$col['Field']}</td>
        <td>{$col['Type']}</td>
        <td>{$col['Null']}</td>
        <td>{$col['Key']}</td>
        <td>{$col['Default']}</td>
        <td>{$col['Extra']}</td>
        </tr>";
    }

    echo "</table>";


    /* =========================
    SHOW INDEXES
    ========================= */

    echo "<h3>Indexes</h3>";

    $indexes = $conn->query("SHOW INDEX FROM `$table`");

    echo "<table border='1' cellpadding='6'>
    <tr>
    <th>Key Name</th>
    <th>Column</th>
    <th>Unique</th>
    </tr>";

    while ($idx = $indexes->fetch_assoc()) {

        echo "<tr>
        <td>{$idx['Key_name']}</td>
        <td>{$idx['Column_name']}</td>
        <td>" . ($idx['Non_unique'] ? "No" : "Yes") . "</td>
        </tr>";
    }

    echo "</table>";


    /* =========================
    SHOW FOREIGN KEYS
    ========================= */

    echo "<h3>Foreign Keys</h3>";

    $fk_query = "
    SELECT 
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME,
        CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '$table'
    AND REFERENCED_TABLE_NAME IS NOT NULL
    ";

    $fk_result = $conn->query($fk_query);

    if ($fk_result->num_rows > 0) {

        echo "<table border='1' cellpadding='6'>
        <tr>
        <th>Constraint</th>
        <th>Column</th>
        <th>References Table</th>
        <th>References Column</th>
        </tr>";

        while ($fk = $fk_result->fetch_assoc()) {

            echo "<tr>
            <td>{$fk['CONSTRAINT_NAME']}</td>
            <td>{$fk['COLUMN_NAME']}</td>
            <td>{$fk['REFERENCED_TABLE_NAME']}</td>
            <td>{$fk['REFERENCED_COLUMN_NAME']}</td>
            </tr>";
        }

        echo "</table>";

    } else {

        echo "No Foreign Keys";

    }

}

?>