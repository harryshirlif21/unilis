/**
 * Global Theme Manager for UNILIS
 * Handles theme switching across all pages
 */

// Global theme manager
class ThemeManager {
    constructor() {
        this.init();
    }

    init() {
        // Load theme from localStorage or use default (light)
        const savedTheme = localStorage.getItem('unilis_theme') || 'light';
        this.applyTheme(savedTheme);
        
        // Apply theme immediately on page load
        document.addEventListener('DOMContentLoaded', () => {
            this.applyTheme(savedTheme);
        });
    }

    applyTheme(theme) {
        // Apply theme to body
        document.body.setAttribute('data-theme', theme);
        
        // Store in localStorage for persistence across pages
        localStorage.setItem('unilis_theme', theme);
        
        // Update theme selector if it exists
        const themeSelect = document.getElementById('theme');
        if (themeSelect) {
            themeSelect.value = theme;
        }
        
        // Update global theme variable if it exists
        if (typeof currentTheme !== 'undefined') {
            currentTheme = theme;
        }
    }

    switchTheme(theme) {
        this.applyTheme(theme);
        
        // Show toast notification if toast function exists
        if (typeof showToast === 'function') {
            const themeName = theme === 'dark' ? 'Dark Mode' : theme === 'light' ? 'Light Mode' : 'Auto';
            showToast(`Theme changed to ${themeName}`, 'success');
        }
    }

    getCurrentTheme() {
        return localStorage.getItem('unilis_theme') || 'light';
    }

    // Auto-detect system preference
    detectSystemTheme() {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        return prefersDark ? 'dark' : 'light';
    }

    // Handle auto theme (system preference)
    handleAutoTheme() {
        const systemTheme = this.detectSystemTheme();
        this.applyTheme(systemTheme);
    }
}

// Initialize global theme manager
const themeManager = new ThemeManager();

// Listen for system theme changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const currentTheme = themeManager.getCurrentTheme();
    if (currentTheme === 'auto' || !currentTheme) {
        themeManager.handleAutoTheme();
    }
});

// Make theme manager globally available
window.themeManager = themeManager;

// Export for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}
