<?php
require_once __DIR__.'/../auth/Auth.php';
require_once __DIR__.'/../config/database.php';
Auth::guard();

$studentId = $_GET['student_id'] ?? null;
$isPreview = $_GET['preview'] ?? false;
$isPrint = $_GET['print'] ?? false;

if (!$studentId) {
    die('Student ID required');
}

$db = getDB();

// Fetch student details
$studentStmt = $db->prepare("
    SELECT id, full_name, reg_number, email, lab_id 
    FROM users 
    WHERE id = ? AND role = 'student'
");
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch();

if (!$student) {
    die('Student not found');
}

// Fetch latest practical for student
$practicalStmt = $db->prepare("
    SELECT p.id, p.title, p.description, p.lab_id, sp.status, sp.start_time, sp.end_time, 
           sp.readings_json, sp.completion_percentage, sp.blockchain_hash
    FROM practicals p
    JOIN student_practicals sp ON p.id = sp.practical_id
    WHERE sp.student_id = ?
    ORDER BY sp.start_time DESC
    LIMIT 1
");
$practicalStmt->execute([$studentId]);
$practical = $practicalStmt->fetch();

$readings = [];
if ($practical && $practical['readings_json']) {
    $readings = json_decode($practical['readings_json'], true) ?: [];
}

// Format dates
$startTime = $practical ? date('d M Y H:i:s', strtotime($practical['start_time'])) : '--';
$endTime = $practical ? date('d M Y H:i:s', strtotime($practical['end_time'])) : '--';
$currentDate = date('d M Y H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Report - <?= htmlspecialchars($student['full_name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Syne', sans-serif;
            color: #1f2937;
            line-height: 1.6;
            background: #f9fafb;
            padding: 2rem;
        }

        .report-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 3rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* Header */
        .report-header {
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }

        /* Section Styles */
        .report-section {
            margin-bottom: 2rem;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-left: 4px solid #3b82f6;
            padding-left: 1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 0.375rem;
            border-left: 3px solid #3b82f6;
        }

        .info-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            display: block;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* Tables */
        .readings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .readings-table thead {
            background-color: #3b82f6;
            color: white;
        }

        .readings-table th {
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            border: 1px solid #3b82f6;
        }

        .readings-table td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
        }

        .readings-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .readings-table tbody tr:hover {
            background-color: #f3f4f6;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-badge.completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-badge.in-progress {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge.pending {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Blockchain Section */
        .blockchain-section {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .blockchain-hash {
            font-family: 'DM Mono', monospace;
            font-size: 0.9rem;
            background: white;
            padding: 0.75rem;
            border-radius: 0.25rem;
            word-break: break-all;
            color: #0369a1;
            border: 1px solid #bfdbfe;
        }

        /* Footer */
        .report-footer {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.85rem;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .report-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .print-actions {
                page-break-before: always;
            }
        }

        .print-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 0.375rem;
        }

        .print-btn {
            padding: 0.7rem 1.5rem;
            font-size: 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .print-btn.print {
            background: #3b82f6;
            color: white;
        }

        .print-btn.print:hover {
            background: #2563eb;
        }

        .print-btn.back {
            background: #e5e7eb;
            color: #1f2937;
        }

        .print-btn.back:hover {
            background: #d1d5db;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .report-container {
                padding: 1.5rem;
            }

            .report-footer {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>

<div class="report-container">

    <!-- Print Actions -->
    <div class="print-actions no-print">
        <button class="print-btn print" onclick="window.print()">🖨️ Print Report</button>
        <button class="print-btn back" onclick="window.history.back()">← Back</button>
    </div>

    <!-- Report Header -->
    <div class="report-header">
        <h1 class="header-title">Smart Lab Report</h1>
        <p class="header-subtitle">Practical Execution Report - UNILIS Laboratory Management System</p>
    </div>

    <!-- Student Information -->
    <div class="report-section">
        <h2 class="section-title">Student Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?= htmlspecialchars($student['full_name']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Registration Number</span>
                <span class="info-value"><?= htmlspecialchars($student['reg_number']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($student['email']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lab Assignment</span>
                <span class="info-value"><?= htmlspecialchars($student['lab_id'] || 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- Practical Information -->
    <?php if ($practical): ?>
    <div class="report-section">
        <h2 class="section-title">Practical Details</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Experiment Name</span>
                <span class="info-value"><?= htmlspecialchars($practical['title']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="status-badge <?= $practical['status'] ?>"><?= ucfirst($practical['status']) ?></span>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Start Time</span>
                <span class="info-value"><?= $startTime ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">End Time</span>
                <span class="info-value"><?= $endTime ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Completion</span>
                <span class="info-value"><?= $practical['completion_percentage'] ?? 0 ?>%</span>
            </div>
            <div class="info-item">
                <span class="info-label">Lab Location</span>
                <span class="info-value"><?= htmlspecialchars($practical['lab_id'] || 'N/A') ?></span>
            </div>
        </div>

        <div class="report-section">
            <h3 class="section-title">Experiment Description</h3>
            <p><?= nl2br(htmlspecialchars($practical['description'])) ?></p>
        </div>
    </div>

    <!-- Readings Data -->
    <?php if (!empty($readings)): ?>
    <div class="report-section">
        <h2 class="section-title">Recorded Readings</h2>
        <table class="readings-table">
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Value</th>
                    <th>Unit</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($readings as $reading): ?>
                <tr>
                    <td><?= htmlspecialchars($reading['parameter'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($reading['value'] ?? '--') ?></td>
                    <td><?= htmlspecialchars($reading['unit'] ?? '--') ?></td>
                    <td><?= htmlspecialchars($reading['timestamp'] ?? '--') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Blockchain Verification -->
    <?php if ($practical): ?>
    <div class="blockchain-section">
        <h3 class="section-title">Blockchain Verification</h3>
        <p>This practical record is secured on the UNILIS blockchain network.</p>
        <div style="margin-top: 1rem;">
            <strong>Transaction Hash:</strong>
            <div class="blockchain-hash">
                <?= htmlspecialchars($practical['blockchain_hash'] ?? 'Not yet recorded') ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Footer -->
<div class="report-footer">
    <div>
        <strong>Report Generated:</strong> <?= $currentDate ?>
    </div>
    <div>
        <strong>System:</strong> UNILIS SmartLab v1.0
    </div>
</div>

<script>
    // Auto-print if print parameter is set
    <?php if ($isPrint): ?>
    window.onload = function() {
        window.print();
        setTimeout(() => window.history.back(), 1000);
    };
    <?php endif; ?>

    // For preview, just display the page
</script>

</body>
</html>
