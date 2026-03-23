<?php
// lecturer/ajax/save_weights.php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success'=>false,'message'=>'Unauthorised']); exit;
}

$lecturer_id = intval($_SESSION['user_id']);
$unit_id     = intval($_POST['unit_id'] ?? 0);
if (!$unit_id) { echo json_encode(['success'=>false,'message'=>'unit_id required']); exit; }

$types = ['quiz','assignment','cat','exam'];
$weights = [];
$total = 0;
foreach ($types as $t) {
    $w = floatval($_POST['weight_'.$t] ?? 0);
    $weights[$t] = $w;
    $total += $w;
}

if (abs($total - 100) > 0.05) {
    echo json_encode(['success'=>false,'message'=>'Weights must total 100% (got '.round($total,2).'%)']); exit;
}

try {
    // Create table if not exists — safe guard
    $conn->query("
        CREATE TABLE IF NOT EXISTS assessment_weights (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            unit_id          INT NOT NULL,
            lecturer_id      INT NOT NULL,
            assessment_type  VARCHAR(32) NOT NULL,
            weight_percent   DECIMAL(5,2) NOT NULL DEFAULT 0,
            updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_unit_lec_type (unit_id, lecturer_id, assessment_type)
        )
    ");

    $stmt = $conn->prepare("
        INSERT INTO assessment_weights (unit_id, lecturer_id, assessment_type, weight_percent)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE weight_percent = VALUES(weight_percent)
    ");
    foreach ($weights as $type => $w) {
        $stmt->bind_param("iisd", $unit_id, $lecturer_id, $type, $w);
        $stmt->execute();
    }
    $stmt->close();
    echo json_encode(['success'=>true]);
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success'=>false,'message'=>'DB error: '.$e->getMessage()]);
}