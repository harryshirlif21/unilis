<?php
/**
 * teams/config.php
 * Configuration and constants specific to the Teams module
 * Loads the main database connection and defines feature-specific settings.
 *
 * Place this file in: teams/config.php
 * Include example from within teams/:
 *   require_once __DIR__ . '/config.php';
 *   // or from api/, controllers/, etc.:
 *   require_once __DIR__ . '/../config.php';
 */

// Determine application root and load the shared mysqli connection in a
// way that is robust even if APP_ROOT was already defined elsewhere.
$localRoot = dirname(__DIR__); // e.g. /htdocs/unilis
$candidatePaths = [];

if (defined('APP_ROOT')) {
    $candidatePaths[] = rtrim(APP_ROOT, '/\\') . '/config/db.php';
}
$candidatePaths[] = $localRoot . '/config/db.php';

$loadedDb = false;
foreach ($candidatePaths as $path) {
    if (is_file($path)) {
        require_once $path;
        $loadedDb = true;
        break;
    }
}

if (!$loadedDb) {
    throw new Exception('teams/config.php could not locate config/db.php');
}

// Ensure APP_ROOT is defined consistently for downstream use.
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $localRoot);
}

// After this point, $conn (mysqli) should be available everywhere.

// ─────────────────────────────────────────────────────────────────────────────
// 2. General constants
// ─────────────────────────────────────────────────────────────────────────────
define('TEAMS_VERSION', '1.0.0-dev');
define('TEAMS_UPLOAD_DIR', APP_ROOT . '/uploads/teams/');     // absolute path on disk
define('TEAMS_UPLOAD_URL', '/uploads/teams/');                // web-accessible path

// Make sure upload directory exists (create it with proper permissions)
if (!is_dir(TEAMS_UPLOAD_DIR)) {
    mkdir(TEAMS_UPLOAD_DIR, 0755, true);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. File upload settings
// ─────────────────────────────────────────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024);           // 20 MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'jpg', 'jpeg', 'png']);
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
    'image/jpeg',
    'image/png'
]);

// ─────────────────────────────────────────────────────────────────────────────
// 4. Health score & ghost detection
// ─────────────────────────────────────────────────────────────────────────────
define('HEALTH_SCORE_WEIGHTS', [
    'tasks_done'     => 40,
    'activity'       => 30,
    'deadline'       => 30,
]);

define('GHOST_INACTIVE_DAYS', 3);           // days without activity → flagged as ghost
define('NUDGE_COOLDOWN_HOURS', 24);         // cannot nudge same person again within X hours

// ─────────────────────────────────────────────────────────────────────────────
// 5. Checklist defaults per assessment type
// (used when auto-populating submission_checklist for a new team)
// ─────────────────────────────────────────────────────────────────────────────
define('CHECKLIST_TEMPLATES', [
    'assignment' => [
        'Cover page included',
        'All student IDs listed',
        'Reference list / bibliography present',
        'Plagiarism declaration signed',
        'All sections complete',
        'Lecturer name and course code correct'
    ],
    'cat' => [
        'Student ID on every page',
        'Question numbers clearly marked',
        'Word count within limit',
        'Submitted before deadline',
        'Answers legible and well-structured'
    ],
    'project' => [
        'Proposal document signed',
        'Progress report submitted',
        'Final report complete',
        'Presentation slides ready',
        'Code / artefact attached'
    ],
    'practical' => [
        'Lab report format followed',
        'Raw data / screenshots attached',
        'Calculations clearly shown',
        'Conclusion and discussion written',
        'Safety precautions mentioned'
    ]
]);

// ─────────────────────────────────────────────────────────────────────────────
// 6. Security & session
// ─────────────────────────────────────────────────────────────────────────────
// (you already have csrf_token in session — we can add helper later if needed)

// ─────────────────────────────────────────────────────────────────────────────
// 7. Optional: error reporting control (development vs production)
// ─────────────────────────────────────────────────────────────────────────────
if (defined('ENV') && ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}

// Optional: you can define ENV in root index.php or .htaccess
// Example: define('ENV', 'development');

?>