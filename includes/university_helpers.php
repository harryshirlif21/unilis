<?php

function get_jkuat_university_id(mysqli $conn): int
{
    $shortName = 'JKUAT';
    $fullName = 'JOMO KENYATTA UNIVERSITY OF AGRICULTURE AND TECHNOLOGY';
    $stmt = $conn->prepare("
        SELECT id
        FROM universities
        WHERE UPPER(TRIM(name)) IN (?, ?)
           OR UPPER(TRIM(name)) LIKE '%JKUAT%'
        ORDER BY id
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to find the JKUAT university record: ' . $conn->error);
    }

    $stmt->bind_param('ss', $shortName, $fullName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        if ($result->num_rows === 0) {
            throw new RuntimeException('Add a JKUAT university record before assigning students.');
        }
        throw new RuntimeException('More than one university record matches JKUAT. Resolve the duplicate records first.');
    }

    $universityId = (int)$result->fetch_assoc()['id'];
    $stmt->close();

    return $universityId;
}
