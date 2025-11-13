<?php
require_once __DIR__ . "/config/db.php";

// Step 0: Get all tables in the current database
$tablesResult = $conn->query("SHOW TABLES");
if (!$tablesResult) {
    die("❌ Error fetching tables: " . htmlspecialchars($conn->error));
}

$tables = [];
while ($row = $tablesResult->fetch_array()) {
    $tables[] = $row[0];
}

echo "<h1>Database Structure Overview</h1>";

foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";

    // 1️⃣ Show columns
    $columnsResult = $conn->query("DESCRIBE $table");
    if ($columnsResult) {
        echo "<h3>Columns</h3>";
        echo "<table border='1' cellpadding='5'>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>";
        while ($col = $columnsResult->fetch_assoc()) {
            echo "<tr>";
            foreach ($col as $val) {
                echo "<td>" . htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ Could not fetch columns for $table: " . htmlspecialchars($conn->error) . "</p>";
    }

    // 2️⃣ Show foreign keys referencing other tables
    $fkResult = $conn->query("
        SELECT
            k.CONSTRAINT_NAME,
            k.COLUMN_NAME,
            k.REFERENCED_TABLE_NAME,
            k.REFERENCED_COLUMN_NAME
        FROM
            information_schema.KEY_COLUMN_USAGE AS k
        WHERE
            k.TABLE_SCHEMA = DATABASE()
            AND k.TABLE_NAME = '$table'
            AND k.REFERENCED_TABLE_NAME IS NOT NULL
    ");

    if ($fkResult && $fkResult->num_rows > 0) {
        echo "<h3>Foreign Keys</h3>";
        echo "<table border='1' cellpadding='5'>
                <tr>
                    <th>Constraint Name</th>
                    <th>Column</th>
                    <th>References Table</th>
                    <th>References Column</th>
                </tr>";
        while ($fk = $fkResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>ℹ️ No foreign keys for this table.</p>";
    }

    echo "<hr>";
}

$conn->close();
?>
