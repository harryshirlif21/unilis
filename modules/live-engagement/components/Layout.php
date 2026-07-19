<?php
/**
 * Live Engagement Module - Standalone Page Layout
 *
 * Self-contained HTML shell for the module (no LMS header/footer).
 *
 * @package UNILIS\LiveEngagement\Components
 * @version 1.0.0
 */

namespace LE\Components;

class Layout
{
    /**
     * Open the standalone page document.
     *
     * @param array{
     *   title?: string,
     *   layout?: 'app'|'minimal'|'immersive',
     *   bodyClass?: string,
     *   activeNav?: string,
     *   includeJs?: bool
     * } $options
     */
    public static function start(array $options = []): void
    {
        $title = $options['title'] ?? 'Live Engagement';
        $layout = $options['layout'] ?? 'app';
        $bodyClass = trim('le-standalone ' . ($options['bodyClass'] ?? ''));
        $activeNav = $options['activeNav'] ?? '';
        $includeJs = $options['includeJs'] ?? true;

        $fullTitle = str_contains($title, 'Live Engagement')
            ? $title
            : $title . ' | UNILIS Live Engagement';

        $GLOBALS['le_layout_include_js'] = $includeJs;

        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= UI::escape($fullTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="<?= le_asset_url('css/live-engagement.css') ?>">
    <?= le_csrf_meta() ?>
    <?= UI::inlineScript() ?>
    <script>UI.loadTheme();</script>
    <?php if ($includeJs): ?>
    <script>
        window.LE_API_BASE = '<?= le_module_url('api') ?>/';
    </script>
    <script src="<?= le_asset_url('js/live-engagement.js') ?>?v=<?= (int) filemtime(LE_MODULE_PATH . '/assets/js/live-engagement.js') ?>"></script>
    <?php endif; ?>
</head>
<body class="<?= UI::escape($bodyClass) ?>" data-le-layout="<?= UI::escape($layout) ?>">
        <?php
        if ($layout === 'app') {
            echo self::renderAppHeader($activeNav);
        }
        ?>
<main class="le-app-main" role="main">
        <?php
    }

    /**
     * Close the standalone page document.
     *
     * @param array{includeJs?: bool} $options
     */
    public static function end(array $options = []): void
    {
        ?>
</main>
</body>
</html>
        <?php
    }

    /**
     * Render the module top navigation bar.
     */
    public static function renderAppHeader(string $activeNav = ''): string
    {
        $userName = le_current_user_name() ?? 'User';
        $role = le_current_user_role();
        $isStaff = le_has_role(['lecturer', 'admin']);

        $navItems = [];
        if ($isStaff) {
            $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'page' => 'dashboard'],
                'presentations' => ['label' => 'Presentations', 'icon' => 'slideshow', 'page' => 'presentations'],
                'reports' => ['label' => 'Reports', 'icon' => 'analytics', 'page' => 'reports'],
            ];
        }
        $navItems['join'] = ['label' => 'Join Session', 'icon' => 'login', 'page' => 'join'];

        $navHtml = '';
        foreach ($navItems as $key => $item) {
            $isActive = $activeNav === $key;
            $navHtml .= sprintf(
                '<a href="%s" class="le-app-nav-link%s"><span class="material-symbols-rounded">%s</span><span>%s</span></a>',
                le_page_url($item['page']),
                $isActive ? ' is-active' : '',
                UI::escape($item['icon']),
                UI::escape($item['label'])
            );
        }

        $roleLabel = $role ? ucfirst($role) : 'Guest';
        $logoutUrl = le_base_url() . '/logout.php';

        return sprintf(
            '<header class="le-app-header">
                <div class="le-app-header-inner">
                    <a href="%s" class="le-app-brand">
                        <span class="le-app-brand-icon material-symbols-rounded">live_tv</span>
                        <span class="le-app-brand-text">
                            <strong>Live Engagement</strong>
                            <small>UNILIS</small>
                        </span>
                    </a>
                    <nav class="le-app-nav" aria-label="Live Engagement">%s</nav>
                    <div class="le-app-header-actions">
                        %s
                        <div class="le-app-user" title="%s">
                            <span class="material-symbols-rounded">account_circle</span>
                            <span class="le-app-user-meta">
                                <strong>%s</strong>
                                <small>%s</small>
                            </span>
                        </div>
                        <a href="%s" class="le-btn le-btn-ghost le-btn-sm le-app-logout" title="Sign out">
                            <span class="material-symbols-rounded">logout</span>
                        </a>
                    </div>
                </div>
            </header>',
            le_page_url($isStaff ? 'dashboard' : 'join'),
            $navHtml,
            UI::themeSwitcher(),
            UI::escape($userName),
            UI::escape($userName),
            UI::escape($roleLabel),
            UI::escape($logoutUrl)
        );
    }
}
