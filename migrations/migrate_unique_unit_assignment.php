<?php
// migrations/migrate_unique_unit_assignment.php

require_once __DIR__ . '/../config/db.php';

function migrate_unique_unit_assignment(mysqli $conn) {
    $log = ['label' => 'Add unique constraint to lecturer_units', 'status' => 'ok', 'msg' => ''];

    // Check if the constraint already exists
    $result = $conn->query("
        SELECT COUNT(*) AS count
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME = 'lecturer_units'
        AND CONSTRAINT_NAME = 'uniq_unit_id'
    ");
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        $log['status'] = 'skip';
        $log['msg'] = 'Unique constraint on unit_id already exists.';
        return $log;
    }

    // Check for duplicate unit_id entries before adding the constraint
    $duplicate_check = $conn->query("SELECT unit_id, COUNT(*) FROM lecturer_units GROUP BY unit_id HAVING COUNT(*) > 1");
    if ($duplicate_check->num_rows > 0) {
        $log['status'] = 'err';
        $log['msg'] = 'Cannot add unique constraint. Duplicate unit_id entries found in lecturer_units. Please resolve manually.';
        // You might want to list the duplicates to help the admin
        $duplicates = [];
        while($row = $duplicate_check->fetch_assoc()){
            $duplicates[] = $row['unit_id'];
        }
        $log['msg'] .= ' Duplicates found for unit_ids: ' . implode(', ', $duplicates);
        return $log;
    }

    try {
        $sql = "ALTER TABLE lecturer_units ADD CONSTRAINT uniq_unit_id UNIQUE (unit_id)";
        if ($conn->query($sql)) {
            $log['msg'] = 'Successfully added unique constraint to unit_id.';
        } else {
            $log['status'] = 'err';
            $log['msg'] = $conn->error;
        }
    } catch (Exception $e) {
        $log['status'] = 'err';
        $log['msg'] = $e->getMessage();
    }
    
    return $log;
}

// This script can be run directly for individual migration
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo "Forbidden: only an admin may run database migrations.";
        exit;
    }

    $result = migrate_unique_unit_assignment($conn);
    echo "Migration: " . $result['label'] . "<br>";
    echo "Status: " . $result['status'] . "<br>";
    echo "Message: " . $result['msg'] . "<br>";
}
