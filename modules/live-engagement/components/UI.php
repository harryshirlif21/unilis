<?php
/**
 * Live Engagement Module - Reusable UI Components
 * 
 * Premium component library implementing the 2025/2026 design system.
 * All components use the CSS custom properties from the design system.
 * 
 * @package UNILIS\LiveEngagement\Components
 * @version 2.0.0
 */

namespace LE\Components;

class UI
{
    /**
     * Render a glassmorphism card
     */
    public static function card(string $title = '', string $content = '', string $footer = '', array $options = []): string
    {
        $class = $options['class'] ?? '';
        $solid = !empty($options['solid']);
        $float = !empty($options['float']);
        $interactive = !empty($options['interactive']);
        $onclick = $options['onclick'] ?? '';
        
        $classes = $solid ? 'le-card-solid' : 'le-card';
        if ($float) $classes .= ' le-card-float';
        if ($interactive) $classes .= ' le-card-interactive';
        if ($class) $classes .= ' ' . $class;
        
        $header = $title ? sprintf(
            '<div class="le-card-header"><h3 class="le-card-title">%s</h3></div>',
            self::escape($title)
        ) : '';
        
        $footerHtml = $footer ? sprintf(
            '<div class="le-card-footer">%s</div>',
            $footer
        ) : '';
        
        $attrs = $onclick ? sprintf(' onclick="%s"', self::escape($onclick)) : '';
        
        return sprintf(
            '<div class="%s"%s>%s<div class="le-card-body">%s</div>%s</div>',
            $classes,
            $attrs,
            $header,
            $content,
            $footerHtml
        );
    }

    /**
     * Render a button with ripple effect support
     */
    public static function button(string $label, string $type = 'primary', array $options = []): string
    {
        $tag = $options['tag'] ?? 'button';
        $href = $options['href'] ?? '#';
        $size = $options['size'] ?? '';
        $icon = $options['icon'] ?? '';
        $outline = !empty($options['outline']);
        $ghost = !empty($options['ghost']);
        $loading = !empty($options['loading']);
        $disabled = !empty($options['disabled']);
        $class = $options['class'] ?? '';
        $onclick = $options['onclick'] ?? '';
        $id = $options['id'] ?? '';
        $type_attr = $options['type'] ?? 'button';
        
        $classes = 'le-btn';
        if ($outline) $classes .= ' le-btn-outline' . ($type !== 'secondary' ? "-{$type}" : '');
        elseif ($ghost) $classes .= ' le-btn-ghost';
        else $classes .= " le-btn-{$type}";
        
        if ($size) $classes .= " le-btn-{$size}";
        if ($loading) $classes .= ' le-btn-loading';
        if ($class) $classes .= ' ' . $class;
        
        $iconHtml = $icon ? sprintf('<span class="material-symbols-rounded" style="font-size: 20px;">%s</span>', $icon) : '';
        $content = $iconHtml . ($label ? ' ' . self::escape($label) : '');
        
        $attrs = sprintf(
            'class="%s"%s%s%s%s',
            $classes,
            $id ? " id=\"{$id}\"" : '',
            $disabled ? ' disabled' : '',
            $onclick ? " onclick=\"{$onclick}\"" : '',
            $tag === 'button' ? " type=\"{$type_attr}\"" : ''
        );
        
        if ($tag === 'a') {
            return sprintf('<a href="%s" %s>%s</a>', $href, $attrs, $content);
        }
        
        return sprintf('<button %s>%s</button>', $attrs, $content);
    }

    /**
     * Render a badge
     */
    public static function badge(string $text, string $type = 'neutral', array $options = []): string
    {
        $class = $options['class'] ?? '';
        $live = !empty($options['live']);
        
        $type = $live ? 'live' : $type;
        $classes = "le-badge le-badge-{$type}";
        if ($class) $classes .= ' ' . $class;
        
        $icon = $options['icon'] ?? '';
        $iconHtml = $icon ? sprintf('<span class="material-symbols-rounded" style="font-size: 14px;">%s</span>', $icon) : '';
        
        return sprintf(
            '<span class="%s">%s%s</span>',
            $classes,
            $iconHtml,
            self::escape($text)
        );
    }

