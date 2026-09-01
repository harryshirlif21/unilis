<?php
/** Shared authorization for short-course builder AJAX actions. */

function shortCourseIsAuthor(): bool
{
    return isset($_SESSION['user_id'])
        && in_array($_SESSION['user_role'] ?? '', ['lecturer', 'department_admin', 'admin'], true);
}

function shortCourseCanView(mysqli $conn, int $courseId): bool
{
    if (!shortCourseIsAuthor() || $courseId <= 0) {
        return false;
    }
    $userId = (int)$_SESSION['user_id'];
    $role = $_SESSION['user_role'];
    if ($role === 'admin') {
        $stmt = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $allowed = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $allowed;
    }
    if ($role === 'department_admin') {
        $departmentId = (int)($_SESSION['department_id'] ?? 0);
        $stmt = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ? AND department_id = ? LIMIT 1');
        $stmt->bind_param('ii', $courseId, $departmentId);
        $stmt->execute();
        $allowed = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $allowed;
    }

    // Lecturer: any linked tutor � primary, contributor, module-level, or
    // lesson-level assignment � or the course owner, may view.
    $stmt = $conn->prepare('SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
    $stmt->close();

    $stmt = $conn->prepare('SELECT 1 FROM public_courses WHERE id = ? AND created_by_lecturer_id = ? LIMIT 1');
    $stmt->bind_param('ii', $courseId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
    $stmt->close();

    $checkTmp = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
    if ($checkTmp && $checkTmp->num_rows > 0) {
        $stmt = $conn->prepare('SELECT 1 FROM tutor_module_permissions tmp JOIN public_course_modules m ON m.id = tmp.module_id WHERE tmp.tutor_id = ? AND m.course_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $courseId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
        $stmt->close();
    }

    $checkTlp = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
    if ($checkTlp && $checkTlp->num_rows > 0) {
        $stmt = $conn->prepare('SELECT 1 FROM tutor_lesson_permissions tlp JOIN public_course_lessons l ON l.id = tlp.lesson_id JOIN public_course_modules m ON m.id = l.module_id WHERE tlp.tutor_id = ? AND m.course_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $courseId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
        $stmt->close();
    }

    return false;
}
function shortCourseCanEditModule(mysqli $conn, int $moduleId): bool
{
    if (!shortCourseIsAuthor() || $moduleId <= 0) {
        return false;
    }
    $role = $_SESSION['user_role'];
    if ($role === 'admin' || $role === 'department_admin') {
        return true;
    }
    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare('
        SELECT pc.id, pc.created_by_lecturer_id
        FROM public_course_modules m
        JOIN public_courses pc ON pc.id = m.course_id
        WHERE m.id = ? LIMIT 1
    ');
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$course) {
        return false;
    }

    // Course owner or primary (active) tutor may edit any module.
    if ((int)$course['created_by_lecturer_id'] === $userId) {
        return true;
    }
    $stmt = $conn->prepare('SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $course['id']);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
    $stmt->close();

    // Contributor: needs an explicit can_edit grant on this module.
    $checkTmp = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
    if ($checkTmp && $checkTmp->num_rows > 0) {
        $stmt = $conn->prepare('SELECT can_edit FROM tutor_module_permissions WHERE tutor_id = ? AND module_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $moduleId);
        $stmt->execute();
        $perm = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($perm) {
            return (bool)$perm['can_edit'];
        }
    }

    return false;
}

function shortCourseCanEditLesson(mysqli $conn, int $lessonId): bool
{
    if (!shortCourseIsAuthor() || $lessonId <= 0) {
        return false;
    }
    $role = $_SESSION['user_role'];
    if ($role === 'admin' || $role === 'department_admin') {
        return true;
    }
    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare('
        SELECT pc.id AS course_id, pc.created_by_lecturer_id, m.id AS module_id
        FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        JOIN public_courses pc ON pc.id = m.course_id
        WHERE l.id = ? LIMIT 1
    ');
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }

    if ((int)$row['created_by_lecturer_id'] === $userId) {
        return true;
    }
    $stmt = $conn->prepare('SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $row['course_id']);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
    $stmt->close();

    // Lesson-level grant takes precedence if present.
    $checkTlp = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
    if ($checkTlp && $checkTlp->num_rows > 0) {
        $stmt = $conn->prepare('SELECT can_edit FROM tutor_lesson_permissions WHERE tutor_id = ? AND lesson_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $lessonId);
        $stmt->execute();
        $perm = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($perm) {
            return (bool)$perm['can_edit'];
        }
    }

    // Otherwise fall back to the lesson's parent module grant.
    return shortCourseCanEditModule($conn, (int)$row['module_id']);
}
function shortCourseIsAssignedToModule(mysqli $conn, int $moduleId): bool
{
    if (!shortCourseIsAuthor() || $moduleId <= 0) {
        return false;
    }
    $role = $_SESSION['user_role'];
    if ($role === 'admin' || $role === 'department_admin') {
        return true;
    }
    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare('
        SELECT pc.id, pc.created_by_lecturer_id
        FROM public_course_modules m
        JOIN public_courses pc ON pc.id = m.course_id
        WHERE m.id = ? LIMIT 1
    ');
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$course) {
        return false;
    }

    if ((int)$course['created_by_lecturer_id'] === $userId) {
        return true;
    }
    $stmt = $conn->prepare('SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $course['id']);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
    $stmt->close();

    // Contributor: any assignment row at all (view-only or edit) counts.
    $checkTmp = $conn->query("SHOW TABLES LIKE 'tutor_module_permissions'");
    if ($checkTmp && $checkTmp->num_rows > 0) {
        $stmt = $conn->prepare('SELECT 1 FROM tutor_module_permissions WHERE tutor_id = ? AND module_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $moduleId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
        $stmt->close();
    }

    return false;
}

function shortCourseIsAssignedToLesson(mysqli $conn, int $lessonId): bool
{
    if (!shortCourseIsAuthor() || $lessonId <= 0) {
        return false;
    }
    $role = $_SESSION['user_role'];
    if ($role === 'admin' || $role === 'department_admin') {
        return true;
    }
    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare('
        SELECT pc.id AS course_id, pc.created_by_lecturer_id, m.id AS module_id
        FROM public_course_lessons l
        JOIN public_course_modules m ON m.id = l.module_id
        JOIN public_courses pc ON pc.id = m.course_id
        WHERE l.id = ? LIMIT 1
    ');
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }

    if ((int)$row['created_by_lecturer_id'] === $userId) {
        return true;
    }
    $stmt = $conn->prepare('SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $row['course_id']);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
    $stmt->close();

    // Direct lesson-level grant (any value) counts as assigned.
    $checkTlp = $conn->query("SHOW TABLES LIKE 'tutor_lesson_permissions'");
    if ($checkTlp && $checkTlp->num_rows > 0) {
        $stmt = $conn->prepare('SELECT 1 FROM tutor_lesson_permissions WHERE tutor_id = ? AND lesson_id = ? LIMIT 1');
        $stmt->bind_param('ii', $userId, $lessonId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) { $stmt->close(); return true; }
        $stmt->close();
    }

    // Otherwise inherit from the parent module's assignment.
    return shortCourseIsAssignedToModule($conn, (int)$row['module_id']);
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
        $stmt = $conn->prepare('SELECT 1 FROM short_course_tutors WHERE lecturer_id = ? AND short_course_id = ? UNION SELECT 1 FROM public_courses WHERE id = ? AND created_by_lecturer_id = ? LIMIT 1');
        $stmt->bind_param('iiii', $userId, $courseId, $courseId, $userId);
    }

    $stmt->execute();
    $allowed = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $allowed;
}
