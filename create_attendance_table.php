<?php
require_once __DIR__.'/smart-lab/config/database.php';

try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create attendance table
    $sql = "
    CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        practical_id VARCHAR(32) NOT NULL,
        verification_method ENUM('qr', 'rfid', 'fingerprint', 'manual') DEFAULT 'qr',
        marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_student_practical (student_id, practical_id),
        INDEX idx_marked_at (marked_at)
    );
    ";
    
    $pdo->exec($sql);
    
    echo 'Attendance table created successfully';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>