    /**
     * Render a status dot indicator
     */
    public static function statusDot(string $status = 'inactive'): string
    {
        $statuses = ['active', 'inactive', 'warning', 'danger'];
        $status = in_array($status, $statuses) ? $status : 'inactive';
        return sprintf('<span class="le-status-dot le-status-dot-%s"></span>', $status);
    }

    /**
     * Render session code with copy functionality
     */
    public static function sessionCode(string $code, bool $small = false): string
    {
        $class = $small ? 'le-session-code le-session-code-sm le-session-code-copy' : 'le-session-code le-session-code-copy';
        return sprintf(
            '<div class="%s" onclick="LiveEngagement.copyToClipboard(\'%s\')" title="Click to copy">%s</div>',
            $class,
            self::escape($code),
            self::escape($code)
        );
    }

    /**
     * Render a modal trigger and overlay
     */
    public static function modal(string $id, string $title, string $content, string $footer = '', array $options = []): string
    {
        $size = $options['size'] ?? '';
        $sizeClass = $size ? " le-modal-{$size}" : '';
        
        return sprintf(
            '<div id="%s" class="le-modal-overlay" style="display:none;">
                <div class="le-modal%s">
                    <div class="le-modal-header">
                        <h3 class="le-card-title">%s</h3>
                        <button class="le-modal-close" onclick="UI.closeModal(\'%s\')">&times;</button>
                    </div>
                    <div class="le-modal-body">%s</div>
                    %s
                </div>
            </div>',
            self::escape($id),
            $sizeClass,
            self::escape($title),
            self::escape($id),
            $content,
            $footer ? sprintf('<div class="le-modal-footer">%s</div>', $footer) : ''
        );
    }

    /**
     * Render form input with label
     */
    public static function input(string $name, string $label = '', string $value = '', array $options = []): string
    {
        $type = $options['type'] ?? 'text';
        $placeholder = $options['placeholder'] ?? '';
        $required = !empty($options['required']);
        $error = $options['error'] ?? '';
        $hint = $options['hint'] ?? '';
        $class = $options['class'] ?? '';
        $id = $options['id'] ?? $name;
        $onchange = $options['onchange'] ?? '';
        
        $classes = 'le-input';
        if ($error) $classes .= ' le-input-error';
        if ($class) $classes .= ' ' . $class;
        
        $labelHtml = $label ? sprintf(
            '<label class="le-label%s" for="%s">%s</label>',
            $required ? ' le-label-required' : '',
            self::escape($id),
            self::escape($label)
        ) : '';
        
        $inputAttrs = sprintf(
            'type="%s" name="%s" id="%s" value="%s" class="%s"%s%s%s',
            self::escape($type),
            self::escape($name),
            self::escape($id),
            self::escape($value),
            $classes,
            $placeholder ? " placeholder=\"" . self::escape($placeholder) . "\"" : '',
            $required ? ' required' : '',
            $onchange ? " onchange=\"{$onchange}\"" : ''
        );
        
        if ($type === 'textarea') {
            $input = sprintf(
                '<textarea name="%s" id="%s" class="%s"%s%s>%s</textarea>',
                self::escape($name),
                self::escape($id),
                $classes,
                $placeholder ? " placeholder=\"" . self::escape($placeholder) . "\"" : '',
                $required ? ' required' : '',
                self::escape($value)
            );
        } else {
            $input = sprintf('<input %s>', $inputAttrs);
        }
        
        $errorHtml = $error ? sprintf('<p class="le-error-text">%s</p>', self::escape($error)) : '';
        $hintHtml = $hint ? sprintf('<p class="le-hint-text">%s</p>', self::escape($hint)) : '';
        
        return sprintf(
            '<div class="le-form-group">%s%s%s%s</div>',
            $labelHtml,
            $input,
            $errorHtml,
            $hintHtml
        );
    }

