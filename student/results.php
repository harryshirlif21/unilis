<?php
/**
 * Student Transcript / Results
 * Shows the logged-in student's own enrollments grouped by academic year then
 * unit, with per-type item tables, type averages, weighted grade and final
 * status, plus retake details. Multiple attempts of the same unit appear as
 * separate blocks.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/results_aggregator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php"); exit;
}

$student_id   = intval($_SESSION['user_id']);
$student_name = $_SESSION['user_name'] ?? 'Student';
$embedded     = isset($_GET['embedded']) && $_GET['embedded'] === '1';

$enrollments = [];
try {
    $enrollments = results_enrollments_for_student($conn, $student_id);
} catch (mysqli_sql_exception $e) {
    error_log("[results] " . $e->getMessage());
}

// Compute each enrollment once.
$resultsByEnr = [];
foreach ($enrollments as $enr) {
    try { $resultsByEnr[$enr['id']] = results_compute_for_enrollment($conn, $enr); }
    catch (mysqli_sql_exception $e) { error_log("[results] " . $e->getMessage()); }
}

// Group by academic year -> units (a unit may appear more than once = attempts).
$grouped = [];
foreach ($enrollments as $enr) {
    $ay = $enr['academic_year'] ?? '—';
    $grouped[$ay][$enr['unit_id']][] = $enr;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; color: #1f2937; }
        .header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 19px; }
        .header .user-info { font-size: 13px; opacity: .85; }
        .header a { color: #fff; text-decoration: none; opacity: .85; margin-left: 16px; }
        .header a:hover { opacity: 1; }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 24px; }
        .intro { margin-bottom: 20px; }
        .intro p { color: #6b7280; font-size: 14px; }
        .ay-block { margin-bottom: 22px; }
        .ay-title { font-size: 15px; font-weight: 700; color: #1e3a8a; margin-bottom: 10px; padding-left: 8px; border-left: 4px solid #2563eb; }
        .unit-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e5e7eb; margin-bottom: 12px; overflow: hidden; }
        .unit-head { display: flex; align-items: center; gap: 12px; padding: 14px 16px; cursor: pointer; }
        .unit-head .chev { transition: transform .2s; color: #2563eb; }
        .unit-card.open .unit-head .chev { transform: rotate(90deg); }
        .unit-title { font-weight: 600; color: #111827; }
        .unit-meta { font-size: .78rem; color: #6b7280; }
        .unit-head .status { margin-left: auto; }
        .unit-body { display: none; padding: 6px 16px 16px; border-top: 1px solid #f1f3f5; }
        .unit-card.open .unit-body { display: block; }
        .empty { padding: 30px; text-align: center; color: #6b7280; background: #fff; border-radius: 10px; border: 1px dashed #d1d5db; }
        .hdr-btn {
            background: #fff; color: #1e3a8a; border: 1px solid #1e3a8a; border-radius: 6px;
            padding: 5px 12px; margin-left: 10px; font-size: 13px; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .hdr-btn:hover { background: #1e3a8a; color: #fff; }
        .hdr-btn.download { background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
        .hdr-btn.download:hover { background: #1e3a8a; color: #fff; }
        <?php if ($embedded): ?>
        body { min-height: 100vh; }
        .header { position: sticky; top: 0; z-index: 1; padding: 12px 18px; }
        .wrap { max-width: none; padding: 18px; }
        <?php endif; ?>
        @media print {
            /* Hide action buttons / nav links when printing; show collapsed unit details */
            .header .user-info button,
            .header .user-info a { display: none; }
            .unit-body { display: block !important; }
            .header { background: #fff; color: #1e3a8a; }
            body { background: #fff; }
        }
    </style>
</head>
<body class="<?= $embedded ? 'embedded' : '' ?>">
    <div class="header">
        <h1><i class="fas fa-file-alt"></i> My Results / Transcript</h1>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($student_name) ?>
            <button type="button" class="hdr-btn" onclick="window.print()" title="Print this transcript"><i class="fas fa-print"></i> Print</button>
            <button type="button" class="hdr-btn download" onclick="window.print()" title="Download as PDF (choose 'Save as PDF')"><i class="fas fa-download"></i> Download PDF</button>
            <?php if ($embedded): ?>
                <button type="button" class="hdr-btn" onclick="if (window.parent && window.parent !== window) window.parent.hideModal('studentResultsModal')"><i class="fas fa-times"></i> Close</button>
            <?php else: ?>
                <a href="my_units.php"><i class="fas fa-arrow-left"></i> Back</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="wrap">
        <div class="intro">
            <p>Your results across units, attempts and academic years. Expand a unit to see individual items, type averages, the weighted grade and any retake.</p>
        </div>

        <?php if (empty($grouped)): ?>
            <div class="empty"><i class="fas fa-inbox" style="font-size:2.5rem;margin-bottom:10px"></i>
                <p>No enrollments found yet. Your results will appear here once you are enrolled in units.</p></div>
        <?php else: ?>
            <?php foreach ($grouped as $ay => $units): ?>
                <div class="ay-block">
                    <div class="ay-title"><i class="fas fa-calendar-alt"></i> Academic Year: <?= htmlspecialchars($ay) ?></div>
                    <?php foreach ($units as $unitId => $attempts): ?>
                        <?php foreach ($attempts as $enr):
                            $r = $resultsByEnr[$enr['id']] ?? null;
                            if (!$r) continue;
                        ?>
                        <div class="unit-card">
                            <div class="unit-head" onclick="this.parentElement.classList.toggle('open')">
                                <i class="fas fa-chevron-right chev"></i>
                                <div>
                                    <div class="unit-title"><?= htmlspecialchars($enr['unit_name'] ?? 'Unit') ?>
                                        <span style="color:#6b7280;font-weight:400"> (<?= htmlspecialchars($enr['unit_code'] ?? '') ?>)</span></div>
                                    <div class="unit-meta">Attempt <?= (int)($enr['attempt_number'] ?? 1) ?> &middot; Semester <?= (int)($enr['semester'] ?? 1) ?>
                                        &middot; <?= htmlspecialchars($ay) ?></div>
                                </div>
                                <span class="status"><?= results_status_badge($r['status']) ?></span>
                            </div>
                            <div class="unit-body">
                                <?= results_render_enrollment_result($conn, $r) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

