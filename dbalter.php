<?php
require_once __DIR__ . "/config/db.php";

echo "<h2>Database Migration: Schools + Student Email Verification</h2>";
echo "<pre style='background:#f4f4f4;padding:15px;border-left:5px solid #2563eb;font-family:monospace;'>";

function runQuery($conn, $sql, $successMsg = null) {
    try {
        if ($conn->query($sql) === TRUE) {
            echo "✔️ " . ($successMsg ?? "Executed") . "\n";
            return true;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
    return false;
}

// -------------------------
// 1. CREATE schools TABLE
// -------------------------
echo "\n--- Creating schools table ---\n";
runQuery($conn, "
    CREATE TABLE IF NOT EXISTS schools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        short_name VARCHAR(20) NOT NULL,
        university_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
        UNIQUE KEY unique_short_name_per_uni (university_id, short_name),
        INDEX idx_short_name (short_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
", "schools table ready");

// -------------------------
// 2. ADD school_id TO departments + FK
// -------------------------
echo "\n--- Updating departments table ---\n";
$res = $conn->query("SHOW COLUMNS FROM departments LIKE 'school_id'");
if ($res->num_rows === 0) {
    runQuery($conn, "ALTER TABLE departments ADD COLUMN school_id INT NULL AFTER university_id", "Added school_id column to departments");
} else {
    echo "⚪ Skipped: school_id already exists\n";
}

$res = $conn->query("
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_NAME = 'departments' 
      AND COLUMN_NAME = 'school_id' 
      AND CONSTRAINT_SCHEMA = DATABASE()
");
if ($res->num_rows === 0) {
    runQuery($conn, "ALTER TABLE departments ADD CONSTRAINT fk_dept_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL", "Linked departments → schools");
} else {
    echo "⚪ Skipped: foreign key already exists\n";
}

// -------------------------
// 3. INSERT JKUAT SCHOOLS
// -------------------------
echo "\n--- Inserting JKUAT Schools ---\n";
$jkuat_id = 1; // fallback
$res = $conn->query("SELECT id FROM universities WHERE name LIKE '%Jomo Kenyatta%' OR name LIKE '%JKUAT%' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) $jkuat_id = $row['id'];

$schools = [
    ['School of Computing & Information Technology', 'SCIT'],
    ['School of Engineering', 'SOE'],
    ['School of Business & Entrepreneurship', 'SOBE'],
    ['School of Architecture & Building Sciences', 'SABS'],
    ['School of Health Sciences', 'SOHS'],
    ['School of Agriculture & Environmental Sciences', 'SOAES']
];

foreach ($schools as $s) {
    $name = $conn->real_escape_string($s[0]);
    $short = $conn->real_escape_string($s[1]);
    $conn->query("INSERT IGNORE INTO schools (name, short_name, university_id) VALUES ('$name', '$short', $jkuat_id)");
    echo "   • $s[0] ($s[1])\n";
}

// Assign all existing departments to SCIT
$scit_id_res = $conn->query("SELECT id FROM schools WHERE short_name='SCIT' AND university_id=$jkuat_id");
if ($scit_id_res && $row = $scit_id_res->fetch_assoc()) {
    $scit_id = $row['id'];
    $conn->query("UPDATE departments SET school_id = $scit_id WHERE school_id IS NULL OR school_id = 0");
    echo "✔️ All existing departments assigned to SCIT (School ID: $scit_id)\n";
}

// -------------------------
// 4. ADD EMAIL VERIFICATION FIELDS TO students TABLE
// -------------------------
$columns = ['verification_code', 'token_expires_at', 'is_verified', 'verified_at'];
foreach ($columns as $col) {
    $res = $conn->query("SHOW COLUMNS FROM students LIKE '$col'");
    if ($res->num_rows === 0) {
        switch ($col) {
            case 'verification_code':
                runQuery($conn, "ALTER TABLE students ADD COLUMN verification_code VARCHAR(100) NULL", "Added verification_code column");
                break;
            case 'token_expires_at':
                runQuery($conn, "ALTER TABLE students ADD COLUMN token_expires_at DATETIME NULL AFTER verification_code", "Added token_expires_at column");
                break;
            case 'is_verified':
                runQuery($conn, "ALTER TABLE students ADD COLUMN is_verified TINYINT(1) DEFAULT 0", "Added is_verified column");
                break;
            case 'verified_at':
                runQuery($conn, "ALTER TABLE students ADD COLUMN verified_at DATETIME NULL", "Added verified_at column");
                break;
        }
    } else {
        echo "⚪ Skipped: $col already exists\n";
    }
}

// Index verification_code if not exists
$res = $conn->query("SHOW INDEX FROM students WHERE Key_name='idx_verification_code'");
if ($res->num_rows === 0) {
    runQuery($conn, "ALTER TABLE students ADD INDEX idx_verification_code (verification_code)", "Indexed verification_code for fast lookup");
} else {
    echo "⚪ Skipped: idx_verification_code already exists\n";
}

// -------------------------
// 5. CREATE missing subtopic_index field
// -------------------------
$res = $conn->query("SHOW COLUMNS FROM student_classnotes_subtopic_progress LIKE 'subtopic_index'");
if ($res->num_rows === 0) {
    runQuery($conn, "ALTER TABLE student_classnotes_subtopic_progress ADD COLUMN subtopic_index INT NOT NULL DEFAULT 0 AFTER classnote_id", "Added subtopic_index column to student_classnotes_subtopic_progress");
} else {
    echo "⚪ Skipped: subtopic_index already exists\n";
}

// -------------------------
// 6. CLEANUP: Remove duplicates & add unique keys
// -------------------------
$cleanup_queries = [
    "DELETE t1 FROM student_classnotes_progress t1
     INNER JOIN student_classnotes_progress t2
     WHERE t1.student_id = t2.student_id 
       AND t1.classnote_id = t2.classnote_id 
       AND t1.id > t2.id" => "Cleaned student_classnotes_progress duplicates",

    "DELETE t1 FROM student_classnotes_subtopic_progress t1
     INNER JOIN student_classnotes_subtopic_progress t2
     WHERE t1.student_id = t2.student_id 
       AND t1.classnote_id = t2.classnote_id 
       AND t1.subtopic_index = t2.subtopic_index 
       AND t1.id > t2.id" => "Cleaned student_classnotes_subtopic_progress duplicates",

    "DELETE t1 FROM student_units t1
     INNER JOIN student_units t2
     WHERE t1.student_id = t2.student_id 
       AND t1.unit_id = t2.unit_id 
       AND t1.id > t2.id" => "Cleaned student_units duplicates",

    "DELETE t1 FROM lecturer_units t1
     INNER JOIN lecturer_units t2
     WHERE t1.lecturer_id = t2.lecturer_id 
       AND t1.unit_id = t2.unit_id 
       AND t1.id > t2.id" => "Cleaned lecturer_units duplicates",
];

foreach ($cleanup_queries as $sql => $msg) runQuery($conn, $sql, $msg);

// Add unique indexes
$unique_constraints = [
    "student_classnotes_progress" => ["uniq_progress" => "(student_id, classnote_id)"],
    "student_classnotes_subtopic_progress" => ["uniq_subtopic" => "(student_id, classnote_id, subtopic_index)"],
    "student_units" => ["uniq_su" => "(student_id, unit_id)"],
    "lecturer_units" => ["uniq_lu" => "(lecturer_id, unit_id)"],
];

foreach ($unique_constraints as $table => $indexes) {
    foreach ($indexes as $index_name => $columns) {
        $res = $conn->query("SHOW INDEX FROM $table WHERE Key_name='$index_name'");
        if ($res->num_rows === 0) {
            runQuery($conn, "ALTER TABLE $table ADD UNIQUE $index_name $columns", "Added unique index $index_name on $table");
        } else {
            echo "⚪ Skipped: unique index $index_name already exists on $table\n";
        }
    }
}

// -------------------------
// 7. SHOW TABLE INFO
// -------------------------
$tables = ['schools','departments','students','student_classnotes_progress','student_classnotes_subtopic_progress','student_units','lecturer_units'];
echo "\n--- Table Info ---\n";
foreach ($tables as $table) {
    echo "\n$table structure:\n";
    $res = $conn->query("SHOW CREATE TABLE $table");
    if ($res && $row = $res->fetch_assoc()) {
        echo $row['Create Table'] . "\n";
    }
}

echo "\n\n🎉 ALL MIGRATIONS COMPLETED SUCCESSFULLY!\n";
$conn->close();
?>
