<?php
require_once __DIR__ . "/config/db.php";

echo "<h3>🚀 Cleaning Duplicate Rows and Applying Unique Constraints...</h3>";

function runQuery($conn, $sql) {
    try {
        if ($conn->query($sql)) {
            echo "<p>✔️ Success: <code>$sql</code></p>";
        }
    } catch (Exception $e) {
        echo "<p>⚠️ Skipped/Error for:<br><code>$sql</code><br>" . $e->getMessage() . "</p>";
    }
}

echo "<h4>🔹 Step 1: Removing Duplicate Rows…</h4>";

/* -------------------------
   REMOVE DUPLICATES
-------------------------- */

// 1) student_classnotes_progress
runQuery($conn, "
    DELETE t1 FROM student_classnotes_progress t1
    JOIN student_classnotes_progress t2
    ON t1.student_id = t2.student_id
       AND t1.classnote_id = t2.classnote_id
       AND t1.id > t2.id
");

// 2) student_classnotes_subtopic_progress
// Use subtopic_index (your original field name)
runQuery($conn, "
    DELETE t1 FROM student_classnotes_subtopic_progress t1
    JOIN student_classnotes_subtopic_progress t2
    ON t1.student_id = t2.student_id
       AND t1.classnote_id = t2.classnote_id
       AND t1.subtopic_index = t2.subtopic_index
       AND t1.id > t2.id
");

// 3) student_units
runQuery($conn, "
    DELETE t1 FROM student_units t1
    JOIN student_units t2
    ON t1.student_id = t2.student_id
       AND t1.unit_id = t2.unit_id
       AND t1.id > t2.id
");

// 4) lecturer_units
runQuery($conn, "
    DELETE t1 FROM lecturer_units t1
    JOIN lecturer_units t2
    ON t1.lecturer_id = t2.lecturer_id
       AND t1.unit_id = t2.unit_id
       AND t1.id > t2.id
");


echo "<h4>🔹 Step 2: Adding Required Unique Constraints…</h4>";

/* -------------------------
   ADD UNIQUE KEYS SAFELY
-------------------------- */

// student_classnotes_progress
runQuery($conn, "
    ALTER TABLE student_classnotes_progress
    ADD UNIQUE KEY uniq_progress (student_id, classnote_id)
");

// student_classnotes_subtopic_progress (with subtopic_index)
runQuery($conn, "
    ALTER TABLE student_classnotes_subtopic_progress
    ADD UNIQUE KEY uniq_subtopic (student_id, classnote_id, subtopic_index)
");

// student_units
runQuery($conn, "
    ALTER TABLE student_units
    ADD UNIQUE KEY uniq_su (student_id, unit_id)
");

// lecturer_units
runQuery($conn, "
    ALTER TABLE lecturer_units
    ADD UNIQUE KEY uniq_lu (lecturer_id, unit_id)
");


echo "<h3>🎉 Database Cleanup & Constraint Fix Completed.</h3>";

$conn->close();
?>
