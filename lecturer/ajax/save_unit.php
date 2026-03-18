<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$name        = trim($_POST['name']        ?? '');
$code        = trim($_POST['code']        ?? '');
$year        = isset($_POST['year'])   ? (int)$_POST['year']     : (int)date('Y');
$semester    = isset($_POST['semester'])? (int)$_POST['semester']: 1;

// ── Validation ────────────────────────────────────────────────
if (!$name) {
    echo json_encode(['success' => false, 'message' => 'Unit name is required']);
    exit;
}
if ($semester < 1 || $semester > 3) {
    echo json_encode(['success' => false, 'message' => 'Invalid semester']);
    exit;
}

// ── Check for duplicate code (if provided) ────────────────────
if ($code) {
    $dup = $conn->prepare("SELECT id FROM units WHERE code = ?");
    $dup->bind_param("s", $code);
    $dup->execute();
    $dup->get_result()->fetch_row() && $dup->close() && (
        print json_encode(['success' => false, 'message' => "Unit code '{$code}' already exists"])
    ) && exit;
    $dup->close();
}

// ── Insert unit ───────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO units (name, code, year, semester)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("ssii", $name, $code, $year, $semester);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create unit: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$unit_id = $stmt->insert_id;
$stmt->close();

// ── Assign to this lecturer ───────────────────────────────────
$assign = $conn->prepare("
    INSERT INTO lecturer_units (lecturer_id, unit_id)
    VALUES (?, ?)
");
$assign->bind_param("ii", $lecturer_id, $unit_id);

if (!$assign->execute()) {
    // Roll back the unit insert
    $conn->query("DELETE FROM units WHERE id = $unit_id");
    echo json_encode(['success' => false, 'message' => 'Failed to assign unit to lecturer']);
    $assign->close();
    exit;
}
$assign->close();

echo json_encode([
    'success' => true,
    'message' => "Unit '{$name}' created successfully",
    'unit'    => [
        'id'       => $unit_id,
        'name'     => $name,
        'code'     => $code,
        'year'     => $year,
        'semester' => $semester,
    ],
]);