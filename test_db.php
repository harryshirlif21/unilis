<?php
require_once 'config/db.php';

if ($conn) {
    echo "✅ Database connected successfully!";
} else {
    echo "❌ Failed to connect to the database.";
}
?>
