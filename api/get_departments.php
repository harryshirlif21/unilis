<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$universityId = (int) ($_GET['university_id'] ?? 0);

try {
    if ($universityId > 0) {
        $stmt = $conn->prepare('SELECT id, name FROM departments WHERE university_id = ? ORDER BY name ASC');
        if (!$stmt) {
            throw new RuntimeException('Failed to load departments');
        }
        $stmt->bind_param('i', $universityId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query('SELECT id, name, university_id FROM departments ORDER BY name ASC');
    }

    $departments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'university_id' => isset($row['university_id']) ? (int) $row['university_id'] : $universityId,
            ];
        }
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    echo json_encode(['success' => true, 'departments' => $departments]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'departments' => []]);
}
