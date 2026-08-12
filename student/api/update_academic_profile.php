<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../teams/includes/team_profile_sync.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = (string) ($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$departmentId = (int) ($input['department_id'] ?? 0);
$courseId = (int) ($input['course_id'] ?? 0);
$yearOfStudy = (int) ($input['year_of_study'] ?? 0);

if ($departmentId <= 0 || $courseId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Department and course are required']);
    exit;
}

if ($yearOfStudy < 1 || $yearOfStudy > 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Year of study must be between 1 and 6']);
    exit;
}

try {
    $studentStmt = $conn->prepare('SELECT university_id, department_id, course_id FROM students WHERE id = ? LIMIT 1');
    if (!$studentStmt) {
        throw new RuntimeException('Failed to load student profile');
    }
    $studentStmt->bind_param('i', $userId);
    $studentStmt->execute();
    $studentRow = $studentStmt->get_result()->fetch_assoc();
    $studentStmt->close();

    if (!$studentRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    $storedUniversityId = (int) ($studentRow['university_id'] ?? 0);

    $deptStmt = $conn->prepare('SELECT id, university_id FROM departments WHERE id = ? LIMIT 1');
    if (!$deptStmt) {
        throw new RuntimeException('Failed to validate department');
    }
    $deptStmt->bind_param('i', $departmentId);
    $deptStmt->execute();
    $department = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();

    if (!$department) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Selected department was not found']);
        exit;
    }

    $departmentUniversityId = (int) ($department['university_id'] ?? 0);
    if ($departmentUniversityId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Selected department is not linked to a school']);
        exit;
    }

    $universityId = $storedUniversityId;
    if ($universityId <= 0) {
        $fallbackDeptId = (int) ($studentRow['department_id'] ?? 0);
        if ($fallbackDeptId > 0) {
            $fallbackStmt = $conn->prepare('SELECT university_id FROM departments WHERE id = ? LIMIT 1');
            if ($fallbackStmt) {
                $fallbackStmt->bind_param('i', $fallbackDeptId);
                $fallbackStmt->execute();
                $fallbackRow = $fallbackStmt->get_result()->fetch_assoc();
                $fallbackStmt->close();
                $universityId = (int) ($fallbackRow['university_id'] ?? 0);
            }
        }
    }

    if ($universityId <= 0) {
        $universityId = $departmentUniversityId;
    }

    if ($departmentUniversityId !== $universityId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Selected department does not belong to your registered school']);
        exit;
    }

    $courseStmt = $conn->prepare('SELECT id, department_id, name FROM courses WHERE id = ? LIMIT 1');
    if (!$courseStmt) {
        throw new RuntimeException('Failed to validate course');
    }
    $courseStmt->bind_param('i', $courseId);
    $courseStmt->execute();
    $course = $courseStmt->get_result()->fetch_assoc();
    $courseStmt->close();

    if (!$course || (int) ($course['department_id'] ?? 0) !== $departmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Selected course does not belong to the chosen department']);
        exit;
    }

    if ($storedUniversityId <= 0) {
        $updateStmt = $conn->prepare('
            UPDATE students
            SET department_id = ?, course_id = ?, year_of_study = ?, university_id = ?
            WHERE id = ?
            LIMIT 1
        ');
        if (!$updateStmt) {
            throw new RuntimeException('Failed to prepare profile update');
        }

        $updateStmt->bind_param('iiiii', $departmentId, $courseId, $yearOfStudy, $universityId, $userId);
    } else {
        $updateStmt = $conn->prepare('
            UPDATE students
            SET department_id = ?, course_id = ?, year_of_study = ?
            WHERE id = ?
            LIMIT 1
        ');
        if (!$updateStmt) {
            throw new RuntimeException('Failed to prepare profile update');
        }

        $updateStmt->bind_param('iiii', $departmentId, $courseId, $yearOfStudy, $userId);
    }
    $updateStmt->execute();
    $updateStmt->close();

    $teamSync = team_sync_after_student_profile_update($conn, $userId, $courseId, $yearOfStudy);

    $_SESSION['course_id'] = $courseId;
    $_SESSION['year_of_study'] = $yearOfStudy;

    $message = 'Profile updated successfully';
    if (($teamSync['teams_updated'] ?? 0) > 0) {
        $message .= '. Your team leadership and team records were kept in sync';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'university_id' => $universityId,
        'department_id' => $departmentId,
        'course_id' => $courseId,
        'year_of_study' => $yearOfStudy,
        'course_name' => (string) ($course['name'] ?? ''),
        'teams_sync' => $teamSync,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
