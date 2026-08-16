<?php
/**
 * Shared chrome for the open-course studio pages.
 *
 * The studio is the authoring side of /learn: lecturers build the courses that
 * external learners work through. It deliberately looks like the rest of the
 * lecturer area (Font Awesome, the same card and button language) rather than
 * like /learn, because the person using it is staff, not a learner.
 *
 * Flashes go through the session so every mutation can redirect afterwards. A
 * builder page that renders its own POST result re-submits the whole form on
 * refresh, which here would mean a second module or a duplicate question.
 */

function studio_e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Resolve legacy relative upload paths from the authoring area.  Covers were
 * historically stored both as `uploads/...` and `/uploads/...`; the former
 * would otherwise resolve below `/lecturer/` on Studio pages.
 */
function studio_asset_url(?string $path): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
        return $path;
    }

    // Remove any relative path components (./, ../, etc.)
    $path = preg_replace('#^(?:(?:\.\.?)/)+#', '', $path) ?? '';
    
    // Ensure path starts with / for web root resolution
    return '/' . ltrim($path, '/');
}

/**
 * Queue a message for the next page load.
 *
 * $details is a list shown underneath, used for the publish blockers.
 */
function studio_flash(string $message, string $kind = 'success', array $details = []): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['studio_flash'] = ['message' => $message, 'kind' => $kind, 'details' => $details];
}

/**
 * Render and clear any queued flash.
 */
function studio_render_flash(): void
{
    if (empty($_SESSION['studio_flash'])) {
        return;
    }

    $flash = $_SESSION['studio_flash'];
    unset($_SESSION['studio_flash']);

    $kind = in_array($flash['kind'] ?? '', ['success', 'error', 'info'], true) ? $flash['kind'] : 'info';
    $icon = ['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation', 'info' => 'fa-circle-info'][$kind];

    echo '<div class="st-flash st-flash-' . $kind . '"><i class="fas ' . $icon . '"></i><div>'
        . '<div>' . studio_e((string)($flash['message'] ?? '')) . '</div>';

    if (!empty($flash['details']) && is_array($flash['details'])) {
        echo '<ul class="st-flash-list">';
        foreach ($flash['details'] as $detail) {
            echo '<li>' . studio_e((string)$detail) . '</li>';
        }
        echo '</ul>';
    }

    echo '</div></div>';
}

/**
 * Show a message and stop, when the catalogue schema has not been created.
 */
function studio_require_schema(mysqli $conn): void
{
    if (learn_schema_ready($conn)) {
        return;
    }

    studio_head('Setup required');
    echo '<div class="st-card"><h2>The open catalogue is not set up yet</h2>'
       . '<p class="st-sub">These courses live in tables that one migration creates. '
       . 'An administrator needs to run <code>migrate_external_learners.php</code> once, from the '
       . 'Database Migrations panel on the admin dashboard.</p>'
       . '<a class="st-btn st-btn-ghost" href="dashboard.php">Back to the dashboard</a></div>';
    studio_foot();
    exit;
}

/**
 * @param array<int, array{label:string, url:?string}> $crumbs
 */
function studio_head(string $title, array $crumbs = []): void
{
    $stamp = @filemtime(__DIR__ . '/css/studio.css');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= studio_e($title) ?> — Open Courses · UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/catalogue.css?v=<?= (int)$stamp ?>">
</head>
<body>
<header class="st-header">
    <a class="st-brand" href="catalogue.php">
        <i class="fas fa-globe"></i>
        <span>Open Courses <strong>Studio</strong></span>
    </a>
    <nav class="st-nav">
        <a href="catalogue.php">My courses</a>
        <a href="/learn/" target="_blank" rel="noopener">View the catalogue <i class="fas fa-arrow-up-right-from-square"></i></a>
        <a class="st-btn st-btn-ghost" href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </nav>
</header>
<main class="st-main">
    <?php if ($crumbs): ?>
        <nav class="st-crumbs">
            <?php foreach ($crumbs as $index => $crumb): ?>
                <?php if ($index > 0): ?><i class="fas fa-chevron-right"></i><?php endif; ?>
                <?php if (!empty($crumb['url'])): ?>
                    <a href="<?= studio_e($crumb['url']) ?>"><?= studio_e($crumb['label']) ?></a>
                <?php else: ?>
                    <span><?= studio_e($crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <?php studio_render_flash(); ?>
    <?php
}

function studio_foot(): void
{
    ?>
</main>
<footer class="st-footer">
    <p>Courses published here appear at <a href="/learn/" target="_blank" rel="noopener">/learn</a>,
       open to learners who are not enrolled students.</p>
</footer>
</body>
</html>
    <?php
}
