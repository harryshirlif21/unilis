<?php
require_once 'config/db.php';

try {
    $conn->begin_transaction();

    // 1️⃣ Delete all data in dependent table first
    $conn->query("DELETE FROM interactive_submissions");
    $conn->query("DELETE FROM students");

    // 2️⃣ Drop foreign key temporarily
    $conn->query("ALTER TABLE interactive_submissions DROP FOREIGN KEY interactive_submissions_ibfk_1");

    // 3️⃣ Alter the students.id column
    $conn->query("ALTER TABLE students MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");

    // 4️⃣ Re-add the foreign key
    $conn->query("
        ALTER TABLE interactive_submissions
        ADD CONSTRAINT interactive_submissions_ibfk_1
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ");

    $conn->commit();

    echo "Tables cleared and 'id' column fixed successfully.";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}

$conn->close();
