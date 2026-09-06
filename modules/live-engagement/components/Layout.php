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
        $bodyClass = trim('le-standalone ld ' . ($options['bodyClass'] ?? ''));
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
    <?php if ($layout === 'app'): ?>
        <link rel="stylesheet" href="<?= le_asset_url('css/live-dash.css') ?>?v=<?= (int) filemtime(LE_MODULE_PATH . '/assets/css/live-dash.css') ?>">
        <link rel="stylesheet" href="<?= le_asset_url('css/live-engagement.css') ?>?v=<?= (int) filemtime(LE_MODULE_PATH . '/assets/css/live-engagement.css') ?>">
    <?php else: ?>
        <link rel="stylesheet" href="<?= le_asset_url('css/live-engagement.css') ?>">
    <?php endif; ?>
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
            $GLOBALS['le_layout_app'] = true;
            echo '<div class="ld-shell">' . "\n";
            echo self::renderRail($activeNav);
            echo '<div class="ld-main">' . "\n";
            echo self::renderTopBar($activeNav);
            echo '<main class="le-app-main" role="main">' . "\n";
        } else {
            $GLOBALS['le_layout_app'] = false;
            echo '<main class="le-app-main" role="main">' . "\n";
        }
        ?>
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
<?php if (!empty($GLOBALS['le_layout_app'])): ?>
</div><!-- /ld-main -->
</div><!-- /ld-shell -->
<div class="le-shell-backdrop" id="leShellBackdrop"></div>
<script>
(function () {
    var rail = document.getElementById('ldSidebarRail');
    var bd = document.getElementById('leShellBackdrop');
    var menu = [].slice.call(document.querySelectorAll('.ld-menu-btn, .le-shell-menu'));
    function setOpen(open) {
        if (rail) rail.classList.toggle('open', open);
        if (bd) bd.classList.toggle('show', open);
    }
    menu.forEach(function (m) { if (m) m.addEventListener('click', function () { setOpen(rail ? !rail.classList.contains('open') : false); }); });
    if (bd) bd.addEventListener('click', function () { setOpen(false); });
    [].slice.call(document.querySelectorAll('.ld-rail-btn, .ld-profile')).forEach(function (a) {
        if (a && a.addEventListener) a.addEventListener('click', function () { setOpen(false); });
    });
})();
</script>
<?php endif; ?>
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
        $isStaff = le_can_present();

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
/**
     * Render the floating sidebar rail (reference UI).
     */
    public static function renderRail(string $activeNav = ''): string
    {
        $userName = le_current_user_name() ?? 'User';
        $role = le_current_user_role();
        $isStaff = le_can_present();
        $roleLabel = $role ? ucfirst($role) : 'Guest';

        $navHtml = '';
        $navItems = [];
        if ($isStaff) {
            $navItems = [
                'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'page' => 'dashboard'],
                'presentations' => ['label' => 'Presentations', 'icon' => 'slideshow', 'page' => 'presentations'],
                'reports' => ['label' => 'Reports', 'icon' => 'analytics', 'page' => 'reports'],
            ];
        }
        $navItems['join'] = ['label' => 'Join Session', 'icon' => 'login', 'page' => 'join'];
        foreach ($navItems as $key => $item) {
            $navHtml .= self::railBtn($item, $key, $activeNav);
        }

        $initials = '';
        $nameParts = preg_split('/\s+/', trim($userName));
        if ($nameParts) {
            $first = mb_substr($nameParts[0], 0, 1);
            $last = count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '';
            $initials = strtoupper(($first . $last) ?: 'U');
        }
        $settingsHref = le_base_url() . '/logout.php';

        return sprintf(
            '<aside class="ld-rail" id="ldSidebarRail" aria-label="Primary">
                <a href="%s" class="ld-brand" title="UNILIS Live Engagement">U</a>
                <div class="ld-rail-nav">%s</div>
                <div class="ld-rail-bottom">
                    <div class="ld-profile" role="button" tabindex="0" title="%s (%s)">
                        <span class="avatar-dot" aria-hidden="true">%s</span>
                        <span class="profile-info"><b>%s</b><small>%s</small></span>
                    </div>
                    <a href="%s" class="ld-rail-btn" title="Sign out" aria-label="Sign out">%s</a>
                </div>
            </aside>',
            le_page_url($isStaff ? 'dashboard' : 'join'),
            $navHtml,
            UI::escape($userName),
            UI::escape($roleLabel),
            UI::escape($initials),
            UI::escape($userName),
            UI::escape($roleLabel),
            UI::escape($settingsHref),
            self::icon('logout')
        );
    }

    /**
     * Render a single rail navigation button (SVG icon).
     */
    private static function railBtn(array $item, string $key, string $activeNav): string
    {
        $isActive = $activeNav === $key;
        return sprintf(
            '<a href="%s" class="ld-rail-btn%s" title="%s" aria-label="%s" aria-current="%s">%s</a>',
            le_page_url($item['page']),
            $isActive ? ' active' : '',
            UI::escape($item['label']),
            UI::escape($item['label']),
            $isActive ? 'page' : 'false',
            self::icon($item['icon'])
        );
    }

    /**
     * Inline SVG line-icon set matching the reference UI.
     */
    private static function icon(string $name): string
    {
        $icons = [
            'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>',
            'slideshow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>',
            'analytics' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19V5h14v14z"/><path d="M8 16v-3"/><path d="M12 16V9"/><path d="M16 16v-6"/></svg>',
            'login' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>',
            'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>',
            'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/></svg>',
            'sun' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
            'moon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15.5A8.5 8.5 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5z"/></svg>',
        ];
        return $icons[$name] ?? $icons['dashboard'];
    }

    /**
     * Render the top bar (identity + nav + theme + account).
     */
    public static function renderTopBar(string $activeNav = ''): string
    {
        $isStaff = le_can_present();

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
                '<a href="%s" class="%s" aria-current="%s">%s<span>%s</span></a>',
                le_page_url($item['page']),
                $isActive ? 'active' : '',
                $isActive ? 'page' : 'false',
                self::icon($item['icon']),
                UI::escape($item['label'])
            );
        }

        return sprintf(
            '<div class="ld-topbar">
                <button class="ld-icon-btn ld-menu-btn" aria-label="Toggle navigation" title="Menu">%s</button>
                <div class="ld-identity">
                    <div class="ld-mini-logo">U</div>
                    <div><strong>Live Engagement</strong><span>UNILIS</span></div>
                </div>
                <nav class="ld-nav" aria-label="Live Engagement">%s</nav>
                <div class="ld-top-actions">
                    %s
                </div>
            </div>',
            self::icon('menu'),
            $navHtml,
            self::themeToggle()
        );
    }

    /**
     * Render a theme toggle (sun/moon) that cycles light/dark and keeps the
     * icon in sync. Uses a real closure object so the switch stays visible.
     */
    private static function themeToggle(): string
    {
        return sprintf(
            '<button class="ld-icon-btn ld-theme-btn" type="button" title="Toggle theme" aria-label="Toggle light/dark mode" onclick="(function(b){var isDark=document.documentElement.getAttribute(\'data-theme\')===\'dark\';UI.setTheme(isDark?\'light\':\'dark\');b.setAttribute(\'aria-pressed\', String(isDark));b.classList.toggle(\'is-dark\', isDark);})(this);">%s</button>',
            self::icon('sun')
        );
    }
}
