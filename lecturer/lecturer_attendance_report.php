<?php
session_start();
require_once 'attendance_functions.php';
require_once 'vendor/autoload.php'; // TCPDF

use TCPDF;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: login.php'); exit;
}

$lecturer_id = $_SESSION['user_id'];
$unit_id = (int)$_GET['unit'];

// Optional: Get unit name
$unit_name = $conn->query("SELECT name FROM units WHERE id = $unit_id")->fetch_assoc()['name'] ?? 'Unit';

$report = getAttendanceReport($unit_id, $lecturer_id);

if (!$report) {
    die("Unauthorized or no data");
}

// PDF Generation
if (isset($_GET['pdf'])) {
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Attendance System');
    $pdf->SetTitle('Attendance Report - ' . $unit_name);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage('L'); // Landscape

    $html = '<h1 style="text-align:center;">Attendance Report<br><small>' . htmlspecialchars($unit_name) . '</small></h1>';
    $html .= '<table border="1" cellpadding="6" cellspacing="0">';
    $html .= '<thead><tr style="background-color:#2563eb;color:white;">
                <th>Reg No</th><th>Name</th>';
    foreach ($report['sessions'] as $s) {
        $html .= '<th>' . date('d/m<br>H:i', strtotime($s['created_at'])) . '</th>';
    }
    $html .= '<th><strong>TOTAL</strong></th></tr></thead><tbody>';

    foreach ($report['report'] as $row) {
        $html .= '<tr>
            <td>' . $row['student']['reg_no'] . '</td>
            <td>' . htmlspecialchars($row['student']['name']) . '</td>';
        foreach ($row['attendance'] as $a) {
            $html .= '<td style="text-align:center">' . ($a ? '1' : '') . '</td>';
        }
        $html .= '<td><strong>' . $row['total'] . '/' . $report['total_sessions'] . '</strong></td>
        </tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html);
    $pdf->Output('Attendance_' . $unit_name . '_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .present { background-color: #d4edda; text-align:center; font-weight:bold; }
        .absent { background-color: #f8d7da; text-align:center; }
        th { background:#2563eb; color:white; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Attendance Report - <?= htmlspecialchars($unit_name) ?></h2>
        <div>
            <a href="lecturer_take_attendance.php?unit=<?= $unit_id ?>" class="btn btn-success">Take Attendance</a>
            <a href="?unit=<?= $unit_id ?>&pdf=1" class="btn btn-danger">Download PDF</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Reg No</th>
                    <th>Student Name</th>
                    <?php foreach ($report['sessions'] as $s): ?>
                        <th><?= date('d/m/y<br>H:i', strtotime($s['created_at'])) ?><br><small><?= $s['session_code'] ?></small></th>
                    <?php endforeach; ?>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['report'] as $row): ?>
                    <tr>
                        <td><?= $row['student']['reg_no'] ?></td>
                        <td><?= htmlspecialchars($row['student']['name']) ?></td>
                        <?php foreach ($row['attendance'] as $a): ?>
                            <td class="<?= $a ? 'present' : 'absent' ?>"><?= $a ? '1' : '' ?></td>
                        <?php endforeach; ?>
                        <td class="table-success"><strong><?= $row['total'] ?> / <?= $report['total_sessions'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>