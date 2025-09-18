<?php
session_start();
include '../config/db.php';

// Mark all as read for this student
$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE user_id = ? AND user_role = 'student' AND is_read = 0
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

echo "success";