    /**
     * Render a select dropdown
     */
    public static function select(string $name, string $label = '', array $options = [], string $selected = '', array $attrs = []): string
    {
        $required = !empty($attrs['required']);
        $id = $attrs['id'] ?? $name;
        $placeholder = $attrs['placeholder'] ?? '';
        $onchange = $attrs['onchange'] ?? '';
        $class = $attrs['class'] ?? '';
        
        $classes = 'le-select';
        if ($class) $classes .= ' ' . $class;
        
        $labelHtml = $label ? sprintf(
            '<label class="le-label%s" for="%s">%s</label>',
            $required ? ' le-label-required' : '',
            self::escape($id),
            self::escape($label)
        ) : '';
        
        $optionHtml = '';
        if ($placeholder) {
            $optionHtml .= sprintf('<option value="">%s</option>', self::escape($placeholder));
        }
        
        foreach ($options as $value => $text) {
            $sel = (string)$value === (string)$selected ? ' selected' : '';
            $optionHtml .= sprintf('<option value="%s"%s>%s</option>', self::escape($value), $sel, self::escape($text));
        }
        
        $selectAttrs = sprintf(
            'name="%s" id="%s" class="%s"%s%s',
            self::escape($name),
            self::escape($id),
            $classes,
            $required ? ' required' : '',
            $onchange ? " onchange=\"{$onchange}\"" : ''
        );
        
        return sprintf(
            '<div class="le-form-group">%s<select %s>%s</select></div>',
            $labelHtml,
            $selectAttrs,
            $optionHtml
        );
    }

    /**
     * Render a toggle switch
     */
    public static function toggle(string $name, string $label = '', bool $checked = false, array $attrs = []): string
    {
        $id = $attrs['id'] ?? $name;
        $onchange = $attrs['onchange'] ?? '';
        
        $checkedAttr = $checked ? ' checked' : '';
        $changeAttr = $onchange ? " onchange=\"{$onchange}\"" : '';
        
        return sprintf(
            '<label class="le-toggle">
                <input type="checkbox" class="le-toggle-input" name="%s" id="%s"%s%s>
                <span class="le-toggle-track"></span>
                <span>%s</span>
            </label>',
            self::escape($name),
            self::escape($id),
            $checkedAttr,
            $changeAttr,
            self::escape($label)
        );
    }

    /**
     * Render loading skeleton
     */
    public static function skeleton(string $type = 'text', int $lines = 3): string
    {
        $html = '';
        
        switch ($type) {
            case 'card':
                $html = '<div class="le-skeleton le-skeleton-card"></div>';
                break;
            case 'avatar':
                $html = '<div class="le-skeleton le-skeleton-avatar"></div>';
                break;
            case 'title':
                $html = '<div class="le-skeleton le-skeleton-title"></div>';
                break;
            case 'text':
            default:
                for ($i = 0; $i < $lines; $i++) {
                    $html .= '<div class="le-skeleton le-skeleton-text"></div>';
                }
                break;
        }
        
        return $html;
    }

    /**
     * Render empty state
     */
    public static function emptyState(string $icon = '📋', string $title = 'Nothing here', string $text = '', string $action = ''): string
    {
        $actionHtml = $action ? sprintf('<div style="margin-top: 16px;">%s</div>', $action) : '';
        
        return sprintf(
            '<div class="le-empty-state">
                <div class="le-empty-icon">%s</div>
                <div class="le-empty-title">%s</div>
                <div class="le-empty-text">%s</div>
                %s
            </div>',
            $icon,
            self::escape($title),
            self::escape($text),
            $actionHtml
        );
    }

