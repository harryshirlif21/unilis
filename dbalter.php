<?php
session_start();
require_once __DIR__ . "/config/db.php";

// Function to display table structure and data
function display_table_info($conn, $table_name) {
    echo "<hr>";
    echo "<h2>Table: $table_name</h2>";

    // Table structure
    echo "<h3>Structure</h3>";
    $res = $conn->query("DESCRIBE `$table_name`");
    echo "<pre>";
    print_r($res->fetch_all(MYSQLI_ASSOC));
    echo "</pre>";

    // Table data
    echo "<h3>Data</h3>";
    $res = $conn->query("SELECT * FROM `$table_name`");
    echo "<pre>";
    print_r($res->fetch_all(MYSQLI_ASSOC));
    echo "</pre>";
}

// Fetch all tables in the current database
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
        display_table_info($conn, $table);
    }
    $conn->close();
    ?>
</body>
</html>
