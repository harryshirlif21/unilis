<?php
/**
 * Live Engagement - Lecturer Dashboard
 *
 * A transparent, sidebar-first workspace for managing live sessions.
 */

require_once __DIR__ . '/../bootstrap.php';

use LE\Components\Layout;

le_require_auth();

$userId = le_current_user_id();
if (!$userId || !le_can_present()) {
    header('Location: ' . le_page_url('join'));
    exit;
}

$activeSessions = [];
$scheduledSessions = [];
$pastSessions = [];
$courses = [];
$units = [];
$loadError = null;

try {
    $sessionModel = new \LE\Models\SessionModel();
    $activeSessions = $sessionModel->getLecturerActiveSessions($userId);
    $scheduledSessions = $sessionModel->getLecturerScheduledSessions($userId);
    $pastSessions = $sessionModel->getLecturerHistory($userId);

    $db = le_db();
    $courses = $db->select(
        "SELECT DISTINCT c.id, c.name
         FROM courses c
         INNER JOIN units u ON u.course_id = c.id
         INNER JOIN lecturer_units lu ON lu.unit_id = u.id
         WHERE lu.lecturer_id = ?
         ORDER BY c.name",
        [$userId],
        'i'
    ) ?? [];
    $units = $db->select(
        "SELECT u.id, u.name, u.course_id
         FROM units u
         INNER JOIN lecturer_units lu ON lu.unit_id = u.id
         WHERE lu.lecturer_id = ?
         ORDER BY u.name",
        [$userId],
        'i'
    ) ?? [];
} catch (Throwable $e) {
    $loadError = $e->getMessage();
    error_log('Live Engagement dashboard load failed: ' . $e->getMessage());
}

$allSessions = array_merge($activeSessions, $scheduledSessions, $pastSessions);
$totalOnline = array_sum(array_map(
    static fn(array $session): int => (int)($session['online_count'] ?? 0),
    $activeSessions
));
$engagementScores = array_values(array_filter(
    array_map(static fn(array $session) => $session['engagement_score'] ?? null, $pastSessions),
    static fn($score): bool => $score !== null && $score !== ''
));
$engagementAverage = $engagementScores
    ? (int)round(array_sum($engagementScores) / count($engagementScores))
    : null;
$autoCreate = le_get('create', '') === '1';
$autoCreateType = le_get('type', '');
$userName = le_current_user_name() ?: 'Presenter';
$nameParts = preg_split('/\s+/', trim($userName));
$initials = strtoupper(
    mb_substr($nameParts[0] ?? 'U', 0, 1) .
    (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '')
);

