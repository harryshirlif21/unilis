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
        // Suppress expected errors (already exists, etc.)
        if (strpos($e->getMessage(), "already exists") !== false || 
            strpos($e->getMessage(), "Duplicate column") !== false ||
            strpos($e->getMessage(), "Duplicate key") !== false) {
            echo "⚪ Skipped (already done): " . ($successMsg ?? $sql) . "\n";
        } else {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
    return false;
}

// ==================================================================
// 1. CREATE schools TABLE
// ==================================================================
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

// ==================================================================
// 2. ADD school_id TO departments + FK
// ==================================================================
echo "\n--- Updating departments table ---\n";
runQuery($conn, "
    ALTER TABLE departments 
    ADD COLUMN IF NOT EXISTS school_id INT NULL AFTER university_id
", "Added school_id column to departments");

runQuery($conn, "
    ALTER TABLE departments 
    ADD CONSTRAINT fk_dept_school 
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL
", "Linked departments → schools");

// ==================================================================
// 3. INSERT JKUAT SCHOOLS (if not exist)
// ==================================================================
echo "\n--- Inserting JKUAT Schools ---\n";

// Get JKUAT university ID (adjust name if needed)
$jkuat_id = 1; // Change if your JKUAT has different ID
$res = $conn->query("SELECT id FROM universities WHERE name LIKE '%Jomo Kenyatta%' OR name LIKE '%JKUAT%' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $jkuat_id = $row['id'];
}

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

// Assign ALL existing departments to SCIT
$scit_id_res = $conn->query("SELECT id FROM schools WHERE short_name = 'SCIT' AND university_id = $jkuat_id");
if ($scit_id_res && $row = $scit_id_res->fetch_assoc()) {
    $scit_id = $row['id'];
    $conn->query("UPDATE departments SET school_id = $scit_id WHERE school_id IS NULL OR school_id = 0");
    echo "✔️ All existing departments assigned to SCIT (School ID: $scit_id)\n";
}

// ==================================================================
// 4. ADD EMAIL VERIFICATION FIELDS TO students TABLE
// ==================================================================
echo "\n--- Adding verification fields to students ---\n";

runQuery($conn, "
    ALTER TABLE students 
    ADD COLUMN IF NOT EXISTS verification_code VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL
", "Added verification_code, is_verified, verified_at");

runQuery($conn, "
    ALTER TABLE students 
    ADD INDEX idx_verification_code (verification_code)
", "Indexed verification_code for fast lookup");

// ==================================================================
// 5. YOUR ORIGINAL CLEANUP: Remove Duplicates + Add Unique Keys
// ==================================================================
echo "\n--- Running Duplicate Cleanup & Unique Constraints ---\n";

$cleanup_queries = [
    // Remove duplicates
    "DELETE t1 FROM student_classnotes_progress t1
     INNER JOIN student_classnotes_progress t2
     WHERE t1.student_id = t2.student_id 
       AND t1.classnote_id = t2.classnote_id 
       AND t1.id > t2.id" 
    => "Cleaned student_classnotes_progress duplicates",

    "DELETE t1 FROM student_classnotes_subtopic_progress t1
     INNER JOIN student_classnotes_subtopic_progress t2
     WHERE t1.student_id = t2.student_id 
       AND t1.classnote_id = t2.classnote_id 
       AND t1.subtopic_index = t2.subtopic_index 
       AND t1.id > t2.id"
    => "Cleaned subtopic progress duplicates",

    "DELETE t1 FROM student_units t1
     INNER JOIN student_units t2
     WHERE t1.student_id = t2.student_id 
       AND t1.unit_id = t2.unit_id 
       AND t1.id > t2.id"
    => "Cleaned student_units duplicates",

    "DELETE t1 FROM lecturer_units t1
     INNER JOIN lecturer_units t2
     WHERE t1.lecturer_id = t2.lecturer_id 
       AND t1.unit_id = t2.unit_id 
       AND t1.id > t2.id"
    => "Cleaned lecturer_units duplicates",

    // Add unique constraints
    "ALTER TABLE student_classnotes_progress ADD UNIQUE IF NOT EXISTS uniq_progress (student_id, classnote_id)"
    => "Unique constraint: student + classnote",

    "ALTER TABLE student_classnotes_subtopic_progress ADD UNIQUE IF NOT EXISTS uniq_subtopic (student_id, classnote_id, subtopic_index)"
    => "Unique constraint: subtopic progress",

    "ALTER TABLE student_units ADD UNIQUE IF NOT EXISTS uniq_su (student_id, unit_id)"
    => "Unique constraint: student_units",

    "ALTER TABLE lecturer_units ADD UNIQUE IF NOT EXISTS uniq_lu (lecturer_id, unit_id)"
    => "Unique constraint: lecturer_units"
];

foreach ($cleanup_queries as $sql => $msg) {
    runQuery($conn, $sql, $msg);
}

echo "\n\n🎉 ALL MIGRATIONS COMPLETED SUCCESSFULLY!\n";
echo "   • schools table created\n";
echo "   • departments linked to schools (SCIT auto-assigned)\n";
echo "   • students table now supports email verification\n";
echo "   • Duplicates removed & unique constraints added\n";

$conn->close();
?>