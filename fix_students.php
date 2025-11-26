<?php
require_once 'config/db.php';

/* ---------------------------------------------------------
   STEP 1: CREATE recordings TABLE
--------------------------------------------------------- */
$createRecordingsTableSQL = "
CREATE TABLE IF NOT EXISTS recordings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($createRecordingsTableSQL)) {
    echo "<p>Table <strong>recordings</strong> exists or was created.</p>";
} else {
    die("Error creating recordings table: " . $conn->error);
}

/* ---------------------------------------------------------
   STEP 2: CREATE classnotes TABLE
--------------------------------------------------------- */
$createClassNotesSQL = "
CREATE TABLE IF NOT EXISTS classnotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NULL,
    media_type ENUM('pdf','ppt','excel','video','other','image') DEFAULT 'other',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($createClassNotesSQL)) {
    echo "<p>Table <strong>classnotes</strong> exists or was created.</p>";
} else {
    die("Error creating classnotes table: " . $conn->error);
}

/* ---------------------------------------------------------
   STEP 3: CREATE student_classnotes_progress TABLE
--------------------------------------------------------- */
$createStudentProgressSQL = "
CREATE TABLE IF NOT EXISTS student_classnotes_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    classnote_id INT NOT NULL,
    status ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
    last_accessed TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (classnote_id) REFERENCES classnotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($createStudentProgressSQL)) {
    echo "<p>Table <strong>student_classnotes_progress</strong> exists or was created.</p>";
} else {
    die("Error creating student_classnotes_progress table: " . $conn->error);
}

/* ---------------------------------------------------------
   FUNCTIONS: TABLE STRUCTURE + DATA
--------------------------------------------------------- */
function getTables($conn) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    return $tables;
}

function getColumns($conn, $table) {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    return $columns;
}

function getForeignKeys($conn, $table) {
    $fks = [];
    $sql = "
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = '$table'
        AND REFERENCED_TABLE_NAME IS NOT NULL;
    ";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $fks[] = $row;
    }
    return $fks;
}

function getTableData($conn, $table) {
    return $conn->query("SELECT * FROM `$table`");
}

/* ---------------------------------------------------------
   STEP 4: DISPLAY TABLE STRUCTURE + CONTENTS
--------------------------------------------------------- */

$tables = getTables($conn);

echo "<h1>Database Structure & Data</h1>";

foreach ($tables as $table) {

    echo "<h2 style='color:#2d3748;'>Table: <strong>$table</strong></h2>";

    /* ---- Columns ---- */
    echo "<h4>Columns:</h4><ul>";
    foreach (getColumns($conn, $table) as $col) {
        echo "<li><strong>{$col['Field']}</strong> — {$col['Type']} (Null: {$col['Null']}, Key: {$col['Key']}, Default: {$col['Default']})</li>";
    }
    echo "</ul>";

    /* ---- Foreign Keys ---- */
    $fks = getForeignKeys($conn, $table);
    if ($fks) {
        echo "<h4>Foreign Keys:</h4><ul>";
        foreach ($fks as $fk) {
            echo "<li>{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']})</li>";
        }
        echo "</ul>";
    }

    /* ---- Table Data ---- */
    echo "<h4>Data:</h4>";

    $dataResult = getTableData($conn, $table);

    if ($dataResult->num_rows === 0) {
        echo "<p style='color:#718096;'>No rows found.</p>";
    } else {

        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse; margin-bottom:20px;'>";

        // Header
        echo "<tr style='background:#edf2f7;'>";
        while ($field = $dataResult->fetch_field()) {
            echo "<th>{$field->name}</th>";
        }
        echo "</tr>";

        // Rows
        $dataResult->data_seek(0);
        while ($row = $dataResult->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                $value = htmlspecialchars($value ?? "NULL");
                echo "<td>$value</td>";
            }
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "<hr>";
}

?>
