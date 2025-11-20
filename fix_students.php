<?php
require_once 'config/db.php';

// --- Step 1: Create 'recordings' table if it doesn't exist ---
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

if ($conn->query($createRecordingsTableSQL) === TRUE) {
    echo "<p>Table <strong>recordings</strong> exists or was created successfully.</p>";
} else {
    die("Error creating recordings table: " . $conn->error);
}

// --- Step 2: Create 'classnotes' table ---
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

if ($conn->query($createClassNotesSQL) === TRUE) {
    echo "<p>Table <strong>classnotes</strong> exists or was created successfully.</p>";
} else {
    die("Error creating classnotes table: " . $conn->error);
}

// --- Step 3: Create 'student_classnotes_progress' table ---
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

if ($conn->query($createStudentProgressSQL) === TRUE) {
    echo "<p>Table <strong>student_classnotes_progress</strong> exists or was created successfully.</p>";
} else {
    die("Error creating student_classnotes_progress table: " . $conn->error);
}

// --- Step 4: Functions to get tables, columns, and foreign keys ---
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
        SELECT
            k.COLUMN_NAME,
            k.REFERENCED_TABLE_NAME,
            k.REFERENCED_COLUMN_NAME
        FROM
            information_schema.KEY_COLUMN_USAGE k
        WHERE
            k.TABLE_SCHEMA = DATABASE()
            AND k.TABLE_NAME = '$table'
            AND k.REFERENCED_TABLE_NAME IS NOT NULL;
    ";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $fks[] = $row;
    }
    return $fks;
}

// --- Step 5: Display all tables, columns, and foreign keys ---
$tables = getTables($conn);
foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";

    // Columns
    echo "<h4>Columns:</h4><ul>";
    foreach (getColumns($conn, $table) as $col) {
        echo "<li>{$col['Field']} — {$col['Type']} — Null: {$col['Null']} — Key: {$col['Key']} — Default: {$col['Default']}</li>";
    }
    echo "</ul>";

    // Foreign keys
    $fks = getForeignKeys($conn, $table);
    if ($fks) {
        echo "<h4>Foreign Keys:</h4><ul>";
        foreach ($fks as $fk) {
            echo "<li>{$fk['COLUMN_NAME']} references {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']})</li>";
        }
        echo "</ul>";
    }
    echo "<hr>";
}
?>
