<?php
// This script verifies the corrected report query using the production DB loader.
require_once __DIR__ . '/config/database_production.php';
require_once __DIR__ . '/models/ReportModel.php';

try {
    $pdo = getDB();
    echo "Connected to DB host: " . DB_HOST . "\n";

    $reportModel = new ReportModel();

    // Find a sample student ID from the users table.
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'student' LIMIT 1");
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo "No student account found in the database.\n";
        exit(0);
    }

    echo "Testing getStudentReports() for student: {$student['full_name']} ({$student['id']})\n";
    $reports = $reportModel->getStudentReports($student['id']);
    echo "Query executed successfully. Found " . count($reports) . " report(s).\n";

    foreach ($reports as $report) {
        echo "- Report ID: {$report['id']}, Practical: {$report['practical_title']}, Status: {$report['status']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
