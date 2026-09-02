<?php
require_once __DIR__ . '/config/db.php';
$r = $conn->query('SELECT id, email, name FROM external_learners LIMIT 3');
if ($r) {
    echo "External learners:\n";
    while($row = $r->fetch_assoc()) {
        echo $row['email'] . ' | ' . $row['name'] . "\n";
    }
} else {
    echo "No external learners\n";
}

// Also check students table
$r2 = $conn->query('SELECT id, email, name FROM students LIMIT 3');
if ($r2 && $r2->num_rows > 0) {
    echo "\nStudents:\n";
    while($row = $r2->fetch_assoc()) {
        echo $row['email'] . ' | ' . $row['name'] . "\n";
    }
}
