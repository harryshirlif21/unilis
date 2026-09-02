<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ajax/short_course_access.php';

if (!shortCourseIsAuthor()) {
    header('Location: ../login.php');
    exit;
}

$file = str_replace('\\', '/', trim((string)($_GET['file'] ?? '')));
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

// More permissive regex to handle uniqid patterns
if (!preg_match('#^uploads/(files/)?course_presentations/[A-Za-z0-9._-]+\.(ppt|pptx)$#i', $file)) {
    error_log("ppt_preview: Invalid file pattern - $file");
    http_response_code(400);
    exit('Invalid presentation file.');
}

$absolute = realpath(__DIR__ . '/../' . $file);
$uploads = realpath(__DIR__ . '/../uploads/files/course_presentations');

// Debug logging
error_log("ppt_preview: file=$file, absolute=" . ($absolute ?: 'false') . ", uploads=" . ($uploads ?: 'false'));

if ($absolute === false) {
    error_log("ppt_preview: File not found at path: " . __DIR__ . '/../' . $file);
    http_response_code(404);
    exit('Presentation not found.');
}

if ($uploads === false) {
    error_log("ppt_preview: Uploads directory not found: " . __DIR__ . '/../uploads/files/course_presentations');
    http_response_code(404);
    exit('Uploads directory not found.');
}

$normalizedUploads = str_replace('\\', '/', $uploads);
$normalizedAbsolute = str_replace('\\', '/', $absolute);

if (strpos($normalizedAbsolute, $normalizedUploads . '/') !== 0) {
    error_log("ppt_preview: Path traversal detected - absolute=$normalizedAbsolute, uploads=$normalizedUploads");
    http_response_code(404);
    exit('Presentation not found.');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    ? 'https'
    : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/lecturer/ppt_preview.php'))), '/');
$fileUrl = $scheme . '://' . $host . $basePath . '/' . $file;
$isLocal = in_array(strtolower((string)parse_url($fileUrl, PHP_URL_HOST)), ['localhost', '127.0.0.1', '::1'], true);
$viewerUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($fileUrl);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PowerPoint Preview</title>
    <style>
        * { box-sizing: border-box; } body { margin:0; font-family:Segoe UI,Arial,sans-serif; background:#f5f7fb; color:#1f2937; }
        .bar { display:flex; align-items:center; gap:12px; padding:12px 18px; background:#182238; color:#fff; }
        .bar a { margin-left:auto; color:#fff; text-decoration:none; padding:7px 11px; border:1px solid rgba(255,255,255,.45); border-radius:6px; }
        iframe { display:block; width:100%; height:calc(100vh - 56px); border:0; background:#fff; }
        .notice { max-width:680px; margin:10vh auto; padding:28px; background:#fff; border-radius:12px; box-shadow:0 4px 18px rgba(15,23,42,.08); text-align:center; }
        .notice a { display:inline-block; margin-top:16px; padding:10px 16px; border-radius:6px; background:#d24726; color:#fff; text-decoration:none; }
    </style>
</head>
<body>
<?php if ($isLocal): ?>
    <div class="notice">
        <h1>PowerPoint preview needs a public URL</h1>
        <p>Office’s web viewer cannot retrieve files from localhost. Download the presentation to preview it locally, or use the full preview after deployment.</p>
        <a href="../<?= htmlspecialchars($file) ?>" target="_blank" rel="noopener">Download presentation</a>
    </div>
<?php else: ?>
    <?php if (!$embed): ?><div class="bar"><strong>PowerPoint Preview</strong><a href="../<?= htmlspecialchars($file) ?>" target="_blank" rel="noopener">Download</a></div><?php endif; ?>
    <iframe src="<?= htmlspecialchars($viewerUrl, ENT_QUOTES) ?>" title="PowerPoint preview" allowfullscreen></iframe>
<?php endif; ?>
</body>
</html>
