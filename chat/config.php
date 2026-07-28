<?php
/**
 * chat/config.php
 * Bootstrap for the Chat module: loads the shared mysqli connection and the
 * module's own helpers, and defines module-wide constants.
 *
 * Include from within chat/:
 *   require_once __DIR__ . '/config.php';
 * or from api/ and views/:
 *   require_once __DIR__ . '/../config.php';
 */

// Locate config/db.php the same way teams/config.php does, so the module keeps
// working whether or not APP_ROOT was already defined by an outer script.
$chatLocalRoot = dirname(__DIR__);
$chatCandidatePaths = [];

if (defined('APP_ROOT')) {
    $chatCandidatePaths[] = rtrim(APP_ROOT, '/\\') . '/config/db.php';
}
$chatCandidatePaths[] = $chatLocalRoot . '/config/db.php';

$chatLoadedDb = false;
foreach ($chatCandidatePaths as $chatPath) {
    if (is_file($chatPath)) {
        require_once $chatPath;
        $chatLoadedDb = true;
        break;
    }
}

if (!$chatLoadedDb) {
    throw new Exception('chat/config.php could not locate config/db.php');
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $chatLocalRoot);
}

// After this point $conn (mysqli) is available.

define('CHAT_VERSION', '1.0.0');

// Longest message accepted. Kept well under the TEXT column limit so a
// multibyte body can never be silently truncated by MySQL.
define('CHAT_MAX_BODY_LENGTH', 4000);

// Messages returned per page by the history endpoint.
define('CHAT_PAGE_SIZE', 50);

// How long a group's membership is trusted before it is rebuilt from
// team_members / students. Chat polls every few seconds; without this a poll
// would re-run the membership queries continuously.
define('CHAT_MEMBER_SYNC_TTL_SECONDS', 300);

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/chat_files.php';
require_once __DIR__ . '/includes/chat_access.php';
require_once __DIR__ . '/includes/chat_groups.php';
require_once __DIR__ . '/includes/chat_repository.php';
