<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$lecturer_id = (int)$_SESSION['user_id'];
$unit_id     = (int)($_GET['unit'] ?? 0);

if ($unit_id <= 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Invalid unit</h3>");
}

// Verify lecturer teaches this unit
$stmt = $conn->prepare("SELECT 1 FROM lecturer_units WHERE lecturer_id = ? AND unit_id = ?");
$stmt->bind_param("ii", $lecturer_id, $unit_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    die("<h3 style='color:red;text-align:center;margin:100px;'>Unauthorized</h3>");
}
$stmt->close();

// Get unit name
$unit_name = $conn->query("SELECT name FROM units WHERE id = $unit_id")
                ->fetch_assoc()['name'] ?? "Unit";

// Get all sessions
$sessions = $conn->query("
    SELECT id, session_code, created_at 
    FROM attendance_sessions 
    WHERE unit_id = $unit_id AND lecturer_id = $lecturer_id 
    ORDER BY created_at DESC
");

// Get all students
$students = $conn->query("
    SELECT s.id, s.reg_no, s.name 
    FROM students s 
    JOIN student_units su ON s.id = su.student_id 
    WHERE su.unit_id = $unit_id 
    ORDER BY s.reg_no
");

$session_list = [];
while ($s = $sessions->fetch_assoc()) {
    $session_list[] = $s;
}

$report = [];
while ($student = $students->fetch_assoc()) {
    $row = ['info' => $student, 'attendance' => [], 'total' => 0];
    foreach ($session_list as $sess) {
        $rec = $conn->query("
            SELECT attended FROM attendance_records 
            WHERE session_id = {$sess['id']} AND student_id = {$student['id']}
        ")->fetch_assoc();
        $present = $rec['attended'] ?? 0;
        $row['attendance'][] = $present;
        $row['total'] += $present;
    }
    $report[] = $row;
}

// ============= PDF GENERATION (NO TCPDF NEEDED) =============
if (isset($_GET['pdf'])) {
    $html = '
    <h1 style="text-align:center;color:#f59e0b;">Attendance Report</h1>
    <h3 style="text-align:center;">' . htmlspecialchars($unit_name) . '</h3>
    <p style="text-align:center;">Generated on ' . date('d M Y') . '</p>
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%;font-size:12px;">
        <thead>
            <tr style="background:#f59e0b;color:white;">
                <th>Reg No</th>
                <th>Name</th>';
    foreach ($session_list as $s) {
        $html .= '<th>' . date('d/m<br>H:i', strtotime($s['created_at'])) . '</th>';
    }
    $html .= '<th><strong>TOTAL</strong></th>
            </tr>
        </thead>
        <tbody>';

    foreach ($report as $row) {
        $html .= '<tr>
            <td>' . $row['info']['reg_no'] . '</td>
            <td>' . htmlspecialchars($row['info']['name']) . '</td>';
        foreach ($row['attendance'] as $a) {
            $html .= '<td style="text-align:center">' . ($a ? '1' : '') . '</td>';
        }
        $html .= '<td><strong>' . $row['total'] . '/' . count($session_list) . '</strong></td>
        </tr>';
    }
    $html .= '</tbody></table>';

    // Use free online HTML-to-PDF service (no installation!)
    $pdf_url = "https://api.htmlcsstoimage.com/v1/pdf";
    $post_data = json_encode([
        "html" => $html,
        "css"  => "body{font-family:Arial,sans-serif;} table{width:100%;border-collapse:collapse;}",
        "google_fonts" => "Roboto"
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pdf_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    $pdf_content = curl_exec($ch);
    curl_close($ch);

    if ($pdf_content) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Attendance_' . preg_replace('/[^a-zA-Z0-9]/', '_', $unit_name) . '_' . date('Y-m-d') . '.pdf"');
        echo $pdf_content;
        exit;
    } else {
        die("PDF generation failed. Try again.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance Report - <?= htmlspecialchars($unit_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; }
        .header { background: linear-gradient(135deg, #f59e0b, #f97316); color: white; padding: 20px; border-radius: 15px; }
        .table th { background: #f59e0b; color: white; }
        .present { background: #d4edda !important; text-align:center; font-weight:bold; font-size:1.3rem; }
        .absent  { background: #f8d7da !important; text-align:center; }
        .total-col { background: #fff3cd !important; font-weight:bold; text-align:center; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="header shadow-lg mb-4 text-center">
        <h2 class="mb-1">Attendance Report</h2>
        <h4><?= htmlspecialchars($unit_name) ?></h4>
    </div>

    <div class="text-end mb-3">
        <a href="lecturer_take_attendance.php?unit=<?= $unit_id ?>" class="btn btn-success btn-lg me-2">
            Take Attendance
        </a>
        <a href="?unit=<?= $unit_id ?>&pdf=1" class="btn btn-danger btn-lg">
            Download PDF
        </a>
    </div>

    <div class="table-responsive shadow-lg rounded">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Reg No</th>
                    <th>Name</th>
                    <?php foreach ($session_list as $s): ?>
                        <th><?= date('d/m<br>H:i', strtotime($s['created_at'])) ?><br>
                            <small class="text-warning"><?= $s['session_code'] ?></small>
                        </th>
                    <?php endforeach; ?>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report as $row): ?>
                    <tr>
                        <td><strong><?= $row['info']['reg_no'] ?></strong></td>
                        <td><?= htmlspecialchars($row['info']['name']) ?></td>
                        <?php foreach ($row['attendance'] as $a): ?>
                            <td class="<?= $a ? 'present' : 'absent' ?>"><?= $a ? '1' : '' ?></td>
                        <?php endforeach; ?>
                        <td class="total-col">
                            <?= $row['total'] ?> / <?= count($session_list) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>