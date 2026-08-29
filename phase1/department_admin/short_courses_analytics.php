<?php
/**
 * Short Courses Analytics
 * UNILIS — consolidated course/banner management + student registration analytics.
 *
 * Sits beside phase1/department_admin/dashboard.php and reuses its auth pattern
 * (phase1_guard_role) and the design system used elsewhere in the app
 * (CSS variables --bg/--surface/--accent/--border/--radius, Inter font).
 *
 * Roles / scoping (server-side, never trust a hidden form field):
 *   - admin           -> sees and manages courses across ALL departments
 *   - department_admin -> restricted to $_SESSION['department_id']
 *   - lecturer         -> restricted to their own department (lecturers.department_id)
 *
 * All queries use prepared statements with correctly typed bindings.
 */

define('PHASE1_ACCESS', true);
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_extended.php';

phase1_guard_role(['admin', 'department_admin', 'lecturer'], '../../login.php');

/* ── Constants & environment-agnostic uploads config ─────────────────────── */
// Single shared config constant for public media. Leave empty for a
// web-root-relative URL ("/uploads/..."), which is exactly how the /learn
// catalogue already resolves cover_image. Set to an S3/CDN base URL
// (https://cdn.example.com) to make uploads portable across hosts/containers
// that do not share a filesystem. The same constant is used by both the admin
// preview and the public page, so a banner is never environment-dependent.
define('UPLOAD_BASE_URL', rtrim((string)(getenv('UPLOAD_BASE_URL') ?: ''), '/'));
define('MAX_COVER_SIZE', 5 * 1024 * 1024); // 5 MB
define('APP_ROOT_DIR', realpath(__DIR__ . '/../../'));

$currentRole     = $_SESSION['user_role'] ?? '';
$currentUserId   = (int)($_SESSION['user_id'] ?? 0);
$currentUserName = $_SESSION['user_name'] ?? 'Admin';
$isGlobalAdmin   = ($currentRole === 'admin');

// Resolve the department scope for the current user.
$deptScope = null; // null = all departments (global admin only)
if (!$isGlobalAdmin) {
    if ($currentRole === 'department_admin') {
        $deptScope = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : null;
    } elseif ($currentRole === 'lecturer') {
        $stmt = $conn->prepare('SELECT department_id FROM lecturers WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $currentUserId);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $deptScope = $r && isset($r['department_id']) ? (int)$r['department_id'] : null;
        }
    }
}

/* ── Shared helpers ──────────────────────────────────────────────────────── */

/** Resolve a stored (root-relative) media path to a public URL. */
function short_course_media_url(string $path): string
{
    $path = trim($path);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    if (defined('UPLOAD_BASE_URL') && UPLOAD_BASE_URL !== '') {
        return UPLOAD_BASE_URL . '/' . ltrim($path, '/');
    }
    return '/' . ltrim($path, '/'); // matches /learn catalogue resolution
}

/** Absolute filesystem path for a stored root-relative media path, or null. */
function short_course_abs_path(string $path): ?string
{
    $path = trim($path);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path) || strpos($path, '..') !== false) {
        return null;
    }
    return APP_ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
}

/** Remove a previously stored file, but only if we manage it (inside uploads). */
function short_course_remove_managed_file(string $path): void
{
    $abs = short_course_abs_path($path);
    $base = realpath(APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads');
    if ($abs === null || $base === false) {
        return;
    }
    $real = realpath($abs);
    if ($real !== false && strpos($real, $base . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
        @unlink($real);
    }
}

/**
 * Startup self-check: make sure the uploads dirs exist and are writable, and
 * that they are reachable via the web root (or a configured public base URL).
 * Returns ['ok' => bool, 'messages' => string[]].
 */
function short_course_upload_check(): array
{
    $messages = [];
    $base = APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads';
    $dirs = [$base, $base . DIRECTORY_SEPARATOR . 'sponsors'];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $messages[] = 'Upload directory is not writable by the web server: ' . str_replace(APP_ROOT_DIR, '', $dir)
                . ' — banners cannot be saved and will fail silently elsewhere. Fix permissions before uploading.';
        }
    }

    // Reachability: uploads must live under the document root, or a
    // UPLOAD_BASE_URL must be configured, for the browser to load the file.
    $uploadsWeb = str_replace('\\', '/', realpath(APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads') ?: '');
    $docRoot    = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    $underDocRoot = ($docRoot !== '' && strpos($uploadsWeb, $docRoot) === 0);
    if ((!defined('UPLOAD_BASE_URL') || UPLOAD_BASE_URL === '') && !$underDocRoot) {
        $messages[] = 'assets/uploads is not under the document root and no UPLOAD_BASE_URL is set — banners will 404 for public users.';
    }

    return ['ok' => empty($messages), 'messages' => $messages];
}

/** slugify() + guaranteed-unique slug against public_courses. */
function short_course_unique_slug(mysqli $conn, string $title, int $ignoreId = 0): string
{
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
    if ($base === '') {
        $base = 'course';
    }
    $candidate = $base;
    $i = 2;
    while (true) {
        $stmt = $conn->prepare('SELECT id FROM public_courses WHERE slug = ? AND id <> ? LIMIT 1');
        if (!$stmt) {
            return $candidate;
        }
        $stmt->bind_param('si', $candidate, $ignoreId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $candidate;
        }
        $candidate = $base . '-' . $i++;
    }
}



/**
 * STEP 1 — Move the banner image into its final location under
 * assets/uploads (the same directory the lecturer dashboard uses for its
 * notes uploads). Validation uses finfo (MIME sniff) rather than trusting
 * the client extension, and enforces a max size.
 *
 * This step ONLY physically stores the file. The caller is responsible for
 * STEP 2 (popup + banner error on failure) and STEP 3 (saving the returned
 * path to the database afterwards). A failed move therefore never reaches the
 * database.
 *
 * @return array{ok:bool, path:string, error:string}
 */
function short_course_move_banner(array $file, string $subdir = ''): array
{
    $relRoot = 'assets/uploads' . ($subdir !== '' ? '/' . $subdir : '');
    $absRoot = APP_ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relRoot);

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'path' => '', 'error' => 'Invalid upload field.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'path' => '', 'error' => ''];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Upload failed (PHP error code ' . (int)$file['error'] . ').'];
    }
    if ((int)$file['size'] > MAX_COVER_SIZE) {
        return ['ok' => false, 'path' => '', 'error' => 'Image exceeds the ' . (MAX_COVER_SIZE / 1048576) . ' MB limit.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file((string)$file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'path' => '', 'error' => 'Unsupported file type "' . htmlspecialchars((string)$mime, ENT_QUOTES, 'UTF-8') . '". Only JPEG, PNG, GIF, WEBP images are allowed.'];
    }

    if (!is_dir($absRoot)) {
        @mkdir($absRoot, 0775, true);
    }
    if (!is_dir($absRoot) || !is_writable($absRoot)) {
        return ['ok' => false, 'path' => '', 'error' => 'Banner directory is not writable by the web server.'];
    }

    $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($file['name'] ?? 'banner'));
    $clean = pathinfo($clean, PATHINFO_FILENAME);
    $name  = time() . '_' . $clean . '.' . $allowed[$mime];

    if (!move_uploaded_file((string)$file['tmp_name'], $absRoot . DIRECTORY_SEPARATOR . $name)) {
        return ['ok' => false, 'path' => '', 'error' => 'Could not store the uploaded image on this server.'];
    }

    return ['ok' => true, 'path' => $relRoot . '/' . $name, 'error' => ''];
}

