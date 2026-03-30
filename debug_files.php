<?php
// Debug script to check file submissions and their actual files
session_start();
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain');

echo "=== File Submission Debug Report ===\n\n";

try {
    // Check uploads directory
    $uploadsDir = __DIR__ . '/assets/uploads/';
    echo "Uploads Directory: " . $uploadsDir . "\n";
    echo "Directory Exists: " . (is_dir($uploadsDir) ? "YES" : "NO") . "\n";
    echo "Directory Writable: " . (is_writable($uploadsDir) ? "YES" : "NO") . "\n\n";

    // List some files in uploads directory
    if (is_dir($uploadsDir)) {
        $files = scandir($uploadsDir);
        echo "Files in uploads directory (" . count($files) . " total):\n";
        $realFiles = array_filter($files, function($file) {
            return $file !== '.' && $file !== '..';
        });
        foreach (array_slice($realFiles, 0, 20) as $file) {
            $fullPath = $uploadsDir . $file;
            echo "  - " . $file . " (" . (file_exists($fullPath) ? "EXISTS" : "MISSING") . ", " . number_format(filesize($fullPath)/1024, 2) . " KB)\n";
        }
        if (count($realFiles) > 20) {
            echo "  ... and " . (count($realFiles) - 20) . " more files\n";
        }
    } else {
        echo "Uploads directory does not exist!\n";
    }

    echo "\n=== Database Submissions ===\n\n";

    // Get all submissions
    $sql = "
        SELECT 
            s.id,
            s.file_path,
            s.submitted_at,
            s.student_id,
            st.name AS student_name,
            a.title AS assignment_title
        FROM submissions s
        LEFT JOIN students st ON s.student_id = st.id
        LEFT JOIN assignments a ON s.assignment_id = a.id
        WHERE s.file_path IS NOT NULL
        ORDER BY s.submitted_at DESC
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo "Found " . count($submissions) . " submissions with files:\n\n";

    foreach ($submissions as $submission) {
        $fullPath = $uploadsDir . $submission['file_path'];
        $exists = file_exists($fullPath);
        $readable = $exists && is_readable($fullPath);
        
        echo "Submission ID: " . $submission['id'] . "\n";
        echo "Student: " . $submission['student_name'] . "\n";
        echo "Assignment: " . ($submission['assignment_title'] ?: 'N/A') . "\n";
        echo "Database Path: " . $submission['file_path'] . "\n";
        echo "Full Path: " . $fullPath . "\n";
        echo "File Exists: " . ($exists ? "YES" : "NO") . "\n";
        echo "File Readable: " . ($readable ? "YES" : "NO") . "\n";
        
        if ($exists) {
            echo "File Size: " . number_format(filesize($fullPath)/1024, 2) . " KB\n";
            echo "File Modified: " . date('Y-m-d H:i:s', filemtime($fullPath)) . "\n";
        }
        
        echo "Status: " . ($exists ? "✅ OK" : "❌ MISSING") . "\n";
        echo "----------------------------------------\n";
    }

    // Check for orphaned files (files in uploads not in database)
    echo "\n=== Orphaned Files Check ===\n\n";
    
    if (is_dir($uploadsDir)) {
        $allFiles = array_filter(scandir($uploadsDir), function($file) {
            return $file !== '.' && $file !== '..';
        });
        
        $dbFiles = [];
        foreach ($submissions as $submission) {
            $dbFiles[] = $submission['file_path'];
        }
        
        $orphaned = array_diff($allFiles, $dbFiles);
        
        if (!empty($orphaned)) {
            echo "Found " . count($orphaned) . " orphaned files (in uploads but not in database):\n";
            foreach ($orphaned as $file) {
                $fullPath = $uploadsDir . $file;
                echo "  - " . $file . " (" . number_format(filesize($fullPath)/1024, 2) . " KB)\n";
            }
        } else {
            echo "No orphaned files found.\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>