    /**
     * Render a tab container
     */
    public static function tabs(array $tabs, string $activeTab = ''): string
    {
        if (empty($tabs)) return '';
        
        $firstTab = array_key_first($tabs);
        $active = $activeTab ?: $firstTab;
        
        $tabHeaders = '';
        $tabContents = '';
        
        foreach ($tabs as $id => $tab) {
            $title = $tab['title'] ?? $id;
            $content = $tab['content'] ?? '';
            $icon = $tab['icon'] ?? '';
            
            $isActive = $id === $active;
            $headerClass = $isActive ? 'le-tab active' : 'le-tab';
            $contentClass = $isActive ? 'le-tab-content active' : 'le-tab-content';
            
            $iconHtml = $icon ? sprintf('<span class="material-symbols-rounded" style="font-size: 18px;">%s</span> ', $icon) : '';
            
            $tabHeaders .= sprintf(
                '<button class="%s" onclick="UI.switchTab(\'%s\', \'%s\')">%s%s</button>',
                $headerClass,
                self::escape($id),
                $iconHtml,
                self::escape($title)
            );
            
            $tabContents .= sprintf(
                '<div class="%s" id="tab-%s">%s</div>',
                $contentClass,
                self::escape($id),
                $content
            );
        }
        
        return sprintf(
            '<div class="le-tabs" id="tab-header-container">%s</div>%s',
            $tabHeaders,
            $tabContents
        );
    }

    /**
     * Render progress bar
     */
    public static function progressBar(int $percent, array $options = []): string
    {
        $striped = !empty($options['striped']);
        $height = $options['height'] ?? '8px';
        $class = $options['class'] ?? '';
        
        $fillClass = 'le-progress-fill' . ($striped ? ' striped' : '');
        if ($class) $fillClass .= ' ' . $class;
        
        return sprintf(
            '<div class="le-progress" style="height: %s;">
                <div class="%s" style="width: %d%%;"></div>
            </div>',
            $height,
            $fillClass,
            min(100, max(0, $percent))
        );
    }

    /**
     * Render toast notification (for PHP-rendered toasts)
     */
    public static function toast(string $message, string $type = 'info', string $icon = ''): string
    {
        $icons = [
            'success' => 'check_circle',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info'
        ];
        
        $iconName = $icon ?: ($icons[$type] ?? 'info');
        
        return sprintf(
            '<div class="le-toast le-toast-%s">
                <span class="le-toast-icon material-symbols-rounded">%s</span>
                <span class="le-toast-content">%s</span>
            </div>',
            $type,
            $iconName,
            self::escape($message)
        );
    }

    /**
     * Render participant avatar
     */
    public static function participantAvatar(string $name, bool $online = false, string $imageUrl = ''): string
    {
        $initial = strtoupper(substr($name, 0, 1));
        $onlineClass = $online ? ' le-participant-online' : '';
        
        $avatarContent = $imageUrl
            ? sprintf('<img src="%s" alt="%s">', self::escape($imageUrl), self::escape($name))
            : self::escape($initial);
        
        return sprintf(
            '<div class="le-participant-avatar%s">%s</div>',
            $onlineClass,
            $avatarContent
        );
    }

    /**
     * Render participant row
     */
    public static function participant(string $name, string $role = 'participant', bool $online = false, array $options = []): string
    {
        $imageUrl = $options['avatar_url'] ?? '';
        $handRaised = !empty($options['hand_raised']);
        
        $avatar = self::participantAvatar($name, $online, $imageUrl);
        $handIcon = $handRaised ? ' <span class="le-hand-raised material-symbols-rounded" style="font-size: 18px;">pan_tool</span>' : '';
        
        return sprintf(
            '<div class="le-participant%s">
                %s
                <span class="le-participant-name">%s%s</span>
                <span class="le-participant-role">%s</span>
            </div>',
            $online ? ' le-participant-online' : '',
            $avatar,
            self::escape($name),
            $handIcon,
            self::escape($role)
        );
    }