/**
 * Fetch a course by id, enforcing the current user's department scope.
 * Returns associative array or null when not found / out of scope.
 */
function short_course_fetch_scoped(mysqli $conn, int $id, ?int $deptScope): ?array
{
    $sql = 'SELECT c.*, d.name AS department_name FROM public_courses c LEFT JOIN departments d ON d.id = c.department_id WHERE c.id = ?';
    if ($deptScope !== null) {
        $sql .= ' AND c.department_id = ?';
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    if ($deptScope !== null) {
        $stmt->bind_param('ii', $id, $deptScope);
    } else {
        $stmt->bind_param('i', $id);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Audit log via the existing system_upgrade_logs infrastructure. */
function short_course_log(string $action, string $description, string $status, array $details = []): void
{
    global $conn;
    phase1_log_upgrade($action, $description, $status, $details, $conn);
}

/* ── Schema guards (consolidated "ensure column/table" helpers) ─────────── */

/** Idempotent "ensure column" — one reusable function instead of many ALTER blocks. */
function short_course_ensure_column(mysqli $conn, string $table, string $column, string $definition): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
    $stmt->close();
    if ($exists) {
        return true;
    }
    $tableQ = str_replace('`', '``', $table);
    $columnQ = str_replace('`', '``', $column);
    return (bool)$conn->query("ALTER TABLE `{$tableQ}` ADD COLUMN `{$columnQ}` {$definition}");
}

/** Idempotent "ensure table" guard. */
function short_course_ensure_table(mysqli $conn, string $table, string $createSql): bool
{
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if ($res && $res->num_rows > 0) {
        return true;
    }
    return (bool)$conn->query($createSql);
}

/* ── Schema guard execution + startup self-check ─────────────────────────── */
$schemaWarnings = [];
// Guard the columns this page reads/writes. All are added idempotently; the
// page still degrades gracefully if any cannot be added.
short_course_ensure_column($conn, 'public_courses', 'is_paid', 'TINYINT(1) NOT NULL DEFAULT 0');
short_course_ensure_column($conn, 'public_courses', 'price', 'DECIMAL(10,2) NULL');
short_course_ensure_column($conn, 'public_courses', 'payment_methods', 'VARCHAR(255) NULL');
short_course_ensure_column($conn, 'public_courses', 'is_sponsored', 'TINYINT(1) NOT NULL DEFAULT 0');
short_course_ensure_column($conn, 'public_courses', 'sponsor_name', 'VARCHAR(255) NULL');
short_course_ensure_column($conn, 'public_courses', 'sponsor_details', 'TEXT NULL');
short_course_ensure_column($conn, 'public_courses', 'sponsor_logo', 'VARCHAR(500) NULL');

// Banner storage self-check — surface loudly, never fail silently.
$uploadCheck = short_course_upload_check();
if (!$uploadCheck['ok']) {
    $schemaWarnings = array_merge($schemaWarnings, $uploadCheck['messages']);
}

/* ── State for the page ──────────────────────────────────────────────────── */
$errors   = [];
$messages = [];
// Set by the POST handlers in STEP 2 when moving a banner fails. Used at the
// bottom of the page to fire a browser popup in addition to the error banner.
$bannerMoveError = '';

/* ── Handle POST actions (all server-side scoped + prepared) ────────────── */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE ─────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $title     = trim((string)($_POST['title'] ?? ''));
        $code      = trim((string)($_POST['code'] ?? ''));
        $duration  = trim((string)($_POST['duration'] ?? ''));
        $summary   = trim((string)($_POST['summary'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $level     = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $_POST['level'] : 'beginner';
        $hours     = ($_POST['estimated_hours'] ?? '') !== '' ? (float)$_POST['estimated_hours'] : null;
        $passMark  = (int)($_POST['pass_mark'] ?? 70);
        $isPaid    = !empty($_POST['is_paid']) ? 1 : 0;
        $price     = $isPaid ? ((string)($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null) : null;
        $paymentMethods = $isPaid ? trim((string)($_POST['payment_methods'] ?? '')) : null;
        $isSponsored = !empty($_POST['is_sponsored']) ? 1 : 0;
        $sponsorName = $isSponsored ? trim((string)($_POST['sponsor_name'] ?? '')) : null;
        $sponsorDetails = $isSponsored ? trim((string)($_POST['sponsor_details'] ?? '')) : null;

        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if ($isPaid && ($price === null || $price <= 0)) {
            $errors[] = 'A valid price is required for a paid course.';
        }

        // New course's department: department_admin/lecturer always use their
        // own scope; global admin may pick any department.
        if ($deptScope !== null) {
            $deptId = $deptScope;
        } else {
            $deptId = (int)($_POST['department_id'] ?? 0);
            $deptStmt = $conn->prepare('SELECT id FROM departments WHERE id = ?');
            if ($deptStmt) {
                $deptStmt->bind_param('i', $deptId);
                $deptStmt->execute();
                $deptExists = (bool)$deptStmt->get_result()->fetch_assoc();
                $deptStmt->close();
                if (!$deptExists) {
                    $errors[] = 'Invalid department selected.';
                    $deptId = 0;
                }
            }
        }

        if (empty($errors) && $uploadCheck['ok']) {
            $slug = short_course_unique_slug($conn, $title);

            // STEP 1 — move the banner file to its final location (if uploaded).
            $coverPath  = '';
            $coverError = '';
            if (!empty($_FILES['cover_image']['name'])) {
                $coverRes = short_course_move_banner($_FILES['cover_image']);
                if ($coverRes['ok']) {
                    $coverPath = $coverRes['path'];          // used in STEP 3
                } elseif ($coverRes['error'] !== '') {
                    $coverError = $coverRes['error'];        // STEP 2 failure
                }
            }

            // STEP 2 — a failed banner move surfaces as a popup error and
            // aborts the insert below, so no path is persisted on failure.
            if ($coverError !== '') {
                $errors[] = $coverError;
                $bannerMoveError = $coverError;
            }

            // STEP 3 — only now (banner moved, or nothing uploaded) save the
            // stored path along with the new course to the database.
            if (empty($errors)) {
                $stmt = $conn->prepare('INSERT INTO public_courses
                    (slug, title, code, duration, department_id, summary, description, cover_image,
                     level, estimated_hours, is_published, certificate_enabled, pass_mark,
                     is_paid, price, payment_methods, is_sponsored, sponsor_name, sponsor_details,
                     created_by_lecturer_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt) {
                    $pub = 0; $cert = 1;
                    $stmt->bind_param(
                        'ssssissssdiiiidsissi',
                        $slug, $title, $code, $duration, $deptId, $summary, $description, $coverPath,
                        $level, $hours, $pub, $cert, $passMark,
                        $isPaid, $price, $paymentMethods, $isSponsored, $sponsorName, $sponsorDetails,
                        $currentUserId
                    );
                    if ($stmt->execute()) {
                        $newId = $stmt->insert_id;
                        $stmt->close();
                        short_course_log('short_course_create', "Created short course \"{$title}\" (ID {$newId})", 'success', ['course_id' => $newId]);
                        $messages[] = 'Course created. It is unpublished until you publish it from the list.';
                    } else {
                        $errors[] = 'Failed to create course: ' . $conn->error;
                    }
                } else {
                    $errors[] = 'Failed to prepare create: ' . $conn->error;
                }
            }
        }
    }
}


/* Additional POST actions (update / toggle / delete / tutor assign+remove) */

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $courseId = (int)($_POST['course_id'] ?? 0);

    // UPDATE ─────────────────────────────────────────────────────────────
    if ($action === 'update' && $courseId > 0) {
        $course = short_course_fetch_scoped($conn, $courseId, $deptScope);
        if (!$course) {
            $errors[] = 'Course not found or you do not have permission to edit it.';
        } else {
            $title        = trim((string)($_POST['title'] ?? $course['title']));
            $code         = trim((string)($_POST['code'] ?? ''));
            $duration     = trim((string)($_POST['duration'] ?? ''));
            $summary      = trim((string)($_POST['summary'] ?? ''));
            $description  = trim((string)($_POST['description'] ?? ''));
            $level        = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $_POST['level'] : $course['level'];
            $hours        = ($_POST['estimated_hours'] ?? '') !== '' ? (float)$_POST['estimated_hours'] : null;
            $passMark     = (int)($_POST['pass_mark'] ?? $course['pass_mark']);
            $isPaid       = !empty($_POST['is_paid']) ? 1 : 0;
            $price        = $isPaid ? ((string)($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null) : null;
            $paymentMethods = $isPaid ? trim((string)($_POST['payment_methods'] ?? '')) : null;
            $isSponsored  = !empty($_POST['is_sponsored']) ? 1 : 0;
            $sponsorName  = $isSponsored ? trim((string)($_POST['sponsor_name'] ?? '')) : null;
            $sponsorDetails = $isSponsored ? trim((string)($_POST['sponsor_details'] ?? '')) : null;

            if ($title === '') {
                $errors[] = 'Title is required.';
            }
            if ($isPaid && ($price === null || $price <= 0)) {
                $errors[] = 'A valid price is required for a paid course.';
            }

            // Banner: preserve existing when no new file is uploaded.
            $coverPath = (string)($course['cover_image'] ?? '');
            $removedOld = false;
            $coverError = '';
            if (empty($errors) && !empty($_FILES['cover_image']['name']) && $uploadCheck['ok']) {
                // STEP 1 — move the newly uploaded banner to its final location.
                $coverRes = short_course_move_banner($_FILES['cover_image']);
                if (!$coverRes['ok']) {
                    $coverError = $coverRes['error'];   // STEP 2 failure
                } else {
                    // STEP 3 — this path gets saved to the DB below. Remove the
                    // previously managed file to avoid unbounded disk growth
                    // (history is intentionally not kept).
                    if ($coverPath !== '' && $coverPath !== '0') {
                        short_course_remove_managed_file($coverPath);
                        $removedOld = true;
                    }
                    $coverPath = $coverRes['path'];
                }
            }

            // STEP 2 — a failed banner move surfaces as a popup error and
            // prevents the DB update below from persisting it.
            if ($coverError !== '') {
                $errors[] = $coverError;
                $bannerMoveError = $coverError;
            }

            if (empty($errors)) {
                $slug = short_course_unique_slug($conn, $title, $courseId);
                $stmt = $conn->prepare('UPDATE public_courses SET
                    slug = ?, title = ?, code = ?, duration = ?, summary = ?, description = ?,
                    cover_image = ?, level = ?, estimated_hours = ?, pass_mark = ?,
                    is_paid = ?, price = ?, payment_methods = ?, is_sponsored = ?, sponsor_name = ?,
                    sponsor_details = ?
                    WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param(
                        'ssssssssdiidsissi',
                        $slug, $title, $code, $duration, $summary, $description,
                        $coverPath, $level, $hours, $passMark,
                        $isPaid, $price, $paymentMethods, $isSponsored, $sponsorName,
                        $sponsorDetails, $courseId
                    );
                    if ($stmt->execute()) {
                        $stmt->close();
                        short_course_log('short_course_update', "Updated short course \"{$title}\" (ID {$courseId})", 'success', ['course_id' => $courseId, 'banner_replaced' => $removedOld]);
                        $messages[] = $removedOld
                            ? 'Course updated and the previous banner was removed.'
                            : 'Course updated.';
                    } else {
                        $errors[] = 'Failed to update course: ' . $conn->error;
                    }
                } else {
                    $errors[] = 'Failed to prepare update: ' . $conn->error;
                }
            }
        }
    }

    // TOGGLE publish/unpublish ───────────────────────────────────────────
    if ($action === 'toggle' && $courseId > 0) {
        $course = short_course_fetch_scoped($conn, $courseId, $deptScope);
        if (!$course) {
            $errors[] = 'Course not found or you do not have permission to change it.';
        } else {
            $new = $course['is_published'] ? 0 : 1;
            $stmt = $conn->prepare('UPDATE public_courses SET is_published = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $new, $courseId);
                if ($stmt->execute()) {
                    $stmt->close();
                    short_course_log('short_course_toggle', ($new ? 'Published' : 'Unpublished') . " short course \"{$course['title']}\" (ID {$courseId})", 'success', ['course_id' => $courseId, 'is_published' => $new]);
                    $messages[] = ($new ? 'Course published — it now appears on the public /learn catalogue.' : 'Course unpublished — it is hidden from the public /learn catalogue.');
                } else {
                    $errors[] = 'Failed to update publish state.';
                }
            }
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $courseId = (int)($_POST['course_id'] ?? 0);



    // DELETE ─────────────────────────────────────────────────────────────
    if ($action === 'delete' && $courseId > 0) {
        $course = short_course_fetch_scoped($conn, $courseId, $deptScope);
        if (!$course) {
            $errors[] = 'Course not found or you do not have permission to delete it.';
        } elseif (empty($_POST['confirm'])) {
            $errors[] = 'Deletion requires explicit confirmation.';
        } else {
            // Foreign keys cascade: enrollments, tutors, sponsors, modules, lessons.
            $stmt = $conn->prepare('DELETE FROM public_courses WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $courseId);
                if ($stmt->execute()) {
                    $stmt->close();
                    short_course_log('short_course_delete', "Deleted short course \"{$course['title']}\" (ID {$courseId})", 'warning', ['course_id' => $courseId]);
                    $messages[] = 'Course deleted (including its enrollments, tutors and sponsors).';
                } else {
                    $errors[] = 'Failed to delete course: ' . $conn->error;
                }
            }
        }
    }

    // ASSIGN tutor ───────────────────────────────────────────────────────
    if ($action === 'assign_tutor' && $courseId > 0) {
        $course = short_course_fetch_scoped($conn, $courseId, $deptScope);
        $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
        if (!$course) {
            $errors[] = 'Course not found or out of your scope.';
        } elseif ($lecturerId <= 0) {
            $errors[] = 'Please select a lecturer to assign.';
        } else {
            $stmt = $conn->prepare('SELECT id FROM lecturers WHERE id = ? AND department_id = ?');
            if ($stmt) {
                if ($deptScope !== null) {
                    $stmt->bind_param('ii', $lecturerId, $deptScope);
                } else {
                    $stmt->bind_param('ii', $lecturerId, $course['department_id']);
                }
                $stmt->execute();
                $exists = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$exists) {
                    $errors[] = 'Selected lecturer is not valid for this course.';
                }
            }
            if (empty($errors)) {
                $stmt = $conn->prepare('INSERT INTO short_course_tutors (short_course_id, lecturer_id, assigned_by, is_active)
                    VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1, assigned_by = VALUES(assigned_by)');
                if ($stmt) {
                    $stmt->bind_param('iii', $courseId, $lecturerId, $currentUserId);
                    if ($stmt->execute()) {
                        $stmt->close();
                        short_course_log('short_course_assign_tutor', "Assigned lecturer #{$lecturerId} to course \"{$course['title']}\"", 'success', ['course_id' => $courseId, 'lecturer_id' => $lecturerId]);
                        $messages[] = 'Tutor assigned.';
                    } else {
                        $errors[] = 'Failed to assign tutor: ' . $conn->error;
                    }
                }
            }
        }
    }

    // REMOVE tutor ───────────────────────────────────────────────────────
    if ($action === 'remove_tutor') {
        $tutorId = (int)($_POST['tutor_id'] ?? 0);
        $courseId = (int)($_POST['course_id'] ?? 0);
        $course = $courseId > 0 ? short_course_fetch_scoped($conn, $courseId, $deptScope) : null;
        if (!$course) {
            $errors[] = 'Course not found or out of your scope.';
        } else {
            $stmt = $conn->prepare('DELETE FROM short_course_tutors WHERE id = ? AND short_course_id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $tutorId, $courseId);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $stmt->close();
                    short_course_log('short_course_remove_tutor', "Removed tutor record #{$tutorId} from course \"{$course['title']}\"", 'info', ['course_id' => $courseId, 'tutor_id' => $tutorId]);
                    $messages[] = 'Tutor removed.';
                } else {
                    $errors[] = 'Tutor record not found.';
                }
            }
        }
    }
}


/* ── Data gathering (filters, courses, analytics) ───────────────────────── */

// Departments (for the admin filter + create form).
$departments = [];
$deptRes = $conn->query('SELECT id, name FROM departments ORDER BY name');
if ($deptRes) {
    while ($d = $deptRes->fetch_assoc()) {
        $departments[] = $d;
    }
}

// Filters from the query string.
$filterDept      = ($isGlobalAdmin && isset($_GET['dept'])) ? (int)$_GET['dept'] : 0;
$filterPub       = isset($_GET['pub']) && $_GET['pub'] !== '' ? (int)$_GET['pub'] : -1;
$filterPaid      = isset($_GET['paid']) && $_GET['paid'] !== '' ? (int)$_GET['paid'] : -1;
$filterSponsored = isset($_GET['sponsored']) && $_GET['sponsored'] !== '' ? (int)$_GET['sponsored'] : -1;
$q               = trim((string)($_GET['q'] ?? ''));
$sort            = in_array($_GET['sort'] ?? '', ['title_asc', 'title_desc', 'created_desc', 'created_asc', 'enrollments_desc', 'price_asc'], true) ? $_GET['sort'] : 'created_desc';

$where  = [];
$params = [];
$types  = '';
if ($deptScope !== null) {
    $where[] = 'c.department_id = ?';
    $types  .= 'i';
    $params[] = $deptScope;
} elseif ($filterDept > 0) {
    $where[] = 'c.department_id = ?';
    $types  .= 'i';
    $params[] = $filterDept;
}
if ($filterPub >= 0) {
    $where[] = 'c.is_published = ?';
    $types .= 'i';
    $params[] = $filterPub;
}
if ($filterPaid >= 0) {
    $where[] = 'c.is_paid = ?';
    $types .= 'i';
    $params[] = $filterPaid;
}
if ($filterSponsored >= 0) {
    $where[] = 'c.is_sponsored = ?';
    $types .= 'i';
    $params[] = $filterSponsored;
}
if ($q !== '') {
    $where[] = '(c.title LIKE ? OR c.code LIKE ? OR c.summary LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$orderBy = match ($sort) {
    'title_asc'        => 'c.title ASC',
    'title_desc'       => 'c.title DESC',
    'created_asc'      => 'c.created_at ASC',
    'enrollments_desc' => 'enrollments DESC',
    'price_asc'        => 'c.price ASC',
    default            => 'c.created_at DESC',
};

$sql = "SELECT c.*, d.name AS department_name,
        (SELECT COUNT(*) FROM external_enrollments e WHERE e.course_id = c.id) AS enrollments,
        (SELECT COUNT(*) FROM short_course_tutors t WHERE t.short_course_id = c.id AND t.is_active = 1) AS tutor_count,
        (SELECT GROUP_CONCAT(l.name SEPARATOR ', ')
            FROM short_course_tutors t JOIN lecturers l ON l.id = t.lecturer_id
            WHERE t.short_course_id = c.id AND t.is_active = 1) AS tutor_names
        FROM public_courses c
        LEFT JOIN departments d ON d.id = c.department_id
        WHERE " . (($where ? implode(' AND ', $where) : '1=1')) . "
        ORDER BY {$orderBy}";

$courses = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Overall stat aggregates (scoped).
$totals = ['courses' => count($courses), 'published' => 0, 'enrollments' => 0, 'paid' => 0, 'expected_revenue' => 0.0];
foreach ($courses as $c) {
    $totals['published'] += (int)$c['is_published'];
    $totals['enrollments'] += (int)$c['enrollments'];
    if ((int)$c['is_paid'] === 1) {
        $totals['paid']++;
        $totals['expected_revenue'] += (float)$c['price'] * (int)$c['enrollments'];
    }
}


/**
 * Per-course analytics bundle (registrations, trend, tutors, sponsors).
 */
function short_course_analytics(mysqli $conn, int $courseId): array
{
    $out = ['registrations' => [], 'trend' => [], 'tutors' => [], 'sponsors' => []];

    $stmt = $conn->prepare('SELECT el.id, el.name, el.email, el.phone, el.country, el.organisation,
                ee.enrolled_at, ee.completed_at
            FROM external_enrollments ee
            JOIN external_learners el ON el.id = ee.learner_id
            WHERE ee.course_id = ?
            ORDER BY ee.enrolled_at DESC');
    if ($stmt) {
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $out['registrations'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT DATE_FORMAT(enrolled_at, '%Y-%m') AS ym, COUNT(*) AS cnt
            FROM external_enrollments WHERE course_id = ? GROUP BY ym ORDER BY ym");
    if ($stmt) {
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $out['trend'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT t.id, t.lecturer_id, t.is_active, t.created_at, l.name AS lecturer_name, l.email
            FROM short_course_tutors t JOIN lecturers l ON l.id = t.lecturer_id
            WHERE t.short_course_id = ? AND t.is_active = 1');
    if ($stmt) {
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $out['tutors'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT id, sponsor_name, sponsor_details, sponsor_logo
            FROM course_sponsors WHERE course_id = ? ORDER BY id');
    if ($stmt) {
        $stmt->bind_param('i', $courseId);
        $stmt->execute();
        $out['sponsors'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    return $out;
}

$analyticsById = [];
$unassigned = [];
foreach ($courses as $c) {
    $analyticsById[(int)$c['id']] = short_course_analytics($conn, (int)$c['id']);
    if ((int)$c['tutor_count'] === 0) {
        $unassigned[] = $c;
    }
}

// Integrity warnings for sponsorship: sponsored course missing name/logo.
$sponsorIntegrity = [];
foreach ($courses as $c) {
    if ((int)$c['is_sponsored'] === 1 && (trim((string)($c['sponsor_name'] ?? '')) === '' || trim((string)($c['sponsor_logo'] ?? '')) === '')) {
        $sponsorIntegrity[] = $c;
    }
}

// Per-course lecturer options for assignment (used by the assign form).
$lecturersByDept = [];
if ($isGlobalAdmin) {
    $ld = $conn->query('SELECT id, department_id, name, email FROM lecturers ORDER BY name');
    if ($ld) {
        while ($row = $ld->fetch_assoc()) {
            $lecturersByDept[(int)$row['department_id']][] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Short Courses Analytics · UNILIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    :root {
        /* Design system — matches the app-wide tokens used across UNILIS. */
        --bg: linear-gradient(145deg, #eef2f7 0%, #e8edf5 50%, #edf1f7 100%);
        --surface: #ffffff;
        --surface-inset: #f4f6fb;
        --surface-alt: #f1f5f9;
        --accent: #2563eb;
        --accent-dark: #1d4ed8;
        --accent-soft: rgba(37,99,235,0.10);
        --green: #16a34a;
        --green-soft: rgba(22,163,74,0.10);
        --gold: #b45309;
        --gold-soft: rgba(180,83,9,0.10);
        --danger: #dc2626;
        --danger-soft: rgba(220,38,38,0.10);
        --text: #0a0f1e;
        --text-2: #374151;
        --text-3: #6b7280;
        --text-4: #9ca3af;
        --border: #dde3ee;
        --border-strong: #c4ccd8;
        --border-subtle: #eaeff6;
        --radius-sm: 6px;
        --radius-md: 9px;
        --radius-lg: 13px;
        --radius-xl: 18px;
        --radius-pill: 999px;
        --shadow-sm: 0 2px 6px rgba(10,15,30,0.07);
        --shadow-card: 0 2px 8px rgba(10,15,30,0.07), 0 0 1px rgba(10,15,30,0.08);
        --shadow-md: 0 4px 16px rgba(10,15,30,0.09);
        --transition: all .15s ease;
        --font: 'Inter', -apple-system, 'Segoe UI', sans-serif;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }
    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }

    .layout { display: flex; min-height: 100vh; }
    .sidebar {
        width: 232px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--border-subtle);
        padding: 20px 14px; position: sticky; top: 0; height: 100vh; overflow-y: auto;
    }
    .brand { display: flex; align-items: center; gap: 10px; padding: 4px 8px 18px; border-bottom: 1px solid var(--border-subtle); margin-bottom: 16px; }
    .brand .logo { width: 36px; height: 36px; border-radius: var(--radius-md); background: linear-gradient(135deg, var(--accent), #7c3aed); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; }
    .brand h1 { font-size: 14px; font-weight: 800; }
    .brand small { display: block; font-weight: 500; color: var(--text-3); font-size: 11px; }
    .nav a { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: var(--radius-md); color: var(--text-2); margin-bottom: 2px; font-weight: 500; }
    .nav a:hover { background: var(--surface-inset); text-decoration: none; }
    .nav a.active { background: var(--accent-soft); color: var(--accent-dark); font-weight: 600; }
    .nav a i { width: 18px; text-align: center; }

    .main { flex: 1; padding: 28px 34px; min-width: 0; }
    .page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
    .page-head h2 { font-size: 22px; font-weight: 800; letter-spacing: -0.01em; }
    .page-head p { color: var(--text-3); margin-top: 4px; max-width: 620px; }

    .btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: var(--radius-md); border: 1px solid transparent; font: inherit; font-weight: 600; cursor: pointer; transition: var(--transition); }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { background: var(--accent-dark); }
    .btn-secondary { background: var(--surface); color: var(--text-2); border-color: var(--border-strong); }
    .btn-secondary:hover { background: var(--surface-inset); }
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-sm { padding: 5px 10px; font-size: 12.5px; border-radius: var(--radius-sm); }


    .banner { border-radius: var(--radius-lg); padding: 13px 16px; margin-bottom: 14px; border: 1px solid var(--border); font-weight: 500; display: flex; gap: 10px; align-items: flex-start; }
    .banner i { margin-top: 2px; }
    .banner.error { background: var(--danger-soft); color: var(--danger); border-color: rgba(220,38,38,0.25); }
    .banner.success { background: var(--green-soft); color: var(--green); border-color: rgba(22,163,74,0.25); }
    .banner.warning { background: var(--gold-soft); color: var(--gold); border-color: rgba(180,83,9,0.25); }
    .banner.info { background: var(--accent-soft); color: var(--accent-dark); border-color: rgba(37,99,235,0.22); }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .stat-card { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 18px; box-shadow: var(--shadow-sm); }
    .stat-card .num { font-size: 26px; font-weight: 800; color: var(--accent-dark); }
    .stat-card .lbl { font-size: 12.5px; color: var(--text-3); font-weight: 600; margin-top: 2px; }
    .stat-card .sub { font-size: 11.5px; color: var(--text-4); margin-top: 2px; }

    .panel { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-xl); box-shadow: var(--shadow-card); margin-bottom: 22px; overflow: hidden; }
    .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .panel-head h3 { font-size: 15px; font-weight: 700; }
    .panel-body { padding: 18px 20px; }

    .filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .field { display: flex; flex-direction: column; gap: 5px; }
    .field label { font-size: 12px; font-weight: 600; color: var(--text-3); }
    .field input, .field select, .field textarea {
        padding: 9px 12px; border: 1px solid var(--border-strong); border-radius: var(--radius-md);
        font: inherit; background: var(--surface); color: var(--text); transition: border-color .15s ease, box-shadow .15s ease;
    }
    .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
    .field textarea { resize: vertical; min-height: 84px; }

    .table-wrap { overflow-x: auto; }
    table.tbl { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .tbl thead th { text-align: left; padding: 11px 14px; background: var(--surface-inset); color: var(--text-3); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; border-bottom: 1px solid var(--border-subtle); }
    .tbl tbody td { padding: 12px 14px; border-bottom: 1px solid var(--border-subtle); vertical-align: top; }
    .tbl tbody tr:hover { background: #fafbfe; }
    .course-title { font-weight: 700; color: var(--text); }
    .course-code { color: var(--text-3); font-size: 12px; }

    .badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: var(--radius-pill); font-size: 11.5px; font-weight: 700; }
    .badge.pub { background: var(--green-soft); color: var(--green); }
    .badge.unpub { background: var(--surface-inset); color: var(--text-3); }
    .badge.paid { background: var(--accent-soft); color: var(--accent-dark); }
    .badge.free { background: var(--surface-inset); color: var(--text-3); }
    .badge.sponsor { background: var(--gold-soft); color: var(--gold); }
    .badge.warn { background: var(--danger-soft); color: var(--danger); }

    .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .icon-btn { background: none; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); width: 30px; height: 30px; cursor: pointer; color: var(--text-3); display: inline-flex; align-items: center; justify-content: center; }
    .icon-btn:hover { background: var(--surface-inset); color: var(--text); border-color: var(--border-strong); }
    .icon-btn.danger:hover { background: var(--danger-soft); color: var(--danger); border-color: rgba(220,38,38,0.25); }

    .empty { text-align: center; padding: 40px 20px; color: var(--text-3); }
    .empty i { font-size: 30px; margin-bottom: 10px; color: var(--text-4); }


    .detail-row { background: var(--surface-inset); }
    .detail-row > td { padding: 18px 20px; }
    .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .detail-card { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 14px; }
    .detail-card h4 { font-size: 12.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-3); margin-bottom: 10px; font-weight: 700; }
    .mini-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .mini-table th { text-align: left; color: var(--text-3); font-weight: 600; padding: 5px 6px; border-bottom: 1px solid var(--border-subtle); font-size: 11px; text-transform: uppercase; }
    .mini-table td { padding: 6px; border-bottom: 1px solid var(--border-subtle); }
    .trend-bar { display: flex; align-items: flex-end; gap: 5px; height: 90px; }
    .trend-bar .bar { flex: 1; background: var(--accent); border-radius: 4px 4px 0 0; min-height: 2px; }
    .trend-bar .bar span { display: block; text-align: center; font-size: 10px; color: var(--text-3); margin-top: 4px; }

    .modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.45); display: none; align-items: flex-start; justify-content: center; padding: 40px 16px; z-index: 50; overflow-y: auto; }
    .modal-backdrop.open { display: flex; }
    .modal { background: var(--surface); border-radius: var(--radius-xl); max-width: 720px; width: 100%; box-shadow: var(--shadow-md); }
    .modal-head { padding: 18px 22px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; }
    .modal-head h3 { font-size: 16px; font-weight: 800; }
    .modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-3); line-height: 1; }
    .modal-body { padding: 22px; }
    .modal-foot { padding: 16px 22px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 10px; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
    .form-grid .span2 { grid-column: span 2; }
    .checkbox-line { display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--text-2); }
    .preview-thumb { max-width: 220px; max-height: 90px; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-top: 6px; object-fit: cover; }

    @media (max-width: 860px) { .sidebar { display: none; } .main { padding: 18px; } .form-grid .span2 { grid-column: span 1; } }
</style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="logo">U</div>
            <div>
                <h1>UNILIS</h1>
                <small>Short Courses Analytics</small>
            </div>
        </div>
        <nav class="nav">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Department Dashboard</a>
            <a class="active" href="short_courses_analytics.php"><i class="fas fa-chart-line"></i> Short Courses</a>
            <a href="../../admin/dashboard.php"><i class="fas fa-shield-alt"></i> Admin Dashboard</a>
        </nav>
    </aside>

    <main class="main">
        <div class="page-head">
            <div>
                <h2>Short Courses Analytics</h2>
                <p>Manage short courses, banners, tutors, sponsors and track student registrations.
                   <?= $isGlobalAdmin ? 'Viewing all departments.' : 'Restricted to your department.' ?></p>
            </div>
            <button class="btn btn-primary" id="btnNew"><i class="fas fa-plus"></i> New Course</button>
        </div>

        <?php foreach ($errors as $e): ?>
            <div class="banner error"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($e) ?></span></div>
        <?php endforeach; ?>
        <?php foreach ($messages as $m): ?>
            <div class="banner success"><i class="fas fa-check-circle"></i><span><?= htmlspecialchars($m) ?></span></div>
        <?php endforeach; ?>
        <?php foreach ($schemaWarnings as $w): ?>
            <div class="banner warning"><i class="fas fa-triangle-exclamation"></i><span><?= htmlspecialchars($w) ?></span></div>
        <?php endforeach; ?>
        <?php if (!$uploadCheck['ok']): ?>
            <div class="banner error"><i class="fas fa-upload"></i><span>Banner uploads are disabled because the upload directory is not usable. Fix the permissions above, then refresh.</span></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="num"><?= $totals['courses'] ?></div><div class="lbl">Short courses</div><div class="sub"><?= $totals['published'] ?> published</div></div>
            <div class="stat-card"><div class="num"><?= $totals['enrollments'] ?></div><div class="lbl">Total registrations</div><div class="sub">across visible courses</div></div>
            <div class="stat-card"><div class="num"><?= $totals['paid'] ?></div><div class="lbl">Paid courses</div><div class="sub">fee-based</div></div>
            <div class="stat-card"><div class="num"><?= number_format($totals['expected_revenue'], 2) ?></div><div class="lbl">Expected revenue</div><div class="sub">price × registrations</div></div>
            <div class="stat-card"><div class="num"><?= count($unassigned) ?></div><div class="lbl">No tutor</div><div class="sub">need a lecturer</div></div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h3><i class="fas fa-filter"></i> Filters &amp; search</h3>
            </div>
            <div class="panel-body">
                <form method="get" class="filters">
                    <div class="field">
                        <label>Search</label>
                        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Title, code, summary">
                    </div>
                    <?php if ($isGlobalAdmin): ?>
                    <div class="field">
                        <label>Department</label>
                        <select name="dept">
                            <option value="0">All departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= $filterDept === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="field">
                        <label>Status</label>
                        <select name="pub">
                            <option value="" <?= $filterPub < 0 ? 'selected' : '' ?>>Any</option>
                            <option value="1" <?= $filterPub === 1 ? 'selected' : '' ?>>Published</option>
                            <option value="0" <?= $filterPub === 0 ? 'selected' : '' ?>>Unpublished</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Type</label>
                        <select name="paid">
                            <option value="" <?= $filterPaid < 0 ? 'selected' : '' ?>>Any</option>
                            <option value="1" <?= $filterPaid === 1 ? 'selected' : '' ?>>Paid</option>
                            <option value="0" <?= $filterPaid === 0 ? 'selected' : '' ?>>Free</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Sponsorship</label>
                        <select name="sponsored">
                            <option value="" <?= $filterSponsored < 0 ? 'selected' : '' ?>>Any</option>
                            <option value="1" <?= $filterSponsored === 1 ? 'selected' : '' ?>>Sponsored</option>
                            <option value="0" <?= $filterSponsored === 0 ? 'selected' : '' ?>>Not sponsored</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Sort by</label>
                        <select name="sort">
                            <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>Newest</option>
                            <option value="created_asc" <?= $sort === 'created_asc' ? 'selected' : '' ?>>Oldest</option>
                            <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title A–Z</option>
                            <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Title Z–A</option>
                            <option value="enrollments_desc" <?= $sort === 'enrollments_desc' ? 'selected' : '' ?>>Most enrolled</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price low–high</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-magnifying-glass"></i> Apply</button>
                        <a class="btn btn-secondary" href="short_courses_analytics.php">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($sponsorIntegrity): ?>
            <div class="banner warning"><i class="fas fa-circle-info"></i><span>
                <?= count($sponsorIntegrity) ?> sponsored course(s) are missing sponsor name and/or logo:
                <?= htmlspecialchars(implode(', ', array_map(fn($c) => $c['title'], $sponsorIntegrity))) ?>.
            </span></div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-head">
                <h3><i class="fas fa-book-open"></i> Short courses (<?= count($courses) ?>)</h3>
            </div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Registrations</th>
                            <th>Tutors</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$courses): ?>
                        <tr><td colspan="6">
                            <div class="empty"><i class="fas fa-inbox"></i><div>No courses match your filters.</div></div>
                        </td></tr>
                    <?php else: ?>
                    <?php foreach ($courses as $c):
                        $cid = (int)$c['id'];
                        $an = $analyticsById[$cid];
                        $maxTrend = 1;
                        foreach ($an['trend'] as $t) { $maxTrend = max($maxTrend, (int)$t['cnt']); }
                        $deptName = $c['department_name'] ?? '';
                        $tutorOptions = $isGlobalAdmin
                            ? ($lecturersByDept[(int)$c['department_id']] ?? [])
                            : $lecturers;
                    ?>
                        <tr data-course="<?= $cid ?>">
                            <td>
                                <div class="course-title"><?= htmlspecialchars($c['title']) ?></div>
                                <div class="course-code"><?= htmlspecialchars((string)($c['code'] ?? '')) ?>
                                    <?php if ($deptName): ?> · <?= htmlspecialchars($deptName) ?><?php endif; ?></div>
                                <?php $cover = short_course_media_url((string)$c['cover_image']); ?>
                                <?php if ($cover !== ''): ?>
                                    <img class="preview-thumb" src="<?= htmlspecialchars($cover) ?>" alt="" style="max-height:44px;">
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$c['is_published']): ?>
                                    <span class="badge pub"><i class="fas fa-eye"></i> Published</span>
                                <?php else: ?>
                                    <span class="badge unpub"><i class="fas fa-eye-slash"></i> Unpublished</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$c['is_paid']): ?>
                                    <span class="badge paid">Paid · <?= htmlspecialchars(number_format((float)$c['price'], 2)) ?></span>
                                <?php else: ?>
                                    <span class="badge free">Free</span>
                                <?php endif; ?>
                                <?php if ((int)$c['is_sponsored']): ?>
                                    <div style="margin-top:4px;"><span class="badge sponsor"><i class="fas fa-handshake"></i> Sponsored</span></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int)$c['enrollments'] ?></strong></td>
                            <td>
                                <?php if ((int)$c['tutor_count'] > 0): ?>
                                    <span title="<?= htmlspecialchars((string)$c['tutor_names']) ?>"><?= (int)$c['tutor_count'] ?> tutor(s)</span>
                                <?php else: ?>
                                    <span class="badge warn">No tutor</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn" title="View analytics" data-toggle="analytics-<?= $cid ?>"><i class="fas fa-chart-simple"></i></button>
                                    <button class="icon-btn" title="Edit" data-edit="<?= $cid ?>"><i class="fas fa-pen"></i></button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Publish / unpublish this course?');">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="course_id" value="<?= $cid ?>">
                                        <button class="icon-btn" title="<?= (int)$c['is_published'] ? 'Unpublish' : 'Publish' ?>"><i class="fas fa-<?= (int)$c['is_published'] ? 'eye-slash' : 'eye' ?>"></i></button>
                                    </form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this course permanently? Its enrollments, tutors and sponsors will also be removed.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="course_id" value="<?= $cid ?>">
                                        <input type="hidden" name="confirm" value="1">
                                        <button class="icon-btn danger" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr class="detail-row" id="analytics-<?= $cid ?>" style="display:none;">
                            <td colspan="6">
                                <div class="detail-grid">
                                    <div class="detail-card">
                                        <h4>Registered students (<?= count($an['registrations']) ?>)</h4>
                                        <?php if (!$an['registrations']): ?>
                                            <div class="empty" style="padding:16px;">No registrations yet.</div>
                                        <?php else: ?>
                                            <table class="mini-table">
                                                <thead><tr><th>Name</th><th>Email</th><th>Enrolled</th><th>Status</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($an['registrations'] as $r): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($r['name']) ?></td>
                                                        <td><?= htmlspecialchars($r['email']) ?></td>
                                                        <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)$r['enrolled_at']))) ?></td>
                                                        <td><?= $r['completed_at'] ? 'Completed' : 'Active' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                    <div class="detail-card">
                                        <h4>Enrollment trend</h4>
                                        <?php if (!$an['trend']): ?>
                                            <div class="empty" style="padding:16px;">No trend data.</div>
                                        <?php else: ?>
                                            <div class="trend-bar">
                                                <?php foreach ($an['trend'] as $t): ?>
                                                    <div class="bar" style="height:<?= max(2, round(((int)$t['cnt'] / $maxTrend) * 82)) ?>px;"><span><?= htmlspecialchars($t['ym']) ?></span></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ((int)$c['is_paid']): ?>
                                            <p style="margin-top:12px; font-size:12.5px; color:var(--text-3);">
                                                Revenue: <strong><?= htmlspecialchars(number_format((float)$c['price'] * (int)$c['enrollments'], 2)) ?></strong>
                                                (<?= htmlspecialchars((string)$c['price']) ?> × <?= (int)$c['enrollments'] ?>).
                                                Actual collections/pending are not tracked — no payments table exists yet.
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="detail-card">
                                        <h4>Tutors</h4>
                                        <?php if (!$an['tutors']): ?>
                                            <div class="empty" style="padding:16px;">No tutor assigned.</div>
                                        <?php else: ?>
                                            <table class="mini-table">
                                                <thead><tr><th>Lecturer</th><th></th></tr></thead>
                                                <tbody>
                                                <?php foreach ($an['tutors'] as $tut): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($tut['lecturer_name']) ?></td>
                                                        <td style="text-align:right;">
                                                            <form method="post" style="display:inline;" onsubmit="return confirm('Remove this tutor?');">
                                                                <input type="hidden" name="action" value="remove_tutor">
                                                                <input type="hidden" name="course_id" value="<?= $cid ?>">
                                                                <input type="hidden" name="tutor_id" value="<?= (int)$tut['id'] ?>">
                                                                <button class="icon-btn danger btn-sm" title="Remove"><i class="fas fa-user-minus"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                        <form method="post" style="margin-top:10px; display:flex; gap:6px;">
                                            <input type="hidden" name="action" value="assign_tutor">
                                            <input type="hidden" name="course_id" value="<?= $cid ?>">
                                            <select name="lecturer_id" style="flex:1; padding:6px; border:1px solid var(--border-strong); border-radius:var(--radius-sm); font:inherit;">
                                                <option value="">Assign a lecturer…</option>
                                                <?php foreach ($tutorOptions as $le): ?>
                                                    <option value="<?= (int)$le['id'] ?>"><?= htmlspecialchars($le['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-secondary btn-sm" type="submit">Add</button>
                                        </form>
                                    </div>
                                    <div class="detail-card">
                                        <h4>Sponsors</h4>
                                        <?php if (!$an['sponsors']): ?>
                                            <div class="empty" style="padding:16px;">Not sponsored.</div>
                                        <?php else: ?>
                                            <?php foreach ($an['sponsors'] as $sp): ?>
                                                <div style="display:flex; gap:10px; align-items:center; padding:6px 0; border-bottom:1px solid var(--border-subtle);">
                                                    <?php if (!empty($sp['sponsor_logo'])): ?>
                                                        <img src="<?= htmlspecialchars(short_course_media_url((string)$sp['sponsor_logo'])) ?>" alt="" style="max-width:48px; max-height:26px; object-fit:contain;">
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?= htmlspecialchars($sp['sponsor_name']) ?></strong>
                                                        <?php if (!empty($sp['sponsor_details'])): ?>
                                                            <div style="font-size:12px; color:var(--text-3);"><?= htmlspecialchars($sp['sponsor_details']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<div class="panel">
            <div class="panel-head">
                <h3><i class="fas fa-users-slash"></i> Action needed — courses with no tutor (<?= count($unassigned) ?>)</h3>
            </div>
            <div class="panel-body">
                <?php if (!$unassigned): ?>
                    <div class="empty" style="padding:16px;"><i class="fas fa-check-circle"></i><div>Every course has at least one tutor.</div></div>
                <?php else: ?>
                    <?php foreach ($unassigned as $c): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid var(--border-subtle); flex-wrap:wrap;">
                            <div>
                                <strong><?= htmlspecialchars($c['title']) ?></strong>
                                <span class="badge warn" style="margin-left:8px;">No tutor</span>
                            </div>
                            <button class="btn btn-secondary btn-sm" data-toggle="analytics-<?= (int)$c['id'] ?>"><i class="fas fa-user-plus"></i> Assign tutor</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Create / Edit modal -->
<div class="modal-backdrop" id="courseModal">
    <div class="modal">
        <form method="post" enctype="multipart/form-data" id="courseForm">
            <input type="hidden" name="action" id="f_action" value="create">
            <input type="hidden" name="course_id" id="f_course_id" value="0">
            <div class="modal-head">
                <h3 id="f_title_label">New short course</h3>
                <button type="button" class="modal-close" data-close-modal>&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field span2">
                        <label>Course title *</label>
                        <input type="text" name="title" id="f_title" required>
                    </div>
                    <div class="field">
                        <label>Code</label>
                        <input type="text" name="code" id="f_code">
                    </div>
                    <div class="field">
                        <label>Duration</label>
                        <input type="text" name="duration" id="f_duration" placeholder="e.g. 6 weeks">
                    </div>
                    <?php if ($isGlobalAdmin): ?>
                    <div class="field">
                        <label>Department</label>
                        <select name="department_id" id="f_dept">
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="department_id" id="f_dept" value="<?= (int)$deptScope ?>">
                    <?php endif; ?>
                    <div class="field">
                        <label>Level</label>
                        <select name="level" id="f_level">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Estimated hours</label>
                        <input type="number" name="estimated_hours" id="f_hours" step="0.5" min="0">
                    </div>
                    <div class="field">
                        <label>Pass mark (%)</label>
                        <input type="number" name="pass_mark" id="f_pass" min="0" max="100" value="70">
                    </div>
                    <div class="field span2">
                        <label>Summary</label>
                        <input type="text" name="summary" id="f_summary">
                    </div>
                    <div class="field span2">
                        <label>Description</label>
                        <textarea name="description" id="f_description"></textarea>
                    </div>

                    <div class="field span2">
                        <label class="checkbox-line"><input type="checkbox" name="is_paid" id="f_paid" value="1"> Paid course (fee required)</label>
                    </div>
                    <div class="field">
                        <label>Price (₦/KSh)</label>
                        <input type="number" name="price" id="f_price" step="0.01" min="0">
                    </div>
                    <div class="field">
                        <label>Payment methods</label>
                        <input type="text" name="payment_methods" id="f_payment_methods" placeholder="M-Pesa, PayPal, Card">
                    </div>

                    <div class="field span2">
                        <label class="checkbox-line"><input type="checkbox" name="is_sponsored" id="f_sponsored" value="1"> Sponsored course</label>
                    </div>
                    <div class="field">
                        <label>Sponsor name</label>
                        <input type="text" name="sponsor_name" id="f_sponsor_name">
                    </div>
                    <div class="field span2">
                        <label>Sponsor details</label>
                        <input type="text" name="sponsor_details" id="f_sponsor_details">
                    </div>

                    <div class="field span2">
                        <label>Banner image (JPEG / PNG / GIF / WEBP, max <?= MAX_COVER_SIZE / 1048576 ?> MB)</label>
                        <input type="file" name="cover_image" id="f_cover" accept="image/*">
                        <?php if ($uploadCheck['ok'] === false): ?>
                            <span style="color:var(--danger); font-size:12px;">Uploads disabled — upload directory is not writable.</span>
                        <?php endif; ?>
                        <img class="preview-thumb" id="f_cover_preview" alt="" style="display:none;">
                        <small id="f_cover_note" style="color:var(--text-3);">Leaving this blank keeps the existing banner when editing.</small>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary" id="f_submit">Create course</button>
            </div>
        </form>
    </div>
</div>

<script>
    const courseData = <?= json_encode(array_map(function ($c) {
        return [
            'id' => (int)$c['id'],
            'title' => $c['title'],
            'code' => (string)($c['code'] ?? ''),
            'duration' => (string)($c['duration'] ?? ''),
            'department_id' => (int)$c['department_id'],
            'level' => $c['level'] ?? 'beginner',
            'estimated_hours' => $c['estimated_hours'] ?? '',
            'pass_mark' => (int)$c['pass_mark'],
            'summary' => (string)($c['summary'] ?? ''),
            'description' => (string)($c['description'] ?? ''),
            'is_paid' => (int)$c['is_paid'],
            'price' => $c['price'] ?? '',
            'payment_methods' => (string)($c['payment_methods'] ?? ''),
            'is_sponsored' => (int)$c['is_sponsored'],
            'sponsor_name' => (string)($c['sponsor_name'] ?? ''),
            'sponsor_details' => (string)($c['sponsor_details'] ?? ''),
            'cover_image' => short_course_media_url((string)$c['cover_image']),
        ];
    }, $courses)) ?>);

    <?php if (!empty($bannerMoveError)): ?>
    // STEP 2 — a failed banner move is surfaced to the user as a popup (on top
    // of the red error banner already rendered in the page body).
    alert('Banner upload failed: <?= htmlspecialchars($bannerMoveError, ENT_QUOTES) ?>');
    <?php endif; ?>

    const modal = document.getElementById('courseModal');
    const form = document.getElementById('courseForm');
    const submitBtn = document.getElementById('f_submit');

    function openModal() {
        modal.classList.add('open');
        document.getElementById('f_title').focus();
    }
    function closeModal() { modal.classList.remove('open'); }
    function setVal(id, v) { document.getElementById(id).value = v == null ? '' : v; }
    function setCheck(id, v) { document.getElementById(id).checked = !!v; }

    document.getElementById('btnNew').addEventListener('click', function () {
        form.reset();
        setVal('f_action', 'create');
        setVal('f_course_id', 0);
        document.getElementById('f_title_label').textContent = 'New short course';
        submitBtn.textContent = 'Create course';
        setVal('f_pass', 70);
        document.getElementById('f_cover_preview').style.display = 'none';
        document.getElementById('f_cover').value = '';
        openModal();
    });

    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-edit');
            const c = courseData[id];
            if (!c) return;
            form.reset();
            setVal('f_action', 'update');
            setVal('f_course_id', c.id);
            document.getElementById('f_title_label').textContent = 'Edit course';
            submitBtn.textContent = 'Save changes';
            setVal('f_title', c.title);
            setVal('f_code', c.code);
            setVal('f_duration', c.duration);
            setVal('f_dept', c.department_id);
            setVal('f_level', c.level);
            setVal('f_hours', c.estimated_hours);
            setVal('f_pass', c.pass_mark);
            setVal('f_summary', c.summary);
            setVal('f_description', c.description);
            setCheck('f_paid', c.is_paid);
            setVal('f_price', c.price);
            setVal('f_payment_methods', c.payment_methods);
            setCheck('f_sponsored', c.is_sponsored);
            setVal('f_sponsor_name', c.sponsor_name);
            setVal('f_sponsor_details', c.sponsor_details);
            const prev = document.getElementById('f_cover_preview');
            if (c.cover_image) { prev.src = c.cover_image; prev.style.display = 'block'; }
            else { prev.style.display = 'none'; prev.removeAttribute('src'); }
            document.getElementById('f_cover').value = '';
            openModal();
        });
    });

    // Analytics detail row toggle
    document.querySelectorAll('[data-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const el = document.getElementById(btn.getAttribute('data-toggle'));
            if (el) el.style.display = el.style.display === 'none' ? 'table-row' : 'none';
        });
    });

    // Cover image preview
    document.getElementById('f_cover').addEventListener('change', function () {
        const prev = document.getElementById('f_cover_preview');
        if (this.files && this.files[0]) {
            prev.src = URL.createObjectURL(this.files[0]);
            prev.style.display = 'block';
        } else {
            prev.style.display = 'none';
        }
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (b) {
        b.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>

