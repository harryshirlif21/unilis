<?php
// include your db connection
require_once __DIR__ . "/config/db.php";

echo "Fixing student_classnotes_subtopic_progress table structure...<br>";

// Check if table exists
$check_table_sql = "SHOW TABLES LIKE 'student_classnotes_subtopic_progress'";
$result = $conn->query($check_table_sql);

if ($result->num_rows == 0) {
    // Table does not exist, create it
    $create_table_sql = "
        CREATE TABLE student_classnotes_subtopic_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            classnote_id INT NOT NULL,
            subtopic_id INT NOT NULL,
            viewed TINYINT(1) DEFAULT 0,
            completed TINYINT(1) DEFAULT 0,
            selected_choice INT NULL,
            is_correct TINYINT(1) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id),
            FOREIGN KEY (classnote_id) REFERENCES classnotes(id)
        )
    ";
    
    if ($conn->query($create_table_sql) === TRUE) {
        echo "✅ SUCCESS: student_classnotes_subtopic_progress table created.<br>";
    } else {
        echo "❌ ERROR: Failed to create table: " . $conn->error . "<br>";
    }
} else {
    echo "✅ Table student_classnotes_subtopic_progress already exists.<br>";

    // Check and add missing columns dynamically
    $required_columns = [
        'student_id' => 'INT NOT NULL',
        'classnote_id' => 'INT NOT NULL',
        'subtopic_id' => 'INT NOT NULL',
        'viewed' => 'TINYINT(1) DEFAULT 0',
        'completed' => 'TINYINT(1) DEFAULT 0',
        'selected_choice' => 'INT NULL',
        'is_correct' => 'TINYINT(1) DEFAULT NULL',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];

    $missing_columns = [];
    foreach ($required_columns as $col => $definition) {
        $check_sql = "SHOW COLUMNS FROM student_classnotes_subtopic_progress LIKE '$col'";
        $res = $conn->query($check_sql);
        if ($res->num_rows == 0) {
            $missing_columns[] = "ADD COLUMN $col $definition";
        }
    }

    if (!empty($missing_columns)) {
        $alter_sql = "ALTER TABLE student_classnotes_subtopic_progress " . implode(", ", $missing_columns);
        if ($conn->query($alter_sql) === TRUE) {
            echo "✅ SUCCESS: Added missing columns:<br>";
            foreach ($missing_columns as $col) {
                echo "&nbsp;&nbsp;• " . str_replace("ADD COLUMN ", "", $col) . "<br>";
            }
        } else {
            echo "❌ ERROR: Failed to add columns: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ All required columns already exist.<br>";
    }
}

// close connection
$conn->close();
?>