    /**
     * Render a table with premium styling
     */
    public static function table(array $headers, array $rows, array $options = []): string
    {
        $class = $options['class'] ?? '';
        $responsive = !empty($options['responsive']) !== false;
        
        $headerHtml = '';
        foreach ($headers as $header) {
            $headerHtml .= sprintf('<th style="text-align: left; padding: 12px 16px; font-size: 0.85rem; font-weight: 600; color: var(--le-gray-600);">%s</th>', self::escape($header));
        }
        
        $rowsHtml = '';
        foreach ($rows as $row) {
            $rowsHtml .= '<tr style="border-bottom: 1px solid var(--le-gray-100); transition: background var(--le-transition-fast);" onmouseover="this.style.background=\'var(--le-gray-50)\'" onmouseout="this.style.background=\'\'">';
            foreach ($row as $cell) {
                $rowsHtml .= sprintf('<td style="padding: 12px 16px;">%s</td>', $cell);
            }
            $rowsHtml .= '</tr>';
        }
        
        $wrapper = $responsive ? '<div style="overflow-x: auto;">' : '';
        $wrapperEnd = $responsive ? '</div>' : '';
        
        return sprintf(
            '%s<table class="%s" style="width: 100%%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--le-gray-200);">%s</tr>
                </thead>
                <tbody>%s</tbody>
            </table>%s',
            $wrapper,
            $class ? "le-table-{$class}" : '',
            $headerHtml,
            $rowsHtml,
            $wrapperEnd
        );
    }

    /**
     * Render a theme switcher component
     */
    public static function themeSwitcher(): string
    {
        return '
        <div class="le-theme-switcher" style="display: flex; gap: 4px; padding: 4px; background: var(--le-gray-100); border-radius: var(--le-radius-full);">
            <button class="le-btn-icon le-btn-ghost" onclick="UI.setTheme(\'light\')" title="Light mode" style="border-radius: var(--le-radius-full);">
                <span class="material-symbols-rounded" style="font-size: 20px;">light_mode</span>
            </button>
            <button class="le-btn-icon le-btn-ghost" onclick="UI.setTheme(\'dark\')" title="Dark mode" style="border-radius: var(--le-radius-full);">
                <span class="material-symbols-rounded" style="font-size: 20px;">dark_mode</span>
            </button>
            <button class="le-btn-icon le-btn-ghost" onclick="UI.setTheme(\'high-contrast\')" title="High contrast" style="border-radius: var(--le-radius-full);">
                <span class="material-symbols-rounded" style="font-size: 20px;">contrast</span>
            </button>
        </div>';
    }

    /**
     * Render a page header with breadcrumb and actions
     */
    public static function pageHeader(string $title, string $subtitle = '', array $actions = []): string
    {
        $actionsHtml = '';
        foreach ($actions as $action) {
            $actionsHtml .= $action . ' ';
        }
        
        return sprintf(
            '<div class="le-page-header" style="margin-bottom: var(--le-space-4);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--le-space-2);">
                    <div>
                        <h1 style="font-size: var(--le-font-size-3xl); font-weight: var(--le-font-weight-bold); margin: 0; letter-spacing: var(--le-letter-spacing-tight);">
                            %s
                        </h1>
                        %s
                    </div>
                    <div style="display: flex; gap: var(--le-space-2); flex-wrap: wrap;">
                        %s
                    </div>
                </div>
            </div>',
            self::escape($title),
            $subtitle ? sprintf('<p style="color: var(--le-gray-500); margin-top: var(--le-space-1); font-size: var(--le-font-size-sm);">%s</p>', self::escape($subtitle)) : '',
            $actionsHtml
        );
    }

