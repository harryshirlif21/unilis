<?php
// Include your existing DB connection
require_once __DIR__ . "/config/db.php";

// === Step 1: Rename note_id → notes_id (if needed) ===
$check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'note_id'");
if ($check && $check->num_rows > 0) {
    $sql = "ALTER TABLE notifications CHANGE note_id notes_id INT";
    if ($conn->query($sql)) {
        echo "<p>✅ Column <strong>note_id</strong> successfully renamed to <strong>notes_id</strong>.</p>";
    } else {
        echo "<p>❌ Error renaming column: " . htmlspecialchars($conn->error) . "</p>";
    }
} else {
    echo "<p>ℹ️ Column <strong>note_id</strong> does not exist or was already renamed.</p>";
}

// === Step 2: Check and Add Foreign Keys for notifications ===
echo "<h2>Foreign Key Constraints for Notifications Table</h2>";

$relations = [
    'notes_id' => ['table' => 'notes', 'column' => 'id'],
    'assignment_id' => ['table' => 'assignments', 'column' => 'id'],
    'interactive_assignment_id' => ['table' => 'interactive_assignments', 'column' => 'id'],
    'meeting_id' => ['table' => 'meetings', 'column' => 'id']
];

$existingFKs = [];

$fkQuery = "
    SELECT 
        k.COLUMN_NAME,
        k.CONSTRAINT_NAME,
        k.REFERENCED_TABLE_NAME,
        k.REFERENCED_COLUMN_NAME
    FROM 
        information_schema.KEY_COLUMN_USAGE AS k
    WHERE 
        k.TABLE_SCHEMA = DATABASE()
        AND k.TABLE_NAME = 'notifications'
        AND k.REFERENCED_TABLE_NAME IS NOT NULL;
";

$result = $conn->query($fkQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existingFKs[$row['COLUMN_NAME']] = $row;
    }
}

foreach ($relations as $column => $ref) {
    if (!isset($existingFKs[$column])) {
        $constraintName = "fk_notifications_" . $ref['table'];
        $sql = "ALTER TABLE notifications 
                ADD CONSTRAINT $constraintName 
                FOREIGN KEY ($column) REFERENCES {$ref['table']}({$ref['column']}) 
                ON DELETE SET NULL ON UPDATE CASCADE";

        if ($conn->query($sql)) {
            echo "<p>✅ Foreign key added: <strong>$column → {$ref['table']}({$ref['column']})</strong></p>";
        } else {
            echo "<p>❌ Error adding foreign key for <strong>$column</strong>: " . htmlspecialchars($conn->error) . "</p>";
        }
    } else {
        echo "<p>ℹ️ Foreign key for <strong>$column</strong> already exists → 
              {$existingFKs[$column]['REFERENCED_TABLE_NAME']}({$existingFKs[$column]['REFERENCED_COLUMN_NAME']})</p>";
    }
}

// === Step 3: Show current notifications table structure ===
echo "<h2>Updated Notifications Table Structure</h2>";

$result = $conn->query("DESCRIBE notifications");
if ($result) {
    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $col) {
            echo "<td>" . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error fetching structure: " . htmlspecialchars($conn->error);
}

// === Step 4: Create WebRTC tables using separate student_id and lecturer_id ===
echo "<h2>Creating WebRTC Tables</h2>";

// 1. meeting_attendance
$sql = "CREATE TABLE IF NOT EXISTS meeting_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT NULL,
    lecturer_id INT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    duration_minutes INT DEFAULT NULL,
    status ENUM('joined','left','absent') DEFAULT 'joined',
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "<p>✅ Table <strong>meeting_attendance</strong> is ready.</p>";
} else {
    echo "<p>❌ Error creating <strong>meeting_attendance</strong>: " . htmlspecialchars($conn->error) . "</p>";
}

// 2. meeting_signals
$sql = "CREATE TABLE IF NOT EXISTS meeting_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    from_student_id INT NULL,
    from_lecturer_id INT NULL,
    to_student_id INT NULL,
    to_lecturer_id INT NULL,
    type ENUM('offer','answer','candidate') NOT NULL,
    data TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (from_student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (from_lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
    FOREIGN KEY (to_student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (to_lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "<p>✅ Table <strong>meeting_signals</strong> is ready.</p>";
} else {
    echo "<p>❌ Error creating <strong>meeting_signals</strong>: " . htmlspecialchars($conn->error) . "</p>";
}

// Show the new tables structure
$tables = ['meeting_attendance', 'meeting_signals'];
foreach ($tables as $tbl) {
    echo "<h3>Structure of $tbl</h3>";
    $result = $conn->query("DESCRIBE $tbl");
    if ($result) {
        echo "<table border='1' cellpadding='5'>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $col) {
                echo "<td>" . htmlspecialchars($col ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ Error fetching structure of $tbl: " . htmlspecialchars($conn->error) . "</p>";
    }
}

$conn->close();
?>
