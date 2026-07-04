<?php
header('Content-Type: application/json');

function connectToDatabase(): mysqli
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $dbname = getenv('MYSQL_DATABASE') ?: 'unilis';
    $credentials = [
        [getenv('MYSQL_USER') ?: 'unilisuser', getenv('MYSQL_PASSWORD') ?: 'unilispass'],
        ['root', ''],
    ];

    $lastError = null;
    foreach ($credentials as [$user, $password]) {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli($host, $user, $password, $dbname);
            $conn->set_charset('utf8mb4');
            return $conn;
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new Exception('Unable to connect to the database for migration: ' . $lastError);
}

try {
    $conn = connectToDatabase();

    $check = $conn->query("SHOW COLUMNS FROM team_members LIKE 'role'");
    if (!$check) {
        throw new Exception('Failed to inspect team_members table: ' . $conn->error);
    }

    $sql = null;
    $message = 'team_members.role column already supports specific roles.';

    if ($check->num_rows === 0) {
        $sql = "ALTER TABLE `team_members`
                ADD COLUMN `role` VARCHAR(80) NOT NULL DEFAULT 'member' AFTER `student_id`";
        $message = 'Added team_members.role column successfully.';
    } else {
        $column = $check->fetch_assoc();
        $columnType = strtolower((string)($column['Type'] ?? ''));
        if (str_contains($columnType, 'enum(') || str_contains($columnType, 'set(')) {
            $sql = "ALTER TABLE `team_members`
                    MODIFY COLUMN `role` VARCHAR(80) NOT NULL DEFAULT 'member'";
            $message = 'Updated team_members.role column to allow specific team roles.';
        }
    }

    if ($sql !== null && !$conn->query($sql)) {
        throw new Exception('Failed to update role column: ' . $conn->error);
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}