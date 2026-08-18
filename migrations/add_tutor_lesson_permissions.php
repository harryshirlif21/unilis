<?php
/**
 * Migration: Create tutor_lesson_permissions table
 *
 * Adds a lesson-level counterpart to tutor_module_permissions so an admin can
 * assign a specific tutor to a specific lesson. A lesson is editable by a tutor
 * when:
 *   - the tutor is the course-level tutor for the lesson's course (one per
 *     course), or
 *   - the tutor has can_edit on the lesson's module (tutor_module_permissions),
 *     or
 *   - the tutor has can_edit on this exact lesson
 *
 * When lessons are deleted the row cascades away, mirroring tutor_module_permissions.
 */

require_once __DIR__ . '/../config/db.php';

$migrationName = 'add_tutor_lesson_permissions';
$description = 'Create tutor_lesson_permissions table for lesson-level tutor assignment';

echo "Starting migration: Create tutor_lesson_permissions...\n";

try {
    // Check if migration already run
    $check = $conn->query("SELECT id FROM migrations WHERE migration_name = '$migrationName' LIMIT 1");
    if ($check && $check->num_rows > 0) {
        echo "⚠ Migration already executed. Skipping.\n";
        exit(0);
    }

    echo "Creating tutor_lesson_permissions table...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS tutor_lesson_permissions (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tutor_id INT NOT NULL COMMENT 'Lecturer ID from lecturers table',
            lesson_id INT NOT NULL COMMENT 'Lesson ID from public_course_lessons',
            can_edit TINYINT(1) DEFAULT 1 COMMENT 'Whether tutor can edit this lesson',
            can_teach TINYINT(1) DEFAULT 1 COMMENT 'Whether tutor can teach this lesson',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT NULL COMMENT 'Admin ID who assigned this permission',
            UNIQUE KEY uniq_tutor_lesson (tutor_id, lesson_id),
            KEY idx_tlp_tutor (tutor_id),
            KEY idx_tlp_lesson (lesson_id),
            CONSTRAINT fk_tlp_tutor FOREIGN KEY (tutor_id) REFERENCES lecturers(id) ON DELETE CASCADE,
            CONSTRAINT fk_tlp_lesson FOREIGN KEY (lesson_id) REFERENCES public_course_lessons(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ tutor_lesson_permissions table created\n";

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