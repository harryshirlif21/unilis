<?php
require_once __DIR__ . "/config/db.php";

echo "<h2>Adding Full Attendance System</h2>";
echo "<pre style='background:#f4f4f4;padding:15px;border-left:5px solid #dc2626;font-family:monospace;'>";

function runQuery($conn, $sql, $msg = null) {
    try {
        if ($conn->query($sql) === TRUE) {
            echo "SUCCESS: " . ($msg ?? "Query executed") . "\n";
            return true;
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    return false;
}

// 1. Add attendance_session_id to notifications
$res = $conn->query("SHOW COLUMNS FROM notifications LIKE 'attendance_session_id'");
if (!$res || $res->num_rows === 0) {
    runQuery($conn, "ALTER TABLE notifications ADD COLUMN attendance_session_id INT NULL AFTER meeting_id", "Added attendance_session_id to notifications");
    runQuery($conn, "ALTER TABLE notifications ADD INDEX idx_att_session_notif (attendance_session_id)");
}

// 2. Create attendance_sessions
runQuery($conn, "
CREATE TABLE IF NOT EXISTS attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    session_code VARCHAR(6) NOT NULL,
    duration_minutes INT NOT NULL,
    deadline DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_code (session_code),
    KEY idx_unit_lecturer (unit_id, lecturer_id),
    KEY idx_deadline (deadline),
    CONSTRAINT fk_as_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    CONSTRAINT fk_as_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
", "attendance_sessions table ready");

// 3. Create attendance_records
runQuery($conn, "
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    attended TINYINT(1) DEFAULT 0,
    attended_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_student_session (session_id, student_id),
    KEY idx_session (session_id),
    KEY idx_student (student_id),
    CONSTRAINT fk_ar_session FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
", "attendance_records table ready");

echo "</pre>";
echo "<h3 style='color:green;'>Attendance tables created successfully!</h3>";
$conn->close();
?>