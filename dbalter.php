<?php
require_once 'config/db.php';

echo "<h2>Creating Team Feature Tables...</h2>";

$queries = [];

/* =========================
TEAMS
========================= */
$queries[] = "CREATE TABLE IF NOT EXISTS teams (
id INT AUTO_INCREMENT PRIMARY KEY,
title VARCHAR(200) NOT NULL,
description TEXT,
unit_id INT NOT NULL,
assessment_id INT,
assessment_type VARCHAR(50) NOT NULL DEFAULT 'assignment',
created_by INT NOT NULL,
course_id INT NOT NULL,
year INT NOT NULL,
status ENUM('active','locked','archived') DEFAULT 'active',
submission_mode ENUM('team','individual','mixed') DEFAULT 'team',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
deadline DATETIME,
INDEX(unit_id),
INDEX(course_id),
INDEX(created_by),
FOREIGN KEY (unit_id) REFERENCES units(id),
FOREIGN KEY (course_id) REFERENCES courses(id),
FOREIGN KEY (created_by) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
TEAM ACTIVITY LOG
========================= */
$queries[] = "CREATE TABLE IF NOT EXISTS team_activity_log (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
user_id INT NOT NULL,
action_type VARCHAR(100) NOT NULL,
action_detail TEXT,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(user_id),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (user_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
TEAM ASSIGNMENTS
========================= */
$queries[] = "CREATE TABLE IF NOT EXISTS team_assignments (
id INT AUTO_INCREMENT PRIMARY KEY,
title VARCHAR(255) NOT NULL,
description TEXT,
unit_id INT NOT NULL,
course_id INT NOT NULL,
assignment_mode ENUM('individual_only','team_only','both') DEFAULT 'individual_only',
submission_deadline DATETIME,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
INDEX(unit_id),
INDEX(course_id),
FOREIGN KEY (unit_id) REFERENCES units(id),
FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB";

/* =========================
TEAM FILES
========================= */
$queries[] = "CREATE TABLE IF NOT EXISTS team_files (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
uploader_id INT NOT NULL,
original_name VARCHAR(255) NOT NULL,
stored_name VARCHAR(100) NOT NULL,
filepath VARCHAR(512) NOT NULL,
file_size BIGINT UNSIGNED NOT NULL,
mime_type VARCHAR(100) NOT NULL,
version INT UNSIGNED DEFAULT 1,
is_current TINYINT UNSIGNED DEFAULT 1,
uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(uploader_id),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (uploader_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
TEAM MEMBERS
========================= */
$queries[] = "CREATE TABLE IF NOT EXISTS team_members (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
student_id INT NOT NULL,
role ENUM('leader','editor','researcher','presenter','developer','lab_partner','member') DEFAULT 'member',
joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(student_id),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
TEAM TASKS
========================= */
$queries[] = "CREATE TABLE IF NOT EXISTS team_tasks (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
title VARCHAR(255) NOT NULL,
description TEXT,
assigned_to INT,
due_date DATE,
priority ENUM('Low','Medium','High') DEFAULT 'Medium',
status ENUM('Backlog','In Progress','In Review','Done') DEFAULT 'Backlog',
created_by INT NOT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(assigned_to),
INDEX(created_by),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (assigned_to) REFERENCES students(id),
FOREIGN KEY (created_by) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
RUN CREATION
========================= */

foreach ($queries as $sql) {

    if ($conn->query($sql)) {
        echo "Table checked/created successfully.<br>";
    } else {
        echo "Error: " . $conn->error . "<br>";
    }
}

echo "<hr>";
echo "<h2>Team Tables Structure</h2>";

/* =========================
TABLES TO DISPLAY
========================= */

$tables = [
"teams",
"team_activity_log",
"team_assignments",
"team_files",
"team_members",
"team_tasks"
];

foreach ($tables as $table) {

echo "<h3>Table: $table</h3>";

/* =========================
SHOW COLUMNS
========================= */

$columns = $conn->query("SHOW COLUMNS FROM `$table`");

echo "<b>Fields</b>";

echo "<table border='1' cellpadding='6' cellspacing='0'>";
echo "<tr>
<th>Field</th>
<th>Type</th>
<th>Null</th>
<th>Key</th>
<th>Default</th>
<th>Extra</th>
</tr>";

while ($col = $columns->fetch_assoc()) {

echo "<tr>
<td>{$col['Field']}</td>
<td>{$col['Type']}</td>
<td>{$col['Null']}</td>
<td>{$col['Key']}</td>
<td>{$col['Default']}</td>
<td>{$col['Extra']}</td>
</tr>";
}

echo "</table><br>";

/* =========================
SHOW FOREIGN KEYS
========================= */

echo "<b>Foreign Keys</b>";

$fk_query = "
SELECT 
COLUMN_NAME,
REFERENCED_TABLE_NAME,
REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = '$table'
AND REFERENCED_TABLE_NAME IS NOT NULL
";

$fk_result = $conn->query($fk_query);

if ($fk_result->num_rows > 0) {

echo "<table border='1' cellpadding='6'>";
echo "<tr>
<th>Column</th>
<th>References Table</th>
<th>References Column</th>
</tr>";

while ($fk = $fk_result->fetch_assoc()) {

echo "<tr>
<td>{$fk['COLUMN_NAME']}</td>
<td>{$fk['REFERENCED_TABLE_NAME']}</td>
<td>{$fk['REFERENCED_COLUMN_NAME']}</td>
</tr>";
}

echo "</table>";

} else {

echo "No Foreign Keys";

}

echo "<hr>";

}

?>