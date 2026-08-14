<?php
/** Shared authorization for short-course builder AJAX actions. */

function shortCourseIsAuthor(): bool
{
    return isset($_SESSION['user_id'])
        && in_array($_SESSION['user_role'] ?? '', ['lecturer', 'department_admin', 'admin'], true);
}

function shortCourseCanManage(mysqli $conn, int $courseId): bool
{
    if (!shortCourseIsAuthor() || $courseId <= 0) {
        return false;
    }

    $userId = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'];
    if ($role === 'admin') {
        $stmt = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $courseId);
    } elseif ($role === 'department_admin') {
        $departmentId = (int)($_SESSION['department_id'] ?? 0);
        $stmt = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ? AND department_id = ? LIMIT 1');
        $stmt->bind_param('ii', $courseId, $departmentId);
    } else {
        $stmt = $conn->prepare('SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? AND is_active = 1 UNION SELECT 1 FROM public_courses WHERE id = ? AND created_by_lecturer_id = ? LIMIT 1');
        $stmt->bind_param('iiii', $userId, $courseId, $courseId, $userId);
    }

    $stmt->execute();
    $allowed = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $allowed;
}