    /**
     * Render a stat card for dashboards
     */
    public static function statCard(string $label, string $value, string $icon = '', string $trend = '', string $color = 'primary'): string
    {
        $iconHtml = $icon ? sprintf('<span class="material-symbols-rounded" style="font-size: 28px; opacity: 0.8;">%s</span>', $icon) : '';
        $trendHtml = $trend ? sprintf('<span style="font-size: var(--le-font-size-xs); color: var(--le-%s);">%s</span>', $color, $trend) : '';
        
        $bgMap = [
            'primary' => 'var(--le-primary-lighter)',
            'success' => 'var(--le-success-lighter)',
            'warning' => 'var(--le-warning-lighter)',
            'danger' => 'var(--le-danger-lighter)',
            'info' => 'var(--le-info-lighter)',
        ];
        $bg = $bgMap[$color] ?? $bgMap['primary'];
        
        $colorMap = [
            'primary' => 'var(--le-primary)',
            'success' => 'var(--le-success)',
            'warning' => 'var(--le-warning)',
            'danger' => 'var(--le-danger)',
            'info' => 'var(--le-info)',
        ];
        $valColor = $colorMap[$color] ?? $colorMap['primary'];
        
        return sprintf(
            '<div style="padding: var(--le-space-3); background: %s; border-radius: var(--le-radius-lg); transition: all var(--le-transition);" class="le-stat-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-size: var(--le-font-size-3xl); font-weight: var(--le-font-weight-bold); color: %s;">%s</div>
                        <div style="font-size: var(--le-font-size-sm); color: var(--le-gray-600); margin-top: 4px;">%s</div>
                        %s
                    </div>
                    %s
                </div>
            </div>',
            $bg,
            $valColor,
            $value,
            self::escape($label),
            $trendHtml ? "<div style=\"margin-top: 4px;\">{$trendHtml}</div>" : '',
            $iconHtml ? "<div>{$iconHtml}</div>" : ''
        );
    }

    /**
     * Escape HTML entities
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Render inline script for UI helpers
     */
    public static function inlineScript(): string
    {
        return '<script>
        window.UI = {
            closeModal: function(id) {
                const el = document.getElementById(id);
                if (el) el.style.display = "none";
            },
            openModal: function(id) {
                const el = document.getElementById(id);
                if (el) el.style.display = "flex";
            },
            switchTab: function(tabId) {
                document.querySelectorAll(".le-tab").forEach(t => t.classList.remove("active"));
                document.querySelectorAll(".le-tab-content").forEach(c => c.classList.remove("active"));
                const tab = document.querySelector(`.le-tab[onclick*="\'${tabId}\'"]`);
                if (tab) tab.classList.add("active");
                const content = document.getElementById(`tab-${tabId}`);
                if (content) content.classList.add("active");
            },
            setTheme: function(theme) {
                document.documentElement.setAttribute("data-theme", theme);
                localStorage.setItem("le-theme", theme);
                // Dispatch event for JS listeners
                document.dispatchEvent(new CustomEvent("themeChange", { detail: { theme: theme } }));
            },
            loadTheme: function() {
                const saved = localStorage.getItem("le-theme");
                if (saved) {
                    document.documentElement.setAttribute("data-theme", saved);
                } else if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
                    document.documentElement.setAttribute("data-theme", "dark");
                }
            },
            toggleSidebar: function() {
                document.querySelector(".le-presenter-sidebar")?.classList.toggle("active");
                document.querySelector(".le-student-sidebar")?.classList.toggle("active");
            },
            showSkeleton: function(container, type, lines) {
                const el = typeof container === "string" ? document.querySelector(container) : container;
                if (!el) return;
                el.innerHTML = "";
                for (let i = 0; i < (lines || 3); i++) {
                    const skeleton = document.createElement("div");
                    skeleton.className = "le-skeleton le-skeleton-" + (type || "text");
                    if (type === "text") skeleton.style.width = (60 + Math.random() * 40) + "%";
                    el.appendChild(skeleton);
                }
            }
        };
        document.addEventListener("DOMContentLoaded", UI.loadTheme);
        </script>';
    }
}