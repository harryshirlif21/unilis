<?php
require_once 'config/db.php';

echo "<h2>Creating Team Feature Tables...</h2>";

$queries = [];

/* =========================
TEAMS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS teams (
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
$queries[] = "
CREATE TABLE IF NOT EXISTS team_activity_log (
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
$queries[] = "
CREATE TABLE IF NOT EXISTS team_assignments (
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
$queries[] = "
CREATE TABLE IF NOT EXISTS team_files (
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
TEAM INVITATIONS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS team_invitations (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
invited_student_id INT NOT NULL,
invited_by INT NOT NULL,
status ENUM('pending','accepted','rejected','cancelled') DEFAULT 'pending',
invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
responded_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(invited_student_id),
INDEX(invited_by),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (invited_student_id) REFERENCES students(id),
FOREIGN KEY (invited_by) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
TEAM MEMBERS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS team_members (
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
TEAM SUBMISSIONS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS team_submissions (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
student_id INT,
assessment_id INT NOT NULL,
file_name VARCHAR(255) NOT NULL,
file_path VARCHAR(512) NOT NULL,
mime_type VARCHAR(100),
file_size BIGINT UNSIGNED DEFAULT 0,
submission_type ENUM('team','individual') NOT NULL,
version INT DEFAULT 1,
is_current TINYINT UNSIGNED DEFAULT 1,
uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
lecturer_status ENUM('Received','Under Review','Needs Revision','Accepted','Rejected') DEFAULT 'Received',
lecturer_note TEXT,
reviewed_at DATETIME,
comments TEXT,
INDEX(team_id),
INDEX(student_id),
INDEX(assessment_id),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (student_id) REFERENCES students(id),
FOREIGN KEY (assessment_id) REFERENCES team_assignments(id)
) ENGINE=InnoDB";

/* =========================
TEAM TASKS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS team_tasks (
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
SUBMISSION CHECKLIST
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS submission_checklist (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
item_label VARCHAR(255) NOT NULL,
is_checked TINYINT(1) DEFAULT 0,
checked_by INT,
checked_at DATETIME,
INDEX(team_id),
INDEX(checked_by),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (checked_by) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
SUBMISSION SIGNOFFS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS submission_signoffs (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
user_id INT NOT NULL,
signed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(user_id),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (user_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
PEER EVALUATIONS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS peer_evaluations (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
evaluator_id INT NOT NULL,
evaluatee_id INT NOT NULL,
contribution TINYINT UNSIGNED NOT NULL,
communication TINYINT UNSIGNED NOT NULL,
quality TINYINT UNSIGNED NOT NULL,
reliability TINYINT UNSIGNED NOT NULL,
submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(evaluator_id),
INDEX(evaluatee_id),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (evaluator_id) REFERENCES students(id),
FOREIGN KEY (evaluatee_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
GHOST FLAGS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS ghost_flags (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
user_id INT NOT NULL,
flagged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
nudge_sent_at DATETIME,
resolved_at DATETIME,
INDEX(team_id),
INDEX(user_id),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (user_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
STANDUP ENTRIES
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS standup_entries (
id INT AUTO_INCREMENT PRIMARY KEY,
team_id INT NOT NULL,
user_id INT NOT NULL,
did_today TEXT NOT NULL,
will_do_next TEXT NOT NULL,
blockers TEXT,
entry_date DATE NOT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
INDEX(team_id),
INDEX(user_id),
INDEX(entry_date),
FOREIGN KEY (team_id) REFERENCES teams(id),
FOREIGN KEY (user_id) REFERENCES students(id)
) ENGINE=InnoDB";

/* =========================
ANNOUNCEMENTS
========================= */
$queries[] = "
CREATE TABLE IF NOT EXISTS announcements (
id INT AUTO_INCREMENT PRIMARY KEY,
lecturer_id INT NOT NULL,
unit_id INT,
team_id INT,
message TEXT NOT NULL,
is_global TINYINT(1) DEFAULT 0,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
INDEX(lecturer_id),
INDEX(unit_id),
INDEX(team_id),
FOREIGN KEY (lecturer_id) REFERENCES lecturers(id),
FOREIGN KEY (unit_id) REFERENCES units(id),
FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB";

/* =========================
RUN ALL QUERIES
========================= */

foreach ($queries as $sql) {

    if ($conn->query($sql)) {
        echo "Table created or already exists.<br>";
    } else {
        echo "Error: " . $conn->error . "<br>";
    }
}

echo "<br><b>Done.</b>";
?>