<?php
/**
 * Migration: Add start_date/end_date to modules and lessons, and create tutor_module_permissions table
 * 
 * This migration adds:
 * 1. start_date and end_date columns to public_course_modules
 * 2. start_date and end_date columns to public_course_lessons  
 * 3. tutor_module_permissions table to control which tutors can edit specific modules
 */

require_once __DIR__ . '/../config/db.php';

$migrationName = 'add_module_lesson_dates_and_permissions';
$description = 'Add start_date/end_date columns to modules and lessons, create tutor_module_permissions table';

echo "Starting migration: Add module/lesson dates and tutor permissions...\n";

try {
    // Ensure migrations table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            description TEXT,
            INDEX idx_migration_name (migration_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Check if migration already run
    $check = $conn->query("SELECT id FROM migrations WHERE migration_name = '$migrationName' LIMIT 1");
    if ($check && $check->num_rows > 0) {
        echo "⚠ Migration already executed. Skipping.\n";
        exit(0);
    }

    // Add start_date and end_date to public_course_modules
    echo "Adding start_date and end_date to public_course_modules...\n";
    
    // Check if start_date column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM public_course_modules LIKE 'start_date'");
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE public_course_modules ADD COLUMN start_date DATE NULL COMMENT 'Module start date for scheduling'");
        echo "✓ Added start_date column\n";
    } else {
        echo "✓ start_date column already exists\n";
    }
    
    // Check if end_date column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM public_course_modules LIKE 'end_date'");
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE public_course_modules ADD COLUMN end_date DATE NULL COMMENT 'Module end date for scheduling'");
        echo "✓ Added end_date column\n";
    } else {
        echo "✓ end_date column already exists\n";
    }
    echo "✓ public_course_modules updated\n";

    // Add start_date and end_date to public_course_lessons
    echo "Adding start_date and end_date to public_course_lessons...\n";
    
    // Check if start_date column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM public_course_lessons LIKE 'start_date'");
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE public_course_lessons ADD COLUMN start_date DATE NULL COMMENT 'Lesson start date for scheduling'");
        echo "✓ Added start_date column\n";
    } else {
        echo "✓ start_date column already exists\n";
    }
    
    // Check if end_date column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM public_course_lessons LIKE 'end_date'");
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE public_course_lessons ADD COLUMN end_date DATE NULL COMMENT 'Lesson end date for scheduling'");
        echo "✓ Added end_date column\n";
    } else {
        echo "✓ end_date column already exists\n";
    }
    echo "✓ public_course_lessons updated\n";

    // Add video_url column to public_course_lessons
    echo "Adding video_url to public_course_lessons...\n";
    $checkColumn = $conn->query("SHOW COLUMNS FROM public_course_lessons LIKE 'video_url'");
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE public_course_lessons ADD COLUMN video_url VARCHAR(500) NULL COMMENT 'YouTube URL for recorded lesson'");
        echo "✓ Added video_url column\n";
    } else {
        echo "✓ video_url column already exists\n";
    }
    echo "✓ public_course_lessons updated with video_url\n";

    // Create tutor_module_permissions table
    echo "Creating tutor_module_permissions table...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS tutor_module_permissions (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tutor_id INT NOT NULL COMMENT 'Lecturer ID from lecturers table',
            module_id INT NOT NULL COMMENT 'Module ID from public_course_modules',
            can_edit TINYINT(1) DEFAULT 1 COMMENT 'Whether tutor can edit this module',
            can_teach TINYINT(1) DEFAULT 1 COMMENT 'Whether tutor can teach this module',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT NULL COMMENT 'Admin ID who assigned this permission',
            UNIQUE KEY uniq_tutor_module (tutor_id, module_id),
            KEY idx_tutor (tutor_id),
            KEY idx_module (module_id),
            CONSTRAINT fk_tmp_tutor FOREIGN KEY (tutor_id) REFERENCES lecturers(id) ON DELETE CASCADE,
            CONSTRAINT fk_tmp_module FOREIGN KEY (module_id) REFERENCES public_course_modules(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ tutor_module_permissions table created\n";

    // Record migration
    echo "Recording migration...\n";
    $stmt = $conn->prepare("INSERT INTO migrations (migration_name, description) VALUES (?, ?)");
    $stmt->bind_param('ss', $migrationName, $description);
    $stmt->execute();
    $stmt->close();
    echo "✓ Migration recorded\n";

    echo "\n✓ Migration completed successfully!\n";

} catch (mysqli_sql_exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
