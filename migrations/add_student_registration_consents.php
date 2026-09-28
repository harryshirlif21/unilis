<?php
require_once __DIR__ . '/../config/db.php';

/** Add auditable consent fields used by student signup. Safe to run repeatedly. */
$columns = [
    'terms_consent' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'privacy_consent' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'consented_at' => 'DATETIME NULL DEFAULT NULL',
];

try {
    $existing = [];
    $result = $conn->query("SHOW COLUMNS FROM students");
    if (!$result) {
        throw new RuntimeException('Could not inspect students table: ' . $conn->error);
    }
    while ($column = $result->fetch_assoc()) {
        $existing[] = $column['Field'];
    }

    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            if (!$conn->query("ALTER TABLE students ADD COLUMN `$name` $definition")) {
                throw new RuntimeException("Could not add $name: " . $conn->error);
            }
            echo htmlspecialchars("Added students.$name") . "<br>";
        } else {
            echo htmlspecialchars("students.$name already exists") . "<br>";
        }
    }

    echo 'Student registration consent migration complete.';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Migration failed: ' . htmlspecialchars($e->getMessage());
}
