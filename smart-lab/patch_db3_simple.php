<?php
/**
 * Simple Database Patch Script for UNILIS SmartLab
 * Creates missing tables and columns for QR, OTP, and biometric features
 * Run this script once and delete it afterwards
 */

// Use existing database configuration
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/database.php';

echo "<!DOCTYPE html><html><head><title>UNILIS SmartLab - Database Patch</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";
echo "</head><body><h1>UNILIS SmartLab Database Patch</h1>";

try {
    // For local development, use main database
    if (!defined('DB_HOST') || DB_HOST === 'smart-labs-db') {
        $db = new PDO('mysql:host=localhost;dbname=unilis_smartlab;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        $db = getDB();
    }
    
    echo "<p class='ok'>✓ Database connection successful</p>";
    
    // Check and create qr_sessions table
    echo "<h2>1. Checking qr_sessions table</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'qr_sessions'");
    if ($stmt->rowCount() === 0) {
        echo "<p class='warning'>⚠ qr_sessions table not found, creating...</p>";
        $sql = "CREATE TABLE qr_sessions (
            id varchar(36) NOT NULL,
            token varchar(255) NOT NULL,
            status enum('pending','claimed','expired') DEFAULT 'pending',
            user_id varchar(36) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at timestamp NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        echo "<p class='ok'>✓ qr_sessions table created successfully</p>";
    } else {
        echo "<p class='ok'>✓ qr_sessions table already exists</p>";
    }
    
    // Check and create otp_codes table
    echo "<h2>2. Checking otp_codes table</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'otp_codes'");
    if ($stmt->rowCount() === 0) {
        echo "<p class='warning'>⚠ otp_codes table not found, creating...</p>";
        $sql = "CREATE TABLE otp_codes (
            id varchar(36) NOT NULL,
            user_id varchar(36) NOT NULL,
            code varchar(10) NOT NULL,
            type enum('biometric','auth_code') NOT NULL DEFAULT 'biometric',
            expires_at timestamp NOT NULL,
            used tinyint(1) DEFAULT 0,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        echo "<p class='ok'>✓ otp_codes table created successfully</p>";
    } else {
        echo "<p class='ok'>✓ otp_codes table already exists</p>";
    }
    
    // Check and add device_fingerprint column to users table
    echo "<h2>3. Checking device_fingerprint column in users table</h2>";
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'device_fingerprint'");
    if ($stmt->rowCount() === 0) {
        echo "<p class='warning'>⚠ device_fingerprint column not found, adding...</p>";
        $sql = "ALTER TABLE users ADD COLUMN device_fingerprint varchar(255) DEFAULT NULL AFTER biometric_hash";
        $db->exec($sql);
        echo "<p class='ok'>✓ device_fingerprint column added successfully</p>";
    } else {
        echo "<p class='ok'>✓ device_fingerprint column already exists</p>";
    }
    
    // Verify all tables exist
    echo "<h2>4. Final Verification</h2>";
    $tables = ['qr_sessions', 'otp_codes'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='ok'>✓ $table table exists</p>";
        } else {
            echo "<p class='error'>✗ $table table missing</p>";
        }
    }
    
    // Verify device_fingerprint column
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'device_fingerprint'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='ok'>✓ device_fingerprint column exists</p>";
    } else {
        echo "<p class='error'>✗ device_fingerprint column missing</p>";
    }
    
    echo "<h2>5. Cleanup Instructions</h2>";
    echo "<div class='warning'>";
    echo "<p><strong>IMPORTANT:</strong> Delete this file (patch_db3_simple.php) after successful patch!</p>";
    echo "<p>This script should only be run once to avoid duplicate operations.</p>";
    echo "</div>";
    
    echo "<h2>✅ Patch Complete!</h2>";
    echo "<p>The database is now ready for QR code, OTP, and biometric features.</p>";
    echo "<p><a href='/smart-lab/'>← Back to SmartLab</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>
