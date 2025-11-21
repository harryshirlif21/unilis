<?php
require_once __DIR__ . "/config/db.php";

echo "<h3>Cleaning Duplicate Rows...</h3>";

$queries = [

    // Remove duplicates
    "DELETE t1 FROM student_classnotes_progress t1
        JOIN student_classnotes_progress t2
        ON t1.student_id = t2.student_id AND t1.classnote_id = t2.classnote_id
        AND t1.id > t2.id",

    "DELETE t1 FROM student_classnotes_subtopic_progress t1
        JOIN student_classnotes_subtopic_progress t2
        ON t1.student_id = t2.student_id AND t1.classnote_id = t2.classnote_id
        AND t1.subtopic_index = t2.subtopic_index
        AND t1.id > t2.id",

    // Add required keys
    "ALTER TABLE students ADD PRIMARY KEY (id)",
    "ALTER TABLE classnotes ADD PRIMARY KEY (id)",

    "ALTER TABLE student_classnotes_progress
        ADD PRIMARY KEY (id),
        ADD UNIQUE KEY uniq_progress (student_id, classnote_id)",

    "ALTER TABLE student_classnotes_subtopic_progress
        ADD PRIMARY KEY (id),
        ADD UNIQUE KEY uniq_subtopic (student_id, classnote_id, subtopic_index)"
];

foreach ($queries as $sql) {
    try {
        if ($conn->query($sql)) {
            echo "<p>✔️ Success: <code>$sql</code></p>";
        }
    } catch (Exception $e) {
        echo "<p>⚠️ Error for query:<br><code>$sql</code><br>" . $e->getMessage() . "</p>";
    }
}

echo "<h3>Database Fix Completed.</h3>";
$conn->close();
?>
