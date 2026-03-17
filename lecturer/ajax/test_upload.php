<?php
// lecturer/ajax/test_upload.php
// DIAGNOSTIC ONLY — DELETE THIS FILE AFTER TESTING
// Visit: https://unilis.jhubafrica.com/lecturer/ajax/test_upload.php
session_start();
header('Content-Type: application/json');

$checks = [];

// 1. PHP version
$checks['php_version'] = PHP_VERSION;

// 2. Upload limits
$checks['upload_max_filesize'] = ini_get('upload_max_filesize');
$checks['post_max_size']       = ini_get('post_max_size');
$checks['max_execution_time']  = ini_get('max_execution_time');

// 3. Session
$checks['session_active']  = session_status() === PHP_SESSION_ACTIVE;
$checks['user_id_in_session'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET';
$checks['user_role']          = $_SESSION['user_role'] ?? 'NOT SET';

// 4. db.php path
$db_path = realpath('../../config/db.php');
$checks['db_path_resolved'] = $db_path ?: 'NOT FOUND — ../../config/db.php does not exist from this location';
$checks['db_path_exists']   = $db_path ? 'YES' : 'NO';

// 5. Upload dirs
$base = realpath('../../uploads');
$checks['uploads_dir_exists']  = $base ? 'YES ('.$base.')' : 'NO — ../../uploads not found';
$checks['uploads_writable']    = ($base && is_writable($base)) ? 'YES' : 'NO';

foreach (['course_videos', 'course_pdfs', 'course_images', 'course_audio', 'course_diagrams'] as $dir) {
    $path = $base . DIRECTORY_SEPARATOR . $dir;
    $checks['dir_' . $dir] = is_dir($path) ? 'exists, writable:' . (is_writable($path) ? 'YES' : 'NO') : 'MISSING';
}

// 6. This file's location
$checks['this_file_path'] = __FILE__;
$checks['this_file_dir']  = __DIR__;

// 7. finfo available
$checks['finfo_available'] = class_exists('finfo') ? 'YES' : 'NO';

// 8. move_uploaded_file — check tmp dir
$checks['sys_temp_dir'] = sys_get_temp_dir();
$checks['tmp_writable']  = is_writable(sys_get_temp_dir()) ? 'YES' : 'NO';

echo json_encode($checks, JSON_PRETTY_PRINT);