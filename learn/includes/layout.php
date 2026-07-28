<?php
/**
 * Shared chrome for the public learning pages.
 *
 * Uses the UNILIS brand palette and the glass treatment the Live Engagement
 * module already established, so the public side reads as part of the same
 * product rather than a bolted-on microsite.
 */

/**
 * Asset URL with a cache-busting token, so a deploy is not held back by the
 * long max-age static files are served with here.
 */
function learn_asset(string $relativePath): string
{
    $stamp = @filemtime(__DIR__ . '/../' . $relativePath);

    return htmlspecialchars(
        '/learn/' . $relativePath . '?v=' . ($stamp !== false ? $stamp : '0'),
        ENT_QUOTES
    );
}

function learn_e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Stop with a setup notice unless the migration has been run.
 *
 * Every page that reads external_learners or the catalogue has to call this
 * first. config/db.php puts mysqli into MYSQLI_REPORT_STRICT, so querying a
 * table that does not exist throws rather than returning empty - which surfaced
 * as a bare 500 on /learn/course.php before the migration had been applied.
 * A missing migration is an install state, not a crash, and should say so.
 */
function learn_require_schema(mysqli $conn): void
{
    if (learn_schema_ready($conn)) {
        return;
    }

    learn_head(['title' => 'Setup required', 'narrow' => true]);
    echo '<div class="ln-card">'
       . '<h1>Not set up yet</h1>'
       . '<p class="ln-sub">The open learning catalogue has not been created in this database. '
       . 'An administrator needs to run <code>migrate_external_learners.php</code> once — from the '
       . 'Database Migrations panel on the admin dashboard.</p>'
       . '<a class="ln-btn ln-btn-ghost ln-btn-block" href="/">Back to UNILIS</a>'
       . '</div>';
    learn_foot();
    exit;
}

/**
 * Open the page. $options: title, learner (array|null), narrow (bool).
 */
function learn_head(array $options = []): void
{
    $title = $options['title'] ?? 'UNILIS Learning';
    $learner = $options['learner'] ?? null;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= learn_e($title) ?> · UNILIS Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="<?= learn_asset('assets/learn.css') ?>">
</head>
<body>
<header class="ln-header">
    <a class="ln-brand" href="/learn/">
        <span class="material-symbols-rounded">school</span>
        <span>UNILIS <strong>Learning</strong></span>
    </a>
    <nav class="ln-nav">
        <a href="/learn/">Courses</a>
        <a href="/learn/verify_certificate.php">Verify a certificate</a>
        <?php if ($learner !== null): ?>
            <a href="/learn/dashboard.php">My learning</a>
            <span class="ln-who" title="<?= learn_e($learner['email']) ?>"><?= learn_e($learner['name']) ?></span>
            <a class="ln-btn ln-btn-ghost" href="/learn/logout.php">Sign out</a>
        <?php else: ?>
            <a class="ln-btn ln-btn-ghost" href="/learn/login.php">Sign in</a>
            <a class="ln-btn ln-btn-primary" href="/learn/register.php">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="ln-main<?= !empty($options['narrow']) ? ' ln-main-narrow' : '' ?>">
    <?php
}

function learn_foot(): void
{
    ?>
</main>
<footer class="ln-footer">
    <p>UNILIS Learning · open courses from JHUB Africa</p>
    <p><a href="/">Back to UNILIS</a> · <a href="/login.php">Student &amp; staff sign in</a></p>
</footer>
</body>
</html>
    <?php
}

/**
 * Render a list of validation errors, or nothing when there are none.
 */
function learn_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="ln-alert ln-alert-error"><span class="material-symbols-rounded">error</span><div><ul>';
    foreach ($errors as $error) {
        echo '<li>' . learn_e($error) . '</li>';
    }
    echo '</ul></div></div>';
}

function learn_notice(string $message, string $kind = 'info'): void
{
    $icon = $kind === 'success' ? 'check_circle' : ($kind === 'error' ? 'error' : 'info');
    echo '<div class="ln-alert ln-alert-' . learn_e($kind) . '">'
        . '<span class="material-symbols-rounded">' . $icon . '</span>'
        . '<div>' . learn_e($message) . '</div></div>';
}