function le_dashboard_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function le_dashboard_date(?string $date): string
{
    if (!$date) {
        return 'Not scheduled';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('M j, Y · g:i A', $timestamp) : 'Not scheduled';
}

function le_dashboard_status(array $session): array
{
    $status = strtolower((string)($session['status'] ?? 'scheduled'));
    return match ($status) {
        'active' => ['Live now', 'live'],
        'paused' => ['Paused', 'paused'],
        'ended' => ['Completed', 'ended'],
        default => ['Scheduled', 'scheduled'],
    };
}

Layout::start([
    'title' => 'Dashboard',
    'layout' => 'minimal',
    'includeJs' => true,
]);
?>

<style>
    body.le-standalone,
    body.le-standalone[data-theme="dark"] {
        background: transparent !important;
    }

    .le-dashboard {
        --dash-text: #17201b;
        --dash-muted: #718078;
        --dash-line: rgba(20, 35, 27, .14);
        --dash-line-strong: rgba(20, 35, 27, .24);
        --dash-accent: #19be5b;
        --dash-accent-soft: rgba(25, 190, 91, .10);
        --dash-danger: #dc2626;
        display: grid;
        grid-template-columns: 248px minmax(0, 1fr);
        min-height: 100vh;
        color: var(--dash-text);
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    [data-theme="dark"] .le-dashboard {
        --dash-text: #eaf3ed;
        --dash-muted: #91a29a;
        --dash-line: rgba(255, 255, 255, .13);
        --dash-line-strong: rgba(255, 255, 255, .24);
        --dash-accent: #66f29a;
        --dash-accent-soft: rgba(102, 242, 154, .12);
    }

    .le-dashboard-sidebar {
        border-right: 1px solid var(--dash-line);
        padding: 28px 18px;
        display: flex;
        flex-direction: column;
        gap: 34px;
    }

    .le-dashboard-brand,
    .le-dashboard-nav a,
    .le-dashboard-sidebar-footer a {
        color: inherit;
        text-decoration: none;
    }

    .le-dashboard-brand {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 0 10px;
    }

    .le-dashboard-brand-mark {
        width: 38px;
        height: 38px;
        border: 1px solid var(--dash-accent);
        border-radius: 12px;
        display: grid;
        place-items: center;
        color: var(--dash-accent);
        font-weight: 800;
    }

    .le-dashboard-brand strong,
    .le-dashboard-brand small {
        display: block;
    }

    .le-dashboard-brand strong {
        font-size: 14px;
        letter-spacing: -.02em;
    }

    .le-dashboard-brand small {
        margin-top: 3px;
        color: var(--dash-muted);
        font-size: 10px;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .le-dashboard-nav {
        display: grid;
        gap: 6px;
    }

    .le-dashboard-nav-label {
        padding: 0 12px 8px;
        color: var(--dash-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .le-dashboard-nav a {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 44px;
        padding: 0 12px;
        border: 1px solid transparent;
        border-radius: 12px;
        color: var(--dash-muted);
        font-size: 13px;
        transition: color .2s ease, border-color .2s ease;
    }

    .le-dashboard-nav a:hover,
    .le-dashboard-nav a.active {
        border-color: var(--dash-line-strong);
        color: var(--dash-text);
    }

    .le-dashboard-nav a.active .material-symbols-rounded {
        color: var(--dash-accent);
    }

    .le-dashboard-sidebar-footer {
        margin-top: auto;
        display: grid;
        gap: 16px;
    }

    .le-dashboard-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 10px;
        border-top: 1px solid var(--dash-line);
    }

    .le-dashboard-avatar {
        width: 34px;
        height: 34px;
        border: 1px solid var(--dash-line-strong);
        border-radius: 50%;
        display: grid;
        place-items: center;
        color: var(--dash-accent);
        font-size: 11px;
        font-weight: 800;
    }

    .le-dashboard-user strong,
    .le-dashboard-user small {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .le-dashboard-user strong {
        max-width: 150px;
        font-size: 12px;
    }

    .le-dashboard-user small {
        margin-top: 3px;
        color: var(--dash-muted);
        font-size: 10px;
    }

    .le-dashboard-sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 10px;
        color: var(--dash-muted);
        font-size: 12px;
    }

    .le-dashboard-main {
        min-width: 0;
        padding: 28px clamp(20px, 4vw, 58px) 52px;
    }

    .le-dashboard-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        min-height: 42px;
        border-bottom: 1px solid var(--dash-line);
        padding-bottom: 22px;
    }

    .le-dashboard-breadcrumb {
        color: var(--dash-muted);
        font-size: 12px;
    }

    .le-dashboard-breadcrumb strong {
        color: var(--dash-text);
        font-weight: 600;
    }

    .le-dashboard-top-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .le-dashboard-icon-button,
    .le-dashboard-outline-button,
    .le-dashboard-primary-button {
        min-height: 38px;
        border: 1px solid var(--dash-line-strong);
        border-radius: 10px;
        background: transparent;
        color: var(--dash-text);
        cursor: pointer;
        font: inherit;
        transition: border-color .2s ease, color .2s ease, transform .2s ease;
    }

    .le-dashboard-icon-button {
        width: 38px;
        display: grid;
        place-items: center;
    }

    .le-dashboard-outline-button,
    .le-dashboard-primary-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 14px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 650;
    }

    .le-dashboard-primary-button {
        border-color: var(--dash-accent);
        color: var(--dash-accent);
    }

    .le-dashboard-icon-button:hover,
    .le-dashboard-outline-button:hover,
    .le-dashboard-primary-button:hover {
        border-color: var(--dash-accent);
        color: var(--dash-accent);
        transform: translateY(-1px);
    }

    .le-dashboard-hero {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        padding: 52px 0 34px;
    }

    .le-dashboard-kicker {
        color: var(--dash-accent);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .le-dashboard-hero h1 {
        margin: 12px 0 10px;
        color: var(--dash-text);
        font-size: clamp(32px, 4vw, 54px);
        letter-spacing: -.06em;
        line-height: .98;
    }

    .le-dashboard-hero p {
        max-width: 580px;
        margin: 0;
        color: var(--dash-muted);
        font-size: 14px;
        line-height: 1.6;
    }

    .le-dashboard-hero-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .le-dashboard-alert {
        margin-bottom: 24px;
        border-left: 3px solid var(--dash-danger);
        padding: 12px 14px;
        color: var(--dash-muted);
        font-size: 12px;
    }

    .le-dashboard-alert strong {
        color: var(--dash-danger);
    }

    .le-dashboard-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-top: 1px solid var(--dash-line);
        border-bottom: 1px solid var(--dash-line);
    }

    .le-dashboard-metric {
        min-height: 128px;
        padding: 22px 20px;
        border-right: 1px solid var(--dash-line);
    }

    .le-dashboard-metric:last-child {
        border-right: 0;
    }

    .le-dashboard-metric-label {
        color: var(--dash-muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .le-dashboard-metric-value {
        display: block;
        margin-top: 14px;
        color: var(--dash-text);
        font-size: 32px;
        font-weight: 700;
        letter-spacing: -.05em;
    }

    .le-dashboard-metric-note {
        display: block;
        margin-top: 5px;
        color: var(--dash-muted);
        font-size: 11px;
    }

    .le-dashboard-section {
        margin-top: 34px;
    }

    .le-dashboard-section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 15px;
    }

    .le-dashboard-section-heading h2 {
        margin: 0;
        color: var(--dash-text);
        font-size: 16px;
        letter-spacing: -.02em;
    }

    .le-dashboard-section-heading span {
        color: var(--dash-muted);
        font-size: 11px;
    }

    .le-dashboard-session-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .le-dashboard-session-card {
        min-width: 0;
        border: 1px solid var(--dash-line);
        border-radius: 14px;
        padding: 18px;
    }

    .le-dashboard-session-card:hover {
        border-color: var(--dash-line-strong);
    }

    .le-dashboard-session-card header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .le-dashboard-session-card h3 {
        overflow: hidden;
        margin: 0;
        color: var(--dash-text);
        font-size: 14px;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .le-dashboard-status {
        flex: 0 0 auto;
        border: 1px solid var(--dash-line);
        border-radius: 999px;
        padding: 4px 8px;
        color: var(--dash-muted);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .le-dashboard-status.live {
        border-color: var(--dash-accent);
        color: var(--dash-accent);
    }

    .le-dashboard-session-code {
        display: inline-block;
        margin: 24px 0 18px;
        color: var(--dash-accent);
        font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: .16em;
    }

    .le-dashboard-session-meta {
        min-height: 38px;
        color: var(--dash-muted);
        font-size: 11px;
        line-height: 1.6;
    }

    .le-dashboard-card-actions {
        display: grid;
        grid-template-columns: 1fr 38px 38px;
        gap: 7px;
        margin-top: 18px;
    }

    .le-dashboard-card-actions button {
        min-height: 34px;
        border: 1px solid var(--dash-line);
        border-radius: 9px;
        background: transparent;
        color: var(--dash-muted);
        cursor: pointer;
        font: inherit;
        font-size: 11px;
    }

    .le-dashboard-card-actions button:hover {
        border-color: var(--dash-accent);
        color: var(--dash-accent);
    }

    .le-dashboard-card-actions .primary {
        border-color: var(--dash-accent);
        color: var(--dash-accent);
    }

    .le-dashboard-empty {
        border-top: 1px solid var(--dash-line);
        border-bottom: 1px solid var(--dash-line);
        padding: 34px 20px;
        color: var(--dash-muted);
        text-align: center;
        font-size: 13px;
    }

    .le-dashboard-history {
        overflow-x: auto;
        border-top: 1px solid var(--dash-line);
        border-bottom: 1px solid var(--dash-line);
    }

    .le-dashboard-history table {
        width: 100%;
        border-collapse: collapse;
        min-width: 620px;
    }

    .le-dashboard-history th,
    .le-dashboard-history td {
        padding: 14px 12px;
        border-bottom: 1px solid var(--dash-line);
        text-align: left;
        font-size: 12px;
    }

    .le-dashboard-history tr:last-child td {
        border-bottom: 0;
    }

    .le-dashboard-history th {
        color: var(--dash-muted);
        font-size: 10px;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .le-dashboard-history td {
        color: var(--dash-text);
    }

    .le-dashboard-history td.muted {
        color: var(--dash-muted);
    }

    @media (max-width: 1050px) {
        .le-dashboard {
            grid-template-columns: 210px minmax(0, 1fr);
        }

        .le-dashboard-session-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .le-dashboard {
            display: block;
        }

        .le-dashboard-sidebar {
            border-right: 0;
            border-bottom: 1px solid var(--dash-line);
            padding: 16px;
            gap: 18px;
        }

        .le-dashboard-nav {
            display: flex;
            overflow-x: auto;
        }

        .le-dashboard-nav-label,
        .le-dashboard-sidebar-footer {
            display: none;
        }

        .le-dashboard-nav a {
            flex: 0 0 auto;
            padding: 0 11px;
        }

        .le-dashboard-main {
            padding: 20px 16px 40px;
        }

        .le-dashboard-hero {
            display: block;
            padding: 36px 0 26px;
        }

        .le-dashboard-hero-actions {
            justify-content: flex-start;
            margin-top: 22px;
        }

        .le-dashboard-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .le-dashboard-metric:nth-child(2) {
            border-right: 0;
        }

        .le-dashboard-metric:nth-child(-n + 2) {
            border-bottom: 1px solid var(--dash-line);
        }

        .le-dashboard-session-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="le-dashboard">
    <aside class="le-dashboard-sidebar" aria-label="Live Engagement navigation">
        <a class="le-dashboard-brand" href="<?= le_dashboard_escape(le_page_url('dashboard')) ?>">
            <span class="le-dashboard-brand-mark">U</span>
            <span>
                <strong>Live Engagement</strong>
                <small>UNILIS workspace</small>
            </span>
        </a>

        <nav class="le-dashboard-nav">
            <div class="le-dashboard-nav-label">Workspace</div>
            <a class="active" href="<?= le_dashboard_escape(le_page_url('dashboard')) ?>">
                <span class="material-symbols-rounded">dashboard</span>
                Dashboard
            </a>
            <a href="<?= le_dashboard_escape(le_page_url('presentations')) ?>">
                <span class="material-symbols-rounded">slideshow</span>
                Presentations
            </a>
            <a href="<?= le_dashboard_escape(le_page_url('join')) ?>">
                <span class="material-symbols-rounded">login</span>
                Join session
            </a>
        </nav>

        <div class="le-dashboard-sidebar-footer">
            <div class="le-dashboard-user">
                <span class="le-dashboard-avatar"><?= le_dashboard_escape($initials ?: 'U') ?></span>
                <span>
                    <strong><?= le_dashboard_escape($userName) ?></strong>
                    <small><?= le_dashboard_escape(ucfirst(le_current_user_role() ?: 'Presenter')) ?></small>
                </span>
            </div>
            <a href="<?= le_dashboard_escape(le_base_url() . '/logout.php') ?>">
                <span class="material-symbols-rounded">logout</span>
                Sign out
            </a>
        </div>
    </aside>

    <main class="le-dashboard-main">
        <div class="le-dashboard-topbar">
            <div class="le-dashboard-breadcrumb">UNILIS / <strong>Dashboard</strong></div>
            <div class="le-dashboard-top-actions">
                <button class="le-dashboard-icon-button" type="button" onclick="LiveEngagement.toggleTheme()" aria-label="Toggle theme" title="Toggle theme">
                    <span class="material-symbols-rounded">contrast</span>
                </button>
                <a class="le-dashboard-outline-button" href="<?= le_dashboard_escape(le_page_url('presentations')) ?>">
                    <span class="material-symbols-rounded">slideshow</span>
                    Library
                </a>
            </div>
        </div>

        <section class="le-dashboard-hero">
            <div>
                <div class="le-dashboard-kicker">Live workspace</div>
                <h1>Good to see you,<br><?= le_dashboard_escape($nameParts[0] ?? 'Presenter') ?>.</h1>
                <p>Run interactive sessions, keep your audience connected, and see participation at a glance.</p>
            </div>
            <div class="le-dashboard-hero-actions">
                <button class="le-dashboard-primary-button" type="button" onclick="openCreateSession()">
                    <span class="material-symbols-rounded">add</span>
                    New session
                </button>
                <a class="le-dashboard-outline-button" href="<?= le_dashboard_escape(le_page_url('join')) ?>">
                    <span class="material-symbols-rounded">qr_code_scanner</span>
                    Join a session
                </a>
            </div>
        </section>

        <?php if ($loadError): ?>
            <div class="le-dashboard-alert" role="alert">
                <strong>Session data unavailable.</strong>
                The dashboard shell is ready, but the session data could not be loaded. Check the Live Engagement database setup.
            </div>
        <?php endif; ?>

        <section class="le-dashboard-metrics" aria-label="Session summary">
            <div class="le-dashboard-metric">
                <span class="le-dashboard-metric-label">Live sessions</span>
                <strong class="le-dashboard-metric-value"><?= count($activeSessions) ?></strong>
                <span class="le-dashboard-metric-note"><?= $totalOnline ?> participants online</span>
            </div>
            <div class="le-dashboard-metric">
                <span class="le-dashboard-metric-label">Upcoming</span>
                <strong class="le-dashboard-metric-value"><?= count($scheduledSessions) ?></strong>
                <span class="le-dashboard-metric-note">Ready to start</span>
            </div>
            <div class="le-dashboard-metric">
                <span class="le-dashboard-metric-label">Completed</span>
                <strong class="le-dashboard-metric-value"><?= count($pastSessions) ?></strong>
                <span class="le-dashboard-metric-note">Session history</span>
            </div>
            <div class="le-dashboard-metric">
                <span class="le-dashboard-metric-label">Engagement</span>
                <strong class="le-dashboard-metric-value"><?= $engagementAverage !== null ? $engagementAverage . '%' : '—' ?></strong>
                <span class="le-dashboard-metric-note">Average from history</span>
            </div>
        </section>

        <section class="le-dashboard-section">
            <div class="le-dashboard-section-heading">
                <h2>Live now</h2>
                <span><?= count($activeSessions) ?> active</span>
            </div>
            <?php if ($activeSessions): ?>
                <div class="le-dashboard-session-grid">
                    <?php foreach ($activeSessions as $session): ?>
                        <?php [$statusLabel, $statusClass] = le_dashboard_status($session); ?>
                        <article class="le-dashboard-session-card">
                            <header>
                                <h3 title="<?= le_dashboard_escape($session['title'] ?? '') ?>"><?= le_dashboard_escape($session['title'] ?? 'Untitled session') ?></h3>
                                <span class="le-dashboard-status <?= le_dashboard_escape($statusClass) ?>"><?= le_dashboard_escape($statusLabel) ?></span>
                            </header>
                            <div class="le-dashboard-session-code"><?= le_dashboard_escape($session['session_code'] ?? '--------') ?></div>
                            <div class="le-dashboard-session-meta">
                                <?= (int)($session['online_count'] ?? 0) ?> online ·
                                <?= (int)($session['total_participants'] ?? 0) ?> total participants
                            </div>
                            <div class="le-dashboard-card-actions">
                                <button class="primary" type="button" data-le-session-action="open" data-session-id="<?= (int)$session['id'] ?>">Open session</button>
                                <button type="button" data-le-session-action="edit" data-session-id="<?= (int)$session['id'] ?>" aria-label="Edit session" title="Edit session"><span class="material-symbols-rounded">edit</span></button>
                                <button type="button" data-le-session-action="delete" data-session-id="<?= (int)$session['id'] ?>" aria-label="Delete session" title="Delete session"><span class="material-symbols-rounded">delete</span></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="le-dashboard-empty">No live sessions. Start one when your audience is ready.</div>
            <?php endif; ?>
        </section>

        <section class="le-dashboard-section">
            <div class="le-dashboard-section-heading">
                <h2>Upcoming sessions</h2>
                <span><?= count($scheduledSessions) ?> scheduled</span>
            </div>
            <?php if ($scheduledSessions): ?>
                <div class="le-dashboard-session-grid">
                    <?php foreach ($scheduledSessions as $session): ?>
                        <?php [$statusLabel, $statusClass] = le_dashboard_status($session); ?>
                        <article class="le-dashboard-session-card">
                            <header>
                                <h3 title="<?= le_dashboard_escape($session['title'] ?? '') ?>"><?= le_dashboard_escape($session['title'] ?? 'Untitled session') ?></h3>
                                <span class="le-dashboard-status <?= le_dashboard_escape($statusClass) ?>"><?= le_dashboard_escape($statusLabel) ?></span>
                            </header>
                            <div class="le-dashboard-session-code"><?= le_dashboard_escape($session['session_code'] ?? '--------') ?></div>
                            <div class="le-dashboard-session-meta">
                                <?= le_dashboard_date($session['scheduled_start'] ?? null) ?><br>
                                <?= (int)($session['total_participants'] ?? 0) ?> participants joined
                            </div>
                            <div class="le-dashboard-card-actions">
                                <button class="primary" type="button" data-le-session-action="open" data-session-id="<?= (int)$session['id'] ?>">Open session</button>
                                <button type="button" data-le-session-action="edit" data-session-id="<?= (int)$session['id'] ?>" aria-label="Edit session" title="Edit session"><span class="material-symbols-rounded">edit</span></button>
                                <button type="button" data-le-session-action="delete" data-session-id="<?= (int)$session['id'] ?>" aria-label="Delete session" title="Delete session"><span class="material-symbols-rounded">delete</span></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="le-dashboard-empty">
                    No upcoming sessions.
                    <button class="le-dashboard-outline-button" type="button" onclick="openCreateSession()">Create the first one</button>
                </div>
            <?php endif; ?>
        </section>

        <section class="le-dashboard-section">
            <div class="le-dashboard-section-heading">
                <h2>Recent history</h2>
                <span><?= count($pastSessions) ?> completed</span>
            </div>
            <?php if ($pastSessions): ?>
                <div class="le-dashboard-history">
                    <table>
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Ended</th>
                                <th>Participants</th>
                                <th>Engagement</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($pastSessions, 0, 8) as $session): ?>
                                <tr>
                                    <td><?= le_dashboard_escape($session['title'] ?? 'Untitled session') ?></td>
                                    <td class="muted"><?= le_dashboard_date($session['actual_end'] ?? null) ?></td>
                                    <td><?= (int)($session['total_participants'] ?? 0) ?></td>
                                    <td><?= ($session['engagement_score'] ?? null) !== null ? (int)$session['engagement_score'] . '%' : '—' ?></td>
                                    <td><button class="le-dashboard-outline-button" type="button" data-le-session-action="delete" data-session-id="<?= (int)$session['id'] ?>">Delete</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="le-dashboard-empty">Completed sessions will appear here after you run your first session.</div>
            <?php endif; ?>
        </section>
    </main>
</div>

<div id="createSessionModal" class="le-modal-overlay" style="display:none;">
    <div class="le-modal le-modal-lg">
        <div class="le-modal-header">
            <h3 class="le-card-title">Create new session</h3>
            <button class="le-modal-close" type="button" onclick="closeDashboardModal('createSessionModal')">&times;</button>
        </div>
        <form id="createSessionForm">
            <div class="le-modal-body">
                <div id="createSessionStatus" role="status" aria-live="polite" hidden></div>
                <div class="le-form-group">
                    <label class="le-label le-label-required" for="createSessionTitle">Session title</label>
                    <input class="le-input" id="createSessionTitle" name="title" required placeholder="e.g. Week 4 live Q&A">
                </div>
                <div class="le-form-group">
                    <label class="le-label" for="createSessionDescription">Description</label>
                    <textarea class="le-textarea" id="createSessionDescription" name="description" rows="3" placeholder="What will this session cover?"></textarea>
                </div>
                <div class="le-grid le-grid-2">
                    <div class="le-form-group">
                        <label class="le-label" for="createSessionCourse">Course</label>
                        <select class="le-select" id="createSessionCourse" name="course_id" onchange="filterDashboardUnits(this.value)">
                            <option value="">Select course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int)$course['id'] ?>"><?= le_dashboard_escape($course['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="le-form-group">
                        <label class="le-label" for="createSessionUnit">Unit</label>
                        <select class="le-select" id="createSessionUnit" name="unit_id">
                            <option value="">Select unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int)$unit['id'] ?>" data-course="<?= (int)$unit['course_id'] ?>"><?= le_dashboard_escape($unit['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="le-grid le-grid-2">
                    <div class="le-form-group">
                        <label class="le-label" for="createSessionType">Session type</label>
                        <select class="le-select" id="createSessionType" name="session_type">
                            <option value="mixed">All features</option>
                            <option value="presentation">Presentation</option>
                            <option value="poll">Polling</option>
                            <option value="quiz">Quiz</option>
                            <option value="whiteboard">Whiteboard</option>
                        </select>
                    </div>
                    <div class="le-form-group">
                        <label class="le-label" for="createSessionDuration">Duration (minutes)</label>
                        <input class="le-input" id="createSessionDuration" type="number" name="duration_minutes" value="60" min="5" max="480">
                    </div>
                </div>
                <div class="le-form-group">
                    <label class="le-toggle">
                        <input type="checkbox" name="allow_anonymous" value="1">
                        <span class="le-toggle-track"></span>
                        <span>Allow anonymous participants</span>
                    </label>
                </div>
                <?= le_csrf_field() ?>
            </div>
            <div class="le-modal-footer">
                <button class="le-btn le-btn-secondary" type="button" onclick="closeDashboardModal('createSessionModal')">Cancel</button>
                <button class="le-btn le-btn-primary" id="createSessionButton" type="submit">Create session</button>
            </div>
        </form>
    </div>
</div>

<div id="editSessionModal" class="le-modal-overlay" style="display:none;">
    <div class="le-modal">
        <div class="le-modal-header">
            <h3 class="le-card-title">Edit session</h3>
            <button class="le-modal-close" type="button" onclick="closeDashboardModal('editSessionModal')">&times;</button>
        </div>
        <form data-le-edit-session-form>
            <div class="le-modal-body">
                <div id="editSessionStatus" role="status" aria-live="polite" hidden></div>
                <input type="hidden" id="editSessionId" name="id">
                <div class="le-form-group">
                    <label class="le-label le-label-required" for="editSessionTitle">Session title</label>
                    <input class="le-input" id="editSessionTitle" name="title" required>
                </div>
                <div class="le-form-group">
                    <label class="le-label" for="editSessionDescription">Description</label>
                    <textarea class="le-textarea" id="editSessionDescription" name="description" rows="3"></textarea>
                </div>
                <div class="le-grid le-grid-2">
                    <div class="le-form-group">
                        <label class="le-label" for="editSessionType">Session type</label>
                        <select class="le-select" id="editSessionType" name="session_type">
                            <option value="mixed">All features</option>
                            <option value="presentation">Presentation</option>
                            <option value="poll">Polling</option>
                            <option value="quiz">Quiz</option>
                            <option value="whiteboard">Whiteboard</option>
                        </select>
                    </div>
                    <div class="le-form-group">
                        <label class="le-label" for="editSessionDuration">Duration (minutes)</label>
                        <input class="le-input" id="editSessionDuration" type="number" name="duration_minutes" min="5" max="480">
                    </div>
                </div>
                <div class="le-form-group">
                    <label class="le-label" for="editSessionMaxParticipants">Maximum participants</label>
                    <input class="le-input" id="editSessionMaxParticipants" type="number" name="max_participants" min="0">
                </div>
            </div>
            <div class="le-modal-footer">
                <button class="le-btn le-btn-secondary" type="button" onclick="closeDashboardModal('editSessionModal')">Cancel</button>
                <button class="le-btn le-btn-primary" id="saveSessionBtn" type="submit">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateSession() {
        const modal = document.getElementById('createSessionModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeDashboardModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.style.display = 'none';
    }

    function filterDashboardUnits(courseId) {
        document.querySelectorAll('#createSessionUnit option[data-course]').forEach(function (option) {
            option.hidden = Boolean(courseId) && option.dataset.course !== courseId;
        });
        document.getElementById('createSessionUnit').value = '';
    }

    document.getElementById('createSessionForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = document.getElementById('createSessionButton');
        const status = document.getElementById('createSessionStatus');
        button.disabled = true;
        button.textContent = 'Creating...';
        status.hidden = true;

        try {
            const data = Object.fromEntries(new FormData(event.target).entries());
            const session = await LiveEngagement.createSession(data);
            status.textContent = 'Session created. Code: ' + session.session_code;
            status.hidden = false;
            LiveEngagement.showToast('Session created successfully.', 'success');
            setTimeout(function () { window.location.reload(); }, 900);
        } catch (error) {
            status.textContent = error.message || 'Unable to create the session.';
            status.hidden = false;
        } finally {
            button.disabled = false;
            button.textContent = 'Create session';
        }
    });

    <?php if ($autoCreate): ?>
    document.addEventListener('DOMContentLoaded', function () {
        <?php if ($autoCreateType === 'presentation'): ?>
        document.getElementById('createSessionType').value = 'presentation';
        <?php endif; ?>
        openCreateSession();
    }, { once: true });
    <?php endif; ?>

    <?php if ($loadError): ?>
    console.error('Live Engagement dashboard data load failed.');
    <?php endif; ?>
</script>

<?php Layout::end(); ?>
